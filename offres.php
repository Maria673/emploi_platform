<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
require_once 'config/db.php';

// ✅ AJOUTER/RETIRER UN FAVORI (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_offre']) && isset($_SESSION['user_id'])) {
    $id_offre = intval($_POST['id_offre']);
    
    if ($id_offre > 0 && $_SESSION['user_type'] === 'candidat') {
        try {
            // Vérifier si déjà en favori
            $stmt = $pdo->prepare("SELECT id_favoris FROM favoris WHERE id_candidat = :id_candidat AND id_offre = :id_offre");
            $stmt->execute([':id_candidat' => $_SESSION['user_id'], ':id_offre' => $id_offre]);
            
            if ($stmt->rowCount() === 0) {
                // Insérer le favori
                $stmt = $pdo->prepare("INSERT INTO favoris (id_candidat, id_offre, date_ajout) VALUES (:id_candidat, :id_offre, NOW())");
                $stmt->execute([':id_candidat' => $_SESSION['user_id'], ':id_offre' => $id_offre]);
            } else {
                // Supprimer le favori
                $stmt = $pdo->prepare("DELETE FROM favoris WHERE id_candidat = :id_candidat AND id_offre = :id_offre");
                $stmt->execute([':id_candidat' => $_SESSION['user_id'], ':id_offre' => $id_offre]);
            }
        } catch (PDOException $e) {
            // Erreur
        }
    }
}

// ✅ Récupérer les favoris actuels du candidat
$favoris_ids = [];
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidat') {
    try {
        $stmt = $pdo->prepare("SELECT id_offre FROM favoris WHERE id_candidat = :id_candidat");
        $stmt->execute([':id_candidat' => $_SESSION['user_id']]);
        $favoris_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $favoris_ids = [];
    }
}

// Récupérer les paramètres de recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$ville = isset($_GET['ville']) ? trim($_GET['ville']) : '';
$domaine = isset($_GET['domaine']) ? $_GET['domaine'] : '';
$type_contrat = isset($_GET['type_contrat']) ? $_GET['type_contrat'] : [];

// Construire la requête SQL avec les filtres
$sql = "
    SELECT 
        oe.id_offre,
        oe.titre_emploi,
        oe.localisation,
        oe.description_offre,
        oe.competences_requises,
        oe.date_publication,
        oe.date_limite,
        oe.statut_recherche,
        r.nom_entreprise,
        s.nom_secteur,
        d.nom_domaine
    FROM offres_emploi oe
    LEFT JOIN recruteurs r ON oe.id_recruteur = r.id_recruteur
    LEFT JOIN secteurs s ON oe.id_secteur = s.id_secteur
    LEFT JOIN domaines d ON oe.id_domaine = d.id_domaine
    WHERE 1=1
";

$params = [];

// Filtre de recherche par mot-clé
if (!empty($search)) {
    $sql .= " AND (oe.titre_emploi LIKE :search OR oe.description_offre LIKE :search OR r.nom_entreprise LIKE :search)";
    $params[':search'] = "%$search%";
}

// Filtre par ville
if (!empty($ville)) {
    $sql .= " AND oe.localisation LIKE :ville";
    $params[':ville'] = "%$ville%";
}

// Filtre par domaine
if (!empty($domaine)) {
    $sql .= " AND oe.id_domaine = :domaine";
    $params[':domaine'] = $domaine;
}

// Filtre par type de contrat
if (!empty($type_contrat)) {
    $placeholders = [];
    foreach ($type_contrat as $index => $type) {
        $key = ":type_contrat_$index";
        $placeholders[] = $key;
        $params[$key] = $type;
    }
    $sql .= " AND oe.statut_recherche IN (" . implode(',', $placeholders) . ")";
}

$sql .= " ORDER BY oe.date_publication DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $offres = $stmt->fetchAll();
    $count_offres = count($offres);
} catch (PDOException $e) {
    $offres = [];
    $count_offres = 0;
    $error_message = "Erreur : " . $e->getMessage();
}

