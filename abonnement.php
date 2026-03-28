<?php
session_start();

require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

$user_subscription = null;
$current_plan = 'gratuit';

try {
    if ($_SESSION['user_type'] === 'candidat') {
        $stmt = $pdo->prepare("
            SELECT a.*, c.id_candidat 
            FROM abonnement a
            JOIN candidats c ON a.id_candidat = c.id_candidat
            WHERE c.id_user = :id_user
            ORDER BY a.date_abonnement DESC LIMIT 1
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT a.*, r.id_recruteur 
            FROM abonnement a
            JOIN recruteurs r ON a.id_recruteur = r.id_recruteur
            WHERE r.id_user = :id_user
            ORDER BY a.date_abonnement DESC LIMIT 1
        ");
    }
    $stmt->execute([':id_user' => $_SESSION['user_id']]);
    $user_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_subscription) {
        $current_plan = $user_subscription['type_abonnement'] ?? 'gratuit';
    }
} catch (PDOException $e) {}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type_abonnement'])) {
    $type_abo = $_POST['type_abonnement'];
    try {
        if ($_SESSION['user_type'] === 'candidat') {
            $stmt = $pdo->prepare("SELECT id_candidat FROM candidats WHERE id_user = :id_user");
            $stmt->execute([':id_user' => $_SESSION['user_id']]);
            $candidat = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($candidat) {
                $stmt = $pdo->prepare("INSERT INTO abonnement (id_candidat, type_abonnement, date_abonnement) VALUES (:id_candidat, :type_abo, NOW())");
                $stmt->execute([':id_candidat' => $candidat['id_candidat'], ':type_abo' => $type_abo]);
                $current_plan = $type_abo;
                $message = 'success';
            }
        } else {
            $stmt = $pdo->prepare("SELECT id_recruteur FROM recruteurs WHERE id_user = :id_user");
            $stmt->execute([':id_user' => $_SESSION['user_id']]);
            $recruteur = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($recruteur) {
                $stmt = $pdo->prepare("INSERT INTO abonnement (id_recruteur, type_abonnement, date_abonnement) VALUES (:id_recruteur, :type_abo, NOW())");
                $stmt->execute([':id_recruteur' => $recruteur['id_recruteur'], ':type_abo' => $type_abo]);
                $current_plan = $type_abo;
                $message = 'success';
            }
        }
    } catch (PDOException $e) {
        $message = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonnement - NextCareer</title>
    <link rel="stylesheet" href="assets/css/tailwind.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }

        .plan-card { transition: transform 0.25s ease, box-shadow 0.25s ease; }
        .plan-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(102,126,234,0.15); }
        .plan-active { border-color: #667eea !important; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }

        .check-icon { color: #667eea; }
        .cross-icon { color: #d1d5db; }

        .hero-pattern {
            background-color: #1e1e2e;
            background-image:
                radial-gradient(ellipse at 20% 50%, rgba(102,126,234,0.18) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(118,75,162,0.12) 0%, transparent 50%);
        }

        .premium-card {
            background: linear-gradient(145deg, #667eea 0%, #764ba2 100%);
        }

        details summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="bg-gray-50">

<!-- ═══════════════ NAVBAR ═══════════════ -->
<nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <a href="index.php">
                <div style="font-family:'Inter',sans-serif;font-size:1.75rem;font-weight:700;color:#1a202c;position:relative;display:inline-block;">
                    Next<span style="color:#667eea;">Career</span>
                    <div style="position:absolute;bottom:2px;left:0;width:100%;height:3px;background:linear-gradient(90deg,#667eea 0%,#764ba2 100%);border-radius:2px;"></div>
                </div>
            </a>

            <!-- Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="index.php" class="text-gray-600 hover:text-indigo-600 transition font-medium text-sm">Accueil</a>
                <a href="offres.php" class="text-gray-600 hover:text-indigo-600 transition font-medium text-sm">Offres d'emploi</a>
                <a href="entreprises.php" class="text-gray-600 hover:text-indigo-600 transition font-medium text-sm">Entreprises</a>
                <a href="abonnement.php" class="text-indigo-600 font-semibold text-sm border-b-2 border-indigo-600 pb-1">Abonnement</a>
            </div>

            <!-- User -->
            <div class="flex items-center space-x-4">
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
                    $bg_color = 'bg-gray-100';
                    $text_color = 'text-gray-700';
                    $btn_color = 'bg-gray-700 hover:bg-gray-800';
                }
                ?>

                <div class="hidden sm:flex items-center space-x-3 border-r border-gray-300 pr-4">
                    <div class="w-10 h-10 <?= $bg_color ?> rounded-full flex items-center justify-center">
                        <a href="/pages/candidat/dashboard.php" <span class="text-lg font-bold <?= $text_color ?>"><?= $initiales ?></a>
                    </div>
                    <div class="text-sm">
                        <p class="font-medium text-gray-900"><?= $nom_complet ?></p>
                        <p class="text-xs text-gray-500"><?= ucfirst($user_type) ?></p>
                    </div>
                </div>

                <a href="auth/logout.php" class="<?= $btn_color ?> text-white px-6 py-2 rounded-full transition font-medium text-sm">
                    Déconnexion
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ═══════════════ HERO ═══════════════ -->
<section class="hero-pattern py-20 text-center relative overflow-hidden">
    <div class="absolute top-8 left-16 w-32 h-32 rounded-full border border-indigo-500/10"></div>
    <div class="absolute bottom-8 right-20 w-48 h-48 rounded-full border border-purple-500/10"></div>
    <div class="absolute top-16 right-32 w-16 h-16 rounded-full bg-indigo-500/5"></div>

    <div class="max-w-2xl mx-auto px-4 relative z-10">
        <span class="inline-block bg-indigo-500/10 text-indigo-300 text-xs font-semibold uppercase tracking-widest px-4 py-2 rounded-full mb-6 border border-indigo-500/20">
            Nos offres
        </span>
        <h1 class="text-4xl md:text-5xl font-bold text-white mb-5 leading-tight">
            Choisissez votre
            <span style="background:linear-gradient(90deg,#667eea,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"> plan</span>
        </h1>
        <p class="text-gray-400 text-lg leading-relaxed">
            Accédez aux meilleures fonctionnalités pour booster votre carrière ou vos recrutements.
        </p>

        <div class="flex justify-center gap-10 mt-10">
            <div class="text-center">
                <p class="text-2xl font-bold text-white">500+</p>
                <p class="text-xs text-gray-500 mt-1">Entreprises</p>
            </div>
            <div class="w-px bg-gray-700"></div>
            <div class="text-center">
                <p class="text-2xl font-bold text-white">12k+</p>
                <p class="text-xs text-gray-500 mt-1">Candidats</p>
            </div>
            <div class="w-px bg-gray-700"></div>
            <div class="text-center">
                <p class="text-2xl font-bold text-white">3k+</p>
                <p class="text-xs text-gray-500 mt-1">Offres actives</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ CONTENU ═══════════════ -->
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

    <!-- Notifications -->
    <?php if ($message === 'success'): ?>
        <div class="mb-8 flex items-center gap-3 px-5 py-4 rounded-xl text-sm font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Votre abonnement a été mis à jour avec succès.
        </div>
    <?php elseif ($message === 'error'): ?>
        <div class="mb-8 flex items-center gap-3 px-5 py-4 rounded-xl text-sm font-medium bg-red-50 text-red-700 border border-red-200">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Une erreur est survenue. Veuillez réessayer.
        </div>
    <?php endif; ?>

    <!-- Plan actuel -->
    <?php if (!empty($user_subscription)): ?>
        <div class="mb-10 flex items-center gap-4 px-6 py-4 bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Abonnement actuel</p>
                <p class="font-semibold text-gray-900">
                    Plan <span class="text-indigo-600"><?= ucfirst($current_plan) ?></span>
                    <?php if (!empty($user_subscription['date_abonnement'])): ?>
                        <span class="text-gray-400 font-normal text-sm ml-2">depuis le <?= date('d/m/Y', strtotime($user_subscription['date_abonnement'])) ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Grille des plans -->
    <div class="grid md:grid-cols-3 gap-6 mb-16">

        <!-- ── Plan Gratuit ── -->
        <div class="plan-card bg-white rounded-2xl border-2 border-gray-200 p-8 flex flex-col <?= $current_plan === 'gratuit' ? 'plan-active' : '' ?>">
            <div class="mb-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">Démarrer</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Gratuit</h3>
                <div class="flex items-end gap-1 mb-1">
                    <span class="text-5xl font-extrabold text-gray-900">0</span>
                    <span class="text-gray-400 mb-2 text-base font-medium">FCFA</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">Accès permanent · Sans carte</p>
            </div>

            <ul class="space-y-3 mb-8 flex-1">
                <?php foreach ([
                    ['ok', "Accès aux offres d'emploi"],
                    ['ok', 'Recherche basique'],
                    ['ok', 'Profil candidat simple'],
                    ['no', 'Favoris prioritaires'],
                    ['no', 'Support prioritaire'],
                ] as [$type, $label]): ?>
                <li class="flex items-center gap-3 text-sm <?= $type === 'ok' ? 'text-gray-700' : 'text-gray-400' ?>">
                    <?php if ($type === 'ok'): ?>
                        <svg class="w-4 h-4 flex-shrink-0 check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <?php else: ?>
                        <svg class="w-4 h-4 flex-shrink-0 cross-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <?php endif; ?>
                    <?= $label ?>
                </li>
                <?php endforeach; ?>
            </ul>

            <form method="POST">
                <input type="hidden" name="type_abonnement" value="gratuit">
                <?php if ($current_plan === 'gratuit'): ?>
                    <button disabled class="w-full py-3 rounded-xl text-sm font-semibold border-2 border-indigo-200 text-indigo-400 bg-indigo-50 cursor-not-allowed">
                        ✓ Plan actif
                    </button>
                <?php else: ?>
                    <button type="submit" class="w-full py-3 rounded-xl text-sm font-semibold border-2 border-gray-200 text-gray-600 hover:border-gray-400 hover:text-gray-800 transition">
                        Choisir ce plan
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <!-- ── Plan Premium (mis en avant) ── -->
        <div class="plan-card premium-card rounded-2xl border-2 border-indigo-500 p-8 flex flex-col relative shadow-xl <?= $current_plan === 'premium' ? 'ring-4 ring-indigo-300/40' : '' ?>">
            <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                <span class="bg-gray-900 text-white text-xs font-bold px-5 py-2 rounded-full uppercase tracking-widest shadow-lg">⭐ Populaire</span>
            </div>

            <div class="mb-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-200 mb-2">Le plus choisi</p>
                <h3 class="text-2xl font-bold text-white mb-4">Premium</h3>
                <div class="flex items-end gap-1 mb-1">
                    <span class="text-5xl font-extrabold text-white">9 999</span>
                    <span class="text-indigo-200 mb-2 text-base font-medium">FCFA</span>
                </div>
                <p class="text-xs text-indigo-200 mt-1">Par mois · Résiliable à tout moment</p>
            </div>

            <ul class="space-y-3 mb-8 flex-1">
                <?php foreach ([
                    'Tout du plan Gratuit',
                    'Favoris illimités',
                    'Recherche avancée',
                    'CV mis en avant',
                    'Support prioritaire'
                ] as $f): ?>
                    <li class="flex items-center gap-3 text-sm text-white">
                        <svg class="w-4 h-4 flex-shrink-0 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <?= $f ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="POST">
                <input type="hidden" name="type_abonnement" value="premium">
                <?php if ($current_plan === 'premium'): ?>
                    <button disabled class="w-full py-3 rounded-xl text-sm font-semibold bg-white/20 text-white cursor-not-allowed border border-white/30">
                        ✓ Plan actif
                    </button>
                <?php else: ?>
                    <button type="submit" class="w-full py-3 rounded-xl text-sm font-bold bg-white text-indigo-700 hover:bg-indigo-50 transition shadow-md">
                        Choisir ce plan →
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <!-- ── Plan Entreprise ── -->
        <div class="plan-card bg-white rounded-2xl border-2 border-gray-200 p-8 flex flex-col <?= $current_plan === 'entreprise' ? 'plan-active' : '' ?>">
            <div class="mb-6">
                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-2">Recruteurs</p>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Entreprise</h3>
                <div class="flex items-end gap-1 mb-1">
                    <span class="text-5xl font-extrabold text-gray-900">49 999</span>
                    <span class="text-gray-400 mb-2 text-base font-medium">FCFA</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">Par mois · Facturation mensuelle</p>
            </div>

            <ul class="space-y-3 mb-8 flex-1">
                <?php foreach ([
                    "Offres d'emploi illimitées",
                    'Analytics détaillées',
                    'Accès candidats premium',
                    'Support dédié 24/7',
                    'Branding personnalisé'
                ] as $f): ?>
                    <li class="flex items-center gap-3 text-sm text-gray-700">
                        <svg class="w-4 h-4 flex-shrink-0 check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <?= $f ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="POST">
                <input type="hidden" name="type_abonnement" value="entreprise">
                <?php if ($current_plan === 'entreprise'): ?>
                    <button disabled class="w-full py-3 rounded-xl text-sm font-semibold border-2 border-indigo-200 text-indigo-400 bg-indigo-50 cursor-not-allowed">
                        ✓ Plan actif
                    </button>
                <?php else: ?>
                    <button type="submit" class="w-full py-3 rounded-xl text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 transition">
                        Choisir ce plan
                    </button>
                <?php endif; ?>
            </form>
        </div>

    </div>

    <!-- Bandeau garanties -->
    <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-16 grid md:grid-cols-3 gap-6 shadow-sm">
        <?php foreach ([
            ['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'Paiement sécurisé', 'Vos données sont protégées et chiffrées'],
            ['M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15', 'Résiliation facile', 'Annulez à tout moment, sans frais cachés'],
            ['M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z', 'Support réactif', 'Notre équipe répond en moins de 24h'],
        ] as [$icon, $title, $desc]): ?>
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
                </svg>
            </div>
            <div>
                <p class="font-semibold text-gray-900 text-sm"><?= $title ?></p>
                <p class="text-xs text-gray-500 mt-0.5"><?= $desc ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ═══ FAQ ═══ -->
    <div class="max-w-3xl mx-auto">
        <h2 class="text-2xl font-bold text-gray-900 mb-2 text-center">Questions fréquentes</h2>
        <p class="text-gray-500 text-center mb-10 text-sm">Tout ce que vous devez savoir sur nos abonnements</p>

        <div class="space-y-3">
            <?php foreach ([
                ['Quand serai-je facturé ?', 'Vous serez facturé immédiatement après sélection du plan, puis chaque 30 jours pour le renouvellement automatique.'],
                ['Puis-je changer de plan à tout moment ?', 'Oui, vous pouvez changer de plan à tout moment. Les modifications prennent effet immédiatement.'],
                ['Puis-je annuler mon abonnement ?', 'Oui, vous pouvez annuler à tout moment. Votre accès se terminera à la fin de votre période de facturation en cours.'],
                ['Quels modes de paiement acceptez-vous ?', 'Nous acceptons les paiements par Mobile Money (Orange Money, Moov Money), ainsi que les virements bancaires pour les plans Entreprise.'],
                ["Besoin d'aide ?", 'Contactez notre équipe à support@nextcareer.bf ou appelez le +226 05 64 53 74. Nous répondons en moins de 24h.'],
            ] as [$q, $a]): ?>
            <details class="bg-white rounded-xl border border-gray-200 group overflow-hidden">
                <summary class="flex justify-between items-center px-6 py-4 cursor-pointer list-none font-medium text-gray-900 text-sm hover:bg-gray-50 transition">
                    <?= $q ?>
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </summary>
                <div class="px-6 pb-5 pt-2 text-sm text-gray-600 border-t border-gray-100"><?= $a ?></div>
            </details>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- ═══════════════ FOOTER ═══════════════ -->
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