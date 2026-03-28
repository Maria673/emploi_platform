<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
require_once 'config/db.php';

// Récupérer les 3 dernières offres d'emploi publiées
try {
    $stmt = $pdo->query("
        SELECT 
            oe.id_offre,
            oe.titre_emploi,
            oe.localisation,
            oe.description_offre,
            oe.competences_requises,
            oe.date_publication,
            oe.date_limite,
            oe.statut_recherche,
            oe.id_recruteur,
            oe.id_secteur,
            oe.id_domaine,
            r.nom_entreprise,
            s.nom_secteur,
            d.nom_domaine
        FROM offres_emploi oe
        LEFT JOIN recruteurs r ON oe.id_recruteur = r.id_recruteur
        LEFT JOIN secteurs s ON oe.id_secteur = s.id_secteur
        LEFT JOIN domaines d ON oe.id_domaine = d.id_domaine
        ORDER BY oe.date_publication DESC
        LIMIT 6
    ");
    $offres = $stmt->fetchAll();
} catch (PDOException $e) {
    $offres = [];
    $error_message = "Erreur SQL : " . $e->getMessage();
}

// Récupérer les statistiques
try {
    $stats = [
        'offres' => $pdo->query("SELECT COUNT(*) FROM offres_emploi")->fetchColumn(),
        'entreprises' => $pdo->query("SELECT COUNT(*) FROM recruteurs")->fetchColumn(),
        'candidats' => $pdo->query("SELECT COUNT(*) FROM candidats")->fetchColumn()
    ];
} catch (PDOException $e) {
    $stats = ['offres' => 0, 'entreprises' => 0, 'candidats' => 0];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextCareer - Accueil</title>
    
    <link rel="stylesheet" href="assets/css/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
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
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
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
                        Next<span style="color: #667eea;">Career</span>
                        <div style="position: absolute; bottom: 2px; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 2px;"></div>
                    </div>
                </div>

                <!-- Navigation Links -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="text-indigo-600 font-medium border-b-2 border-indigo-600 pb-1">Accueil</a>
                    <a href="offres.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Offres d'emploi</a>
                    <a href="entreprises.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Entreprises</a>
                    <a href="abonnement.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Abonnement</a>
                </div>

                <!-- Auth Buttons / User menu -->
                <div class="flex items-center space-x-4">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="auth/login.php" class="text-gray-700 hover:text-indigo-600 font-medium transition">Connexion</a>
                        <a href="auth/register.php" class="bg-indigo-600 text-white px-6 py-2 rounded-full hover:bg-indigo-700 transition font-medium">S'inscrire</a>
                    <?php else: ?>
                        <?php
                        // Récupérer les infos complètes de l'utilisateur
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
                        } elseif ($user_type === 'recruteur') {
                            try {
                                $stmt_user = $pdo->prepare("SELECT nom_entreprise FROM recruteurs WHERE id_recruteur = :id");
                                $stmt_user->execute([':id' => $_SESSION['user_id']]);
                                $user_data = $stmt_user->fetch();
                                $user_nom = $user_data['nom_entreprise'] ?? 'Entreprise';
                            } catch (PDOException $e) {
                                $user_nom = 'Entreprise';
                            }
                        }
                        
                        $initiales = '';
                        $nom_complet = '';
                        if ($user_type === 'candidat') {
                            $initiales = strtoupper(substr($user_prenom, 0, 1) . substr($user_nom, 0, 1));
                            $nom_complet = htmlspecialchars($user_prenom . ' ' . $user_nom);
                            $bg_color = 'bg-indigo-100';
                            $text_color = 'text-indigo-600';
                            $btn_color = 'bg-indigo-600 hover:bg-indigo-700';
                        } else {
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
    <section class="gradient-bg text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl md:text-5xl font-bold mb-4">Trouvez votre emploi idéal</h2>
                <p class="text-xl text-white text-opacity-90">La plateforme de référence pour connecter les talents burkinabè avec les meilleures opportunités d'emploi</p>
            </div>

            <!-- Search Bar -->
            <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-2xl p-4">
                <form method="GET" action="offres.php" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1 flex items-center border-r border-gray-200 px-4">
                        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Poste, mot-clé, entreprise..." class="w-full outline-none text-gray-700 placeholder-gray-400">
                    </div>
                    <div class="flex-1 flex items-center px-4">
                        <input type="text" name="ville" value="<?= htmlspecialchars($_GET['ville'] ?? '') ?>" placeholder="Ville, région..." class="w-full outline-none text-gray-700 placeholder-gray-400">
                    </div>
                    <button type="submit" class="gradient-bg text-white px-8 py-4 rounded-xl hover:opacity-90 transition font-medium flex items-center justify-center">
                        Rechercher
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Registration Options -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-8">
                <!-- Candidat Card -->
                <div class="card-hover bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-8 border border-indigo-100">
                    <div class="flex items-start justify-between mb-6">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-3">Espace Candidat</h3>
                            <p class="text-gray-600">Créez votre profil et trouvez l'emploi qui vous correspond</p>
                        </div>
                    </div>
                    <a href="auth/register-candidat.php" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700">
                        Commencer →
                    </a>
                </div>

                <!-- Recruteur Card -->
                <div class="card-hover bg-gradient-to-br from-green-50 to-teal-50 rounded-2xl p-8 border border-green-100">
                    <div class="flex items-start justify-between mb-6">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-3">Espace Recruteur</h3>
                            <p class="text-gray-600">Publiez vos offres et trouvez les meilleurs talents</p>
                        </div>
                    </div>
                    <a href="auth/register-recruteur.php" class="inline-flex items-center text-green-600 font-semibold hover:text-green-700">
                        Commencer →
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Offres Récentes -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-10">
                <div>
                    <h2 class="text-3xl font-bold text-gray-800">Offres récentes</h2>
                    <p class="text-gray-600 mt-2">Découvrez les dernières opportunités publiées</p>
                </div>
                <a href="offres.php" class="text-indigo-600 font-medium hover:text-indigo-700 flex items-center">
                    Voir toutes les offres →
                </a>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <?php if (!empty($offres)): ?>
                    <?php foreach ($offres as $offre): ?>
                        <div class="card-hover bg-white rounded-xl p-6 border border-gray-200">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-800 flex-1">
                                    <?= htmlspecialchars($offre['titre_emploi']) ?>
                                </h3>
                                <span class="bg-indigo-50 text-indigo-600 text-xs font-semibold px-3 py-1 rounded-full ml-2">Nouveau</span>
                            </div>
                            
                            <?php if (!empty($offre['nom_entreprise'])): ?>
                            <p class="text-sm text-gray-700 font-medium mb-2">
                                <?= htmlspecialchars($offre['nom_entreprise']) ?>
                            </p>
                            <?php endif; ?>
                            
                            <div class="flex items-center gap-3 mb-4">
                                <?php if (!empty($offre['localisation'])): ?>
                                <span class="text-sm text-gray-600">
                                    📍 <?= htmlspecialchars($offre['localisation']) ?>
                                </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($offre['statut_recherche'])): ?>
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">
                                    <?= htmlspecialchars($offre['statut_recherche']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($offre['nom_secteur']) || !empty($offre['nom_domaine'])): ?>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <?php if (!empty($offre['nom_secteur'])): ?>
                                <span class="bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded-full">
                                    <?= htmlspecialchars($offre['nom_secteur']) ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($offre['nom_domaine'])): ?>
                                <span class="bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded-full">
                                    <?= htmlspecialchars($offre['nom_domaine']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                                <span class="text-xs text-gray-500">
                                    <?php
                                        if (!empty($offre['date_publication'])) {
                                            $date_diff = time() - strtotime($offre['date_publication']);
                                            $days = floor($date_diff / (60 * 60 * 24));
                                            echo $days == 0 ? "Aujourd'hui" : "Il y a $days jour" . ($days > 1 ? 's' : '');
                                        } else {
                                            echo "Date inconnue";
                                        }
                                    ?>
                                </span>
                                <a href="offre.php?id=<?= $offre['id_offre'] ?>" class="text-indigo-600 font-medium hover:text-indigo-700 text-sm">
                                    Voir détails →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-3 text-center py-12">
                        <p class="text-4xl mb-4">📋</p>
                        <p class="text-gray-600 text-lg">Aucune offre d'emploi disponible pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Comment ça marche -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-800 mb-3">Comment ça marche ?</h2>
                <p class="text-gray-600">Trouvez votre emploi idéal en 4 étapes simples</p>
            </div>

            <div class="grid md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-indigo-600 text-white rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
                        1
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Créez votre profil</h3>
                    <p class="text-gray-600">Inscrivez-vous gratuitement et complétez votre profil avec vos compétences</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-indigo-600 text-white rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
                        2
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Recherchez des offres</h3>
                    <p class="text-gray-600">Explorez des opportunités et filtrez selon vos critères</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-indigo-600 text-white rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
                        3
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Postulez facilement</h3>
                    <p class="text-gray-600">Envoyez votre candidature en quelques clics</p>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 bg-green-500 text-white rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
                        4
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Décrochez le job</h3>
                    <p class="text-gray-600">Suivez vos candidatures et échangez avec les recruteurs</p>
                </div>
            </div>
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


    <!-- Cookie Banner -->
    <div id="cookieBanner" class="fixed bottom-0 left-0 right-0 bg-gray-900 text-white p-4 shadow-2xl transform translate-y-full transition-transform duration-500 z-50">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">
            <div class="flex items-start space-x-3">
                <span class="text-2xl">🍪</span>
                <div>
                    <p class="text-sm">
                        Nous utilisons des cookies pour améliorer votre expérience sur notre site.
                    </p>
                </div>
            </div>
            <div class="flex space-x-3">
                <button onclick="refuseCookies()" class="px-6 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition text-sm font-medium">
                    Refuser
                </button>
                <button onclick="acceptCookies()" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg transition text-sm font-medium">
                    Accepter
                </button>
            </div>
        </div>
    </div>

    <script>
        function setCookie(name, value, days) {
            const d = new Date();
            d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = "expires=" + d.toUTCString();
            document.cookie = name + "=" + value + ";" + expires + ";path=/";
        }

        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for(let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }

        function acceptCookies() {
            setCookie('cookieConsent', 'accepted', 365);
            hideCookieBanner();
        }

        function refuseCookies() {
            setCookie('cookieConsent', 'refused', 365);
            hideCookieBanner();
        }

        function hideCookieBanner() {
            const banner = document.getElementById('cookieBanner');
            banner.style.transform = 'translateY(100%)';
        }

        function showCookieBanner() {
            const banner = document.getElementById('cookieBanner');
            setTimeout(() => {
                banner.style.transform = 'translateY(0)';
            }, 1000);
        }

        window.addEventListener('DOMContentLoaded', () => {
            const consent = getCookie('cookieConsent');
            if (!consent) {
                showCookieBanner();
            }
        });
    </script>
</body>
</html>
