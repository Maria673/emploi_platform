<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'recruteur') {
    header('Location: ../../auth/login.php');
    exit();
}

require_once '../../config/db.php';

$id_recruteur = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.email
        FROM recruteurs r
        INNER JOIN utilisateurs u ON r.id_user = u.id_user
        WHERE r.id_recruteur = :id_recruteur
    ");
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    $stmt->execute();
    $recruteur = $stmt->fetch();
    
    if (!$recruteur) {
        header('Location: ../../auth/login.php');
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des informations : " . $e->getMessage();
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM offres_emploi WHERE id_recruteur = :id_recruteur");
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    $stmt->execute();
    $total_offres = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM candidatures ca
        INNER JOIN offres_emploi oe ON ca.id_offre = oe.id_offre
        WHERE oe.id_recruteur = :id_recruteur
    ");
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    $stmt->execute();
    $total_candidatures = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM candidatures ca
        INNER JOIN offres_emploi oe ON ca.id_offre = oe.id_offre
        WHERE oe.id_recruteur = :id_recruteur
        AND ca.statut = 'En attente'
    ");
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    $stmt->execute();
    $candidatures_en_attente = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM candidatures ca
        INNER JOIN offres_emploi oe ON ca.id_offre = oe.id_offre
        WHERE oe.id_recruteur = :id_recruteur
        AND DATE(ca.date_candidature) = CURDATE()
    ");
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    $stmt->execute();
    $candidatures_aujourd_hui = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $total_offres = 0;
    $total_candidatures = 0;
    $candidatures_en_attente = 0;
    $candidatures_aujourd_hui = 0;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            oe.*,
            COUNT(DISTINCT ca.id_candidature) as nb_candidatures,
            COUNT(DISTINCT CASE WHEN ca.statut = 'En attente' THEN ca.id_candidature END) as nb_en_attente
        FROM offres_emploi oe
        LEFT JOIN candidatures ca ON oe.id_offre = ca.id_offre
        WHERE oe.id_recruteur = :id_recruteur
        GROUP BY oe.id_offre
        ORDER BY oe.date_publication DESC
        LIMIT 5
    ");
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    $stmt->execute();
    $offres_actives = $stmt->fetchAll();
} catch (PDOException $e) {
    $offres_actives = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            ca.*,
            c.nom,
            c.prenom,
            oe.titre_emploi,
            oe.id_offre
        FROM candidatures ca
        INNER JOIN candidats c ON ca.id_candidat = c.id_candidat
        INNER JOIN offres_emploi oe ON ca.id_offre = oe.id_offre
        WHERE oe.id_recruteur = :id_recruteur
        ORDER BY ca.date_candidature DESC
        LIMIT 5
    ");
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    $stmt->execute();
    $dernieres_candidatures = $stmt->fetchAll();
} catch (PDOException $e) {
    $dernieres_candidatures = [];
}

