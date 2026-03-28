<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'recruteur') {
    header('Location: ../../auth/login.php');
    exit();
}

require_once '../../config/db.php';

$id_recruteur = $_SESSION['user_id'];

// Récupérer les infos du recruteur
try {
    $stmt = $pdo->prepare("SELECT nom_entreprise FROM recruteurs WHERE id_recruteur = :id");
    $stmt->execute([':id' => $id_recruteur]);
    $recruteur = $stmt->fetch();
} catch (PDOException $e) {
    $recruteur = ['nom_entreprise' => 'Entreprise'];
}

$filtre_search = $_GET['search'] ?? '';

try {
    $query = "
        SELECT 
            a.*,
            c.id_candidat,
            c.nom,
            c.prenom,
            c.email,
            c.telephone,
            c.niveau_etudes,
            c.photo_profil
        FROM abonnements a
        INNER JOIN candidats c ON a.id_candidat = c.id_candidat
        WHERE a.id_recruteur = :id_recruteur
    ";
    
    if ($filtre_search) {
        $query .= " AND (c.nom LIKE :search OR c.prenom LIKE :search OR c.email LIKE :search)";
    }
    
    $query .= " ORDER BY a.date_abonnement DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id_recruteur', $id_recruteur, PDO::PARAM_INT);
    
    if ($filtre_search) {
        $search_param = '%' . $filtre_search . '%';
        $stmt->bindParam(':search', $search_param);
    }
    
    $stmt->execute();
    $abonnes = $stmt->fetchAll();
} catch (PDOException $e) {
    $abonnes = [];
}

