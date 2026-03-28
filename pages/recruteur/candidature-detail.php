<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'recruteur') {
    header('Location: ../../auth/login.php');
    exit();
}

require_once '../../config/db.php';

$id_recruteur = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT nom_entreprise FROM recruteurs WHERE id_recruteur = :id");
$stmt->execute([':id' => $id_recruteur]);
$recruteur = $stmt->fetch();

$id_candidature = $_GET['id'] ?? null;
if (!$id_candidature) {
    header('Location: candidatures.php');
    exit();
}

$success = '';
$error = '';

// Récupérer la candidature
try {
    $stmt = $pdo->prepare("
        SELECT 
            ca.*,
            c.*,
            o.titre_emploi,
            o.description_offre,
            o.id_recruteur
        FROM candidatures ca
        INNER JOIN candidats c ON ca.id_candidat = c.id_candidat
        INNER JOIN offres_emploi o ON ca.id_offre = o.id_offre
        WHERE ca.id_candidature = :id
    ");
    $stmt->bindParam(':id', $id_candidature);
    $stmt->execute();
    $candidature = $stmt->fetch();
    
    if (!$candidature || $candidature['id_recruteur'] != $id_recruteur) {
        header('Location: candidatures.php');
        exit();
    }
} catch (PDOException $e) {
    $error = "Erreur de chargement";
    $candidature = null;
}

// Traiter le changement de statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nouveau_statut'])) {
    $nouveau_statut = $_POST['nouveau_statut'];
    
    try {
        $stmt = $pdo->prepare("UPDATE candidatures SET statut = :statut WHERE id_candidature = :id");
        $stmt->execute([
            ':statut' => $nouveau_statut,
            ':id' => $id_candidature
        ]);
        
        $success = "Statut mis à jour avec succès !";
        $candidature['statut'] = $nouveau_statut;
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour du statut";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails candidature - NextCareer</title>
    <link rel="stylesheet" href="../../assets/css/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#50C878',
                        'primary-dark': '#2EAD5A',
                        'primary-light': '#7FD89F',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg {
            background: linear-gradient(135deg, #50C878 0%, #2EAD5A 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(80, 200, 120, 0.2);
        }
    </style>
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
                    <a href="../../index.php" class="text-gray-600 hover:text-green-600 transition font-medium">Accueil</a>
                    <a href="../../offres.php" class="text-gray-600 hover:text-green-600 transition font-medium">Offres d'emploi</a>
                    <a href="../../entreprises.php" class="text-gray-600 hover:text-green-600 transition font-medium">Entreprises</a>
                </div>

                <!-- Right Side - User Info -->
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
    
                    <a href="../../auth/logout.php" class="gradient-bg text-white px-6 py-2 rounded-full hover:opacity-90 transition font-medium text-sm">
                        Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Back link -->
        <a href="candidatures.php" class="inline-flex items-center gap-2 text-green-600 hover:text-green-700 mb-6 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour aux candidatures
        </a>

        <!-- Messages -->
        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl flex items-center">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-medium"><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl flex items-center">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($candidature): ?>
            <div class="space-y-6">
                <!-- Infos candidat -->
                <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                    <div class="flex items-start justify-between mb-6">
                        <div class="flex items-center">
                            <!-- Avatar -->
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                <span class="text-2xl font-bold text-green-600">
                                    <?= strtoupper(substr($candidature['prenom'] ?? 'U', 0, 1) . substr($candidature['nom'] ?? 'N', 0, 1)) ?>
                                </span>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">
                                    <?= htmlspecialchars(($candidature['prenom'] ?? 'Prénom') . ' ' . ($candidature['nom'] ?? 'Nom')) ?>
                                </h1>
                                <p class="text-gray-600 flex items-center mt-1">
                                    <svg class="w-4 h-4 mr-1.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="font-medium text-green-600"><?= htmlspecialchars($candidature['titre_emploi'] ?? 'Poste non spécifié') ?></span>
                                </p>
                            </div>
                        </div>
                        <div>
                            <?php
                            $statut = $candidature['statut'] ?? 'En attente';
                            $badge_class = match($statut) {
                                'Acceptée' => 'bg-green-100 text-green-700 border border-green-200',
                                'Refusée' => 'bg-red-100 text-red-700 border border-red-200',
                                default => 'bg-orange-100 text-orange-700 border border-orange-200',
                            };
                            ?>
                            <span class="px-4 py-2 rounded-full text-sm font-semibold <?= $badge_class ?>">
                                <?= htmlspecialchars($statut) ?>
                            </span>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6 border-t border-gray-200 pt-6">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Email</p>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($candidature['email'] ?? 'Non fourni') ?></p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Téléphone</p>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($candidature['telephone'] ?? 'Non fourni') ?></p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Niveau d'études</p>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($candidature['niveau_etudes'] ?? 'Non spécifié') ?></p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Date de candidature</p>
                                <p class="font-medium text-gray-900">
                                    <?= !empty($candidature['date_candidature']) ? date('d/m/Y à H:i', strtotime($candidature['date_candidature'])) : 'Non disponible' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lettre de motivation -->
                <?php if (!empty($candidature['lettre_motivation'])): ?>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-900 flex items-center">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Lettre de motivation
                            </h2>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-5 text-gray-700 text-sm leading-relaxed border border-gray-200" style="word-wrap: break-word; overflow-wrap: break-word; white-space: pre-wrap;">
<?= htmlspecialchars($candidature['lettre_motivation']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- CV -->
                <?php if (!empty($candidature['cv_numerique'])): ?>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Curriculum Vitae
                        </h2>
                        <div class="flex items-center justify-between p-5 bg-gradient-to-r from-green-50 to-green-100 rounded-lg border-2 border-green-200">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-7 h-7 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">CV du candidat</p>
                                    <p class="text-xs text-gray-600 mt-0.5">Document PDF</p>
                                </div>
                            </div>
                          <?php if (!empty($candidature['cv_numerique'])): ?>
                                <?php 
                                $cv_filename = $candidature['cv_numerique'];
                                $cv_url = '../../uploads/cv/' . $cv_filename;
                                ?>
                                <a href="<?= htmlspecialchars($cv_url) ?>" 
                                    download="<?= htmlspecialchars(pathinfo($cv_filename, PATHINFO_BASENAME)) ?>"
                                    target="_blank" 
                                    class="px-6 py-2.5 gradient-bg text-white rounded-lg hover:opacity-90 transition text-sm font-medium inline-flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        Télécharger CV
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Changer le statut -->
                <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                    <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Gérer le statut de la candidature
                    </h2>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nouveau statut</label>
                            <select name="nouveau_statut" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="En attente" <?= ($candidature['statut'] ?? '') === 'En attente' ? 'selected' : '' ?>>⏳ En attente</option>
                                <option value="Acceptée" <?= ($candidature['statut'] ?? '') === 'Acceptée' ? 'selected' : '' ?>>✅ Acceptée</option>
                                <option value="Refusée" <?= ($candidature['statut'] ?? '') === 'Refusée' ? 'selected' : '' ?>>❌ Refusée</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="w-full px-6 py-3 gradient-bg text-white rounded-xl hover:opacity-90 transition font-medium inline-flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Mettre à jour le statut
                        </button>
                    </form>
                </div>

                <!-- Actions supplémentaires -->
                <div class="grid md:grid-cols-2 gap-4">
                    <a href="mailto:<?= htmlspecialchars($candidature['email'] ?? '') ?>" 
                       class="px-6 py-3 bg-white border-2 border-green-500 text-green-600 rounded-xl hover:bg-green-50 transition font-medium text-center inline-flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Contacter par email
                    </a>
                    <a href="tel:<?= htmlspecialchars($candidature['telephone'] ?? '') ?>" 
                       class="px-6 py-3 bg-white border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium text-center inline-flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        Appeler
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl p-12 border border-gray-200 text-center">
                <svg class="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Candidature introuvable</h3>
                <p class="text-gray-600 mb-6">Cette candidature n'existe pas ou vous n'avez pas l'autorisation d'y accéder</p>
                <a href="candidatures.php" class="inline-flex items-center text-green-600 hover:text-green-700 font-medium">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour aux candidatures
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>