function tempsEcoule($date) {
    if (empty($date)) return 'N/A';
    $diff = time() - strtotime($date);
    $hours = floor($diff / 3600);
    if ($hours < 1) return "À l'instant";
    if ($hours < 24) return "Il y a " . $hours . "h";
    $days = floor($hours / 24);
    return "Il y a " . $days . " jour" . ($days > 1 ? 's' : '');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - NextCareer</title>
    
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

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    Entreprise <?= htmlspecialchars($recruteur['nom_entreprise']) ?>
                </h1>
                <p class="text-gray-600">Gérez vos offres et candidatures en un clin d'œil</p>
            </div>
            <a href="publier.php" class="inline-flex items-center px-6 py-3 gradient-bg text-white rounded-xl hover:opacity-90 transition font-medium">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Publier une offre
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                <p class="text-sm text-gray-600 mb-1">Offres publiées</p>
                <p class="text-3xl font-bold text-gray-900"><?= $total_offres ?></p>
            </div>

            <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                <p class="text-sm text-gray-600 mb-1">Total candidatures</p>
                <p class="text-3xl font-bold text-blue-600"><?= $total_candidatures ?></p>
            </div>

            <div class="bg-white rounded-xl p-6 border-2 border-orange-300 card-hover">
                <p class="text-sm text-gray-600 mb-1">En attente</p>
                <p class="text-3xl font-bold text-orange-600"><?= $candidatures_en_attente ?></p>
            </div>

            <div class="bg-white rounded-xl p-6 border-2 border-green-500 card-hover">
                <p class="text-sm text-gray-600 mb-1">Aujourd'hui</p>
                <p class="text-3xl font-bold text-green-600"><?= $candidatures_aujourd_hui ?></p>
            </div>
        </div>

        <!-- Two Column Layout -->
        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Column (2/3) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Offres Actives -->
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Mes offres récentes</h2>
                        <a href="mes-offres.php" class="text-green-600 font-medium hover:text-green-700 text-sm inline-flex items-center">
                            Voir tout
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    <?php if (!empty($offres_actives)): ?>
                        <div class="space-y-3">
                            <?php foreach ($offres_actives as $offre): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:border-green-500 transition">
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900 mb-1"><?= htmlspecialchars($offre['titre_emploi']) ?></h3>
                                        <p class="text-xs text-gray-500">Publié <?= tempsEcoule($offre['date_publication']) ?></p>
                                    </div>
                                    <div class="flex items-center gap-4 mr-4">
                                        <div class="text-center">
                                            <p class="text-lg font-bold text-blue-600"><?= $offre['nb_candidatures'] ?></p>
                                            <p class="text-xs text-gray-500">Candidatures</p>
                                        </div>
                                        <?php if ($offre['nb_en_attente'] > 0): ?>
                                        <div class="text-center">
                                            <p class="text-lg font-bold text-orange-600"><?= $offre['nb_en_attente'] ?></p>
                                            <p class="text-xs text-gray-500">En attente</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="offre-gerer.php?id=<?= $offre['id_offre'] ?>" class="px-4 py-2 gradient-bg text-white rounded-lg hover:opacity-90 text-sm font-medium whitespace-nowrap">
                                        Gérer
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Aucune offre publiée</h3>
                            <p class="text-gray-600 mb-4">Commencez par publier votre première offre</p>
                            <a href="publier.php" class="inline-flex items-center text-green-600 font-medium hover:text-green-700">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Publier une offre
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Dernières Candidatures -->
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Candidatures récentes</h2>
                        <a href="candidatures.php" class="text-green-600 font-medium hover:text-green-700 text-sm inline-flex items-center">
                            Voir tout
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    <?php if (!empty($dernieres_candidatures)): ?>
                        <div class="space-y-3">
                            <?php foreach ($dernieres_candidatures as $candidature): ?>
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:border-green-500 transition">
                                    <div class="flex items-center gap-3 flex-1">
                                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                            <span class="text-sm font-bold text-green-600">
                                                <?= strtoupper(substr($candidature['prenom'] ?? 'U', 0, 1) . substr($candidature['nom'] ?? 'N', 0, 1)) ?>
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-900 truncate">
                                                <?= htmlspecialchars(($candidature['prenom'] ?? 'Prénom') . ' ' . ($candidature['nom'] ?? 'Nom')) ?>
                                            </p>
                                            <p class="text-xs text-gray-600 truncate">
                                                <?= htmlspecialchars($candidature['titre_emploi'] ?? 'Poste') ?> • <?= tempsEcoule($candidature['date_candidature']) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <a href="candidature-detail.php?id=<?= $candidature['id_candidature'] ?>" class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-sm font-medium whitespace-nowrap ml-3">
                                        Voir
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Aucune candidature</h3>
                            <p class="text-gray-600">Les candidatures apparaîtront ici</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column (1/3) -->
            <div class="space-y-6">
                <!-- Actions Rapides -->
                <div class="bg-white rounded-xl p-6 border border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Actions rapides</h3>
                    
                    <div class="space-y-2">   
                        <a href="publier.php" class="w-full flex items-center justify-center px-4 py-3 gradient-bg text-white rounded-lg hover:opacity-90 transition font-medium">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Publier une offre
                        </a>

                        <a href="profil.php" class="w-full flex items-center justify-center px-4 py-3 bg-white border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition">
                            <span class="font-medium text-gray-900">Mon profil</span>
                        </a>    

                        <a href="mes-offres.php" class="w-full flex items-center justify-center px-4 py-3 bg-white border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition">
                            <span class="font-medium text-gray-900">Mes offres</span>
                        </a>

                        <a href="candidatures.php" class="w-full flex items-center justify-center px-4 py-3 bg-white border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition">
                            <span class="font-medium text-gray-900">Candidatures</span>
                        </a>

                        <a href="abonnes.php" class="w-full flex items-center justify-center px-4 py-3 bg-white border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition">
                            <span class="font-medium text-gray-900"> Mes abonnés</span>
                        </a>
                    </div>
                </div>

                <!-- Astuce -->
                <div class="gradient-bg rounded-xl p-6 text-white">
                    <div class="flex items-center mb-3">
                        <div class="w-8 h-8 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mr-2">
                            <span class="text-xl">💡</span>
                        </div>
                        <h3 class="text-lg font-bold">Conseil</h3>
                    </div>
                    <p class="text-sm text-white text-opacity-90 mb-4">
                        Les offres détaillées avec une description claire attirent 40% de candidatures en plus !
                    </p>
                    <a href="mes-offres.php" class="inline-block bg-white text-green-600 px-4 py-2 rounded-lg font-medium text-sm hover:bg-opacity-90 transition">
                        Voir mes offres
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>