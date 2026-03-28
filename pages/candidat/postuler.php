<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Vérifier que l'utilisateur est connecté et est un candidat
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'candidat') {
    header('Location: /emploi_platform/auth/login.php');
    exit();
}

// Vérifier l'ID de l'offre
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: /emploi_platform/offres.php');
    exit();
}

$id_offre = (int) $_GET['id'];

// Récupérer les détails de l'offre et du recruteur
try {
    $stmt = $pdo->prepare("
        SELECT o.*, r.nom_entreprise, s.nom_secteur
        FROM offres_emploi o
        LEFT JOIN recruteurs r ON o.id_recruteur = r.id_recruteur
        LEFT JOIN secteurs s ON o.id_secteur = s.id_secteur
        WHERE o.id_offre = :id_offre
    ");
    $stmt->execute([':id_offre' => $id_offre]);
    $offre = $stmt->fetch();
    
    if (!$offre) {
        header('Location: /emploi_platform/offres.php');
        exit();
    }
} catch (PDOException $e) {
    die('Erreur: ' . $e->getMessage());
}

// Récupérer les infos du candidat
try {
    $stmt_candidat = $pdo->prepare("
        SELECT c.nom, c.prenom, c.ville, c.cv_numerique, u.email, u.tel_user
        FROM candidats c 
        JOIN utilisateurs u ON c.id_user = u.id_user 
        WHERE c.id_candidat = :id
    ");
    $stmt_candidat->execute([':id' => $_SESSION['user_id']]);
    $candidat = $stmt_candidat->fetch();
} catch (PDOException $e) {
    $candidat = null;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lettre_motivation = isset($_POST['lettre_motivation']) ? trim($_POST['lettre_motivation']) : '';
    $id_candidat = $_SESSION['user_id'];

    // Validation
    if (empty($lettre_motivation)) {
        $errors[] = "La lettre de motivation est obligatoire.";
    } elseif (strlen($lettre_motivation) < 100) {
        $errors[] = "La lettre de motivation doit contenir au moins 100 caractères.";
    }

    // Vérifier si le candidat a déjà postulé
    try {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM candidatures WHERE id_candidat = :id_candidat AND id_offre = :id_offre");
        $stmt_check->execute([':id_candidat' => $id_candidat, ':id_offre' => $id_offre]);
        if ($stmt_check->fetchColumn() > 0) {
            $errors[] = "Vous avez déjà postulé à cette offre.";
        }
    } catch (PDOException $e) {
        $errors[] = "Erreur lors de la vérification de la candidature.";
    }

    if (empty($errors)) {
        // Gestion du CV upload
        $cv_db_path = null;
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['cv'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $allowed_ext = ['pdf','doc','docx'];
                $max_size = 5 * 1024 * 1024;
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed_ext)) {
                    $errors[] = 'Type de fichier non autorisé. Formats acceptés : PDF, DOC, DOCX.';
                } elseif ($file['size'] > $max_size) {
                    $errors[] = 'Le fichier est trop volumineux (maximum 5 MB).';
                } else {
                    $upload_dir = __DIR__ . '/uploads/cv/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $new_name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $dest = $upload_dir . $new_name;
                    
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $cv_db_path = 'pages/candidat/uploads/cv/' . $new_name;
                    } else {
                        $errors[] = 'Erreur lors de l\'upload du fichier.';
                    }
                }
            } else {
                $errors[] = 'Erreur lors de l\'upload du fichier.';
            }
        }

        if (empty($errors)) {
            try {
                $col_check = $pdo->prepare("SHOW COLUMNS FROM candidatures LIKE 'cv_path'");
                $col_check->execute();
                $has_cv_column = $col_check->fetch() !== false;

                if ($has_cv_column) {
                    $stmt_ins = $pdo->prepare("INSERT INTO candidatures (id_candidat, id_offre, lettre_motivation, cv_path, date_candidature, statut) VALUES (:id_candidat, :id_offre, :lettre, :cv_path, NOW(), 'En attente')");
                    $stmt_ins->execute([
                        ':id_candidat' => $id_candidat,
                        ':id_offre' => $id_offre,
                        ':lettre' => $lettre_motivation,
                        ':cv_path' => $cv_db_path
                    ]);
                } else {
                    $stmt_ins = $pdo->prepare("INSERT INTO candidatures (id_candidat, id_offre, lettre_motivation, date_candidature, statut) VALUES (:id_candidat, :id_offre, :lettre, NOW(), 'En attente')");
                    $stmt_ins->execute([
                        ':id_candidat' => $id_candidat,
                        ':id_offre' => $id_offre,
                        ':lettre' => $lettre_motivation
                    ]);
                }
                $success = true;
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de l'enregistrement de la candidature.";
                if (!empty($cv_db_path)) {
                    $maybe = __DIR__ . '/../../' . $cv_db_path;
                    if (file_exists($maybe)) @unlink($maybe);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Postuler - <?= htmlspecialchars($offre['titre_emploi'] ?? $offre['titre'] ?? 'Offre') ?> | NextCareer</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { 
    font-family: 'Inter', sans-serif; 
    background: #f5f5fa;
    color: #1e1e2e; 
    min-height: 100vh;
}
a { text-decoration: none; color: inherit; }

/* Navbar */
.navbar {
    background: #fff;
    border-bottom: 1px solid #eee;
    padding: 0 48px;
    height: 62px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.nav-logo {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1a202c;
}

.nav-logo span { color: #667eea; }

/* Page Container */
.page {
    max-width: 900px;
    margin: 0 auto;
    padding: 40px 24px 80px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #667eea;
    font-weight: 500;
    margin-bottom: 24px;
    background: #fff;
    padding: 8px 16px;
    border-radius: 20px;
    transition: all 0.2s;
    border: 1px solid #e5e7eb;
}

.back-link:hover {
    background: #f8f9ff;
    border-color: #667eea;
    transform: translateX(-4px);
}

.back-link svg {
    width: 14px;
    height: 14px;
}

/* Elegant Header Card with Gradient */
.header-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 24px;
    padding: 40px;
    margin-bottom: 24px;
    color: #fff;
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.2);
    position: relative;
    overflow: hidden;
}

.header-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0) 100%);
    pointer-events: none;
}

