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

$filtre_statut = $_GET['statut'] ?? 'tous';
$filtre_offre = $_GET['offre'] ?? '';

try {
    $query = "
        SELECT 
            ca.*,
            c.nom,
            c.prenom,
            c.email,
            c.telephone,
            c.cv_numerique,
            o.titre_emploi,
            o.id_offre
        FROM candidatures ca
        INNER JOIN candidats c ON ca.id_candidat = c.id_candidat
        INNER JOIN offres_emploi o ON ca.id_offre = o.id_offre
        WHERE o.id_recruteur = :id_recruteur
    ";
    
    if ($filtre_statut !== 'tous') {
        $query .= " AND ca.statut = :statut";
    }
    
    if ($filtre_offre) {
        $query .= " AND ca.id_offre = :offre";
    }
    
    $query .= " ORDER BY ca.date_candidature DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    
    if ($filtre_statut !== 'tous') {
        $stmt->bindParam(':statut', $filtre_statut);
    }
    
    if ($filtre_offre) {
        $stmt->bindParam(':offre', $filtre_offre);
    }
    
    $stmt->execute();
    $candidatures = $stmt->fetchAll();
} catch (PDOException $e) {
    $candidatures = [];
}

// Récupérer les offres du recruteur pour le filtre
try {
    $stmt = $pdo->prepare("SELECT id_offre, titre_emploi FROM offres_emploi WHERE id_recruteur = :id_recruteur ORDER BY titre_emploi");
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    $stmt->execute();
    $offres = $stmt->fetchAll();
} catch (PDOException $e) {
    $offres = [];
}

// Statistiques
$total = count($candidatures);
$en_attente = count(array_filter($candidatures, fn($c) => ($c['statut'] ?? '') === 'En attente'));
$acceptees = count(array_filter($candidatures, fn($c) => ($c['statut'] ?? '') === 'Acceptée'));
$refusees = count(array_filter($candidatures, fn($c) => ($c['statut'] ?? '') === 'Refusée'));

function tempsEcoule($date) {
    if (empty($date)) return 'N/A';
    $diff = time() - strtotime($date);
    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "Il y a " . $minutes . " min";
    } elseif ($diff < 86400) {
        $heures = floor($diff / 3600);
        return "Il y a " . $heures . "h";
    } elseif ($diff < 604800) {
        $jours = floor($diff / 86400);
        return "Il y a " . $jours . " jour" . ($jours > 1 ? 's' : '');
    } else {
        return date('d/m/Y', strtotime($date));
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidatures reçues - NextCareer</title>
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
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(80, 200, 120, 0.2); }
        .gradient-bg {
            background: linear-gradient(135deg, #50C878 0%, #2EAD5A 100%);
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
        <a href="dashboard.php" class="inline-flex items-center gap-2 text-green-600 hover:text-green-700 mb-6 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour au tableau de bord
        </a>

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Candidatures reçues</h1>
            <p class="text-gray-600">Gérez et suivez les candidatures pour vos offres d'emploi</p>
        </div>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total</p>
                        <p class="text-3xl font-bold text-gray-900"><?= $total ?></p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">En attente</p>
                        <p class="text-3xl font-bold text-orange-600"><?= $en_attente ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Acceptées</p>
                        <p class="text-3xl font-bold text-green-600"><?= $acceptees ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Refusées</p>
                        <p class="text-3xl font-bold text-red-600"><?= $refusees ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-xl p-6 border border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Filtrer les candidatures</h3>
            <form method="GET" class="grid md:grid-cols-3 gap-4">
                <!-- Statut -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                    <select name="statut" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <option value="tous" <?= $filtre_statut === 'tous' ? 'selected' : '' ?>>Tous les statuts</option>
                        <option value="En attente" <?= $filtre_statut === 'En attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="Acceptée" <?= $filtre_statut === 'Acceptée' ? 'selected' : '' ?>>Acceptées</option>
                        <option value="Refusée" <?= $filtre_statut === 'Refusée' ? 'selected' : '' ?>>Refusées</option>
                    </select>
                </div>

                <!-- Offre -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Offre d'emploi</label>
                    <select name="offre" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <option value="">Toutes les offres</option>
                        <?php foreach ($offres as $offre): ?>
                            <option value="<?= $offre['id_offre'] ?>" <?= $filtre_offre == $offre['id_offre'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($offre['titre_emploi'] ?? 'Sans titre') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Boutons -->
                <div class="flex items-end gap-3">
                    <button type="submit" class="flex-1 px-6 py-3 gradient-bg text-white rounded-xl hover:opacity-90 transition font-medium">
                        Appliquer
                    </button>
                    <a href="candidatures.php" class="px-6 py-3 bg-white border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <!-- Liste des candidatures -->
        <?php if (empty($candidatures)): ?>
            <div class="bg-white rounded-xl p-12 border border-gray-200 text-center">
                <svg class="w-20 h-20 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Aucune candidature trouvée</h3>
                <p class="text-gray-600 mb-4">Vous n'avez pas encore reçu de candidatures correspondant à vos critères</p>
                <?php if ($filtre_statut !== 'tous' || $filtre_offre): ?>
                    <a href="candidatures.php" class="inline-flex items-center text-green-600 hover:text-green-700 font-medium">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Voir toutes les candidatures
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($candidatures as $c): ?>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 hover:border-green-500 transition card-hover">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <!-- Avatar et nom -->
                                <div class="flex items-center mb-3">
                                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-lg font-bold text-green-600">
                                            <?= strtoupper(substr($c['prenom'] ?? 'U', 0, 1) . substr($c['nom'] ?? 'N', 0, 1)) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900">
                                            <?= htmlspecialchars(($c['prenom'] ?? 'Prénom') . ' ' . ($c['nom'] ?? 'Nom')) ?>
                                        </h3>
                                        <p class="text-green-600 text-sm font-medium">
                                            <?= htmlspecialchars($c['titre_emploi'] ?? 'Poste non spécifié') ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Informations de contact -->
                                <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-3">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                        <?= htmlspecialchars($c['email'] ?? 'Email non fourni') ?>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                        </svg>
                                        <?= htmlspecialchars($c['telephone'] ?? 'Téléphone non fourni') ?>
                                    </div>
                                </div>

                                <!-- Date -->
                                <div class="flex items-center text-xs text-gray-500">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <?= tempsEcoule($c['date_candidature'] ?? null) ?>
                                </div>
                            </div>

                            <!-- Statut et actions -->
                            <div class="flex flex-col items-end gap-3 ml-6">
                                <?php
                                $statut = $c['statut'] ?? 'En attente';
                                $badge_class = match($statut) {
                                    'Acceptée' => 'bg-green-100 text-green-700 border border-green-200',
                                    'Refusée' => 'bg-red-100 text-red-700 border border-red-200',
                                    default => 'bg-orange-100 text-orange-700 border border-orange-200',
                                };
                                ?>
                                <span class="px-4 py-1.5 rounded-full text-xs font-semibold <?= $badge_class ?>">
                                    <?= htmlspecialchars($statut) ?>
                                </span>
                                <a href="candidature-detail.php?id=<?= $c['id_candidature'] ?>" 
                                   class="px-5 py-2 gradient-bg text-white rounded-lg hover:opacity-90 transition text-sm font-medium inline-flex items-center">
                                    
                                    Voir détails
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>