<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidat') {
    header('Location: ../../auth/login.php');
    exit();
}

require_once '../../config/db.php';

// ✅ AJOUTER/RETIRER UN ABONNEMENT (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_recruteur'])) {
    $id_recruteur = intval($_POST['id_recruteur']);
    
    if ($id_recruteur > 0) {
        try {
            // Vérifier que le recruteur existe
            $stmt = $pdo->prepare("SELECT id_recruteur FROM recruteurs WHERE id_recruteur = :id");
            $stmt->execute([':id' => $id_recruteur]);
            
            if ($stmt->rowCount() > 0) {
                // Vérifier que le candidat n'est pas déjà abonné
                $stmt = $pdo->prepare("
                    SELECT id_abonnement FROM abonnements 
                    WHERE id_candidat = :id_candidat AND id_recruteur = :id_recruteur
                ");
                $stmt->execute([
                    ':id_candidat' => $_SESSION['user_id'],
                    ':id_recruteur' => $id_recruteur
                ]);
                
                if ($stmt->rowCount() === 0) {
                    // Ajouter l'abonnement
                    $stmt = $pdo->prepare("
                        INSERT INTO abonnements (id_candidat, id_recruteur, date_abonnement)
                        VALUES (:id_candidat, :id_recruteur, NOW())
                    ");
                    $stmt->execute([
                        ':id_candidat' => $_SESSION['user_id'],
                        ':id_recruteur' => $id_recruteur
                    ]);
                }
            }
        } catch (PDOException $e) {
            // Erreur
        }
    }
}

// ✅ RETIRER UN ABONNEMENT
if (isset($_GET['unsubscribe']) && is_numeric($_GET['unsubscribe'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM abonnements WHERE id_abonnement = :id AND id_candidat = :id_candidat");
        $stmt->execute([':id' => $_GET['unsubscribe'], ':id_candidat' => $_SESSION['user_id']]);
        header('Location: abonnements.php');
        exit();
    } catch (PDOException $e) {}
}

// ✅ Récupérer les abonnements - VERSION CORRIGÉE avec LEFT JOIN
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id_abonnement,
            a.id_candidat,
            a.id_recruteur,
            a.date_abonnement,
            COALESCE(r.id_recruteur, a.id_recruteur) as recruteur_id,
            COALESCE(r.nom_entreprise, e.nom_entreprise, 'Entreprise inconnue') as nom_entreprise,
            COALESCE(r.ville_entreprise, e.ville, 'Non spécifié') as ville_entreprise,
            r.adresse_professionnelle,
            COALESCE(r.nombre_employes, e.nombre_employes) as nombre_employes,
            COUNT(DISTINCT o.id_offre) as offres_count
        FROM abonnements a
        LEFT JOIN recruteurs r ON a.id_recruteur = r.id_recruteur
        LEFT JOIN entreprises e ON a.id_recruteur = e.id_recruteur
        LEFT JOIN offres_emploi o ON a.id_recruteur = o.id_recruteur
        WHERE a.id_candidat = :id
        GROUP BY a.id_abonnement, a.id_candidat, a.id_recruteur, a.date_abonnement, 
                 r.id_recruteur, r.nom_entreprise, r.ville_entreprise, r.adresse_professionnelle, 
                 r.nombre_employes, e.nom_entreprise, e.ville, e.nombre_employes
        ORDER BY a.date_abonnement DESC
    ");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $abonnements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // En cas d'erreur, essayer une requête simplifiée
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.id_abonnement,
                a.id_candidat,
                a.id_recruteur,
                a.date_abonnement,
                a.id_recruteur as recruteur_id,
                r.nom_entreprise,
                r.ville_entreprise,
                r.adresse_professionnelle,
                r.nombre_employes,
                0 as offres_count
            FROM abonnements a
            LEFT JOIN recruteurs r ON a.id_recruteur = r.id_recruteur
            WHERE a.id_candidat = :id
            ORDER BY a.date_abonnement DESC
        ");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $abonnements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $abonnements = [];
    }
}

