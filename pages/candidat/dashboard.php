<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidat') {
    header('Location: ../../auth/login.php');
    exit();
}

require_once '../../config/db.php';

// Variables par défaut
$candidat = ['nom' => '', 'prenom' => 'Utilisateur', 'email' => ''];
$total_candidatures = 0;
$total_favoris = 0;
$total_entreprises = 0;
$profil_pct = 0;
$dernieres_candidatures = [];
$offres_recommandees = [];
$manquants = [];
$notifications = [];
$nb_notifications = 0;

// 1. Candidat
try {
    $stmt = $pdo->prepare("SELECT c.*, u.email FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $candidat = $stmt->fetch();
    if (!$candidat) { 
        session_destroy(); 
        header('Location: ../../auth/login.php'); 
        exit(); 
    }

    // Complétude
    $fields = ['nom','prenom','email','telephone','ville'];
    $total = count($fields);
    $filled = 0;
    $manquants = [];
    foreach ($fields as $f) { 
        if (!empty($candidat[$f])) $filled++; 
        else $manquants[] = ucfirst(str_replace('_',' ',$f)); 
    }
    $profil_pct = $total > 0 ? (int)($filled / $total * 100) : 0;
} catch (PDOException $e) {
    $candidat = ['nom' => '', 'prenom' => 'Utilisateur', 'email' => ''];
}

// 2. Candidatures
try {
    $s = $pdo->prepare("SELECT COUNT(*) as t FROM candidatures WHERE id_candidat = :id");
    $s->execute([':id' => $_SESSION['user_id']]);
    $r = $s->fetch(); 
    $total_candidatures = $r ? (int)$r['t'] : 0;
} catch (PDOException $e) {}

// 3. Favoris
try {
    $s = $pdo->prepare("SELECT COUNT(*) as t FROM favoris WHERE id_candidat = :id");
    $s->execute([':id' => $_SESSION['user_id']]);
    $r = $s->fetch(); 
    $total_favoris = $r ? (int)$r['t'] : 0;
} catch (PDOException $e) {}

// 4. Entreprises suivies
try {
    $s = $pdo->prepare("SELECT COUNT(*) as t FROM abonnements WHERE id_candidat = :id");
    $s->execute([':id' => $_SESSION['user_id']]);
    $r = $s->fetch(); 
    $total_entreprises = $r ? (int)$r['t'] : 0;
} catch (PDOException $e) {}

// 5. Dernières candidatures
try {
    $s = $pdo->prepare("
        SELECT 
            c.id_candidature,
            c.date_candidature,
            c.statut,
            o.titre_emploi as titre_offre,
            r.nom_entreprise
        FROM candidatures c 
        LEFT JOIN offres_emploi o ON c.id_offre = o.id_offre 
        LEFT JOIN recruteurs r ON o.id_recruteur = r.id_recruteur
        WHERE c.id_candidat = :id 
        ORDER BY c.date_candidature DESC 
        LIMIT 5
    ");
    $s->execute([':id' => $_SESSION['user_id']]);
    $dernieres_candidatures = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 6. Offres recommandées
try {
    $s = $pdo->query("
        SELECT 
            o.id_offre, 
            o.titre_emploi as titre, 
            o.localisation as ville, 
            o.type_contrat, 
            r.nom_entreprise
        FROM offres_emploi o 
        LEFT JOIN recruteurs r ON o.id_recruteur = r.id_recruteur
        WHERE o.statut_recherche = 'Ouvert'
        ORDER BY o.date_publication DESC 
        LIMIT 3
    ");
    $offres_recommandees = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 7. NOTIFICATIONS
try {
    // Candidatures acceptées récentes (7 derniers jours)
    $stmt = $pdo->prepare("
        SELECT 
            c.id_candidature,
            c.statut,
            c.date_candidature,
            o.titre_emploi,
            r.nom_entreprise
        FROM candidatures c
        LEFT JOIN offres_emploi o ON c.id_offre = o.id_offre
        LEFT JOIN recruteurs r ON o.id_recruteur = r.id_recruteur
        WHERE c.id_candidat = :id 
        AND c.statut = 'Acceptée'
        AND c.date_candidature >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY c.date_candidature DESC
    ");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $candidatures_acceptees = $stmt->fetchAll();
    
    foreach ($candidatures_acceptees as $ca) {
        $notifications[] = [
            'type' => 'acceptation',
            'titre' => 'Candidature acceptée',
            'message' => 'Votre candidature pour "' . ($ca['titre_emploi'] ?? 'une offre') . '" chez ' . ($ca['nom_entreprise'] ?? 'l\'entreprise') . ' a été acceptée !',
            'lien' => 'candidatures.php',
            'date' => $ca['date_candidature'],
            'icon' => '✅',
            'color' => 'green'
        ];
    }
    
    // Nouvelles offres publiées (3 derniers jours)
    $stmt = $pdo->prepare("
        SELECT 
            o.id_offre, 
            o.titre_emploi, 
            r.nom_entreprise, 
            o.date_publication
        FROM offres_emploi o
        LEFT JOIN recruteurs r ON o.id_recruteur = r.id_recruteur
        WHERE o.statut_recherche = 'Ouvert'
        AND o.date_publication >= DATE_SUB(NOW(), INTERVAL 3 DAY)
        ORDER BY o.date_publication DESC
        LIMIT 5
    ");
    $stmt->execute();
    $nouvelles_offres = $stmt->fetchAll();
    
    foreach ($nouvelles_offres as $no) {
        $notifications[] = [
            'type' => 'nouvelle_offre',
            'titre' => 'Nouvelle offre disponible',
            'message' => ($no['titre_emploi'] ?? 'Une offre') . ' chez ' . ($no['nom_entreprise'] ?? 'une entreprise'),
            'lien' => '../../offres.php?id=' . $no['id_offre'],
            'date' => $no['date_publication'],
            'icon' => '🆕',
            'color' => 'blue'
        ];
    }
    
    // Offres des entreprises suivies (7 derniers jours)
    $stmt = $pdo->prepare("
        SELECT 
            o.id_offre, 
            o.titre_emploi, 
            r.nom_entreprise, 
            o.date_publication
        FROM offres_emploi o
        INNER JOIN recruteurs r ON o.id_recruteur = r.id_recruteur
        INNER JOIN abonnements a ON a.id_recruteur = r.id_recruteur
        WHERE a.id_candidat = :id
        AND o.statut_recherche = 'Ouvert'
        AND o.date_publication >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY o.date_publication DESC
        LIMIT 5
    ");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $offres_suivies = $stmt->fetchAll();
    
    foreach ($offres_suivies as $os) {
        $notifications[] = [
            'type' => 'entreprise_suivie',
            'titre' => 'Entreprise suivie',
            'message' => ($os['nom_entreprise'] ?? 'Une entreprise') . ' a publié : ' . ($os['titre_emploi'] ?? 'une offre'),
            'lien' => '../../offres.php?id=' . $os['id_offre'],
            'date' => $os['date_publication'],
            'icon' => '🔔',
            'color' => 'purple'
        ];
    }
    
    // Trier par date décroissante
    usort($notifications, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    // Limiter à 10 notifications
    $notifications = array_slice($notifications, 0, 10);
    $nb_notifications = count($notifications);
    
} catch (PDOException $e) {}

function tempsEcoule($date) {
    if (empty($date)) return 'N/A';
    $diff = time() - strtotime($date);
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
        return date('d/m/Y', strtotime($date));
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - NextCareer</title>
<link rel="stylesheet" href="../../assets/css/tailwind.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Inter', sans-serif; }
.gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.card-hover { transition: all 0.3s ease; }
.card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.1); }
.notification-panel {
    position: absolute;
    top: 100%;
    right: 0;
    width: 380px;
    max-height: 500px;
    overflow-y: auto;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    margin-top: 8px;
    display: none;
    z-index: 1000;
}
.notification-panel.show {
    display: block;
}
</style>
</head>
<body class="bg-gray-50">

<!-- Navbar -->
<nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-2">
                <div style="font-family:'Inter',sans-serif;font-size:1.75rem;font-weight:700;color:#1a202c;position:relative;display:inline-block;">
                    Next<span style="color:#667eea;">Career</span>
                    <div style="position:absolute;bottom:2px;left:0;width:100%;height:3px;background:linear-gradient(90deg,#667eea 0%,#764ba2 100%);border-radius:2px;"></div>
                </div>
            </div>

            <div class="hidden md:flex items-center space-x-8">
                <a href="dashboard.php" class="text-indigo-600 font-medium border-b-2 border-indigo-600 pb-1">Dashboard</a>
                <a href="../../offres.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Offres d'emploi</a>
                <a href="../../entreprises.php" class="text-gray-600 hover:text-indigo-600 transition font-medium">Entreprises</a>
            </div>

            <div class="flex items-center space-x-4">
                <div class="hidden sm:flex items-center space-x-3 border-r border-gray-300 pr-4">
                    <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                        <span class="text-lg font-bold text-indigo-600">
                            <?= strtoupper(substr($candidat['prenom'] ?? 'U', 0, 1) . substr($candidat['nom'] ?? 'N', 0, 1)) ?>
                        </span>
                    </div>
                    <div class="text-sm">
                        <p class="font-medium text-gray-900"><?= htmlspecialchars(($candidat['prenom'] ?? '') . ' ' . ($candidat['nom'] ?? '')) ?></p>
                        <p class="text-xs text-gray-500">Candidat</p>
                    </div>
                </div>
                
                <a href="../../auth/logout.php" class="bg-indigo-600 text-white px-6 py-2 rounded-full hover:bg-indigo-700 transition font-medium text-sm">
                    Déconnexion
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Greeting -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Bonjour, <?= htmlspecialchars($candidat['prenom'] ?? 'Candidat') ?></h1>
            <p class="text-gray-600 mt-1">Voici un aperçu de votre activité</p>
        </div>
        <div class="flex items-center gap-3">
            <!-- Bouton Notifications -->
            <div class="relative">
                <button onclick="toggleNotifications()" class="relative p-2.5 text-gray-600 hover:text-indigo-600 transition rounded-full hover:bg-gray-100 border-2 border-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <?php if ($nb_notifications > 0): ?>
                        <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center font-bold">
                            <?= min($nb_notifications, 9) ?><?= $nb_notifications > 9 ? '+' : '' ?>
                        </span>
                    <?php endif; ?>
                </button>
                
                <!-- Panneau de notifications -->
                <div id="notificationPanelMain" class="notification-panel">
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="font-bold text-gray-900">Notifications</h3>
                        <p class="text-xs text-gray-500 mt-1"><?= $nb_notifications ?> notification<?= $nb_notifications > 1 ? 's' : '' ?></p>
                    </div>
                    
                    <div class="divide-y divide-gray-100">
                        <?php if (empty($notifications)): ?>
                            <div class="p-8 text-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                </div>
                                <p class="text-gray-500 text-sm font-medium">Aucune notification</p>
                                <p class="text-gray-400 text-xs mt-1">Vous serez notifié ici</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <a href="<?= htmlspecialchars($notif['lien']) ?>" class="block p-4 hover:bg-gray-50 transition">
                                    <div class="flex items-start gap-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 text-xl <?php 
                                            echo $notif['color'] === 'green' ? 'bg-green-100' : 
                                                 ($notif['color'] === 'blue' ? 'bg-blue-100' : 
                                                 ($notif['color'] === 'purple' ? 'bg-purple-100' : 'bg-gray-100')); 
                                        ?>">
                                            <?= $notif['icon'] ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($notif['titre']) ?></p>
                                            <p class="text-gray-600 text-sm mt-1 line-clamp-2"><?= htmlspecialchars($notif['message']) ?></p>
                                            <p class="text-xs text-gray-500 mt-1"><?= tempsEcoule($notif['date']) ?></p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($nb_notifications > 0): ?>
                    <div class="p-3 border-t border-gray-200 bg-gray-50">
                        <a href="#" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium flex items-center justify-center gap-1">
                            Tout marquer comme lu
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <a href="../../offres.php" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-5 py-2.5 rounded-full hover:bg-indigo-700 transition font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Rechercher un emploi
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="card-hover bg-white rounded-xl p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                
                <span class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1 rounded-full font-semibold">Total</span>
            </div>
            <div class="text-3xl font-bold text-indigo-600 mb-1"><?= $total_candidatures ?></div>
            <div class="text-sm text-gray-600">Candidatures envoyées</div>
        </div>

        <div class="card-hover bg-white rounded-xl p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                
                <span class="text-xs bg-yellow-50 text-yellow-600 px-3 py-1 rounded-full font-semibold">Actifs</span>
            </div>
            <div class="text-3xl font-bold text-yellow-600 mb-1"><?= $total_favoris ?></div>
            <div class="text-sm text-gray-600">Offres sauvegardées</div>
        </div>

        <div class="card-hover bg-white rounded-xl p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                
                <span class="text-xs bg-green-50 text-green-600 px-3 py-1 rounded-full font-semibold">Suivi</span>
            </div>
            <div class="text-3xl font-bold text-green-600 mb-1"><?= $total_entreprises ?></div>
            <div class="text-sm text-gray-600">Entreprises suivies</div>
        </div>

        <div class="card-hover bg-white rounded-xl p-6 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                
                <span class="text-xs bg-purple-50 text-purple-600 px-3 py-1 rounded-full font-semibold">Statut</span>
            </div>
            <div class="text-3xl font-bold text-purple-600 mb-1"><?= $profil_pct ?>%</div>
            <div class="text-sm text-gray-600">Profil complété</div>
        </div>
    </div>

    <!-- Two Columns -->
    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <?php if ($profil_pct < 100): ?>
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-800">Complétez votre profil</h3>
                    <span class="text-2xl font-bold text-indigo-600"><?= $profil_pct ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 mb-4">
                    <div class="h-full rounded-full gradient-bg" style="width:<?= $profil_pct ?>%;"></div>
                </div>
                <?php if (!empty($manquants)): ?>
                <div class="flex flex-wrap gap-2 mb-4">
                    <?php foreach ($manquants as $m): ?>
                        <span class="inline-flex items-center gap-1 bg-yellow-50 text-yellow-700 px-3 py-1 rounded-full text-sm font-medium">
                            ⚠️ <?= htmlspecialchars($m) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <a href="profil.php" class="inline-flex items-center gap-2 text-indigo-600 font-medium hover:text-indigo-700">
                    Compléter mon profil →
                </a>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-800">Mes dernières candidatures</h3>
                    <a href="candidatures.php" class="text-indigo-600 font-medium hover:text-indigo-700 text-sm">Voir tout →</a>
                </div>
                <div class="space-y-4">
                    <?php if (empty($dernieres_candidatures)): ?>
                        <div class="text-center py-8">
                            <div class="text-gray-400 text-4xl mb-3">📭</div>
                            <p class="text-gray-600 mb-2">Aucune candidature pour le moment</p>
                            <a href="../../offres.php" class="text-indigo-600 hover:text-indigo-700 font-medium text-sm">
                                Parcourir les offres →
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($dernieres_candidatures as $c): 
                            $date_aff = !empty($c['date_candidature']) ? date('d/m/Y', strtotime($c['date_candidature'])) : '';
                            $statut = $c['statut'] ?? 'En attente';
                            $badge_class = $statut === 'Acceptée' ? 'bg-green-100 text-green-700' : ($statut === 'Refusée' ? 'bg-red-100 text-red-700' : 'bg-indigo-100 text-indigo-700');
                        ?>
                        <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
                            <div class="flex items-center gap-3">
                                <div>
                                    <div class="font-semibold text-gray-800"><?= htmlspecialchars($c['titre_offre'] ?? 'Offre') ?></div>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($c['nom_entreprise'] ?? 'Entreprise') ?> • <?= htmlspecialchars($date_aff) ?></div>
                                </div>
                            </div>
                            <span class="text-xs font-semibold px-3 py-1 rounded-full <?= $badge_class ?> whitespace-nowrap">
                                <?= htmlspecialchars($statut) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Actions rapides</h3>
                <div class="space-y-3">
                    <a href="profil.php" class="flex items-center justify-center gap-3 p-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium">
                        
                        Mon profil
                    </a>
                    <a href="candidatures.php" class="flex items-center justify-center gap-3 p-3 border-2 border-indigo-600 text-indigo-600 rounded-lg hover:bg-indigo-50 transition font-medium">
                        
                        Mes candidatures
                    </a>
                    <a href="favoris.php" class="flex items-center justify-center gap-3 p-3 border-2 border-indigo-600 text-indigo-600 rounded-lg hover:bg-indigo-50 transition font-medium">
                        
                        Mes favoris
                    </a>
                    <a href="abonnements.php" class="flex items-center justify-center gap-3 p-3 border-2 border-indigo-600 text-indigo-600 rounded-lg hover:bg-indigo-50 transition font-medium">
                        
                        Mes abonnements
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 border border-gray-200">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-800">Offres recommandées</h3>
                            </div>

                            <div class="space-y-4">
                                <?php
                                if (empty($offres_recommandees)) {
                                    $offres_recommandees = [
                                        ['titre'=>'Chef de Projet IT',     'nom_entreprise'=>'ONATEL',         'ville'=>'Ouagadougou', 'type_contrat'=>'CDI'],
                                        ['titre'=>'Développeur Mobile',    'nom_entreprise'=>'Startup Hub BF', 'ville'=>'Ouagadougou', 'type_contrat'=>'CDI'],
                                    ];
                                }
                                foreach ($offres_recommandees as $o):
                                ?>
                                <div class="pb-4 border-b border-gray-100 last:border-0">
                                    <h4 class="font-semibold text-gray-800 mb-1"><?= htmlspecialchars($o['titre'] ?? 'Offre') ?></h4>
                                    <p class="text-sm text-gray-600 mb-2">
                                        <?= htmlspecialchars($o['nom_entreprise'] ?? '') ?> • <?= htmlspecialchars($o['ville'] ?? 'Ouagadougou') ?>
                                    </p>
                                    <span class="inline-block text-xs font-semibold bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full">
                                        <?= htmlspecialchars($o['type_contrat'] ?? 'CDI') ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <a href="../../offres.php" class="flex items-center justify-center gap-2 mt-4 text-gray-700 hover:text-indigo-600 font-medium transition">
                                Voir plus d'offres →
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Toggle notifications panel
function toggleNotifications() {
    const panel = document.getElementById('notificationPanelMain');
    panel.classList.toggle('show');
}

// Close notifications when clicking outside
document.addEventListener('click', function(event) {
    const panel = document.getElementById('notificationPanelMain');
    const button = event.target.closest('button[onclick="toggleNotifications()"]');
    
    if (!panel.contains(event.target) && !button) {
        panel.classList.remove('show');
    }
});
</script>

</body>
</html>
