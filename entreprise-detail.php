<?php
// Démarrer la session
session_start();

// Inclure la connexion à la base de données
require_once 'config/db.php';

// Vérifier si un ID d'entreprise est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: entreprises.php');
    exit();
}

$id_entreprise = intval($_GET['id']);
$message_success = '';
$message_error = '';

// ✅ AJOUTER/RETIRER UN ABONNEMENT (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_follow']) && isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] !== 'candidat') {
        $message_error = "Seuls les candidats peuvent suivre des entreprises.";
    } else {
        try {
            // Récupérer l'id_recruteur de l'entreprise
            $stmt = $pdo->prepare("SELECT id_recruteur FROM entreprises WHERE id_entreprise = :id_entreprise");
            $stmt->execute([':id_entreprise' => $id_entreprise]);
            $entreprise_data = $stmt->fetch();
            
            if ($entreprise_data && $entreprise_data['id_recruteur']) {
                $id_recruteur = $entreprise_data['id_recruteur'];
                
                // Vérifier si déjà abonné
                $stmt = $pdo->prepare("SELECT id_abonnement FROM abonnements WHERE id_candidat = :id_candidat AND id_recruteur = :id_recruteur");
                $stmt->execute([':id_candidat' => $_SESSION['user_id'], ':id_recruteur' => $id_recruteur]);
                $result = $stmt->fetch();
                
                if ($result) {
                    // Désabonner
                    $stmt = $pdo->prepare("DELETE FROM abonnements WHERE id_abonnement = :id_abonnement");
                    $stmt->execute([':id_abonnement' => $result['id_abonnement']]);
                    $message_success = "Vous ne suivez plus cette entreprise.";
                } else {
                    // S'abonner
                    $stmt = $pdo->prepare("INSERT INTO abonnements (id_candidat, id_recruteur, date_abonnement) VALUES (:id_candidat, :id_recruteur, NOW())");
                    $stmt->execute([':id_candidat' => $_SESSION['user_id'], ':id_recruteur' => $id_recruteur]);
                    $message_success = "Vous suivez maintenant cette entreprise ! Retrouvez-la dans vos abonnements.";
                }
                
                // Redirection pour éviter la resoumission
                header('Location: entreprise-detail.php?id=' . $id_entreprise . '&msg=' . urlencode($message_success));
                exit();
            } else {
                $message_error = "Impossible de trouver le recruteur associé à cette entreprise.";
            }
        } catch (PDOException $e) {
            $message_error = "Erreur lors de l'opération : " . $e->getMessage();
        }
    }
}

// Afficher le message après redirection
if (isset($_GET['msg'])) {
    $message_success = $_GET['msg'];
}

// Vérifier si l'utilisateur suit déjà l'entreprise
$is_following = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidat') {
    try {
        // Récupérer id_recruteur
        $stmt = $pdo->prepare("SELECT id_recruteur FROM entreprises WHERE id_entreprise = :id_entreprise");
        $stmt->execute([':id_entreprise' => $id_entreprise]);
        $entreprise_recruteur = $stmt->fetch();
        
        if ($entreprise_recruteur && $entreprise_recruteur['id_recruteur']) {
            // Vérifier l'abonnement
            $stmt = $pdo->prepare("SELECT id_abonnement FROM abonnements WHERE id_candidat = :id_candidat AND id_recruteur = :id_recruteur");
            $stmt->execute([':id_candidat' => $_SESSION['user_id'], ':id_recruteur' => $entreprise_recruteur['id_recruteur']]);
            $result = $stmt->fetch();
            if ($result) {
                $is_following = true;
            }
        }
    } catch (PDOException $e) {
        // Erreur silencieuse
    }
}

