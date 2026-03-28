<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
require_once 'config/db.php';

// Récupérer les filtres
$search = isset($_GET['search']) ? $_GET['search'] : '';
$secteur_filter = isset($_GET['secteur']) ? $_GET['secteur'] : '';
$ville_filter = isset($_GET['ville']) ? $_GET['ville'] : '';

// Construire la requête SQL avec filtres
$sql = "SELECT 
            e.*,
            COUNT(DISTINCT oe.id_offre) as nombre_offres
        FROM entreprises e
        LEFT JOIN recruteurs r ON e.id_recruteur = r.id_recruteur
        LEFT JOIN offres_emploi oe ON r.id_recruteur = oe.id_recruteur
        WHERE e.statut = 'active'";

// Ajouter les filtres
if (!empty($search)) {
    $sql .= " AND (e.nom_entreprise LIKE :search OR e.description LIKE :search)";
}
if (!empty($secteur_filter)) {
    $sql .= " AND e.secteur = :secteur";
}
if (!empty($ville_filter)) {
    $sql .= " AND e.ville = :ville";
}

$sql .= " GROUP BY e.id_entreprise ORDER BY e.nom_entreprise ASC";

try {
    $stmt = $pdo->prepare($sql);
    
    // Bind des paramètres
    if (!empty($search)) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
    if (!empty($secteur_filter)) {
        $stmt->bindValue(':secteur', $secteur_filter);
    }
    if (!empty($ville_filter)) {
        $stmt->bindValue(':ville', $ville_filter);
    }
    
    $stmt->execute();
    $entreprises = $stmt->fetchAll();
} catch (PDOException $e) {
    $entreprises = [];
    $error_message = "Erreur lors de la récupération des entreprises : " . $e->getMessage();
}

// Récupérer les secteurs pour le filtre
try {
    $secteurs_stmt = $pdo->query("SELECT DISTINCT secteur FROM entreprises WHERE secteur IS NOT NULL ORDER BY secteur");
    $secteurs = $secteurs_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $secteurs = [];
}

// Récupérer les villes pour le filtre
try {
    $villes_stmt = $pdo->query("SELECT DISTINCT ville FROM entreprises WHERE ville IS NOT NULL ORDER BY ville");
    $villes = $villes_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $villes = [];
}

