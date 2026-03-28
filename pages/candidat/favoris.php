<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidat') {
    header('Location: ../../auth/login.php');
    exit();
}

require_once '../../config/db.php';

// ✅ AJOUTER/RETIRER UN FAVORI (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_offre'])) {
    $id_offre = intval($_POST['id_offre']);
    
    if ($id_offre > 0) {
        try {
            // Vérifier que l'offre existe
            $stmt = $pdo->prepare("SELECT id_offre FROM offres_emploi WHERE id_offre = :id");
            $stmt->execute([':id' => $id_offre]);
            
            if ($stmt->rowCount() > 0) {
                // Vérifier que le candidat n'a pas déjà ce favori
                $stmt = $pdo->prepare("
                    SELECT id_favoris FROM favoris 
                    WHERE id_candidat = :id_candidat AND id_offre = :id_offre
                ");
                $stmt->execute([
                    ':id_candidat' => $_SESSION['user_id'],
                    ':id_offre' => $id_offre
                ]);
                
                if ($stmt->rowCount() === 0) {
                    // Ajouter le favori
                    $stmt = $pdo->prepare("
                        INSERT INTO favoris (id_candidat, id_offre, date_ajout)
                        VALUES (:id_candidat, :id_offre, NOW())
                    ");
                    $stmt->execute([
                        ':id_candidat' => $_SESSION['user_id'],
                        ':id_offre' => $id_offre
                    ]);
                }
            }
        } catch (PDOException $e) {
            // Erreur
        }
    }
}

// ✅ SUPPRIMER UN FAVORI
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM favoris WHERE id_offre = :id_offre AND id_candidat = :id_candidat");
        $stmt->execute([
            ':id_offre' => $_GET['remove'],
            ':id_candidat' => $_SESSION['user_id']
        ]);
        header('Location: favoris.php');
        exit();
    } catch (PDOException $e) {
        // Silencieusement échouer
    }
}

// ✅ Récupérer les favoris avec les infos des offres
$favoris = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            f.id_favoris,
            f.id_offre,
            f.id_candidat,
            f.date_ajout,
            o.id_offre as offre_id,
            o.id_recruteur,
            o.titre_emploi,
            o.localisation,
            o.description_offre,
            o.competences_requises,
            o.date_publication,
            o.statut_recherche,
            r.nom_entreprise
        FROM favoris f
        INNER JOIN offres_emploi o ON f.id_offre = o.id_offre
        LEFT JOIN recruteurs r ON o.id_recruteur = r.id_recruteur
        WHERE f.id_candidat = :id_candidat
        ORDER BY f.date_ajout DESC
    ");
    $stmt->execute([':id_candidat' => $_SESSION['user_id']]);
    $favoris = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $favoris = [];
}

$total_favoris = count($favoris);

// Fonction pour calculer le temps écoulé
function tempsEcoule($date) {
    if (empty($date)) return '';
    
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return "À l'instant";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "il y a " . $minutes . " min";
    } elseif ($diff < 86400) {
        $heures = floor($diff / 3600);
        return "il y a " . $heures . "h";
    } elseif ($diff < 604800) {
        $jours = floor($diff / 86400);
        return "il y a " . $jours . " jour" . ($jours > 1 ? 's' : '');
    } else {
        return date('d/m/Y', $timestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes Favoris - NextCareer</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/tailwind.min.css">
<style>
body { font-family: 'Inter', sans-serif; }
.gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.card-hover { transition: all 0.3s ease; }
.card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15); }
</style>
</head>
<body class="bg-gray-50">

<!-- Navbar -->
<!-- Navbar -->
<nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <a href="../../index.php">
                <div style="font-family: 'Inter', sans-serif; font-size: 1.75rem; font-weight: 700; color: #1a202c; position: relative; display: inline-block;">
                    Next<span style="color: #667eea;">Career</span>
                    <div style="position: absolute; bottom: 2px; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 2px;"></div>
                </div>
            </a>

            <!-- Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="../../index.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Accueil</a>
                <a href="../../offres.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Offres d'emploi</a>
                <a href="../../entreprises.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Entreprises</a>
                <a href="../../abonnement.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Abonnement</a>
            </div>

            <!-- Auth Buttons / User menu -->
            <div class="flex items-center space-x-4">
                <?php
                $user_nom = '';
                $user_prenom = '';
                $user_type = $_SESSION['user_type'] ?? 'candidat';

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
                ?>

                <div class="hidden sm:flex items-center space-x-3 border-r border-gray-300 pr-4">
                    <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                        <span class="text-lg font-bold text-indigo-600"><?= $initiales ?></span>
                    </div>
                    <div class="text-sm">
                        <p class="font-medium text-gray-900"><?= $nom_complet ?></p>
                        <p class="text-xs text-gray-500">Candidat</p>
                    </div>
                </div>

                <a href="../../auth/logout.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-full transition font-medium text-sm">
                    Déconnexion
                </a>
            </div>
        </div>
    </div>