.header-content {
    display: flex;
    gap: 24px;
    align-items: flex-start;
    position: relative;
    z-index: 1;
}

.company-logo {
    width: 90px;
    height: 90px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    position: relative;
}

.company-logo::after {
    content: '';
    position: absolute;
    inset: -2px;
    background: linear-gradient(135deg, rgba(255,255,255,0.5), rgba(255,255,255,0.1));
    border-radius: 20px;
    z-index: -1;
}

.company-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 16px;
    border-radius: 20px;
}

.company-initials {
    font-size: 32px;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.header-info {
    flex: 1;
}

.job-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 12px;
}

.job-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
    text-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.company-name {
    font-size: 16px;
    opacity: 0.95;
    margin-bottom: 16px;
    font-weight: 500;
}

.job-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 14px;
}

.meta-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 6px 12px;
    border-radius: 20px;
}

.meta-item svg {
    width: 14px;
    height: 14px;
}

/* Form Card */
.form-card {
    background: #fff;
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}

.form-title {
    font-size: 22px;
    font-weight: 700;
    color: #1e1e2e;
    margin-bottom: 8px;
}

.form-subtitle {
    font-size: 14px;
    color: #7a7a8e;
    margin-bottom: 32px;
}

/* Alert Messages */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    margin-top: 2px;
}

