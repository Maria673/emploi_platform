<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidat') {
    header('Location: ../../auth/login.php');
    exit();
}

require_once '../../config/db.php';

$candidat = null;
$candidatures = [];
$filtre_statut = $_GET['statut'] ?? 'tous';
$message = '';

// Récupérer infos candidat
try {
    $stmt = $pdo->prepare("SELECT c.*, u.email FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $candidat = $stmt->fetch();
    if (!$candidat) { session_destroy(); header('Location: ../../auth/login.php'); exit(); }
} catch (PDOException $e) {}

// ✅ TRAITER LA CANDIDATURE (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_offre'])) {
    $id_offre = intval($_POST['id_offre']);
    $lettre_motivation = isset($_POST['lettre_motivation']) ? trim($_POST['lettre_motivation']) : '';
    
    if ($id_offre > 0) {
        try {
            $stmt = $pdo->prepare("SELECT id_offre FROM offres_emploi WHERE id_offre = :id");
            $stmt->execute([':id' => $id_offre]);
            
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    SELECT id_candidature FROM candidatures 
                    WHERE id_candidat = :id_candidat AND id_offre = :id_offre
                ");
                $stmt->execute([
                    ':id_candidat' => $_SESSION['user_id'],
                    ':id_offre' => $id_offre
                ]);
                
                if ($stmt->rowCount() === 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO candidatures (id_candidat, id_offre, date_candidature, lettre_motivation, statut)
                        VALUES (:id_candidat, :id_offre, NOW(), :lettre_motivation, 'En attente')
                    ");
                    $stmt->execute([
                        ':id_candidat' => $_SESSION['user_id'],
                        ':id_offre' => $id_offre,
                        ':lettre_motivation' => $lettre_motivation
                    ]);
                    $message = ' Candidature envoyée avec succès !';
                } else {
                    $message = '⚠️ Vous avez déjà postulé à cette offre';
                }
            }
        } catch (PDOException $e) {
            $message = ' Erreur: ' . $e->getMessage();
        }
    }
}

// ✅ RETIRER UNE CANDIDATURE
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM candidatures WHERE id_candidature = :id AND id_candidat = :id_candidat");
        $stmt->execute([
            ':id' => $_GET['remove'],
            ':id_candidat' => $_SESSION['user_id']
        ]);
        $message = ' Candidature retirée';
    } catch (PDOException $e) {}
}

// ✅ RÉCUPÉRER LES CANDIDATURES
try {
    $sql = "SELECT 
                c.id_candidature,
                c.id_offre,
                c.date_candidature,
                c.statut,
                c.lettre_motivation,
                o.id_offre as offre_id,
                o.titre_emploi,
                o.localisation,
                o.statut_recherche,
                o.description_offre,
                r.nom_entreprise,
                d.nom_domaine
            FROM candidatures c
            INNER JOIN offres_emploi o ON c.id_offre = o.id_offre
            LEFT JOIN recruteurs r ON o.id_recruteur = r.id_recruteur
            LEFT JOIN domaines d ON o.id_domaine = d.id_domaine
            WHERE c.id_candidat = :id";
    
    if ($filtre_statut !== 'tous') {
        $sql .= " AND c.statut = :statut";
    }
    
    $sql .= " ORDER BY c.date_candidature DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $_SESSION['user_id']);
    
    if ($filtre_statut !== 'tous') {
        $stmt->bindParam(':statut', $filtre_statut);
    }
    
    $stmt->execute();
    $candidatures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $message = ' Erreur BDD: ' . $e->getMessage();
    $candidatures = [];
}

// Compter par statut
$total = count($candidatures);
$en_attente = count(array_filter($candidatures, fn($c) => strtolower($c['statut']) === 'en attente'));
$acceptees = count(array_filter($candidatures, fn($c) => strtolower($c['statut']) === 'acceptée'));
$refusees = count(array_filter($candidatures, fn($c) => strtolower($c['statut']) === 'refusée'));

// ✅ RÉCUPÉRER INFOS UTILISATEUR POUR LA NAVBAR
$user_nom = '';
$user_prenom = '';
$user_type = $_SESSION['user_type'] ?? '';

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
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes Candidatures - NextCareer</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: #f5f5fa; color: #1e1e2e; }
a { text-decoration: none; color: inherit; }

