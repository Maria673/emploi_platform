<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
require_once '../../config/db.php';

// Vérifier si l'utilisateur est connecté et est un recruteur
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'recruteur') {
    header('Location: ../../auth/login.php');
    exit();
}

$id_recruteur = $_SESSION['user_id'];

// Récupérer les informations du recruteur
try {
    $stmt = $pdo->prepare("SELECT * FROM recruteurs WHERE id_recruteur = :id_recruteur");
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    $stmt->execute();
    $recruteur = $stmt->fetch();
} catch (PDOException $e) {
    $recruteur = null;
}

// Gestion des filtres
$filter_statut = $_GET['statut'] ?? 'tous';
$filter_search = $_GET['search'] ?? '';
$filter_domaine = $_GET['domaine'] ?? '';

// Récupérer toutes les offres avec filtres
try {
    $query = "
        SELECT 
            oe.*,
            d.nom_domaine,
            COUNT(DISTINCT ca.id_candidature) as nb_candidatures,
            COUNT(DISTINCT CASE WHEN ca.statut = 'Vue' OR ca.statut = 'Acceptée' THEN ca.id_candidature END) as nb_vues,
            COUNT(DISTINCT CASE WHEN ca.statut = 'En attente' THEN ca.id_candidature END) as nb_en_attente
        FROM offres_emploi oe
        LEFT JOIN domaines d ON oe.id_domaine = d.id_domaine
        LEFT JOIN candidatures ca ON oe.id_offre = ca.id_offre
        WHERE oe.id_recruteur = :id_recruteur
    ";
    
    // Filtres
    if ($filter_search) {
        $query .= " AND (oe.titre_emploi LIKE :search OR oe.description_offre LIKE :search)";
    }
    
    if ($filter_domaine) {
        $query .= " AND oe.id_domaine = :domaine";
    }
    
    $query .= " GROUP BY oe.id_offre ORDER BY oe.date_publication DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id_recruteur', $id_recruteur);
    
    if ($filter_search) {
        $search_param = '%' . $filter_search . '%';
        $stmt->bindParam(':search', $search_param);
    }
    
    if ($filter_domaine) {
        $stmt->bindParam(':domaine', $filter_domaine);
    }
    
    $stmt->execute();
    $offres = $stmt->fetchAll();
    
    // Filtrer par statut côté PHP
    if ($filter_statut !== 'tous') {
        $offres = array_filter($offres, function($offre) use ($filter_statut) {
            if ($filter_statut === 'actives') {
                return strtotime($offre['date_limite']) >= time() || !$offre['date_limite'];
            } elseif ($filter_statut === 'expirees') {
                return $offre['date_limite'] && strtotime($offre['date_limite']) < time();
            }
            return true;
        });
    }
    
} catch (PDOException $e) {
    $offres = [];
}

// Récupérer les domaines pour le filtre
try {
    $stmt = $pdo->query("SELECT * FROM domaines ORDER BY nom_domaine");
    $domaines = $stmt->fetchAll();
} catch (PDOException $e) {
    $domaines = [];
}