// Statistiques
$total_abonnes = count($abonnes);
$abonnes_cette_semaine = count(array_filter($abonnes, fn($a) => 
    strtotime($a['date_abonnement']) >= strtotime('-7 days')
));

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
    <title>Mes abonnés - NextCareer</title>
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
                    <a href="../../index.php" class="text-gray-600 hover:text-primary transition font-medium">Accueil</a>
                    <a href="../../offres.php" class="text-gray-600 hover:text-primary transition font-medium">Offres d'emploi</a>
                    <a href="../../entreprises.php" class="text-gray-600 hover:text-primary transition font-medium">Entreprises</a>
                </div>
                
                <!-- Right Side - User Info -->
                <div class="flex items-center space-x-4">
                    <div class="hidden sm:flex items-center space-x-3 border-r border-gray-300 pr-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #E8F7F0;">
                            <svg class="w-6 h-6" style="color: #50C878;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div class="text-sm">
                            <a href="/pages/recruteur/dashboard.php" class="text-gray-900 hover:text-primary transition font-medium"><?= htmlspecialchars($recruteur['nom_entreprise'] ?? 'Entreprise') ?></a>
                            <p class="text-xs text-gray-500">Recruteur</p>
                        </div>
                    </div>
    
                    <a href="../../auth/logout.php" class="px-6 py-2 rounded-full text-white font-medium text-sm transition" style="background: linear-gradient(135deg, #50C878 0%, #2EAD5A 100%);" onmouseover="this.style.background='linear-gradient(135deg, #2EAD5A 0%, #1E8B44 100%)'" onmouseout="this.style.background='linear-gradient(135deg, #50C878 0%, #2EAD5A 100%)'">
                        Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Back link -->
        <a href="dashboard.php" class="inline-flex items-center gap-2 hover:opacity-80 mb-6 text-sm font-medium" style="color: #50C878;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Retour au tableau de bord
        </a>

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Mes abonnés</h1>
            <p class="text-gray-600">Candidats abonnés à votre entreprise pour recevoir vos offres</p>
        </div>

        <!-- Statistiques simplifiées -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl p-6 border-2 card-hover" style="border-color: #50C878;">
                <p class="text-sm text-gray-600 mb-1">Total d'abonnés</p>
                <p class="text-4xl font-bold" style="color: #50C878;"><?= $total_abonnes ?></p>
            </div>
            <div class="bg-white rounded-xl p-6 border border-gray-200 card-hover">
                <p class="text-sm text-gray-600 mb-1">Cette semaine</p>
                <p class="text-4xl font-bold text-gray-900"><?= $abonnes_cette_semaine ?></p>
            </div>
        </div>

        <!-- Recherche -->
        <div class="bg-white rounded-xl p-6 border border-gray-200 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Rechercher un abonné</h3>
            <form method="GET" class="flex gap-3">
                <div class="flex-1">
                    <input 
                        type="text" 
                        name="search"
                        value="<?= htmlspecialchars($filtre_search) ?>"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                        placeholder="Nom, prénom ou email..."
                        onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                        onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                    >
                </div>
                <button type="submit" class="px-6 py-3 text-white rounded-xl transition font-medium" style="background: linear-gradient(135deg, #50C878 0%, #2EAD5A 100%);" onmouseover="this.style.background='linear-gradient(135deg, #2EAD5A 0%, #1E8B44 100%)'" onmouseout="this.style.background='linear-gradient(135deg, #50C878 0%, #2EAD5A 100%)'">
                    Rechercher
                </button>
                <?php if ($filtre_search): ?>
                    <a href="abonnes.php" class="px-6 py-3 bg-white border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                        Réinitialiser
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Liste des abonnés -->
        <?php if (empty($abonnes)): ?>
            <div class="bg-white rounded-xl p-12 border border-gray-200 text-center">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Aucun abonné</h3>
                <p class="text-gray-600 mb-4">
                    <?= $filtre_search ? 'Aucun résultat pour cette recherche' : 'Aucun candidat ne s\'est encore abonné à votre entreprise' ?>
                </p>
                <?php if (!$filtre_search): ?>
                    <p class="text-sm text-gray-500">
                        💡 Astuce : Publiez des offres attractives pour attirer des abonnés
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($abonnes as $abonne): ?>
                    <div class="bg-white rounded-xl p-6 border border-gray-200 transition card-hover" onmouseover="this.style.borderColor='#50C878'" onmouseout="this.style.borderColor='#e5e7eb'">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-4 flex-1">
                                <!-- Avatar -->
                                <div class="w-14 h-14 rounded-full flex items-center justify-center flex-shrink-0" style="background-color: #E8F7F0;">
                                    <?php if (!empty($abonne['photo_profil'])): ?>
                                        <img src="<?= htmlspecialchars($abonne['photo_profil']) ?>" 
                                             alt="Photo" 
                                             class="w-14 h-14 rounded-full object-cover"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <span class="hidden text-xl font-bold" style="color: #50C878;">
                                            <?= strtoupper(substr($abonne['prenom'] ?? 'U', 0, 1) . substr($abonne['nom'] ?? 'N', 0, 1)) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xl font-bold" style="color: #50C878;">
                                            <?= strtoupper(substr($abonne['prenom'] ?? 'U', 0, 1) . substr($abonne['nom'] ?? 'N', 0, 1)) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-bold text-gray-900 mb-2">
                                        <?= htmlspecialchars(($abonne['prenom'] ?? 'Prénom') . ' ' . ($abonne['nom'] ?? 'Nom')) ?>
                                    </h3>
                                    
                                    <div class="space-y-1.5">
                                        <p class="text-sm text-gray-600 truncate">
                                            📧 <?= htmlspecialchars($abonne['email'] ?? 'Email non disponible') ?>
                                        </p>
                                        <?php if (!empty($abonne['telephone'])): ?>
                                            <p class="text-sm text-gray-600">
                                                📱 <?= htmlspecialchars($abonne['telephone']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($abonne['niveau_etudes'])): ?>
                                            <p class="text-sm text-gray-600">
                                                🎓 <?= htmlspecialchars($abonne['niveau_etudes']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <p class="text-xs text-gray-500 mt-3">
                                        Abonné depuis <?= tempsEcoule($abonne['date_abonnement']) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col gap-2 ml-4">
                                <a href="mailto:<?= htmlspecialchars($abonne['email']) ?>" 
                                   class="px-5 py-2.5 text-white rounded-lg transition text-sm font-medium text-center whitespace-nowrap"
                                   style="background: linear-gradient(135deg, #50C878 0%, #2EAD5A 100%);"
                                   onmouseover="this.style.background='linear-gradient(135deg, #2EAD5A 0%, #1E8B44 100%)'"
                                   onmouseout="this.style.background='linear-gradient(135deg, #50C878 0%, #2EAD5A 100%)'">
                                    Contacter
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Info -->
            <div class="mt-6 rounded-xl p-4" style="background-color: #E8F7F0; border: 1px solid #A8E4C8;">
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5" style="background-color: #50C878;">
                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium" style="color: #1E8B44;">
                            Les candidats abonnés reçoivent une notification lorsque vous publiez une nouvelle offre d'emploi.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>