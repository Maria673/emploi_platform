<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'recruteur') {
    header('Location: ../../auth/login.php');
    exit();
}

require_once '../../config/db.php';

$id_offre = $_GET['id'] ?? null;
if (!$id_offre) {
    header('Location: mes-offres.php');
    exit();
}

$id_recruteur = $_SESSION['user_id'];
$error = '';
$success = '';

// Récupérer les domaines
try {
    $stmt = $pdo->query("SELECT * FROM domaines ORDER BY nom_domaine");
    $domaines = $stmt->fetchAll();
} catch (PDOException $e) {
    $domaines = [];
}

// Récupérer l'offre
try {
    $stmt = $pdo->prepare("
        SELECT * FROM offres_emploi 
        WHERE id_offre = :id AND id_recruteur = :id_recruteur
    ");
    $stmt->execute([':id' => $id_offre, ':id_recruteur' => $id_recruteur]);
    $offre = $stmt->fetch();
    
    if (!$offre) {
        header('Location: mes-offres.php');
        exit();
    }
    
    // Convertir competences_requises en array
    $competences = $offre['competences_requises'] ? explode(', ', $offre['competences_requises']) : [];
} catch (PDOException $e) {
    header('Location: mes-offres.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'modifier') {
        $titre_emploi = trim($_POST['titre_emploi'] ?? '');
        $id_domaine = $_POST['id_domaine'] ?? null;
        $localisation = trim($_POST['localisation'] ?? '');
        $salaire_min = $_POST['salaire_min'] ?? null;
        $salaire_max = $_POST['salaire_max'] ?? null;
        $date_limite = $_POST['date_limite'] ?? null;
        $description_offre = trim($_POST['description_offre'] ?? '');
        $profil_recherche = trim($_POST['profil_recherche'] ?? '');
        $avantages = trim($_POST['avantages'] ?? '');
        $competences_post = $_POST['competences'] ?? [];
        
        // Validation
        if (empty($titre_emploi) || empty($id_domaine) || empty($localisation) || empty($description_offre)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif (count($competences_post) < 3) {
            $error = "Veuillez ajouter au moins 3 compétences requises.";
        } elseif (strlen($description_offre) < 100) {
            $error = "La description doit contenir au moins 100 caractères.";
        } else {
            try {
                $competences_str = implode(', ', $competences_post);
                
                $stmt = $pdo->prepare("
                    UPDATE offres_emploi SET
                        titre_emploi = :titre,
                        id_domaine = :domaine,
                        localisation = :localisation,
                        salaire_min = :salaire_min,
                        salaire_max = :salaire_max,
                        date_limite = :date_limite,
                        description_offre = :description,
                        profil_recherche = :profil,
                        avantages = :avantages,
                        competences_requises = :competences
                    WHERE id_offre = :id_offre
                ");
                
                $stmt->execute([
                    ':titre' => $titre_emploi,
                    ':domaine' => $id_domaine,
                    ':localisation' => $localisation,
                    ':salaire_min' => $salaire_min ?: null,
                    ':salaire_max' => $salaire_max ?: null,
                    ':date_limite' => $date_limite,
                    ':description' => $description_offre,
                    ':profil' => $profil_recherche ?: null,
                    ':avantages' => $avantages ?: null,
                    ':competences' => $competences_str,
                    ':id_offre' => $id_offre
                ]);
                
                $success = "Offre mise à jour avec succès !";
                
                // Réactualiser l'offre
                $offre['titre_emploi'] = $titre_emploi;
                $offre['id_domaine'] = $id_domaine;
                $offre['localisation'] = $localisation;
                $offre['salaire_min'] = $salaire_min;
                $offre['salaire_max'] = $salaire_max;
                $offre['date_limite'] = $date_limite;
                $offre['description_offre'] = $description_offre;
                $offre['profil_recherche'] = $profil_recherche;
                $offre['avantages'] = $avantages;
                $competences = $competences_post;
            } catch (PDOException $e) {
                $error = "Erreur lors de la mise à jour : " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une offre - NextCareer</title>
    <link rel="stylesheet" href="../../assets/css/tailwind.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Custom color palette based on #50C878 */
        .bg-primary { background-color: #50C878; }
        .bg-primary-dark { background-color: #2EAD5A; }
        .bg-primary-light { background-color: #7FD89F; }
        .bg-primary-pale { background-color: #E8F7F0; }
        .text-primary { color: #50C878; }
        .text-primary-dark { color: #3A8B5C; }
        .border-primary { border-color: #50C878; }
        .hover-primary:hover { background-color: #2EAD5A; }
        .focus-primary:focus { 
            border-color: #50C878;
            box-shadow: 0 0 0 3px rgba(132, 215, 175, 0.1);
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#50C878',
                        'primary-dark': '#2EAD5A',
                        'primary-light': '#7FD89F',
                        'primary-pale': '#E8F7F0',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                 <!-- Logo -->
                <div class="flex items-center space-x-2">
                    <div style="font-family: 'Inter', sans-serif; font-size: 1.75rem; font-weight: 700; color: #1a202c; position: relative; display: inline-block;">
                        Next<span style="color: #50C878;">Career</span>
                        <div style="position: absolute; bottom: 2px; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, #50C878 0%, #2EAD5A 100%); border-radius: 2px;"></div>
                    </div>
                </div>

                <div class="hidden md:flex items-center space-x-8">
                    <a href="/index.php" class="text-gray-600 hover:text-primary-dark transition font-medium">Accueil</a>
                    <a href="/offres.php" class="text-gray-600 hover:text-primary-dark transition font-medium">Offres d'emploi</a>
                    <a href="/entreprises.php" class="text-gray-600 hover:text-primary-dark transition font-medium">Entreprises</a>
                </div>

                <!-- Right Side - User Info -->
                 <div class="flex items-center space-x-4">
                    <div class="hidden sm:flex items-center space-x-3 border-r border-gray-300 pr-4">
                        <div class="w-10 h-10 bg-primary bg-opacity-20 rounded-full flex items-center justify-center">
                            <span class="text-lg font-bold text-primary">
                                <?= strtoupper(substr($recruteur['nom_entreprise'] ?? 'E', 0, 1)) ?>
                            </span>
                        </div>
                        <div class="text-sm">
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($recruteur['nom_entreprise'] ?? 'Entreprise') ?></p>
                            <p class="text-xs text-gray-500">Recruteur</p>
                        </div>
                    </div>

                    <a href="../../auth/logout.php" class="gradient-bg text-white px-6 py-2 rounded-full hover:opacity-90 transition font-medium text-sm">
                        Déconnexion
                    </a>
                </div>
                
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
         <!-- Back link -->
    <a href="offre-gerer.php" class="inline-flex items-center gap-2 hover:opacity-80 mb-6 text-sm font-medium" style="color: #50C878;">
        ← Retour 
    </a>
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Modifier une offre</h1>
            <p class="text-gray-600">Mettez à jour les informations de votre offre d'emploi</p>
        </div>

        <!-- Messages -->
        <?php if ($success): ?>
            <div class="mb-6 px-4 py-3 rounded-xl" style="background-color: #E8F7F0; border: 1px solid #7FD89F; color: #3A8B5C;">
                 <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">
                 <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <form method="POST" class="space-y-6">
            <!-- Informations de base -->
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <div class="flex items-center mb-6">
                    <svg class="w-6 h-6 mr-2" style="color: #50C878;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <h2 class="text-xl font-bold text-gray-900">Informations de base</h2>
                </div>

                <div class="space-y-4">
                    <!-- Titre -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Titre du poste <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="titre_emploi"
                            value="<?= htmlspecialchars($offre['titre_emploi']) ?>"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                            style="outline: none;"
                            onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(132, 215, 175, 0.1)'"
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                        >
                    </div>

                    <div class="grid md:grid-cols-2 gap-4">
                        <!-- Domaine -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Domaine <span class="text-red-500">*</span></label>
                            <select name="id_domaine" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2"
                                    onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(132, 215, 175, 0.1)'"
                                    onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'">
                                <option value="">Sélectionnez un domaine</option>
                                <?php foreach ($domaines as $domaine): ?>
                                    <option value="<?= $domaine['id_domaine'] ?>" <?= $offre['id_domaine'] == $domaine['id_domaine'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($domaine['nom_domaine']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Localisation -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Localisation <span class="text-red-500">*</span></label>
                            <input 
                                type="text" 
                                name="localisation"
                                value="<?= htmlspecialchars($offre['localisation']) ?>"
                                required
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                                onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(132, 215, 175, 0.1)'"
                                onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                            >
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description <span class="text-red-500">*</span></label>
                        <textarea 
                            name="description_offre"
                            required
                            rows="6"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                            onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(132, 215, 175, 0.1)'"
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                        ><?= htmlspecialchars($offre['description_offre']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Rémunération -->
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Rémunération</h2>
                
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Salaire minimum (FCFA)</label>
                        <input 
                            type="number" 
                            name="salaire_min"
                            value="<?= htmlspecialchars($offre['salaire_min'] ?? '') ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                            onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(132, 215, 175, 0.1)'"
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Salaire maximum (FCFA)</label>
                        <input 
                            type="number" 
                            name="salaire_max"
                            value="<?= htmlspecialchars($offre['salaire_max'] ?? '') ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                            onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(132, 215, 175, 0.1)'"
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                        >
                    </div>
                </div>
            </div>

            <!-- Compétences -->
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Compétences requises <span class="text-red-500">*</span></h2>
                
                <div class="flex gap-2 mb-4">
                    <input 
                        type="text" 
                        id="competence-input"
                        class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                        placeholder="Ajouter une compétence"
                        onkeypress="if(event.key==='Enter'){event.preventDefault();addCompetence();}"
                        onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(132, 215, 175, 0.1)'"
                        onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                    >
                    <button 
                        type="button"
                        onclick="addCompetence()"
                        class="px-6 py-3 text-white rounded-xl transition font-medium"
                        style="background: linear-gradient(135deg, #50C878 0%, #2EAD5A 100%);"
                        onmouseover="this.style.background='linear-gradient(135deg, #2EAD5A 0%, #3A8B5C 100%)'"
                        onmouseout="this.style.background='linear-gradient(135deg, #50C878 0%, #2EAD5A 100%)'">
                        Ajouter
                    </button>
                </div>

                <div id="competences-list" class="flex flex-wrap gap-2 mb-4">
                    <?php foreach ($competences as $comp): ?>
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full" style="background-color: #E8F7F0; color: #3A8B5C;">
                            <input type="hidden" name="competences[]" value="<?= htmlspecialchars($comp) ?>">
                            <span class="text-sm font-medium"><?= htmlspecialchars($comp) ?></span>
                            <button type="button" onclick="this.parentElement.remove()" class="rounded-full p-0.5 transition" style="background-color: transparent;" onmouseover="this.style.backgroundColor='#7FD89F'" onmouseout="this.style.backgroundColor='transparent'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Autres informations -->
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Autres informations</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Profil recherché</label>
                        <textarea 
                            name="profil_recherche"
                            rows="3"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                            onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(132, 215, 175, 0.1)'"
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                        ><?= htmlspecialchars($offre['profil_recherche'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Avantages</label>
                        <textarea 
                            name="avantages"
                            rows="3"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                            onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(132, 215, 175, 0.1)'"
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                        ><?= htmlspecialchars($offre['avantages'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date limite <span class="text-red-500">*</span></label>
                        <input 
                            type="date" 
                            name="date_limite"
                            value="<?= htmlspecialchars($offre['date_limite'] ?? '') ?>"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                            onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(132, 215, 175, 0.1)'"
                            onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                        >
                    </div>
                </div>
            </div>

            <!-- Boutons -->
            <div class="flex gap-3">
                <button 
                    type="submit"
                    name="action"
                    value="modifier"
                    class="flex-1 px-6 py-3 text-white rounded-xl transition font-medium"
                    style="background: linear-gradient(135deg, #50C878 0%, #2EAD5A 100%);"
                    onmouseover="this.style.background='linear-gradient(135deg, #2EAD5A 0%, #3A8B5C 100%)'"
                    onmouseout="this.style.background='linear-gradient(135deg, #50C878 0%, #2EAD5A 100%)'">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Enregistrer les modifications
                </button>
                <a href="mes-offres.php" class="px-6 py-3 bg-white border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium text-center">Annuler</a>
            </div>
        </form>
    </div>

    <script>
        let competences = <?= json_encode($competences) ?>;

        function addCompetence() {
            const input = document.getElementById('competence-input');
            const value = input.value.trim();
            
            if (!value) return;
            if (competences.includes(value)) {
                alert('Cette compétence existe déjà');
                return;
            }
            
            competences.push(value);
            updateCompetencesList();
            input.value = '';
            input.focus();
        }

        function updateCompetencesList() {
            const list = document.getElementById('competences-list');
            list.innerHTML = competences.map((comp, index) => `
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full" style="background-color: #E8F7F0; color: #3A8B5C;">
                    <input type="hidden" name="competences[]" value="${comp}">
                    <span class="text-sm font-medium">${comp}</span>
                    <button type="button" onclick="competences.splice(${index}, 1); updateCompetencesList();" class="rounded-full p-0.5 transition hover:bg-opacity-80">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            `).join('');
        }
    </script>
</body>
</html>