$total_entreprises = count($entreprises);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entreprises - NextCareer</title>
    
    <!-- Tailwind CSS CDN -->
    <link rel="stylesheet" href="assets/css/tailwind.min.css">
    
    <!-- Configuration Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#667eea',
                        secondary: '#764ba2',
                    }
                }
            }
        }
    </script>
    
    <style type="text/tailwindcss">
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.2);
        }

        .logo-container {
            position: relative;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f8f9ff 0%, #f3f4ff 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid #e8e9ff;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.08);
        }

        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 8px;
        }

        .logo-fallback {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navbar -->
    <!-- Navbar -->
<nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <a href="index.php">
                <div style="font-family: 'Poppins', sans-serif; font-size: 1.75rem; font-weight: 700; color: #1a202c; position: relative; display: inline-block;">
                    Next<span style="color: #667eea;">Career</span>
                    <div style="position: absolute; bottom: 2px; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 2px;"></div>
                </div>
            </a>

            <!-- Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="index.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Accueil</a>
                <a href="offres.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Offres d'emploi</a>
                <a href="entreprises.php" class="text-indigo-600 font-medium border-b-2 border-indigo-600 pb-1">Entreprises</a>
                <a href="abonnement.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Abonnement</a>
            </div>

            <!-- Auth Buttons / User menu -->
            <div class="flex items-center space-x-4">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="auth/login.php" class="text-gray-700 hover:text-indigo-600 font-medium transition">Connexion</a>
                    <a href="auth/register.php" class="bg-indigo-600 text-white px-6 py-2 rounded-full hover:bg-indigo-700 transition font-medium">S'inscrire</a>
                <?php else: ?>
                    <?php
                    $user_nom = '';
                    $user_prenom = '';
                    $user_type = $_SESSION['user_type'] ?? '';

                    if ($user_type === 'candidat') {
                        try {
                            $stmt_user = $pdo->prepare("SELECT nom, prenom FROM candidats WHERE id_candidat = :id");
                            $stmt_user->execute([':id' => $_SESSION['user_id']]);
                            $user_data = $stmt_user->fetch();
                            $user_nom = $user_data['nom'] ?? '';
                            $user_prenom = $user_data['prenom'] ?? '';
                        } catch (PDOException $e) {
                            $user_nom = $_SESSION['nom'] ?? '';
                            $user_prenom = $_SESSION['prenom'] ?? '';
                        }
                        $initiales = strtoupper(substr($user_prenom, 0, 1) . substr($user_nom, 0, 1));
                        $nom_complet = htmlspecialchars($user_prenom . ' ' . $user_nom);
                        $bg_color = 'bg-indigo-100';
                        $text_color = 'text-indigo-600';
                        $btn_color = 'bg-indigo-600 hover:bg-indigo-700';
                    } elseif ($user_type === 'recruteur') {
                        try {
                            $stmt_user = $pdo->prepare("SELECT nom_entreprise FROM recruteurs WHERE id_recruteur = :id");
                            $stmt_user->execute([':id' => $_SESSION['user_id']]);
                            $user_data = $stmt_user->fetch();
                            $user_nom = $user_data['nom_entreprise'] ?? 'Entreprise';
                        } catch (PDOException $e) {
                            $user_nom = 'Entreprise';
                        }
                        $initiales = strtoupper(substr($user_nom, 0, 1));
                        $nom_complet = htmlspecialchars($user_nom);
                        $bg_color = 'bg-green-100';
                        $text_color = 'text-green-600';
                        $btn_color = 'bg-green-600 hover:bg-green-700';
                    }
                    ?>

                    <div class="hidden sm:flex items-center space-x-3 border-r border-gray-300 pr-4">
                        <div class="w-10 h-10 <?= $bg_color ?> rounded-full flex items-center justify-center">
                            <a href="/pages/candidat/dashboard.php" class="text-lg font-bold <?= $text_color ?>"><?= $initiales ?></a>
                        </div>
                        <div class="text-sm">
                            <p class="font-medium text-gray-900"><?= $nom_complet ?></p>
                            <p class="text-xs text-gray-500"><?= ucfirst($user_type) ?></p>
                        </div>
                    </div>

                    <a href="auth/logout.php" class="<?= $btn_color ?> text-white px-6 py-2 rounded-full transition font-medium text-sm">
                        Déconnexion
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

    <!-- Hero Section -->
    <section class="gradient-bg text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8">
                <span class="bg-white bg-opacity-20 text-white text-sm font-semibold px-4 py-2 rounded-full inline-block mb-4">
                    <?= $total_entreprises ?> entreprises partenaires
                </span>
                <h1 class="text-4xl md:text-5xl font-bold mb-4">
                    Découvrez les <span class="text-indigo-200">Entreprises</span> qui recrutent
                </h1>
                <p class="text-xl text-indigo-100 max-w-3xl mx-auto">
                    Explorez les meilleures entreprises du Burkina Faso et trouvez votre prochain employeur idéal.
                </p>
            </div>
        </div>
    </section>

    <!-- Search and Filters Section -->
    <section class="bg-white shadow-sm -mt-8 relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <form method="GET" action="entreprises.php" class="flex flex-col md:flex-row gap-4">
                <!-- Search -->
                <div class="flex-1 relative">
                    <svg class="absolute left-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input 
                        type="text" 
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Rechercher une entreprise..." 
                        class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                    >
                </div>

                <!-- Secteur Filter -->
                <div class="relative">
                    <select name="secteur" class="appearance-none w-full md:w-64 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none bg-white cursor-pointer">
                        <option value="">Tous les secteurs</option>
                        <?php foreach ($secteurs as $secteur): ?>
                            <option value="<?= htmlspecialchars($secteur) ?>" <?= $secteur_filter == $secteur ? 'selected' : '' ?>>
                                <?= htmlspecialchars($secteur) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="absolute right-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>

                <!-- Ville Filter -->
                <div class="relative">
                    <select name="ville" class="appearance-none w-full md:w-64 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none bg-white cursor-pointer">
                        <option value="">Toutes les villes</option>
                        <?php foreach ($villes as $ville): ?>
                            <option value="<?= htmlspecialchars($ville) ?>" <?= $ville_filter == $ville ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ville) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="absolute right-4 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>

                <!-- Search Button -->
                <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded-xl hover:bg-indigo-700 transition font-medium flex items-center justify-center whitespace-nowrap">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Rechercher
                </button>
            </form>
        </div>
    </section>

    <!-- Results Section -->
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Results Count -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800">
                    <?= $total_entreprises ?> entreprise<?= $total_entreprises > 1 ? 's' : '' ?> trouvée<?= $total_entreprises > 1 ? 's' : '' ?>
                </h2>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-6">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <!-- Entreprises Grid -->
            <?php if (!empty($entreprises)): ?>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($entreprises as $entreprise): ?>
                        <div class="card-hover bg-white rounded-2xl p-6 border border-gray-200">
                            <!-- Logo de l'entreprise -->
                            <div class="flex items-start justify-between mb-4">
                                <div class="logo-container">
                                    <?php if (!empty($entreprise['logo'])): ?>
                                        <img src="<?= htmlspecialchars($entreprise['logo']) ?>" 
                                             alt="Logo <?= htmlspecialchars($entreprise['nom_entreprise']) ?>"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <div class="logo-fallback" style="display:none;">
                                            <?= strtoupper(substr($entreprise['nom_entreprise'], 0, 2)) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="logo-fallback">
                                            <?= strtoupper(substr($entreprise['nom_entreprise'], 0, 2)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Nom de l'entreprise -->
                            <h3 class="text-xl font-bold text-gray-800 mb-2">
                                <?= htmlspecialchars($entreprise['nom_entreprise']) ?>
                            </h3>

                            <!-- Badge Secteur -->
                            <?php if (!empty($entreprise['secteur'])): ?>
                            <div class="mb-3">
                                <span class="bg-indigo-100 text-indigo-700 text-xs font-semibold px-3 py-1 rounded-full">
                                    <?= htmlspecialchars($entreprise['secteur']) ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <!-- Description -->
                            <?php if (!empty($entreprise['description'])): ?>
                            <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                                <?= htmlspecialchars($entreprise['description']) ?>
                            </p>
                            <?php endif; ?>

                            <!-- Informations -->
                            <div class="space-y-2 mb-4">
                                <!-- Localisation -->
                                <?php if (!empty($entreprise['ville'])): ?>
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span><?= htmlspecialchars($entreprise['ville']) ?></span>
                                </div>
                                <?php endif; ?>

                                <!-- Nombre d'employés -->
                                <?php if (!empty($entreprise['nombre_employes'])): ?>
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <span><?= htmlspecialchars($entreprise['nombre_employes']) ?> employés</span>
                                </div>
                                <?php endif; ?>

                                <!-- Offres actives -->
                                <div class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-indigo-600 font-medium">
                                        <?= $entreprise['nombre_offres'] ?> offre<?= $entreprise['nombre_offres'] > 1 ? 's' : '' ?> active<?= $entreprise['nombre_offres'] > 1 ? 's' : '' ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                                <a href="entreprise-detail.php?id=<?= $entreprise['id_entreprise'] ?>" 
                                   class="text-indigo-600 font-medium hover:text-indigo-700 text-sm flex items-center">
                                    Voir les offres
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                                
                                <?php if (!empty($entreprise['site_web'])): ?>
                                <a href="<?= htmlspecialchars($entreprise['site_web']) ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   title="Visiter le site web"
                                   class="w-10 h-10 bg-gray-100 hover:bg-indigo-100 rounded-lg flex items-center justify-center transition group">
                                    <svg class="w-5 h-5 text-gray-600 group-hover:text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                    </svg>
                                </a>
                                <?php else: ?>
                                <div class="w-10 h-10 bg-gray-50 rounded-lg flex items-center justify-center opacity-50" title="Site web non disponible">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                    </svg>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="text-center py-16">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Aucune entreprise trouvée</h3>
                    <p class="text-gray-600 mb-6">Essayez de modifier vos critères de recherche</p>
                    <a href="entreprises.php" class="inline-flex items-center text-indigo-600 font-medium hover:text-indigo-700">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Réinitialiser les filtres
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-16 bg-gradient-to-r from-indigo-600 to-purple-600">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Vous êtes une entreprise ?</h2>
            <p class="text-xl text-indigo-100 mb-8 max-w-2xl mx-auto">
                Rejoignez NextCareer et accédez à des milliers de talents qualifiés pour développer votre équipe.
            </p>
            <a href="auth/register-recruteur.php" class="inline-flex items-center bg-white text-indigo-600 px-8 py-4 rounded-full hover:bg-gray-100 transition font-medium text-lg">
                Créer un compte recruteur
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
        </div>
    </section>

    <!-- Footer -->
   <footer class="bg-gray-900 text-gray-300 pt-12 pb-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                <!-- Logo et description -->
                <div class="md:col-span-1">
                    <div class="flex items-center space-x-2 mb-4">
                        <div style="font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: #e8dfeb; position: relative; display: inline-block;">
                        Next<span style="color: #667eea;">Career</span>
                        <div style="position: absolute; bottom: 4px; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 2px;"></div>
                    </div>
                    </div>
                    <p class="text-sm mb-4">La plateforme de référence pour connecter les talents burkinabè avec les meilleures opportunités d'emploi.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-indigo-600 transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-indigo-600 transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-indigo-600 transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-indigo-600 transition">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Pour les candidats -->
                <div>
                    <h3 class="text-white font-bold mb-4">Pour les candidats</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="hover:text-indigo-400 transition text-sm">Rechercher un emploi</a></li>
                        <li><a href="#" class="hover:text-indigo-400 transition text-sm">Déposer mon CV</a></li>
                        <li><a href="#" class="hover:text-indigo-400 transition text-sm">Guide du candidat</a></li>
                    </ul>
                </div>

                <!-- Pour les recruteurs -->
                <div>
                    <h3 class="text-white font-bold mb-4">Pour les recruteurs</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="hover:text-indigo-400 transition text-sm">Publier une offre</a></li>
                        <li><a href="#" class="hover:text-indigo-400 transition text-sm">Rechercher des CVs</a></li>
                        <li><a href="#" class="hover:text-indigo-400 transition text-sm">Guide du recruteur</a></li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h3 class="text-white font-bold mb-4">Contactez nous !</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start text-sm">
                            <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            </svg>
                            <span>Ouagadougou, Burkina Faso</span>
                        </li>
                        <li class="flex items-start text-sm">
                            <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span>+226 05 64 53 74</span>
                        </li>
                        <li class="flex items-start text-sm">
                            <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>nextcareercontact@gmail.com</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Séparateur -->
            <div class="border-t border-gray-800 pt-6">
                <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                    <p class="text-sm text-gray-400">© 2026 NextCareer. Tous droits réservés.</p>
                    <div class="flex space-x-6 text-sm">
                        <a href="#" class="hover:text-indigo-400 transition">Mentions légales</a>
                        <a href="#" class="hover:text-indigo-400 transition">Politique de confidentialité</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>