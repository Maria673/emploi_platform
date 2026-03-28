<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'recruteur') {
    header('Location: ../../auth/login.php');
    exit();
}

require_once '../../config/db.php';

$recruteur = null;
$message_success = '';
$message_error = '';

// Récupérer les infos du recruteur
try {
    $stmt = $pdo->prepare("SELECT r.*, u.email, u.tel_user FROM recruteurs r JOIN utilisateurs u ON r.id_user = u.id_user WHERE r.id_recruteur = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $recruteur = $stmt->fetch();
    if (!$recruteur) { 
        session_destroy(); 
        header('Location: ../../auth/login.php'); 
        exit(); 
    }
} catch (PDOException $e) {
    $message_error = "Erreur : " . $e->getMessage();
}

// Traiter la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_infos') {
        $nom_entreprise = trim($_POST['nom_entreprise'] ?? '');
        $ville_entreprise = trim($_POST['ville_entreprise'] ?? '');
        $adresse_professionnelle = trim($_POST['adresse_professionnelle'] ?? '');
        $nombre_employes = trim($_POST['nombre_employes'] ?? '');
        $description_entreprise = trim($_POST['description_entreprise'] ?? '');
        $site_web = trim($_POST['site_web'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');

        if (empty($nom_entreprise)) {
            $message_error = "Le nom de l'entreprise est obligatoire";
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("UPDATE recruteurs SET nom_entreprise = :nom, ville_entreprise = :ville, adresse_professionnelle = :adresse, nombre_employes = :employes, description_entreprise = :description WHERE id_recruteur = :id");
                $stmt->execute([
                    ':nom' => $nom_entreprise,
                    ':ville' => $ville_entreprise ?: null,
                    ':adresse' => $adresse_professionnelle ?: null,
                    ':employes' => $nombre_employes ?: null,
                    ':description' => $description_entreprise ?: null,
                    ':id' => $_SESSION['user_id']
                ]);
                
                $stmt = $pdo->prepare("UPDATE utilisateurs SET email = :email, tel_user = :tel WHERE id_user = (SELECT id_user FROM recruteurs WHERE id_recruteur = :id)");
                $stmt->execute([':email' => $email, ':tel' => $telephone, ':id' => $_SESSION['user_id']]);
                
                $pdo->commit();
                $message_success = "Profil mis à jour avec succès";
                
                $stmt = $pdo->prepare("SELECT r.*, u.email, u.tel_user FROM recruteurs r JOIN utilisateurs u ON r.id_user = u.id_user WHERE r.id_recruteur = :id");
                $stmt->bindParam(':id', $_SESSION['user_id']);
                $stmt->execute();
                $recruteur = $stmt->fetch();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message_error = "Erreur : " . $e->getMessage();
            }
        }
    }

    // UPLOAD LOGO
    if ($action === 'upload_logo' && isset($_FILES['logo_file'])) {
        $file = $_FILES['logo_file'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        $max_size = 2 * 1024 * 1024;

        if ($file['error'] === 0 && in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $upload_dir = '../../uploads/logos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'LOGO_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                try {
                    if (!empty($recruteur['logo'])) {
                        $old_file = __DIR__ . '/../../uploads/logos/' . $recruteur['logo'];
                        if (file_exists($old_file)) @unlink($old_file);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE recruteurs SET logo = :logo WHERE id_recruteur = :id");
                    $stmt->execute([':logo' => $new_filename, ':id' => $_SESSION['user_id']]);
                    $message_success = "Logo mis à jour avec succès";
                    
                    $stmt = $pdo->prepare("SELECT r.*, u.email, u.tel_user FROM recruteurs r JOIN utilisateurs u ON r.id_user = u.id_user WHERE r.id_recruteur = :id");
                    $stmt->bindParam(':id', $_SESSION['user_id']);
                    $stmt->execute();
                    $recruteur = $stmt->fetch();
                } catch (PDOException $e) {
                    $message_error = "Erreur DB : " . $e->getMessage();
                }
            } else {
                $message_error = "Erreur lors de l'upload du fichier";
            }
        } else {
            $message_error = "Fichier invalide (PNG, JPG ou WEBP, max 2MB)";
        }
    }

    // DELETE LOGO
    if ($action === 'delete_logo') {
        try {
            if (!empty($recruteur['logo'])) {
                $file = __DIR__ . '/../../uploads/logos/' . $recruteur['logo'];
                if (file_exists($file)) @unlink($file);
            }
            $stmt = $pdo->prepare("UPDATE recruteurs SET logo = NULL WHERE id_recruteur = :id");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $message_success = "Logo supprimé avec succès";
            
            $stmt = $pdo->prepare("SELECT r.*, u.email, u.tel_user FROM recruteurs r JOIN utilisateurs u ON r.id_user = u.id_user WHERE r.id_recruteur = :id");
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
            $recruteur = $stmt->fetch();
        } catch (PDOException $e) {
            $message_error = "Erreur lors de la suppression du logo";
        }
    }
}

$fields = ['nom_entreprise', 'ville_entreprise', 'adresse_professionnelle', 'nombre_employes', 'description_entreprise', 'logo'];
$total_fields = count($fields);
$filled = 0;
foreach ($fields as $f) {
    if (!empty($recruteur[$f])) $filled++;
}
$profil_pct = (int)(($filled / $total_fields) * 100);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - NextCareer</title>
    <link rel="stylesheet" href="../../assets/css/tailwind.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">

<!-- Navbar -->
<nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-2">
                <div style="font-family:'Inter',sans-serif;font-size:1.75rem;font-weight:700;color:#1a202c;position:relative;display:inline-block;">
                    Next<span style="color:#50C878;">Career</span>
                    <div style="position:absolute;bottom:2px;left:0;width:100%;height:3px;background:linear-gradient(90deg,#50C878 0%,#2EAD5A 100%);border-radius:2px;"></div>
                </div>
            </div>

            <div class="hidden md:flex items-center space-x-8">
                <a href="../../index.php" class="text-gray-600 hover:text-gray-900 transition font-medium text-sm">Accueil</a>
                <a href="../../offres.php" class="text-gray-600 hover:text-gray-900 transition font-medium text-sm">Offres d'emploi</a>
                <a href="../../entreprises.php" class="text-gray-600 hover:text-gray-900 transition font-medium text-sm">Entreprises</a>
            </div>

            <div class="flex items-center space-x-4">
                <div class="hidden sm:flex items-center space-x-3 border-r border-gray-300 pr-4">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                            <span class="text-lg font-bold text-green-600">
                                <?= strtoupper(substr($recruteur['nom_entreprise'] ?? 'E', 0, 1)) ?>
                            </span>
                    </div>
                    <div class="text-sm">
                        <p class="font-medium text-gray-900"><?= htmlspecialchars($recruteur['nom_entreprise'] ?? 'Entreprise') ?></p>
                        <p class="text-xs text-gray-500">Recruteur</p>
                    </div>
                </div>

                <a href="../../auth/logout.php" class="px-6 py-2 rounded-full text-white font-medium text-sm transition" style="background:linear-gradient(135deg,#50C878 0%,#2EAD5A 100%);" onmouseover="this.style.background='linear-gradient(135deg,#2EAD5A 0%,#1E8B44 100%)'" onmouseout="this.style.background='linear-gradient(135deg,#50C878 0%,#2EAD5A 100%)'">
                    Déconnexion
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <a href="dashboard.php" class="inline-flex items-center gap-2 hover:opacity-80 mb-6 text-sm font-medium" style="color:#50C878;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Retour au tableau de bord
    </a>

    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Mon Profil</h1>
        <p class="text-gray-600">Gérez les informations de votre entreprise</p>
    </div>

    <?php if ($message_success): ?>
        <div class="mb-6 flex items-center gap-3 px-5 py-4 rounded-xl" style="background-color:#E8F7F0;color:#1E8B44;border:1px solid #A8E4C8;">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="font-medium"><?= htmlspecialchars($message_success) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($message_error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl flex items-center gap-3">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="font-medium"><?= htmlspecialchars($message_error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Sidebar -->
        <div class="space-y-6">
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <h3 class="font-semibold text-gray-900 mb-4 text-sm">Logo de l'entreprise</h3>
                
                <div class="flex flex-col items-center">
                    <div class="w-32 h-32 rounded-xl flex items-center justify-center mb-4 overflow-hidden border-2" style="background-color:#E8F7F0;border-color:#A8E4C8;">
                        <?php if (!empty($recruteur['logo'])): ?>
                            <img src="../../uploads/logos/<?= htmlspecialchars($recruteur['logo']) ?>" alt="Logo" class="w-full h-full object-contain p-3">
                        <?php else: ?>
                            <svg class="w-16 h-16" style="color:#50C878;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="logo-form" class="w-full">
                        <input type="hidden" name="action" value="upload_logo">
                        <input type="file" id="logo-upload" name="logo_file" accept="image/*" class="hidden" onchange="this.form.submit()">
                    </form>

                    <label for="logo-upload" class="w-full px-4 py-2.5 text-white text-sm rounded-xl transition font-medium text-center cursor-pointer" style="background:linear-gradient(135deg,#50C878 0%,#2EAD5A 100%);" onmouseover="this.style.background='linear-gradient(135deg,#2EAD5A 0%,#1E8B44 100%)'" onmouseout="this.style.background='linear-gradient(135deg,#50C878 0%,#2EAD5A 100%)'">
                        Charger un logo
                    </label>

                    <?php if (!empty($recruteur['logo'])): ?>
                        <button onclick="deleteLogo()" class="w-full mt-2 px-4 py-2.5 bg-white border-2 border-red-300 text-red-600 text-sm rounded-xl hover:bg-red-50 transition font-medium">
                            Supprimer
                        </button>
                    <?php endif; ?>

                    <p class="text-xs text-gray-500 mt-3 text-center">PNG, JPG ou WEBP · Max 2MB</p>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-semibold text-gray-900 text-sm">Profil complété</h3>
                    <span class="text-xl font-bold" style="color:#50C878;"><?= $profil_pct ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="h-3 rounded-full transition-all" style="width:<?= $profil_pct ?>%;background:linear-gradient(90deg,#50C878 0%,#2EAD5A 100%);"></div>
                </div>
                <p class="text-xs text-gray-500 mt-3">
                    <?= $profil_pct < 100 ? 'Complétez votre profil pour plus de visibilité' : '✓ Profil complet !' ?>
                </p>
            </div>
        </div>

        <!-- Main content -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Informations de l'entreprise</h3>
                    <button onclick="toggleEdit()" class="px-5 py-2.5 border-2 rounded-xl transition text-sm font-medium" id="edit-btn" style="border-color:#50C878;color:#50C878;" onmouseover="if(!this.classList.contains('editing')){this.style.backgroundColor='#50C878';this.style.color='white';}" onmouseout="if(!this.classList.contains('editing')){this.style.backgroundColor='';this.style.color='#50C878';}">
                        Modifier
                    </button>
                </div>

                <form method="POST" id="info-form">
                    <input type="hidden" name="action" value="update_infos">

                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom de l'entreprise *</label>
                            <input type="text" name="nom_entreprise" value="<?= htmlspecialchars($recruteur['nom_entreprise'] ?? '') ?>" required disabled class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-600" onfocus="this.style.borderColor='#50C878';this.style.boxShadow='0 0 0 3px rgba(80,200,120,0.1)'" onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
                        </div>

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ville</label>
                                <input type="text" name="ville_entreprise" value="<?= htmlspecialchars($recruteur['ville_entreprise'] ?? '') ?>" placeholder="Ex: Ouagadougou" disabled class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent disabled:bg-gray-50" onfocus="this.style.borderColor='#50C878';this.style.boxShadow='0 0 0 3px rgba(80,200,120,0.1)'" onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nombre d'employés</label>
                                <input type="text" name="nombre_employes" value="<?= htmlspecialchars($recruteur['nombre_employes'] ?? '') ?>" placeholder="Ex: 1-10, 50+" disabled class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent disabled:bg-gray-50" onfocus="this.style.borderColor='#50C878';this.style.boxShadow='0 0 0 3px rgba(80,200,120,0.1)'" onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adresse professionnelle</label>
                            <input type="text" name="adresse_professionnelle" value="<?= htmlspecialchars($recruteur['adresse_professionnelle'] ?? '') ?>" placeholder="Adresse complète" disabled class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent disabled:bg-gray-50" onfocus="this.style.borderColor='#50C878';this.style.boxShadow='0 0 0 3px rgba(80,200,120,0.1)'" onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
                        </div>

                        

                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($recruteur['email'] ?? '') ?>" required disabled class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-600" onfocus="this.style.borderColor='#50C878';this.style.boxShadow='0 0 0 3px rgba(80,200,120,0.1)'" onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                                <input type="tel" name="telephone" value="<?= htmlspecialchars($recruteur['tel_user'] ?? '') ?>" placeholder="+226 XX XX XX XX" disabled class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent disabled:bg-gray-50" onfocus="this.style.borderColor='#50C878';this.style.boxShadow='0 0 0 3px rgba(80,200,120,0.1)'" onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description de l'entreprise</label>
                            <textarea name="description_entreprise" rows="4" placeholder="Présentez votre entreprise, sa mission et ses valeurs..." disabled class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent disabled:bg-gray-50" onfocus="this.style.borderColor='#50C878';this.style.boxShadow='0 0 0 3px rgba(80,200,120,0.1)'" onblur="this.style.borderColor='#d1d5db';this.style.boxShadow='none'"><?= htmlspecialchars($recruteur['description_entreprise'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <button type="submit" id="save-btn" disabled class="mt-6 px-6 py-3 text-white rounded-xl transition font-medium disabled:bg-gray-300 disabled:cursor-not-allowed inline-flex items-center" style="background:linear-gradient(135deg,#50C878 0%,#2EAD5A 100%);" onmouseover="if(!this.disabled)this.style.background='linear-gradient(135deg,#2EAD5A 0%,#1E8B44 100%)'" onmouseout="if(!this.disabled)this.style.background='linear-gradient(135deg,#50C878 0%,#2EAD5A 100%)'">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Enregistrer les modifications
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let isEditing = false;

function toggleEdit() {
    const form = document.getElementById('info-form');
    const inputs = form.querySelectorAll('input:not([type="hidden"]), textarea');
    const saveBtn = document.getElementById('save-btn');
    const editBtn = document.getElementById('edit-btn');

    isEditing = !isEditing;

    inputs.forEach(input => input.disabled = !isEditing);
    saveBtn.disabled = !isEditing;
    
    if (isEditing) {
        editBtn.innerHTML = '<svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> Annuler';
        editBtn.classList.add('editing');
        editBtn.style.borderColor = '#d1d5db';
        editBtn.style.color = '#374151';
    } else {
        editBtn.textContent = 'Modifier';
        editBtn.classList.remove('editing');
        editBtn.style.borderColor = '#50C878';
        editBtn.style.color = '#50C878';
    }
}

function deleteLogo() {
    if (!confirm('Êtes-vous sûr de vouloir supprimer le logo ?')) return;
    const form = new FormData();
    form.append('action', 'delete_logo');
    fetch('', { method: 'POST', body: form })
        .then(() => location.reload())
        .catch(() => alert('Erreur lors de la suppression du logo'));
}
</script>

</body>
</html>