</nav>
<!-- Hero Section -->
<section class="gradient-bg text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="dashboard.php" class="inline-flex items-center gap-2 text-indigo-100 hover:text-white mb-4">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M15 19l-7-7 7-7"/></svg>
            Retour
        </a>
        <h1 class="text-4xl font-bold mb-2">Mes Favoris</h1>
        <p class="text-indigo-100"><?= $total_favoris ?> offre(s) sauvegardée(s)</p>
    </div>
</section>

<!-- Contenu -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <?php if (empty($favoris)): ?>
        <!-- État vide -->
        <div class="text-center py-20 bg-white rounded-lg border border-gray-200">
            <div class="text-6xl mb-4"></div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Aucun favoris</h3>
            <p class="text-gray-600 mb-6">Vous n'avez pas encore ajouté d'offres à vos favoris</p>
            <a href="../../offres.php" class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                <span></span>
                Parcourir les offres
            </a>
        </div>
    <?php else: ?>
        <!-- Grille des favoris -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($favoris as $offre): ?>
                <?php if (!empty($offre['id_offre'])): ?>
                <div class="card-hover bg-white rounded-2xl p-6 border border-gray-100 relative overflow-hidden group">
                    <!-- Barre gradient en haut -->
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
                    
                    <!-- Header -->
                    <div class="mb-4">
                        <div class="flex items-start justify-between mb-3">
                            <h3 class="text-xl font-bold text-gray-900 group-hover:text-indigo-600 transition flex-1 pr-3">
                                <?= htmlspecialchars($offre['titre_emploi'] ?? 'Offre') ?>
                            </h3>
                            <a href="?remove=<?= $offre['id_offre'] ?>" 
                               class="p-2 hover:bg-yellow-50 rounded-lg transition text-lg"
                               onclick="return confirm('Retirer cette offre de vos favoris ?')"
                               title="Retirer des favoris">
                                ★
                            </a>
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
                            <span class="font-medium"><?= htmlspecialchars($offre['localisation'] ?? 'Non spécifié') ?></span>
                            <?php if (!empty($offre['statut_recherche'])): ?>
                            <span class="ml-auto bg-blue-50 text-blue-700 text-xs px-3 py-1 rounded-full font-semibold border border-blue-200">
                                <?= htmlspecialchars($offre['statut_recherche']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
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

                    <!-- Description -->
                    <?php if (!empty($offre['description_offre'])): ?>
                    <div class="text-sm text-gray-600 mb-4 line-clamp-2">
                        <?= htmlspecialchars($offre['description_offre']) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Footer -->
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <span class="text-xs text-gray-500">
                            Ajouté <?= tempsEcoule($offre['date_ajout']) ?>
                        </span>
                        
                        <a href="../../offre.php?id=<?= $offre['id_offre'] ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white text-sm font-semibold rounded-lg hover:shadow-lg transition">
                            Voir l'offre
                            <span>→</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-300 pt-12 pb-6 mt-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-4 gap-8 mb-8">
            <div>
                <h3 class="text-white font-bold mb-4">NextCareer</h3>
                <p class="text-sm">La plateforme de référence pour l'emploi au Burkina Faso.</p>
            </div>
            <div>
                <h3 class="text-white font-bold mb-4">Navigation</h3>
                <ul class="space-y-2">
                    <li><a href="../../index.php" class="hover:text-indigo-400 transition text-sm">Accueil</a></li>
                    <li><a href="../../offres.php" class="hover:text-indigo-400 transition text-sm">Offres d'emploi</a></li>
                    <li><a href="favoris.php" class="hover:text-indigo-400 transition text-sm">Mes Favoris</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-white font-bold mb-4">Contact</h3>
                <p class="text-sm"> +226 05 64 53 74</p>
                <p class="text-sm"> support@nextcareer.bf</p>
            </div>
            <div>
                <h3 class="text-white font-bold mb-4">Légal</h3>
                <ul class="space-y-2">
                    <li><a href="#" class="hover:text-indigo-400 transition text-sm">Conditions</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition text-sm">Confidentialité</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-6">
            <p class="text-sm text-gray-400">© 2026 NextCareer. Tous droits réservés.</p>
        </div>
    </div>
</footer>

</body>
</html>