/* Profile Summary */
.profile-summary {
    background: linear-gradient(135deg, #f8f9ff 0%, #f3f4ff 100%);
    border: 1px solid #e8e9ff;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 32px;
}

.profile-summary h3 {
    font-size: 15px;
    font-weight: 600;
    color: #1e1e2e;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.profile-summary h3 svg {
    width: 18px;
    height: 18px;
    color: #667eea;
}

.profile-info {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.info-item {
    font-size: 13px;
    color: #555;
}

.info-label {
    font-weight: 600;
    color: #1e1e2e;
    display: block;
    margin-bottom: 4px;
}

/* Form Fields */
.form-field {
    margin-bottom: 28px;
}

.form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #1e1e2e;
    margin-bottom: 10px;
}

.required {
    color: #dc2626;
    margin-left: 2px;
}

.form-textarea {
    width: 100%;
    padding: 16px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    min-height: 220px;
    transition: all 0.2s;
    line-height: 1.6;
}

.form-textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.char-count {
    font-size: 12px;
    color: #9ca3af;
    text-align: right;
    margin-top: 8px;
}

/* File Upload Elegant */
.file-upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 16px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    background: linear-gradient(135deg, #fafbff 0%, #f9f9fc 100%);
}

.file-upload-area:hover {
    border-color: #667eea;
    background: linear-gradient(135deg, #f8f9ff 0%, #f3f4ff 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
}

.upload-icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 16px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.upload-icon svg {
    width: 26px;
    height: 26px;
    color: #fff;
}

.upload-title {
    font-size: 15px;
    font-weight: 600;
    color: #1e1e2e;
    margin-bottom: 6px;
}

.upload-subtitle {
    font-size: 13px;
    color: #7a7a8e;
}

.file-input {
    display: none;
}

.file-selected {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 14px 18px;
    margin-top: 12px;
}

.file-selected svg {
    width: 20px;
    height: 20px;
    color: #16a34a;
    flex-shrink: 0;
}

.file-name {
    flex: 1;
    font-size: 14px;
    color: #166534;
    font-weight: 500;
}

.file-remove {
    color: #dc2626;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 6px;
    transition: all 0.2s;
}

.file-remove:hover {
    background: rgba(220, 38, 38, 0.1);
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 32px;
}

.btn-submit {
    flex: 1;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 16px 32px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-cancel {
    padding: 16px 32px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    background: #fff;
    color: #6b7280;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel:hover {
    background: #f9fafb;
    border-color: #d1d5db;
}

/* Success State */
.success-state {
    text-align: center;
    padding: 60px 24px;
}

.success-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 28px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
    animation: successPop 0.5s ease;
}

@keyframes successPop {
    0% { transform: scale(0); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.success-icon svg {
    width: 50px;
    height: 50px;
    color: #fff;
}

.success-title {
    font-size: 26px;
    font-weight: 700;
    color: #1e1e2e;
    margin-bottom: 12px;
}

.success-text {
    font-size: 15px;
    color: #6b7280;
    margin-bottom: 32px;
    line-height: 1.6;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 14px 28px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-back:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

@media (max-width: 768px) {
    .navbar { padding: 0 16px; }
    .page { padding: 24px 16px 60px; }
    .header-card { padding: 28px 24px; }
    .header-content { flex-direction: column; }
    .company-logo { width: 70px; height: 70px; }
    .job-title { font-size: 22px; }
    .form-card { padding: 28px 20px; }
    .profile-info { grid-template-columns: 1fr; }
    .form-actions { flex-direction: column; }
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div style="font-family: 'Inter', sans-serif; font-size: 1.5rem; font-weight: 700; color: #1a202c; position: relative; display: inline-block;">
        Next<span style="color: #667eea;">Career</span>
        <div style="position: absolute; bottom: 2px; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 2px;"></div>
    </div>
</nav>

<!-- Page -->
<div class="page">
    <a href="/offre.php?id=<?= $id_offre ?>" class="back-link">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M15 19l-7-7 7-7"/>
        </svg>
        Retour à l'offre
    </a>

    <!-- Elegant Header Card -->
    <div class="header-card">
        <div class="header-content">
            <div class="company-logo">
                <?php 
                    $initiales = strtoupper(substr($offre['nom_entreprise'] ?? 'E', 0, 2));
                ?>
                <div class="company-initials"><?= $initiales ?></div>
            </div>
            
            <div class="header-info">
                <div class="job-badge">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Candidature en cours
                </div>
                
                <h1 class="job-title"><?= htmlspecialchars($offre['titre_emploi'] ?? $offre['titre'] ?? 'Offre d\'emploi') ?></h1>
                <div class="company-name"><?= htmlspecialchars($offre['nom_entreprise'] ?? 'Entreprise') ?></div>
                
                <div class="job-meta">
                    <?php if (!empty($offre['localisation'])): ?>
                        <div class="meta-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            </svg>
                            <?= htmlspecialchars($offre['localisation']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($offre['type_contrat'])): ?>
                        <div class="meta-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <?= htmlspecialchars($offre['type_contrat']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($offre['nom_secteur'])): ?>
                        <div class="meta-item">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <?= htmlspecialchars($offre['nom_secteur']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <!-- Success State -->
        <div class="form-card">
            <div class="success-state">
                <div class="success-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h2 class="success-title">Candidature envoyée !</h2>
                <p class="success-text">
                    Votre candidature a été transmise avec succès à <strong><?= htmlspecialchars($offre['nom_entreprise'] ?? 'l\'entreprise') ?></strong>.<br>
                    Vous recevrez une réponse par email dans les prochains jours.
                </p>
                <button onclick="window.location.href='/emploi_platform/offre.php?id=<?= $id_offre ?>'" class="btn-back">
                    Retour à l'offre
                </button>
            </div>
        </div>
    <?php else: ?>
        <!-- Form -->
        <div class="form-card">
            <h2 class="form-title">Complétez votre candidature</h2>
            <p class="form-subtitle">Remplissez les informations ci-dessous pour postuler à cette offre</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <?php foreach ($errors as $err): ?>
                            <div><?= htmlspecialchars($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Profile Summary -->
            <?php if ($candidat): ?>
                <div class="profile-summary">
                    <h3>
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Vos informations
                    </h3>
                    <div class="profile-info">
                        <div class="info-item">
                            <span class="info-label">Nom complet</span>
                            <?= htmlspecialchars($candidat['prenom'] . ' ' . $candidat['nom']) ?>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <?= htmlspecialchars($candidat['email']) ?>
                        </div>
                        <?php if (!empty($candidat['ville'])): ?>
                            <div class="info-item">
                                <span class="info-label">Ville</span>
                                <?= htmlspecialchars($candidat['ville']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($candidat['tel_user'])): ?>
                            <div class="info-item">
                                <span class="info-label">Téléphone</span>
                                <?= htmlspecialchars($candidat['tel_user']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="candidatureForm">
                <!-- Lettre de motivation -->
                <div class="form-field">
                    <label class="form-label">
                        Lettre de motivation <span class="required">*</span>
                    </label>
                    <textarea 
                        name="lettre_motivation" 
                        class="form-textarea" 
                        placeholder="Parlez de votre parcours, de vos motivations et expliquez pourquoi vous êtes le candidat idéal pour ce poste..."
                        required
                        id="lettreMotivation"
                    ><?= htmlspecialchars($_POST['lettre_motivation'] ?? '') ?></textarea>
                    <div class="char-count">
                        <span id="charCount">0</span> / 2000 caractères (minimum 100)
                    </div>
                </div>

                <!-- CV Upload -->
                <div class="form-field">
                    <label class="form-label">
                        CV (optionnel si déjà dans votre profil)
                    </label>
                    <label for="cvFile" class="file-upload-area">
                        <div class="upload-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                        </div>
                        <div class="upload-title">Cliquez pour choisir un fichier</div>
                        <div class="upload-subtitle">PDF, DOC ou DOCX (max 5 MB)</div>
                    </label>
                    <input type="file" name="cv" id="cvFile" class="file-input" accept=".pdf,.doc,.docx">
                    <div id="fileSelected" class="file-selected" style="display:none;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span class="file-name" id="fileName"></span>
                        <span class="file-remove" onclick="removeFile()">Retirer</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        Envoyer ma candidature
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='/emploi_platform/offre.php?id=<?= $id_offre ?>'">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
// Character counter
const textarea = document.getElementById('lettreMotivation');
const charCount = document.getElementById('charCount');

if (textarea && charCount) {
    textarea.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });
    charCount.textContent = textarea.value.length;
}

// File upload handling
const fileInput = document.getElementById('cvFile');
const fileSelected = document.getElementById('fileSelected');
const fileName = document.getElementById('fileName');

if (fileInput) {
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileName.textContent = this.files[0].name;
            fileSelected.style.display = 'flex';
        }
    });
}

function removeFile() {
    fileInput.value = '';
    fileSelected.style.display = 'none';
}
</script>

</body>
</html>