// Récupérer les informations de l'entreprise
try {
    $stmt = $pdo->prepare("
        SELECT e.*, 
               r.nom_entreprise as recruteur_nom,
               COUNT(DISTINCT oe.id_offre) as nombre_offres_total,
               (SELECT COUNT(*) FROM abonnements a2 WHERE a2.id_recruteur = e.id_recruteur) as nombre_suivis
        FROM entreprises e
        LEFT JOIN recruteurs r ON e.id_recruteur = r.id_recruteur
        LEFT JOIN offres_emploi oe ON r.id_recruteur = oe.id_recruteur
        WHERE e.id_entreprise = :id_entreprise AND e.statut = 'active'
        GROUP BY e.id_entreprise
    ");
    $stmt->bindParam(':id_entreprise', $id_entreprise, PDO::PARAM_INT);
    $stmt->execute();
    $entreprise = $stmt->fetch();

    if (!$entreprise) {
        header('Location: entreprises.php');
        exit();
    }
} catch (PDOException $e) {
    header('Location: entreprises.php');
    exit();
}

// Récupérer toutes les offres RÉELLES de l'entreprise
try {
    $stmt = $pdo->prepare("
        SELECT 
            oe.id_offre,
            oe.titre_emploi,
            oe.localisation,
            oe.description_offre,
            oe.date_publication,
            s.nom_secteur,
            d.nom_domaine
        FROM offres_emploi oe
        INNER JOIN entreprises e ON oe.id_recruteur = e.id_recruteur
        LEFT JOIN secteurs s ON oe.id_secteur = s.id_secteur
        LEFT JOIN domaines d ON oe.id_domaine = d.id_domaine
        WHERE e.id_entreprise = :id_entreprise
        ORDER BY oe.date_publication DESC
    ");
    $stmt->bindParam(':id_entreprise', $id_entreprise, PDO::PARAM_INT);
    $stmt->execute();
    $offres = $stmt->fetchAll();
} catch (PDOException $e) {
    $offres = [];
}

// Fonction pour calculer le temps écoulé
function tempsEcoule($date) {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;
    
    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes == 0 ? "À l'instant" : "Il y a " . $minutes . " min";
    } elseif ($diff < 86400) {
        $heures = floor($diff / 3600);
        return "Il y a " . $heures . "h";
    } elseif ($diff < 604800) {
        $jours = floor($diff / 86400);
        return "Il y a " . $jours . " jour" . ($jours > 1 ? 's' : '');
    } else {
        return date('d/m/Y', $timestamp);
    }
}

// Générer les initiales de l'entreprise
$initiales = '';
$nom_parts = explode(' ', $entreprise['nom_entreprise']);
if (count($nom_parts) >= 2) {
    $initiales = strtoupper(substr($nom_parts[0], 0, 1) . substr($nom_parts[1], 0, 1));
} else {
    $initiales = strtoupper(substr($entreprise['nom_entreprise'], 0, 2));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($entreprise['nom_entreprise']) ?> - NextCareer</title>
    
    <link rel="stylesheet" href="assets/css/tailwind.min.css">
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; }
        
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
        }

        .tab-active {
            border-bottom: 3px solid #667eea;
            color: #667eea;
        }
        
        .logo-initiales {
            width: 96px;
            height: 96px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal.active { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
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
                        $initiales_nav = strtoupper(substr($user_prenom, 0, 1) . substr($user_nom, 0, 1));
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
                        $initiales_nav = strtoupper(substr($user_nom, 0, 1));
                        $nom_complet = htmlspecialchars($user_nom);
                        $bg_color = 'bg-green-100';
                        $text_color = 'text-green-600';
                        $btn_color = 'bg-green-600 hover:bg-green-700';
                    }
                    ?>

                    <div class="hidden sm:flex items-center space-x-3 border-r border-gray-300 pr-4">
                        <div class="w-10 h-10 <?= $bg_color ?> rounded-full flex items-center justify-center">
                            <span class="text-lg font-bold <?= $text_color ?>"><?= $initiales_nav ?></span>
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

    <!-- Messages -->
    <?php if ($message_success): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= htmlspecialchars($message_success) ?>
                </div>
                <?php if ($is_following): ?>
                <a href="pages/candidat/abonnements.php" class="text-green-700 underline font-medium hover:text-green-800">
                    Voir mes abonnements →
                </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($message_error): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($message_error) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center space-x-2 text-sm">
                <a href="index.php" class="text-gray-600 hover:text-indigo-600 transition">Accueil</a>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <a href="entreprises.php" class="text-gray-600 hover:text-indigo-600 transition">Entreprises</a>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-gray-900 font-medium"><?= htmlspecialchars($entreprise['nom_entreprise']) ?></span>
            </div>
        </div>
    </div>

    <!-- Header Entreprise -->
    <section class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div class="flex items-start space-x-6">
                    <!-- Logo avec initiales -->
                    <div class="logo-initiales">
                        <?= $initiales ?>
                    </div>

                    <!-- Infos -->
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <h1 class="text-3xl font-bold text-gray-900">
                                <?= htmlspecialchars($entreprise['nom_entreprise']) ?>
                            </h1>
                            <?php if (!empty($entreprise['site_web'])): ?>
                            <a href="<?= htmlspecialchars($entreprise['site_web']) ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="text-indigo-600 hover:text-indigo-700 transition"
                               title="Visiter le site web">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($entreprise['secteur'])): ?>
                        <div class="mb-3">
                            <span class="bg-indigo-100 text-indigo-700 text-sm font-semibold px-4 py-1.5 rounded-full">
                                <?= htmlspecialchars($entreprise['secteur']) ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                            <?php if (!empty($entreprise['ville'])): ?>
                            <div class="flex items-center">
                                📍 <?= htmlspecialchars($entreprise['ville']) ?>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($entreprise['nombre_employes'])): ?>
                            <div class="flex items-center">
                                👥 <?= htmlspecialchars($entreprise['nombre_employes']) ?> employés
                            </div>
                            <?php endif; ?>

                            <div class="flex items-center text-indigo-600 font-medium">
                                💼 <?= $entreprise['nombre_offres_total'] ?> poste<?= $entreprise['nombre_offres_total'] > 1 ? 's' : '' ?>
                            </div>
                            
                            <div class="flex items-center text-gray-500">
                                👤 <?= $entreprise['nombre_suivis'] ?? 0 ?> abonné<?= ($entreprise['nombre_suivis'] ?? 0) > 1 ? 's' : '' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <?php if (!empty($entreprise['site_web'])): ?>
                    <a href="<?= htmlspecialchars($entreprise['site_web']) ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="inline-flex items-center px-6 py-3 bg-white border-2 border-indigo-600 text-indigo-600 rounded-xl hover:bg-indigo-50 transition font-medium">
                        Site web
                    </a>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'candidat'): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action_follow" value="1">
                        
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 <?= $is_following ? 'bg-gray-100 text-gray-700 border-2 border-gray-300' : 'bg-indigo-600 text-white' ?> rounded-xl hover:opacity-90 transition font-medium">
                            <?= $is_following ? '✓ Abonné' : '+ Suivre' ?>
                        </button>
                    </form>
                    <?php else: ?>
                    <a href="auth/login.php" class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition font-medium">
                        + Suivre
                    </a>
                    <?php endif; ?>
                    
                    <button onclick="openShareModal()" class="inline-flex items-center px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium">
                        Partager
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Tabs -->
    <section class="bg-white border-b border-gray-200 sticky top-16 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8">
                <button onclick="switchTab('offres')" id="tab-offres" class="tab-active py-4 px-2 font-medium text-sm transition">
                    Offres d'emploi (<?= count($offres) ?>)
                </button>
                <button onclick="switchTab('apropos')" id="tab-apropos" class="py-4 px-2 font-medium text-sm text-gray-600 hover:text-indigo-600 transition">
                    À propos
                </button>
            </div>
        </div>
    </section>

    <!-- Content -->
    <section class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Offres Section -->
            <div id="content-offres">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    Postes disponibles (<?= count($offres) ?>)
                </h2>

                <?php if (!empty($offres)): ?>
                    <div class="space-y-4">
                        <?php foreach ($offres as $offre): 
                            $days_since = floor((time() - strtotime($offre['date_publication'])) / 86400);
                        ?>
                            <div class="card-hover bg-white rounded-xl p-6 border border-gray-200">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h3 class="text-xl font-bold text-gray-900">
                                                <?= htmlspecialchars($offre['titre_emploi']) ?>
                                            </h3>
                                            <?php if ($days_since <= 7): ?>
                                            <span class="bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full">
                                                Nouveau
                                            </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-3">
                                            <?php if (!empty($offre['localisation'])): ?>
                                            <div>📍 <?= htmlspecialchars($offre['localisation']) ?></div>
                                            <?php endif; ?>
                                            <div>🕐 <?= tempsEcoule($offre['date_publication']) ?></div>
                                        </div>

                                        <?php if (!empty($offre['description_offre'])): ?>
                                        <p class="text-gray-600 text-sm mb-4">
                                            <?= htmlspecialchars(substr($offre['description_offre'], 0, 150)) ?>...
                                        </p>
                                        <?php endif; ?>

                                        <div class="flex gap-2">
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
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                                    <a href="offre-detail.php?id=<?= $offre['id_offre'] ?>" 
                                       class="text-indigo-600 font-medium hover:text-indigo-700 text-sm">
                                        Voir les détails →
                                    </a>
                                    <a href="pages/candidat/postuler.php?id=<?= $offre['id_offre'] ?>" 
                                       class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium text-sm">
                                        Postuler
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl p-12 text-center border border-gray-200">
                        <div class="text-6xl mb-4">📭</div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">Aucune offre disponible</h3>
                        <p class="text-gray-600">Cette entreprise n'a pas encore publié d'offres d'emploi.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- À propos Section -->
            <div id="content-apropos" class="hidden">
                <div class="bg-white rounded-xl p-8 border border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">À propos de l'entreprise</h2>
                    
                    <?php if (!empty($entreprise['description'])): ?>
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Description</h3>
                        <p class="text-gray-700 leading-relaxed">
                            <?= nl2br(htmlspecialchars($entreprise['description'])) ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <div class="grid md:grid-cols-2 gap-6">
                        <?php if (!empty($entreprise['secteur'])): ?>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Secteur d'activité</h3>
                            <p class="text-gray-900 font-medium"><?= htmlspecialchars($entreprise['secteur']) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($entreprise['ville'])): ?>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Localisation</h3>
                            <p class="text-gray-900 font-medium"><?= htmlspecialchars($entreprise['ville']) ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($entreprise['nombre_employes'])): ?>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Taille</h3>
                            <p class="text-gray-900 font-medium"><?= htmlspecialchars($entreprise['nombre_employes']) ?> employés</p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($entreprise['site_web'])): ?>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Site web</h3>
                            <a href="<?= htmlspecialchars($entreprise['site_web']) ?>" 
                               target="_blank" 
                               class="text-indigo-600 hover:text-indigo-700 font-medium">
                                Visiter le site →
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal Partage -->
    <div class="modal" id="shareModal">
        <div class="modal-content">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Partager cette entreprise</h3>
                <button onclick="closeShareModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Lien de partage</label>
                    <div class="flex gap-2">
                        <input type="text" id="shareLink" value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" readonly class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <button onclick="copyLink()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">
                            Copier
                        </button>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-700 mb-3">Partager sur</p>
                    <div class="grid grid-cols-4 gap-2">
                        <button onclick="shareOn('facebook')" class="p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition text-center">
                            <div class="text-2xl mb-1">📘</div>
                            <div class="text-xs text-gray-700">Facebook</div>
                        </button>
                        <button onclick="shareOn('twitter')" class="p-3 bg-sky-50 rounded-lg hover:bg-sky-100 transition text-center">
                            <div class="text-2xl mb-1">🐦</div>
                            <div class="text-xs text-gray-700">Twitter</div>
                        </button>
                        <button onclick="shareOn('linkedin')" class="p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition text-center">
                            <div class="text-2xl mb-1">💼</div>
                            <div class="text-xs text-gray-700">LinkedIn</div>
                        </button>
                        <button onclick="shareOn('whatsapp')" class="p-3 bg-green-50 rounded-lg hover:bg-green-100 transition text-center">
                            <div class="text-2xl mb-1">💬</div>
                            <div class="text-xs text-gray-700">WhatsApp</div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.getElementById('content-offres').classList.add('hidden');
            document.getElementById('content-apropos').classList.add('hidden');
            
            document.getElementById('tab-offres').classList.remove('tab-active');
            document.getElementById('tab-apropos').classList.remove('tab-active');
            document.getElementById('tab-offres').classList.add('text-gray-600');
            document.getElementById('tab-apropos').classList.add('text-gray-600');
            
            if (tab === 'offres') {
                document.getElementById('content-offres').classList.remove('hidden');
                document.getElementById('tab-offres').classList.add('tab-active');
                document.getElementById('tab-offres').classList.remove('text-gray-600');
            } else {
                document.getElementById('content-apropos').classList.remove('hidden');
                document.getElementById('tab-apropos').classList.add('tab-active');
                document.getElementById('tab-apropos').classList.remove('text-gray-600');
            }
        }

        function openShareModal() {
            document.getElementById('shareModal').classList.add('active');
        }

        function closeShareModal() {
            document.getElementById('shareModal').classList.remove('active');
        }

        function copyLink() {
            const link = document.getElementById('shareLink');
            link.select();
            document.execCommand('copy');
            alert('Lien copié !');
        }

        function shareOn(platform) {
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent('<?= htmlspecialchars($entreprise['nom_entreprise']) ?> - NextCareer');
            
            let shareUrl = '';
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                    break;
                case 'linkedin':
                    shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${title}%20${url}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        // Fermer modal en cliquant dehors
        document.getElementById('shareModal').addEventListener('click', function(e) {
            if (e.target === this) closeShareModal();
        });

        // Fermer avec Echap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeShareModal();
        });
    </script>
</body>
</html>