$total_abonnements = count($abonnements);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mes Abonnements - NextCareer</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: #f5f5fa; color: #1e1e2e; }
a { text-decoration: none; color: inherit; }

.navbar {
    background: #fff; border-bottom: 1px solid #eee; padding: 0 48px; height: 62px;
    display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100;
}
.nav-links { display: flex; gap: 32px; }
.nav-links a { font-size: 14px; color: #444; font-weight: 500; }
.nav-links a:hover { color: #5b5fc7; }
.nav-right { display: flex; align-items: center; gap: 18px; }

.page { max-width: 1180px; margin: 0 auto; padding: 32px 24px; }

.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 14px; color: #5b5fc7; font-weight: 500; margin-bottom: 18px;
}

.page-header {
    display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;
}
.page-title { font-size: 28px; font-weight: 700; color: #1e1e2e; margin-bottom: 6px; }
.page-subtitle { font-size: 14px; color: #7a7a8e; }

.count-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; background: #16a34a; color: #fff;
    border-radius: 50%; font-size: 13px; font-weight: 700;
}

.btn-browse {
    display: inline-flex; align-items: center; justify-content: center;
    background: #5b5fc7; color: #fff; border: none; border-radius: 10px;
    padding: 10px 20px; font-size: 14px; font-weight: 600; cursor: pointer;
}
.btn-browse:hover { background: #4a4eb5; }

.abonnements-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(520px, 1fr));
    gap: 18px;
}

.abonnement-card {
    background: #fff; border: 1px solid #eee; border-radius: 16px;
    padding: 24px;
    transition: all 0.2s;
}
.abonnement-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }

.abonnement-header {
    display: flex; gap: 16px; margin-bottom: 16px;
}