// Récupérer tous les domaines pour le filtre
try {
    $stmt_domaines = $pdo->query("SELECT id_domaine, nom_domaine FROM domaines ORDER BY nom_domaine");
    $domaines = $stmt_domaines->fetchAll();
} catch (PDOException $e) {
    $domaines = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offres d'emploi - NextCareer</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tailwind.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
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
                <a href="offres.php" class="text-indigo-600 font-medium border-b-2 border-indigo-600 pb-1">Offres d'emploi</a>
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
    <section class="gradient-bg text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl font-bold mb-2">Offres d'emploi au Burkina Faso</h1>
            <p class="text-indigo-100"><?= $count_offres ?> offres disponibles</p>
        </div>
    </section>

    <!-- Contenu principal -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar Filtres -->
            <aside class="lg:w-1/4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sticky top-24">
                    <div class="flex items-center mb-6">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        <h2 class="text-lg font-bold text-gray-800">Filtres</h2>
                    </div>

                    <form method="GET" action="/offres.php" class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Mot-clé</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Poste, entreprise..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Ville</label>
                            <select name="ville" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                <option value="">Toutes les villes</option>
                                <option value="Ouagadougou" <?= $ville == 'Ouagadougou' ? 'selected' : '' ?>>Ouagadougou</option>
                                <option value="Bobo-Dioulasso" <?= $ville == 'Bobo-Dioulasso' ? 'selected' : '' ?>>Bobo-Dioulasso</option>
                                <option value="Koudougou" <?= $ville == 'Koudougou' ? 'selected' : '' ?>>Koudougou</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Domaine</label>
                            <select name="domaine" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                <option value="">Tous les domaines</option>
                                <?php foreach ($domaines as $dom): ?>
                                    <option value="<?= $dom['id_domaine'] ?>" <?= $domaine == $dom['id_domaine'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dom['nom_domaine']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Type de contrat</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="type_contrat[]" value="CDI" <?= in_array('CDI', $type_contrat) ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 rounded">
                                    <span class="ml-2 text-sm">CDI</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="type_contrat[]" value="CDD" <?= in_array('CDD', $type_contrat) ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 rounded">
                                    <span class="ml-2 text-sm">CDD</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="type_contrat[]" value="Stage" <?= in_array('Stage', $type_contrat) ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 rounded">
                                    <span class="ml-2 text-sm">Stage</span>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 transition font-medium">
                                Appliquer
                            </button>
                            <a href="offres.php" class="block w-full text-center bg-gray-200 text-gray-700 py-2 rounded-lg hover:bg-gray-300 transition font-medium">
                                Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
            </aside>

            <!-- Liste des offres -->
            <main class="lg:w-3/4">
                <div class="grid md:grid-cols-2 gap-6">
                    <?php if (!empty($offres)): ?>
                        <?php foreach ($offres as $offre): ?>
                            <div class="card-hover bg-white rounded-2xl p-6 border border-gray-100 relative overflow-hidden group">
                                <!-- Barre gradient en haut -->
                                <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
                                
                                <!-- Header -->
                                <div class="mb-4">
                                    <div class="flex items-start justify-between mb-3">
                                        <h3 class="text-xl font-bold text-gray-900 group-hover:text-indigo-600 transition flex-1 pr-3">
                                            <?= htmlspecialchars($offre['titre_emploi']) ?>
                                        </h3>
                                        <span class="bg-gradient-to-r from-orange-400 to-pink-500 text-white text-xs font-bold px-3 py-1.5 rounded-full flex-shrink-0 shadow-sm">
                                            NOUVEAU
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($offre['nom_entreprise'])): ?>
                                    <p class="text-gray-600 font-semibold text-lg mb-3">
                                        <?= htmlspecialchars($offre['nom_entreprise']) ?>
                                    </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Détails -->
                                <div class="space-y-2.5 mb-4">
                                    <div class="flex items-center gap-2 text-sm text-gray-600">
                                        <span class="text-indigo-500">📍</span>
                                        <span class="font-medium"><?= htmlspecialchars($offre['localisation']) ?></span>
                                        <?php if (!empty($offre['statut_recherche'])): ?>
                                        <span class="ml-auto bg-blue-50 text-blue-700 text-xs px-3 py-1 rounded-full font-semibold border border-blue-200">
                                            <?= htmlspecialchars($offre['statut_recherche']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($offre['nom_domaine'])): ?>
                                    <div class="flex items-center gap-2 text-sm text-gray-600">
                                        <span class="text-purple-500">💼</span>
                                        <span><?= htmlspecialchars($offre['nom_domaine']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Compétences -->
                                <?php if (!empty($offre['competences_requises'])): ?>
                                <div class="flex flex-wrap gap-2 mb-5">
                                    <?php 
                                    $competences = explode(',', $offre['competences_requises']);
                                    foreach (array_slice($competences, 0, 3) as $comp): 
                                    ?>
                                    <span class="bg-indigo-50 text-indigo-700 text-xs px-3 py-1.5 rounded-lg font-medium border border-indigo-100">
                                        <?= htmlspecialchars(trim($comp)) ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <!-- Footer -->
                                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                                    <span class="text-xs text-gray-500 flex items-center gap-1">
                                        <?php
                                            if (!empty($offre['date_publication'])) {
                                                $date_diff = time() - strtotime($offre['date_publication']);
                                                $days = floor($date_diff / (60 * 60 * 24));
                                                echo $days == 0 ? "Aujourd'hui" : "Il y a $days jour" . ($days > 1 ? 's' : '');
                                            }
                                        ?>
                                    </span>
                                    
                                    <div class="flex items-center gap-2">
                                        <!-- ✅ BOUTON FAVORIS - TOUT DANS offres.php -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="id_offre" value="<?= $offre['id_offre'] ?>">
                                            <button type="submit" class="p-2 hover:bg-yellow-50 rounded-lg transition" title="Ajouter aux favoris">
                                                <span class="text-lg" style="color: <?= in_array($offre['id_offre'], $favoris_ids) ? '#FFD700' : '#999' ?>;">
                                                    <?= in_array($offre['id_offre'], $favoris_ids) ? '★' : '☆' ?>
                                                </span>
                                            </button>
                                        </form>
                                        
                                        <a href="offre.php?id=<?= $offre['id_offre'] ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-sm font-semibold rounded-lg hover:shadow-lg transition">
                                            Voir l'offre
                                            <span>→</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-2 text-center py-20">
                            <div class="text-6xl mb-4">📭</div>
                            <h3 class="text-2xl font-bold text-gray-800 mb-2">Aucune offre trouvée</h3>
                            <p class="text-gray-600 mb-6">Modifiez vos critères de recherche</p>
                            <a href="offres.php" class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                                <span>↻</span>
                                Réinitialiser
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

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