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

$id_offre = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// Vérifier que l'offre appartient bien au recruteur
try {
    $stmt = $pdo->prepare("
        SELECT * FROM offres_emploi 
        WHERE id_offre = :id_offre AND id_recruteur = :id_recruteur
    ");
    $stmt->execute([
        ':id_offre' => $id_offre,
        ':id_recruteur' => $id_recruteur
    ]);
    $offre = $stmt->fetch();
    
    if (!$offre) {
        header('Location: mes-offres.php');
        exit();
    }
} catch (PDOException $e) {
    header('Location: mes-offres.php');
    exit();
}

// Traiter les actions (accepter/refuser candidature)
if (isset($_GET['action']) && isset($_GET['candidature_id'])) {
    $candidature_id = intval($_GET['candidature_id']);
    $action = $_GET['action'];
    
    try {
        if ($action === 'accepter') {
            $stmt = $pdo->prepare("UPDATE candidatures SET statut = 'Acceptée' WHERE id_candidature = :id");
            $stmt->execute([':id' => $candidature_id]);
            $message = 'Candidature acceptée avec succès';
        } elseif ($action === 'refuser') {
            $stmt = $pdo->prepare("UPDATE candidatures SET statut = 'Refusée' WHERE id_candidature = :id");
            $stmt->execute([':id' => $candidature_id]);
            $message = 'Candidature refusée';
        }
        
        header('Location: offre-gerer.php?id=' . $id_offre . '&msg=' . urlencode($message));
        exit();
    } catch (PDOException $e) {
        $message = 'Erreur: ' . $e->getMessage();
    }
}

// Supprimer l'offre
if (isset($_GET['supprimer']) && $_GET['supprimer'] === 'confirm') {
    try {
        // Supprimer d'abord les candidatures liées
        $stmt = $pdo->prepare("DELETE FROM candidatures WHERE id_offre = :id_offre");
        $stmt->execute([':id_offre' => $id_offre]);
        
        // Supprimer l'offre
        $stmt = $pdo->prepare("DELETE FROM offres_emploi WHERE id_offre = :id_offre AND id_recruteur = :id_recruteur");
        $stmt->execute([
            ':id_offre' => $id_offre,
            ':id_recruteur' => $id_recruteur
        ]);
        
        header('Location: mes-offres.php?msg=' . urlencode('Offre supprimée avec succès'));
        exit();
    } catch (PDOException $e) {
        $message = 'Erreur lors de la suppression: ' . $e->getMessage();
    }
}

// Message après redirection
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// Récupérer les candidatures pour cette offre
try {
    $stmt = $pdo->prepare("
        SELECT 
            ca.*,
            c.nom,
            c.prenom,
            c.telephone,
            c.cv_numerique,
            u.email
        FROM candidatures ca
        INNER JOIN candidats c ON ca.id_candidat = c.id_candidat
        LEFT JOIN utilisateurs u ON c.id_user = u.id_user
        WHERE ca.id_offre = :id_offre
        ORDER BY ca.date_candidature DESC
    ");
    $stmt->execute([':id_offre' => $id_offre]);
    $candidatures = $stmt->fetchAll();
} catch (PDOException $e) {
    $candidatures = [];
}

// Compter par statut
$total = count($candidatures);
$en_attente = count(array_filter($candidatures, fn($c) => ($c['statut'] ?? 'En attente') === 'En attente'));
$acceptees = count(array_filter($candidatures, fn($c) => ($c['statut'] ?? '') === 'Acceptée'));
$refusees = count(array_filter($candidatures, fn($c) => ($c['statut'] ?? '') === 'Refusée'));

function tempsEcoule($date) {
    if (empty($date)) return 'N/A';
    $diff = time() - strtotime($date);
    $hours = floor($diff / 3600);
    if ($hours < 24) {
        return $hours == 0 ? "À l'instant" : "Il y a " . $hours . "h";
    } else {
        $days = floor($hours / 24);
        return "Il y a " . $days . " jour" . ($days > 1 ? 's' : '');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer l'offre - NextCareer</title>
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

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Back link -->
        <a href="mes-offres.php" class="inline-flex items-center gap-2 text-green-600 hover:text-green-700 mb-6 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour à mes offres
        </a>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="mb-6 <?= strpos($message, 'Erreur') !== false ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700' ?> border px-5 py-4 rounded-xl flex items-center">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- En-tête de l'offre -->
        <div class="bg-white rounded-xl p-6 border border-gray-200 mb-6 card-hover">
            <h1 class="text-3xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($offre['titre_emploi']) ?></h1>
            
            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 mb-6">
                <?php if (!empty($offre['localisation'])): ?>
                    <span>📍 <?= htmlspecialchars($offre['localisation']) ?></span>
                <?php endif; ?>
                <span>📅 Publié <?= tempsEcoule($offre['date_publication']) ?></span>
                <?php if (!empty($offre['type_contrat'])): ?>
                    <span class="px-3 py-1 bg-gray-100 rounded-full"><?= htmlspecialchars($offre['type_contrat']) ?></span>
                <?php endif; ?>
            </div>

            <div class="flex gap-3">
                <a href="modifier-offre.php?id=<?= $id_offre ?>" class="px-6 py-2.5 gradient-bg text-white rounded-lg hover:opacity-90 transition font-medium inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier l'offre
                </a>
                <button onclick="if(confirm('Êtes-vous sûr de vouloir supprimer cette offre ? Toutes les candidatures associées seront également supprimées.')) window.location.href='?id=<?= $id_offre ?>&supprimer=confirm'" 
                        class="px-6 py-2.5 bg-white border-2 border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition font-medium inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Supprimer l'offre
                </button>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <p class="text-sm text-gray-600 mb-1">Total</p>
                <p class="text-3xl font-bold text-gray-900"><?= $total ?></p>
            </div>
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <p class="text-sm text-gray-600 mb-1">En attente</p>
                <p class="text-3xl font-bold text-orange-600"><?= $en_attente ?></p>
            </div>
            <div class="bg-white rounded-xl p-6 border-2 border-green-500">
                <p class="text-sm text-gray-600 mb-1">Acceptées</p>
                <p class="text-3xl font-bold text-green-600"><?= $acceptees ?></p>
            </div>
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <p class="text-sm text-gray-600 mb-1">Refusées</p>
                <p class="text-3xl font-bold text-red-600"><?= $refusees ?></p>
            </div>
        </div>

        <!-- Liste des candidatures -->
        <div class="bg-white rounded-xl p-6 border border-gray-200">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Candidatures reçues (<?= $total ?>)</h2>

            <?php if (empty($candidatures)): ?>
                <div class="text-center py-12">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Aucune candidature</h3>
                    <p class="text-gray-600">Cette offre n'a pas encore reçu de candidatures</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($candidatures as $c): 
                        $statut = $c['statut'] ?? 'En attente';
                        $badge_class = match($statut) {
                            'Acceptée' => 'bg-green-100 text-green-700 border border-green-200',
                            'Refusée' => 'bg-red-100 text-red-700 border border-red-200',
                            default => 'bg-orange-100 text-orange-700 border border-orange-200',
                        };
                    ?>
                    <div class="border border-gray-200 rounded-xl p-5 hover:border-green-500 transition">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-4 flex-1">
                                <!-- Avatar -->
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-lg font-bold text-green-600">
                                        <?= strtoupper(substr($c['prenom'] ?? 'U', 0, 1) . substr($c['nom'] ?? 'N', 0, 1)) ?>
                                    </span>
                                </div>

                                <!-- Info -->
                                <div class="flex-1">
                                    <h4 class="text-lg font-bold text-gray-900 mb-1">
                                        <?= htmlspecialchars(($c['prenom'] ?? 'Prénom') . ' ' . ($c['nom'] ?? 'Nom')) ?>
                                    </h4>
                                    <p class="text-sm text-gray-600 mb-1">
                                        Email : <?= htmlspecialchars($c['email'] ?? 'Email non renseigné') ?>
                                    </p>
                                    <?php if (!empty($c['telephone'])): ?>
                                    <p class="text-sm text-gray-600 mb-1">
                                         <?= htmlspecialchars($c['telephone']) ?>
                                    </p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-500">
                                         Candidature envoyée <?= tempsEcoule($c['date_candidature']) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col items-end gap-3 ml-4">
                                <span class="px-4 py-1.5 rounded-full text-xs font-semibold <?= $badge_class ?>">
                                    <?= htmlspecialchars($statut) ?>
                                </span>

                                <div class="flex flex-wrap gap-2 justify-end">
                                    <?php if (!empty($c['cv_numerique'])): ?>
                                        <?php 
                                        $cv_filename = $c['cv_numerique'];
                                        $cv_url = '../../uploads/cv/' . $cv_filename;
                                        ?>
                                        <a href="<?= htmlspecialchars($cv_url) ?>" 
                                           download="<?= htmlspecialchars(pathinfo($cv_filename, PATHINFO_BASENAME)) ?>"
                                           class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition text-xs font-medium">
                                             Télécharger CV
                                        </a>
                                        <a href="<?= htmlspecialchars($cv_url) ?>" 
                                           target="_blank"
                                           class="px-3 py-1.5 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition text-xs font-medium">
                                             Voir CV
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($statut === 'En attente'): ?>
                                        <button onclick="if(confirm('Accepter cette candidature ?')) window.location.href='?id=<?= $id_offre ?>&action=accepter&candidature_id=<?= $c['id_candidature'] ?>'"
                                                class="px-3 py-1.5 bg-green-100 text-green-700 border border-green-200 rounded-lg hover:bg-green-600 hover:text-white transition text-xs font-medium">
                                            ✓ Accepter
                                        </button>
                                        <button onclick="if(confirm('Refuser cette candidature ?')) window.location.href='?id=<?= $id_offre ?>&action=refuser&candidature_id=<?= $c['id_candidature'] ?>'"
                                                class="px-3 py-1.5 bg-red-100 text-red-700 border border-red-200 rounded-lg hover:bg-red-600 hover:text-white transition text-xs font-medium">
                                            ✕ Refuser
                                        </button>
                                    <?php elseif (empty($c['cv_numerique'])): ?>
                                        <span class="text-xs text-gray-500">CV non fourni</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>