// Statistiques
$total_offres = count($offres);
$offres_actives = count(array_filter($offres, function($o) {
    return strtotime($o['date_limite']) >= time() || !$o['date_limite'];
}));
$offres_expirees = $total_offres - $offres_actives;
$total_candidatures = array_sum(array_column($offres, 'nb_candidatures'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes offres - NextCareer</title>
    
    <!-- Tailwind CSS CDN -->
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
        body {
            font-family: 'Inter', sans-serif;
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(80, 200, 120, 0.2);
        }
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
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Mes offres d'emploi</h1>
                    <p class="text-gray-600">Gérez vos offres et suivez les candidatures</p>
                </div>
                <a href="publier.php" class="inline-flex items-center px-6 py-3 gradient-bg text-white rounded-xl hover:opacity-90 transition font-medium">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Publier une offre
                </a>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                <p class="text-sm text-gray-600 mb-1">Total des offres</p>
                <p class="text-3xl font-bold text-gray-900"><?= $total_offres ?></p>
            </div>

            <div class="bg-white rounded-xl p-6 border-2 border-green-500 card-hover">
                <p class="text-sm text-gray-600 mb-1">Offres actives</p>
                <p class="text-3xl font-bold text-green-600"><?= $offres_actives ?></p>
            </div>

            <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                <p class="text-sm text-gray-600 mb-1">Offres expirées</p>
                <p class="text-3xl font-bold text-red-600"><?= $offres_expirees ?></p>
            </div>

            <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                <p class="text-sm text-gray-600 mb-1">Candidatures reçues</p>
                <p class="text-3xl font-bold text-blue-600"><?= $total_candidatures ?></p>
            </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-xl p-6 border border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Filtrer les offres</h3>
            <form method="GET" class="grid md:grid-cols-4 gap-4">
                <!-- Recherche -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rechercher</label>
                    <input 
                        type="text" 
                        name="search"
                        value="<?= htmlspecialchars($filter_search) ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                        placeholder="Titre de l'offre..."
                    >
                </div>

                <!-- Domaine -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Domaine</label>
                    <select 
                        name="domaine"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    >
                        <option value="">Tous</option>
                        <?php foreach ($domaines as $domaine): ?>
                            <option value="<?= $domaine['id_domaine'] ?>" <?= $filter_domaine == $domaine['id_domaine'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($domaine['nom_domaine']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Statut -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                    <select 
                        name="statut"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    >
                        <option value="tous" <?= $filter_statut === 'tous' ? 'selected' : '' ?>>Toutes</option>
                        <option value="actives" <?= $filter_statut === 'actives' ? 'selected' : '' ?>>Actives</option>
                        <option value="expirees" <?= $filter_statut === 'expirees' ? 'selected' : '' ?>>Expirées</option>
                    </select>
                </div>

                <!-- Boutons -->
                <div class="md:col-span-4 flex gap-3">
                    <button 
                        type="submit"
                        class="px-6 py-3 gradient-bg text-white rounded-xl hover:opacity-90 transition font-medium"
                    >
                        Appliquer
                    </button>
                    <a 
                        href="mes-offres.php"
                        class="px-6 py-3 bg-white border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium"
                    >
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <!-- Liste des offres -->
        <?php if (empty($offres)): ?>
            <div class="bg-white rounded-xl p-12 border border-gray-200 text-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Aucune offre trouvée</h3>
                <p class="text-gray-600 mb-6">Commencez par publier votre première offre d'emploi</p>
                <a href="publier.php" class="inline-flex items-center px-6 py-3 gradient-bg text-white rounded-xl hover:opacity-90 transition font-medium">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Publier une offre
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($offres as $offre): ?>
                    <?php
                    $is_expired = $offre['date_limite'] && strtotime($offre['date_limite']) < time();
                    $days_left = $offre['date_limite'] ? floor((strtotime($offre['date_limite']) - time()) / 86400) : null;
                    ?>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 hover:border-green-500 transition card-hover">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <!-- Titre et badges -->
                                <div class="flex items-center gap-3 mb-3">
                                    <h3 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($offre['titre_emploi']) ?></h3>
                                    
                                    <?php if ($is_expired): ?>
                                        <span class="px-3 py-1 bg-red-100 text-red-700 border border-red-200 rounded-full text-xs font-semibold">Expirée</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-700 border border-green-200 rounded-full text-xs font-semibold">Active</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Informations -->
                                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 mb-4">
                                    <?php if ($offre['nom_domaine']): ?>
                                        <span class="px-3 py-1 bg-gray-100 rounded-full"><?= htmlspecialchars($offre['nom_domaine']) ?></span>
                                    <?php endif; ?>

                                    <?php if ($offre['localisation']): ?>
                                        <span>📍 <?= htmlspecialchars($offre['localisation']) ?></span>
                                    <?php endif; ?>

                                    <span>📅 Publié le <?= date('d/m/Y', strtotime($offre['date_publication'])) ?></span>

                                    <?php if ($offre['date_limite'] && !$is_expired): ?>
                                        <span class="text-gray-600">
                                            ⏰ 
                                            <?php if ($days_left !== null): ?>
                                                <?= $days_left > 0 ? "$days_left jour" . ($days_left > 1 ? 's' : '') . " restant" . ($days_left > 1 ? 's' : '') : "Expire aujourd'hui" ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Statistiques compactes -->
                                <div class="inline-flex gap-6 bg-gray-50 px-4 py-2 rounded-lg border border-gray-200">
                                    <div class="text-center">
                                        <p class="text-lg font-bold text-blue-600"><?= $offre['nb_candidatures'] ?></p>
                                        <p class="text-xs text-gray-600">Candidatures</p>
                                    </div>
                                    <div class="border-l border-gray-300"></div>
                                    <div class="text-center">
                                        <p class="text-lg font-bold text-orange-600"><?= $offre['nb_en_attente'] ?></p>
                                        <p class="text-xs text-gray-600">En attente</p>
                                    </div>
                                    <div class="border-l border-gray-300"></div>
                                    <div class="text-center">
                                        <p class="text-lg font-bold text-green-600"><?= $offre['nb_vues'] ?></p>
                                        <p class="text-xs text-gray-600">Vues</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col gap-2 ml-6">
                                <a href="offre-gerer.php?id=<?= $offre['id_offre'] ?>" 
                                   class="px-6 py-2 gradient-bg text-white rounded-lg hover:opacity-90 transition text-sm font-medium text-center whitespace-nowrap">
                                    Gérer l'offre
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