/* ===== NAVBAR ===== */
.navbar {
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    z-index: 50;
}
.navbar-inner {
    max-width: 1180px;
    margin: 0 auto;
    padding: 0 24px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.navbar-logo {
    font-family: 'Inter', sans-serif;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1a202c;
    position: relative;
    display: inline-block;
    text-decoration: none;
}
.navbar-logo span { color: #667eea; }
.navbar-logo::after {
    content: '';
    position: absolute;
    bottom: 2px;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    border-radius: 2px;
}
.navbar-links {
    display: flex;
    align-items: center;
    gap: 32px;
}
.navbar-links a {
    font-size: 14px;
    font-weight: 500;
    color: #6b7280;
    transition: color 0.2s;
}
.navbar-links a:hover { color: #667eea; }
.navbar-links a.active {
    color: #667eea;
    border-bottom: 2px solid #667eea;
    padding-bottom: 2px;
}
.navbar-right {
    display: flex;
    align-items: center;
    gap: 16px;
}
.navbar-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-right: 16px;
    border-right: 1px solid #e5e7eb;
}
.navbar-avatar {
    width: 40px;
    height: 40px;
    background: #e0e7ff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 700;
    color: #667eea;
    flex-shrink: 0;
}
.navbar-user-info .user-name {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    line-height: 1.2;
}
.navbar-user-info .user-role {
    font-size: 12px;
    color: #9ca3af;
}
.btn-logout {
    background: #667eea;
    color: #fff;
    border: none;
    border-radius: 999px;
    padding: 9px 22px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
    text-decoration: none;
}
.btn-logout:hover { background: #5a6fd6; }

/* ===== PAGE ===== */
.page { max-width: 1180px; margin: 0 auto; padding: 32px 24px; }

.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 14px; color: #5b5fc7; font-weight: 500; margin-bottom: 18px;
}

.page-header {
    display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px;
}
.page-title { font-size: 28px; font-weight: 700; color: #1e1e2e; margin-bottom: 6px; }
.page-subtitle { font-size: 14px; color: #7a7a8e; }

.alert {
    padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;
}
.alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
.alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
.alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card {
    background: #fff; border: 1px solid #eee; border-radius: 16px; padding: 20px;
}
.stat-number { font-size: 28px; font-weight: 700; line-height: 1; margin-bottom: 6px; }
.stat-label { font-size: 13px; color: #7a7a8e; }

.filters { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; }
.filter-btn {
    padding: 9px 18px; border-radius: 20px; font-size: 14px; font-weight: 500;
    border: 1.5px solid #ddd; background: #fff; color: #444; cursor: pointer;
    transition: all 0.2s;
}
.filter-btn:hover { border-color: #5b5fc7; background: #f0f0ff; }
.filter-btn.active { border-color: #5b5fc7; background: #5b5fc7; color: #fff; }

.candidatures-list { display: flex; flex-direction: column; gap: 14px; }

.candidature-card {
    background: #fff; border: 1px solid #eee; border-radius: 16px;
    padding: 24px; display: flex; gap: 20px; align-items: flex-start;
    transition: all 0.2s;
}
.candidature-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

.candidature-logo {
    width: 56px; height: 56px; background: #f2f2f7; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 28px;
}

.candidature-content { flex: 1; }
.candidature-title { font-size: 17px; font-weight: 600; color: #1e1e2e; margin-bottom: 6px; }
.candidature-company { font-size: 14px; color: #7a7a8e; margin-bottom: 10px; }

.candidature-meta { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 12px; }
.meta-item { display: flex; align-items: center; gap: 5px; font-size: 13px; color: #7a7a8e; }

.candidature-right { display: flex; flex-direction: column; align-items: flex-end; gap: 12px; }

.badge {
    font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 999px; white-space: nowrap;
}
.badge-pending { background: #ede9fe; color: #6d28d9; }
.badge-accepted { background: #d1fae5; color: #065f46; }
.badge-refused { background: #fee2e2; color: #991b1b; }

.btn-detail {
    display: inline-flex; align-items: center; gap: 6px;
    border: 1.5px solid #5b5fc7; border-radius: 10px; padding: 8px 16px;
    background: #fff; color: #5b5fc7; font-size: 13px; font-weight: 600; cursor: pointer;
    text-decoration: none; transition: all 0.2s;
}
.btn-detail:hover { background: #f0f0ff; }

.btn-remove {
    display: inline-flex; align-items: center; gap: 6px;
    border: 1.5px solid #dc2626; border-radius: 10px; padding: 8px 16px;
    background: #fff; color: #dc2626; font-size: 13px; font-weight: 600; cursor: pointer;
    text-decoration: none; transition: all 0.2s;
}
.btn-remove:hover { background: #fef2f2; }

.empty-state {
    text-align: center; padding: 60px 20px;
    background: #fff; border: 1px solid #eee; border-radius: 16px;
}
.empty-state h3 { font-size: 18px; font-weight: 600; color: #1e1e2e; margin-bottom: 8px; }
.empty-state p { font-size: 14px; color: #7a7a8e; margin-bottom: 24px; }
.btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    background: #5b5fc7; color: #fff; border: none; border-radius: 10px;
    padding: 12px 24px; font-size: 14px; font-weight: 600; cursor: pointer;
    text-decoration: none;
}
.btn-primary:hover { background: #4a4eb5; }

@media (max-width: 900px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .navbar-links { display: none; }
    .navbar-user { display: none; }
}
</style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <div class="navbar-inner">
        <!-- Logo -->
        <a href="../../index.php" class="navbar-logo">Next<span>Career</span></a>

        <!-- Liens -->
        <div class="navbar-links">
            <a href="../../index.php">Accueil</a>
            <a href="../../offres.php">Offres d'emploi</a>
            <a href="../../entreprises.php">Entreprises</a>
            <a href="../../abonnement.php">Abonnement</a>
        </div>

        <!-- Utilisateur + Déconnexion -->
        <div class="navbar-right">
            <div class="navbar-user">
                <div class="navbar-avatar"><?= $initiales ?></div>
                <div class="navbar-user-info">
                    <div class="user-name"><?= $nom_complet ?></div>
                    <div class="user-role"><?= ucfirst($user_type) ?></div>
                </div>
            </div>
            <a href="../../auth/logout.php" class="btn-logout">Déconnexion</a>
        </div>
    </div>
</nav>

<div class="page">
    <a href="dashboard.php" class="back-link">← Retour</a>

    <div class="page-header">
        <div>
            <h1 class="page-title">Mes Candidatures</h1>
            <p class="page-subtitle">Suivez l'état de vos candidatures</p>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert <?php
            if (strpos($message, '✅') !== false) echo 'alert-success';
            elseif (strpos($message, '⚠️') !== false) echo 'alert-warning';
            else echo 'alert-error';
        ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number" style="color:#5b5fc7;"><?= $total ?></div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:#ca8a04;"><?= $en_attente ?></div>
            <div class="stat-label">En attente</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:#16a34a;"><?= $acceptees ?></div>
            <div class="stat-label">Acceptées</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:#dc2626;"><?= $refusees ?></div>
            <div class="stat-label">Refusées</div>
        </div>
    </div>

    <div class="filters">
        <a href="?statut=tous" class="filter-btn <?= $filtre_statut === 'tous' ? 'active' : '' ?>">Tous</a>
        <a href="?statut=En attente" class="filter-btn <?= $filtre_statut === 'En attente' ? 'active' : '' ?>">En attente</a>
        <a href="?statut=Acceptée" class="filter-btn <?= $filtre_statut === 'Acceptée' ? 'active' : '' ?>">Acceptées</a>
        <a href="?statut=Refusée" class="filter-btn <?= $filtre_statut === 'Refusée' ? 'active' : '' ?>">Refusées</a>
    </div>

    <?php if (empty($candidatures)): ?>
        <div class="empty-state">
            <h3>Aucune candidature</h3>
            <p>Vous n'avez pas encore postulé à des offres</p>
            <a href="../../offres.php" class="btn-primary">Rechercher des offres</a>
        </div>
    <?php else: ?>
        <div class="candidatures-list">
            <?php foreach ($candidatures as $c): 
                $statut = $c['statut'] ?? 'En attente';
                $badge_class = match(strtolower($statut)) {
                    'acceptée' => 'badge-accepted',
                    'refusée' => 'badge-refused',
                    default => 'badge-pending',
                };
                $date_display = !empty($c['date_candidature']) ? 
                    date('d/m/Y', strtotime($c['date_candidature'])) : '';
            ?>
            <div class="candidature-card">
                <div class="candidature-logo">📋</div>

                <div class="candidature-content">
                    <div class="candidature-title"><?= htmlspecialchars($c['titre_emploi'] ?? 'Offre') ?></div>
                    <div class="candidature-company"><?= htmlspecialchars($c['nom_entreprise'] ?? 'Entreprise') ?></div>
                    
                    <div class="candidature-meta">
                        <div class="meta-item">📍 <?= htmlspecialchars($c['localisation'] ?? '') ?></div>
                        <?php if (!empty($c['nom_domaine'])): ?>
                        <div class="meta-item">💼 <?= htmlspecialchars($c['nom_domaine']) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($c['description_offre'])): ?>
                    <div style="font-size: 13px; color: #555; margin-top: 8px; line-height: 1.5;">
                        <?= htmlspecialchars(substr($c['description_offre'], 0, 150)) ?>...
                    </div>
                    <?php endif; ?>
                </div>

                <div class="candidature-right">
                    <div style="font-size: 13px; color: #7a7a8e;"><?= $date_display ?></div>
                    <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($statut) ?></span>
                    <div style="display: flex; gap: 6px;">
                        <a href="../../offre.php?id=<?= $c['id_offre'] ?? 0 ?>" class="btn-detail">Voir</a>
                        <a href="?remove=<?= $c['id_candidature'] ?>" class="btn-remove" onclick="return confirm('Retirer ?')">×</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>