.abonnement-logo {
    width: 64px; height: 64px; background: linear-gradient(135deg, #5b5fc7, #8b5cf6);
    border-radius: 14px; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; color: #fff; font-size: 24px; font-weight: 700;
}

.abonnement-info { flex: 1; }
.abonnement-name {
    font-size: 18px; font-weight: 700; color: #1e1e2e; margin-bottom: 4px;
}
.abonnement-location {
    font-size: 13px; color: #7a7a8e; margin-bottom: 8px;
}

.abonnement-stats {
    display: inline-flex; align-items: center; gap: 4px;
    background: #eef2ff; color: #5b5fc7;
    border-radius: 999px; padding: 4px 12px;
    font-size: 11px; font-weight: 600;
}

.abonnement-details {
    font-size: 13px; color: #555; line-height: 1.5; margin-bottom: 14px;
}

.abonnement-footer {
    display: flex; gap: 10px; padding-top: 16px; border-top: 1px solid #f2f2f5;
}

.btn-voir-offres {
    flex: 1;
    display: inline-flex; align-items: center; justify-content: center;
    background: #5b5fc7; color: #fff; border: none; border-radius: 10px;
    padding: 10px 16px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-voir-offres:hover { background: #4a4eb5; }

.btn-unsubscribe {
    display: inline-flex; align-items: center; justify-content: center;
    border: 1.5px solid #dc2626; color: #dc2626; background: #fff; border-radius: 10px;
    padding: 10px 16px; font-size: 13px; font-weight: 600; cursor: pointer;
}
.btn-unsubscribe:hover { background: #fef2f2; }

.date-abonnement {
    font-size: 12px; color: #9ca3af; margin-top: 8px;
}

.empty-state {
    text-align: center; padding: 80px 20px;
    background: #fff; border: 1px solid #eee; border-radius: 16px;
}
.empty-state h3 { font-size: 20px; font-weight: 600; color: #1e1e2e; margin-bottom: 10px; }
.empty-state p { font-size: 14px; color: #7a7a8e; margin-bottom: 28px; }
.btn-primary {
    display: inline-flex; align-items: center; justify-content: center;
    background: #5b5fc7; color: #fff; border: none; border-radius: 10px;
    padding: 12px 24px; font-size: 14px; font-weight: 600; cursor: pointer;
}
.btn-primary:hover { background: #4a4eb5; }

.warning-badge {
    display: inline-block; background: #fff3cd; color: #856404;
    padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;
    margin-left: 8px;
}

@media (max-width: 1100px) {
    .abonnements-grid { grid-template-columns: 1fr; }
}
</style>
</style>
    <link rel="stylesheet" href="../../assets/css/tailwind.min.css">
</head>
</head>
<body>

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

<div class="page">
    <a href="dashboard.php" class="back-link">← Retour</a>

    <div class="page-header">
        <div>
            <h1 class="page-title">
                Mes Abonnements 
                <span class="count-badge"><?= $total_abonnements ?></span>
            </h1>
            <p class="page-subtitle">Entreprises et recruteurs que vous suivez</p>
        </div>
        <button class="btn-browse" onclick="window.location.href='../../entreprises.php'">
            Découvrir des entreprises
        </button>
    </div>

    <?php if (empty($abonnements)): ?>
        <div class="empty-state">
            <h3>Aucun abonnement</h3>
            <p>Suivez des entreprises pour recevoir leurs nouvelles offres en priorité</p>
            <button class="btn-primary" onclick="window.location.href='../../entreprises.php'">
                Explorer les entreprises
            </button>
        </div>
    <?php else: ?>
        <div class="abonnements-grid">
            <?php foreach ($abonnements as $a): 
                $date_abonnement = !empty($a['date_abonnement']) ? date('d/m/Y', strtotime($a['date_abonnement'])) : '';
                
                // Gérer les cas où nom_entreprise est null
                $nom_entreprise = $a['nom_entreprise'] ?? 'Entreprise #' . $a['id_recruteur'];
                $ville = $a['ville_entreprise'] ?? 'Non spécifié';
                
                // Générer les initiales
                $initiales = '';
                $words = explode(' ', $nom_entreprise);
                if (count($words) >= 2) {
                    $initiales = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
                } else {
                    $initiales = strtoupper(substr($nom_entreprise, 0, 2));
                }
            ?>
            <div class="abonnement-card">
                <div class="abonnement-header">
                    <div class="abonnement-logo">
                        <?= $initiales ?>
                    </div>
                    <div class="abonnement-info">
                        <div class="abonnement-name">
                            <?= htmlspecialchars($nom_entreprise) ?>
                            <?php if (empty($a['nom_entreprise'])): ?>
                                <span class="warning-badge">Info manquante</span>
                            <?php endif; ?>
                        </div>
                        <div class="abonnement-location"><?= htmlspecialchars($ville) ?></div>
                        <div class="abonnement-stats">
                            <?= $a['offres_count'] ?? 0 ?> offre<?= ($a['offres_count'] ?? 0) > 1 ? 's' : '' ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($a['adresse_professionnelle']) || !empty($a['nombre_employes'])): ?>
                <div class="abonnement-details">
                    <?php if (!empty($a['adresse_professionnelle'])): ?>
                    📍 <?= htmlspecialchars($a['adresse_professionnelle']) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($a['nombre_employes'])): ?>
                    👥 <?= htmlspecialchars($a['nombre_employes']) ?> employés
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="abonnement-footer">
                    <button class="btn-voir-offres" onclick="window.location.href='../../offres.php?recruteur=<?= $a['recruteur_id'] ?? 0 ?>'">
                        Voir les offres
                    </button>
                    <a href="?unsubscribe=<?= $a['id_abonnement'] ?>" class="btn-unsubscribe" onclick="return confirm('Se désabonner de <?= htmlspecialchars($nom_entreprise) ?> ?')">
                        ×
                    </a>
                </div>

                <?php if ($date_abonnement): ?>
                    <div class="date-abonnement">Abonné depuis le <?= $date_abonnement ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>