<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'candidat') {
    header('Location: ../../auth/login.php');
    exit();
}

require_once '../../config/db.php';

$candidat = null;
$message_success = '';
$message_error = '';

// Récupérer les infos du candidat
try {
    $stmt = $pdo->prepare("SELECT c.*, u.email, u.tel_user FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $candidat = $stmt->fetch();
    if (!$candidat) { session_destroy(); header('Location: ../../auth/login.php'); exit(); }
} catch (PDOException $e) {
    $message_error = "Erreur : " . $e->getMessage();
}

// Récupérer compétences enregistrées (si table existe)
$competences = [];
try {
    $stmtC = $pdo->prepare("SHOW TABLES LIKE 'candidat_competences'");
    $stmtC->execute();
    if ($stmtC->fetch()) {
        $stmt = $pdo->prepare("SELECT id, competence FROM candidat_competences WHERE id_candidat = :id ORDER BY id DESC");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $competences = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $competences = [];
}

// Récupérer formations
$formations = [];
try {
    $stmtF = $pdo->prepare("SHOW TABLES LIKE 'candidat_formations'");
    $stmtF->execute();
    if ($stmtF->fetch()) {
        $stmt = $pdo->prepare("SELECT * FROM candidat_formations WHERE id_candidat = :id ORDER BY annee DESC");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $formations = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $formations = [];
}

// Récupérer expériences
$experiences = [];
try {
    $stmtE = $pdo->prepare("SHOW TABLES LIKE 'candidat_experiences'");
    $stmtE->execute();
    if ($stmtE->fetch()) {
        $stmt = $pdo->prepare("SELECT * FROM candidat_experiences WHERE id_candidat = :id ORDER BY en_cours DESC, date_debut DESC");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $experiences = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $experiences = [];
}

// Traiter la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ─── UPDATE INFOS ───
    if ($action === 'update_infos') {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $ville = trim($_POST['ville'] ?? '');
        $date_naissance = trim($_POST['date_naissance'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $domaine = trim($_POST['domaine'] ?? '');
        $niveau_etudes = trim($_POST['niveau_etudes'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        try {
            $pdo->beginTransaction();
            
            // Vérifier et ajouter les colonnes manquantes
            try {
                $pdo->exec("ALTER TABLE candidats ADD COLUMN IF NOT EXISTS domaine VARCHAR(100) NULL");
                $pdo->exec("ALTER TABLE candidats ADD COLUMN IF NOT EXISTS niveau_etudes VARCHAR(100) NULL");
                $pdo->exec("ALTER TABLE candidats ADD COLUMN IF NOT EXISTS bio TEXT NULL");
            } catch (PDOException $e) {
                // Colonnes existent déjà
            }
            
            // Update candidats avec tous les champs
            $stmt = $pdo->prepare("UPDATE candidats SET nom = :nom, prenom = :prenom, ville = :ville, date_naissance = :date_naissance, adresse = :adresse, domaine = :domaine, niveau_etudes = :niveau_etudes, bio = :bio WHERE id_candidat = :id");
            $stmt->execute([
                ':nom' => $nom, 
                ':prenom' => $prenom, 
                ':ville' => $ville, 
                ':date_naissance' => $date_naissance ?: null, 
                ':adresse' => $adresse ?: null,
                ':domaine' => $domaine ?: null,
                ':niveau_etudes' => $niveau_etudes ?: null,
                ':bio' => $bio ?: null,
                ':id' => $_SESSION['user_id']
            ]);
            
            // Update utilisateurs
            $stmt = $pdo->prepare("UPDATE utilisateurs SET email = :email, tel_user = :tel WHERE id_user = (SELECT id_user FROM candidats WHERE id_candidat = :id)");
            $stmt->execute([':email' => $email, ':tel' => $telephone, ':id' => $_SESSION['user_id']]);
            
            $pdo->commit();
            $message_success = "Profil mis à jour avec succès !";
            
            // Recharger les données
            $stmt = $pdo->prepare("SELECT c.*, u.email, u.tel_user FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
            $candidat = $stmt->fetch();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message_error = "Erreur : " . $e->getMessage();
        }
    }

    // ─── UPLOAD CV ───
    if ($action === 'upload_cv' && isset($_FILES['cv_file'])) {
        $file = $_FILES['cv_file'];
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if ($file['error'] === 0 && in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $upload_dir = '../../uploads/cv/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'CV_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                try {
                    $stmt = $pdo->prepare("UPDATE candidats SET cv_numerique = :cv WHERE id_candidat = :id");
                    $stmt->execute([':cv' => $new_filename, ':id' => $_SESSION['user_id']]);
                    $message_success = "CV uploadé avec succès !";
                    
                    // Recharger
                    $stmt = $pdo->prepare("SELECT c.*, u.email, u.tel_user FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
                    $stmt->bindParam(':id', $_SESSION['user_id']);
                    $stmt->execute();
                    $candidat = $stmt->fetch();
                } catch (PDOException $e) {
                    $message_error = "Erreur DB : " . $e->getMessage();
                }
            } else {
                $message_error = "Erreur lors de l'upload du fichier.";
            }
        } else {
            $message_error = "Fichier invalide (PDF ou DOC, max 5MB).";
        }
    }

    // ─── DELETE CV ───
    if ($action === 'delete_cv') {
        try {
            // Récupérer nom du fichier
            $stmt = $pdo->prepare("SELECT cv_numerique FROM candidats WHERE id_candidat = :id");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $row = $stmt->fetch();
            if ($row && !empty($row['cv_numerique'])) {
                $file = __DIR__ . '/../../uploads/cv/' . $row['cv_numerique'];
                if (file_exists($file)) @unlink($file);
            }
            $stmt2 = $pdo->prepare("UPDATE candidats SET cv_numerique = NULL WHERE id_candidat = :id");
            $stmt2->execute([':id' => $_SESSION['user_id']]);
            $message_success = 'CV supprimé avec succès.';
            // Recharger
            $stmt = $pdo->prepare("SELECT c.*, u.email, u.tel_user FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
            $candidat = $stmt->fetch();
        } catch (PDOException $e) {
            $message_error = 'Erreur lors de la suppression du CV.';
        }
    }

    // ─── ADD / REMOVE COMPETENCE ───
    if ($action === 'add_competence' && !empty($_POST['competence'])) {
        $comp = trim($_POST['competence']);
        if ($comp !== '') {
            try {
                // créer table si absent
                $pdo->exec("CREATE TABLE IF NOT EXISTS candidat_competences (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_candidat INT NOT NULL,
                    competence VARCHAR(255) NOT NULL,
                    INDEX(id_candidat)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $pdo->prepare("INSERT INTO candidat_competences (id_candidat, competence) VALUES (:id, :comp)");
                $stmt->execute([':id' => $_SESSION['user_id'], ':comp' => $comp]);
                $message_success = 'Compétence ajoutée.';
                
                // Recharger compétences
                $stmt = $pdo->prepare("SELECT id, competence FROM candidat_competences WHERE id_candidat = :id ORDER BY id DESC");
                $stmt->execute([':id' => $_SESSION['user_id']]);
                $competences = $stmt->fetchAll();
            } catch (PDOException $e) {
                $message_error = 'Erreur lors de l\'ajout de la compétence.';
            }
        }
        // recharger candidat
        $stmt = $pdo->prepare("SELECT c.*, u.email, u.tel_user FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $candidat = $stmt->fetch();
    }

    if ($action === 'remove_competence' && !empty($_POST['competence_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM candidat_competences WHERE id = :id AND id_candidat = :cid");
            $stmt->execute([':id' => (int)$_POST['competence_id'], ':cid' => $_SESSION['user_id']]);
            $message_success = 'Compétence supprimée.';
            
            // Recharger compétences
            $stmt = $pdo->prepare("SELECT id, competence FROM candidat_competences WHERE id_candidat = :id ORDER BY id DESC");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $competences = $stmt->fetchAll();
        } catch (PDOException $e) {
            $message_error = 'Erreur lors de la suppression.';
        }
        // recharger candidat
        $stmt = $pdo->prepare("SELECT c.*, u.email, u.tel_user FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $candidat = $stmt->fetch();
    }

    // ─── UPLOAD PHOTO ───
    if ($action === 'upload_photo' && isset($_FILES['photo_file'])) {
        $file = $_FILES['photo_file'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 3 * 1024 * 1024;

        if ($file['error'] === 0 && in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $upload_dir = '../../uploads/photos/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'PHOTO_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                try {
                    $stmt = $pdo->prepare("UPDATE candidats SET photo_profil = :photo WHERE id_candidat = :id");
                    $stmt->execute([':photo' => $new_filename, ':id' => $_SESSION['user_id']]);
                    $message_success = " Photo de profil mise à jour !";
                    
                    $stmt = $pdo->prepare("SELECT c.*, u.email FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
                    $stmt->bindParam(':id', $_SESSION['user_id']);
                    $stmt->execute();
                    $candidat = $stmt->fetch();
                } catch (PDOException $e) {
                    $message_error = "Erreur DB : " . $e->getMessage();
                }
            }
        }
    }

    // ─── ADD FORMATION ───
    if ($action === 'add_formation') {
        $titre = trim($_POST['titre'] ?? '');
        $etablissement = trim($_POST['etablissement'] ?? '');
        $annee = trim($_POST['annee'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($titre !== '' && $etablissement !== '') {
            try {
                // Créer table si absente
                $pdo->exec("CREATE TABLE IF NOT EXISTS candidat_formations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_candidat INT NOT NULL,
                    titre VARCHAR(255) NOT NULL,
                    etablissement VARCHAR(255) NOT NULL,
                    annee VARCHAR(20) NULL,
                    description TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX(id_candidat)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $pdo->prepare("INSERT INTO candidat_formations (id_candidat, titre, etablissement, annee, description) VALUES (:id, :titre, :etab, :annee, :desc)");
                $stmt->execute([
                    ':id' => $_SESSION['user_id'],
                    ':titre' => $titre,
                    ':etab' => $etablissement,
                    ':annee' => $annee ?: null,
                    ':desc' => $description ?: null
                ]);
                $message_success = 'Formation ajoutée avec succès.';
                
                // Recharger formations
                $stmt = $pdo->prepare("SELECT * FROM candidat_formations WHERE id_candidat = :id ORDER BY annee DESC");
                $stmt->execute([':id' => $_SESSION['user_id']]);
                $formations = $stmt->fetchAll();
            } catch (PDOException $e) {
                $message_error = 'Erreur lors de l\'ajout de la formation.';
            }
        }
        // Recharger candidat
        $stmt = $pdo->prepare("SELECT c.*, u.email, u.tel_user FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $candidat = $stmt->fetch();
    }

    // ─── DELETE FORMATION ───
    if ($action === 'delete_formation' && !empty($_POST['formation_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM candidat_formations WHERE id = :id AND id_candidat = :cid");
            $stmt->execute([':id' => (int)$_POST['formation_id'], ':cid' => $_SESSION['user_id']]);
            $message_success = 'Formation supprimée.';
            
            // Recharger formations
            $stmt = $pdo->prepare("SELECT * FROM candidat_formations WHERE id_candidat = :id ORDER BY annee DESC");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $formations = $stmt->fetchAll();
        } catch (PDOException $e) {
            $message_error = 'Erreur lors de la suppression.';
        }
        // Recharger
        $stmt = $pdo->prepare("SELECT c.*, u.email, u.tel_user FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $candidat = $stmt->fetch();
    }

    // ─── ADD EXPERIENCE ───
    if ($action === 'add_experience') {
        $poste = trim($_POST['poste'] ?? '');
        $entreprise = trim($_POST['entreprise'] ?? '');
        $date_debut = trim($_POST['date_debut'] ?? '');
        $date_fin = trim($_POST['date_fin'] ?? '');
        $en_cours = isset($_POST['en_cours']) ? 1 : 0;
        $description = trim($_POST['description'] ?? '');
        
        if ($poste !== '' && $entreprise !== '') {
            try {
                // Créer table si absente
                $pdo->exec("CREATE TABLE IF NOT EXISTS candidat_experiences (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_candidat INT NOT NULL,
                    poste VARCHAR(255) NOT NULL,
                    entreprise VARCHAR(255) NOT NULL,
                    date_debut VARCHAR(20) NULL,
                    date_fin VARCHAR(20) NULL,
                    en_cours TINYINT(1) DEFAULT 0,
                    description TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX(id_candidat)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $pdo->prepare("INSERT INTO candidat_experiences (id_candidat, poste, entreprise, date_debut, date_fin, en_cours, description) VALUES (:id, :poste, :entreprise, :debut, :fin, :cours, :desc)");
                $stmt->execute([
                    ':id' => $_SESSION['user_id'],
                    ':poste' => $poste,
                    ':entreprise' => $entreprise,
                    ':debut' => $date_debut ?: null,
                    ':fin' => $en_cours ? null : ($date_fin ?: null),
                    ':cours' => $en_cours,
                    ':desc' => $description ?: null
                ]);
                $message_success = 'Expérience ajoutée avec succès.';
                
                // Recharger expériences
                $stmt = $pdo->prepare("SELECT * FROM candidat_experiences WHERE id_candidat = :id ORDER BY en_cours DESC, date_debut DESC");
                $stmt->execute([':id' => $_SESSION['user_id']]);
                $experiences = $stmt->fetchAll();
            } catch (PDOException $e) {
                $message_error = 'Erreur lors de l\'ajout de l\'expérience.';
            }
        }
        // Recharger
        $stmt = $pdo->prepare("SELECT c.*, u.email, u.tel_user FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $candidat = $stmt->fetch();
    }

    // ─── DELETE EXPERIENCE ───
    if ($action === 'delete_experience' && !empty($_POST['experience_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM candidat_experiences WHERE id = :id AND id_candidat = :cid");
            $stmt->execute([':id' => (int)$_POST['experience_id'], ':cid' => $_SESSION['user_id']]);
            $message_success = 'Expérience supprimée.';
            
            // Recharger expériences
            $stmt = $pdo->prepare("SELECT * FROM candidat_experiences WHERE id_candidat = :id ORDER BY en_cours DESC, date_debut DESC");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $experiences = $stmt->fetchAll();
        } catch (PDOException $e) {
            $message_error = 'Erreur lors de la suppression.';
        }
        // Recharger
        $stmt = $pdo->prepare("SELECT c.*, u.email, u.tel_user FROM candidats c JOIN utilisateurs u ON c.id_user = u.id_user WHERE c.id_candidat = :id");
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $candidat = $stmt->fetch();
    }
}

// Calculer la complétude
$fields = ['nom', 'prenom', 'ville', 'cv_numerique', 'date_naissance', 'adresse', 'photo', 'domaine'];
$total_fields = count($fields);
$filled = 0;
foreach ($fields as $f) {
    if (!empty($candidat[$f])) $filled++;
}
$profil_pct = (int)(($filled / $total_fields) * 100);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mon Profil - NextCareer</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: #f5f5fa; color: #1e1e2e; }
a { text-decoration: none; color: inherit; }

/* ── NAVBAR ── */
.navbar {
    background: #fff; border-bottom: 1px solid #eee; padding: 0 48px; height: 62px;
    display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100;
}
.nav-logo { display: flex; align-items: center; gap: 10px; }
.nav-logo-icon {
    width: 38px; height: 38px; background: #5b5fc7; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
}
.nav-logo-icon svg { width: 20px; height: 20px; }
.nav-logo-text h1 { font-size: 17px; font-weight: 700; color: #5b5fc7; }
.nav-logo-text span { font-size: 11px; color: #999; }
.nav-links { display: flex; gap: 32px; }
.nav-links a { font-size: 14px; color: #444; font-weight: 500; }
.nav-links a:hover { color: #5b5fc7; }
.nav-right { display: flex; align-items: center; gap: 18px; }
.nav-right a { font-size: 14px; color: #444; font-weight: 500; }
.btn-inscrire {
    background: #5b5fc7; color: #fff; border: none; border-radius: 20px;
    padding: 8px 22px; font-size: 14px; font-weight: 600; cursor: pointer;
}

/* ── PAGE ── */
.page { max-width: 1180px; margin: 0 auto; padding: 32px 24px; }

/* Back link */
.back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 14px; color: #5b5fc7; font-weight: 500; margin-bottom: 18px;
}
.back-link:hover { text-decoration: underline; }

.page-title { font-size: 28px; font-weight: 700; color: #1e1e2e; margin-bottom: 6px; }
.page-subtitle { font-size: 14px; color: #7a7a8e; margin-bottom: 32px; }

/* Layout */
.layout { display: grid; grid-template-columns: 320px 1fr; gap: 22px; align-items: start; }

/* ── SIDEBAR ── */
.sidebar { display: flex; flex-direction: column; gap: 18px; }
.card {
    background: #fff; border: 1px solid #eee; border-radius: 16px; padding: 24px;
}

/* Profile card */
.profile-card { text-align: center; }
.profile-pic-wrap {
    width: 100px; height: 100px; border-radius: 50%; background: #e8e8f5;
    margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;
    position: relative; overflow: hidden; flex-shrink: 0;
}

.profile-pic-wrap img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    object-position: center;
    border-radius: 50%;
    display: block;
}
.profile-pic-wrap svg { width: 40px; height: 40px; color: #5b5fc7; }
.photo-badge {
    position: absolute; bottom: 0; right: 0; width: 32px; height: 32px;
    background: #5b5fc7; border: 3px solid #fff; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.photo-badge svg { width: 16px; height: 16px; color: #fff; }
.profile-name { font-size: 18px; font-weight: 700; color: #1e1e2e; margin-bottom: 4px; }
.profile-role { font-size: 13px; color: #5b5fc7; margin-bottom: 6px; }
.profile-location { font-size: 13px; color: #7a7a8e; display: flex; align-items: center; justify-content: center; gap: 4px; }

/* Profil complété */
.progress-wrap { margin-bottom: 8px; }
.progress-label { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.progress-label h4 { font-size: 15px; font-weight: 600; color: #1e1e2e; }
.progress-label span { font-size: 15px; font-weight: 700; color: #16a34a; }
.progress-track { height: 10px; background: #eee; border-radius: 999px; width: 100%; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #16a34a, #22c55e); }

/* CV card */
.cv-card h4 { font-size: 15px; font-weight: 600; color: #1e1e2e; margin-bottom: 14px; }
.cv-file {
    display: flex; align-items: center; gap: 12px; padding: 12px; background: #f9f9fc;
    border-radius: 10px; margin-bottom: 12px; position: relative;
}
.cv-icon {
    width: 38px; height: 38px; background: #5b5fc7; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.cv-icon svg { width: 18px; height: 18px; color: #fff; }
.cv-info { flex: 1; }
.cv-name { font-size: 13px; font-weight: 600; color: #1e1e2e; }
.cv-size { font-size: 12px; color: #7a7a8e; }
.cv-delete {
    width: 24px; height: 24px; border-radius: 50%; background: #fee; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.cv-delete svg { width: 14px; height: 14px; color: #991b1b; }

.btn-upload {
    display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;
    border: 1.5px solid #5b5fc7; border-radius: 10px; padding: 10px;
    background: #fff; color: #5b5fc7; font-size: 14px; font-weight: 600; cursor: pointer;
}
.btn-upload:hover { background: #f0f0ff; }
.btn-upload svg { width: 18px; height: 18px; }

/* ── MAIN CONTENT ── */

/* Tabs */
.tabs {
    display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid #eee; padding-bottom: 0;
}
.tab-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 20px; background: transparent; border: none;
    font-size: 14px; font-weight: 500; color: #7a7a8e; cursor: pointer;
    border-bottom: 3px solid transparent; position: relative; top: 1px;
}
.tab-btn.active { color: #5b5fc7; border-bottom-color: #5b5fc7; }
.tab-btn svg { width: 16px; height: 16px; }

/* Tab content */
.tab-content { display: none; }
.tab-content.active { display: block; }

/* Section card */
.section-card {
    background: #fff; border: 1px solid #eee; border-radius: 16px; padding: 28px;
}
.section-header {
    display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;
}
.section-header h3 { font-size: 18px; font-weight: 700; color: #1e1e2e; }

.btn-modifier {
    display: inline-flex; align-items: center; gap: 6px;
    border: 1.5px solid #5b5fc7; border-radius: 10px; padding: 8px 16px;
    background: #fff; color: #5b5fc7; font-size: 14px; font-weight: 600; cursor: pointer;
}
.btn-modifier:hover { background: #f0f0ff; }
.btn-modifier svg { width: 16px; height: 16px; }

.btn-ajouter {
    display: inline-flex; align-items: center; gap: 6px;
    background: #5b5fc7; border: none; border-radius: 10px; padding: 10px 18px;
    color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;
}
.btn-ajouter:hover { background: #4a4eb5; }
.btn-ajouter svg { width: 16px; height: 16px; }

/* Form */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.form-field { margin-bottom: 18px; }
.form-field label {
    display: block; font-size: 13px; font-weight: 600; color: #1e1e2e; margin-bottom: 8px;
}
.form-field input, .form-field select, .form-field textarea {
    width: 100%; padding: 11px 14px; border: 1px solid #ddd; border-radius: 10px;
    font-size: 14px; font-family: inherit; background: #fff;
}
.form-field input:disabled, .form-field select:disabled, .form-field textarea:disabled {
    background: #f9f9fc; color: #666; cursor: not-allowed;
}
.form-field input:focus, .form-field select:focus, .form-field textarea:focus {
    outline: none; border-color: #5b5fc7;
}
.form-field textarea { resize: vertical; min-height: 100px; }
.char-count { font-size: 12px; color: #7a7a8e; text-align: right; margin-top: 4px; }

.btn-save {
    display: inline-flex; align-items: center; gap: 6px;
    background: #5b5fc7; border: none; border-radius: 10px; padding: 10px 24px;
    color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 12px;
}
.btn-save:hover { background: #4a4eb5; }
.btn-save:disabled { background: #ccc; cursor: not-allowed; }
.btn-save svg { width: 16px; height: 16px; }

/* Compétences */
.competences-list {
    display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px;
}
.competence-tag {
    display: inline-flex; align-items: center; gap: 6px;
    background: #eef2ff; color: #5b5fc7;
    border-radius: 999px; padding: 7px 14px; font-size: 13px; font-weight: 500;
}
.competence-tag svg { width: 14px; height: 14px; }
.competence-tag .remove {
    width: 16px; height: 16px; border-radius: 50%; background: #5b5fc7;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.competence-tag .remove:hover { background: #4a4eb5; }
.competence-tag .remove svg { width: 10px; height: 10px; color: #fff; }

.add-competence { margin-bottom: 20px; }
.add-competence input {
    width: 100%; padding: 11px 14px; border: 1px solid #ddd; border-radius: 10px;
    font-size: 14px; margin-bottom: 10px;
}
.add-competence-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: #5b5fc7; border: none; border-radius: 10px; padding: 8px 18px;
    color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;
}
.add-competence-btn:hover { background: #4a4eb5; }
.add-competence-btn svg { width: 16px; height: 16px; }

.suggestions h5 { font-size: 13px; font-weight: 600; color: #1e1e2e; margin-bottom: 10px; }
.suggestions-list {
    display: flex; flex-wrap: wrap; gap: 8px;
}
.suggestion-tag {
    display: inline-flex; align-items: center; gap: 4px;
    background: #fff; color: #5b5fc7; border: 1px solid #e8e8f5;
    border-radius: 999px; padding: 6px 12px; font-size: 12px; font-weight: 500; cursor: pointer;
}
.suggestion-tag:hover { background: #f0f0ff; }

/* Formations & Expériences */
.item-list { margin-bottom: 20px; }
.item-card {
    background: #f9f9fc; border: 1px solid #eee; border-radius: 12px;
    padding: 18px 20px; margin-bottom: 12px;
    display: flex; align-items: flex-start; gap: 14px;
}
.item-icon {
    width: 42px; height: 42px; background: #eef2ff; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.item-icon svg { width: 20px; height: 20px; color: #5b5fc7; }
.item-content { flex: 1; }
.item-title { font-size: 15px; font-weight: 600; color: #1e1e2e; margin-bottom: 4px; }
.item-subtitle { font-size: 13px; color: #7a7a8e; margin-bottom: 6px; }
.item-date {
    display: flex; align-items: center; gap: 4px;
    font-size: 12px; color: #7a7a8e; margin-bottom: 8px;
}
.item-date svg { width: 14px; height: 14px; }
.item-badge {
    display: inline-block; background: #d1fae5; color: #065f46;
    border-radius: 999px; padding: 3px 10px; font-size: 11px; font-weight: 600; margin-left: 8px;
}
.item-desc { font-size: 13px; color: #444; line-height: 1.5; }

/* Alert */
.alert {
    padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px;
    display: flex; align-items: center; gap: 10px;
}
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.alert svg { width: 18px; height: 18px; flex-shrink: 0; }

/* Responsive */
@media (max-width: 900px) {
    .layout { grid-template-columns: 1fr; }
    .form-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ═══════════ NAVBAR ═══════════ -->
<!-- Navbar -->
<nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-200" style="padding: 0 48px; height: 64px; display: flex; align-items: center; justify-content: space-between;">
    <div style="max-width: 1180px; width: 100%; margin: 0 auto; display: flex; align-items: center; justify-content: space-between;">

        <!-- Logo -->
        <a href="../../index.php">
            <div style="font-family:'Inter',sans-serif;font-size:1.75rem;font-weight:700;color:#1a202c;position:relative;display:inline-block;">
                Next<span style="color:#667eea;">Career</span>
                <div style="position:absolute;bottom:2px;left:0;width:100%;height:3px;background:linear-gradient(90deg,#667eea 0%,#764ba2 100%);border-radius:2px;"></div>
            </div>
        </a>

        <!-- Navigation Links -->
        <div style="display:flex;gap:32px;">
            <a href="../../index.php" style="font-size:14px;color:#4b5563;font-weight:500;text-decoration:none;" onmouseover="this.style.color='#667eea'" onmouseout="this.style.color='#4b5563'">Accueil</a>
            <a href="../../offres.php" style="font-size:14px;color:#4b5563;font-weight:500;text-decoration:none;" onmouseover="this.style.color='#667eea'" onmouseout="this.style.color='#4b5563'">Offres d'emploi</a>
            <a href="../../entreprises.php" style="font-size:14px;color:#4b5563;font-weight:500;text-decoration:none;" onmouseover="this.style.color='#667eea'" onmouseout="this.style.color='#4b5563'">Entreprises</a>
            <a href="../../abonnement.php" style="font-size:14px;color:#4b5563;font-weight:500;text-decoration:none;" onmouseover="this.style.color='#667eea'" onmouseout="this.style.color='#4b5563'">Abonnement</a>
        </div>

        <!-- User menu -->
        <div style="display:flex;align-items:center;gap:16px;">
            <?php
            $user_nom_nav = '';
            $user_prenom_nav = '';
            try {
                $stmt_nav = $pdo->prepare("SELECT nom, prenom FROM candidats WHERE id_candidat = :id");
                $stmt_nav->execute([':id' => $_SESSION['user_id']]);
                $user_data_nav = $stmt_nav->fetch();
                $user_nom_nav = $user_data_nav['nom'] ?? '';
                $user_prenom_nav = $user_data_nav['prenom'] ?? '';
            } catch (PDOException $e) {}
            $initiales_nav = strtoupper(substr($user_prenom_nav, 0, 1) . substr($user_nom_nav, 0, 1));
            $nom_complet_nav = htmlspecialchars($user_prenom_nav . ' ' . $user_nom_nav);
            ?>

            <div style="display:flex;align-items:center;gap:12px;padding-right:16px;border-right:1px solid #d1d5db;">
                <div style="width:40px;height:40px;background:#e0e7ff;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:16px;font-weight:700;color:#667eea;"><?= $initiales_nav ?></span>
                </div>
                <div>
                    <p style="font-size:14px;font-weight:600;color:#1a202c;margin:0;"><?= $nom_complet_nav ?></p>
                    <p style="font-size:12px;color:#6b7280;margin:0;">Candidat</p>
                </div>
            </div>

            <a href="../../auth/logout.php" style="background:#667eea;color:#fff;padding:8px 20px;border-radius:999px;font-size:14px;font-weight:500;text-decoration:none;transition:background 0.2s;" onmouseover="this.style.background='#4f46e5'" onmouseout="this.style.background='#667eea'">
                Déconnexion
            </a>
        </div>
    </div>
</nav>

<!-- ═══════════ PAGE ═══════════ -->
<div class="page">
    <a href="dashboard.php" class="back-link">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;"><path d="M15 19l-7-7 7-7"/></svg>
        Retour au tableau de bord
    </a>

    <h1 class="page-title">Mon Profil</h1>
    <p class="page-subtitle">Complétez votre profil pour augmenter vos chances</p>

    <?php if ($message_success): ?>
        <div class="alert alert-success">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?= htmlspecialchars($message_success) ?>
        </div>
    <?php endif; ?>

    <?php if ($message_error): ?>
        <div class="alert alert-error">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?= htmlspecialchars($message_error) ?>
        </div>
    <?php endif; ?>

    <div class="layout">
        <!-- ════ SIDEBAR ════ -->
        <div class="sidebar">
            <!-- Profile pic -->
            <div class="card" style="text-align: center;">
                <div class="profile-pic-wrap">
                    <?php if (!empty($candidat['photo_profil'])): ?>
                        <img src="../../uploads/photos/<?= htmlspecialchars($candidat['photo_profil']) ?>" alt="Photo">
                    <?php else: ?>
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    <?php endif; ?>
                    <div class="photo-badge" onclick="document.getElementById('photo-upload').click();">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0118.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/></svg>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="photo-form">
                    <input type="hidden" name="action" value="upload_photo">
                    <input type="file" id="photo-upload" name="photo_file" accept="image/*" style="display:none;" onchange="document.getElementById('photo-form').submit();">
                </form>
                
                <div class="profile-name"><?= htmlspecialchars($candidat['prenom'] . ' ' . $candidat['nom']) ?></div>
                <div class="profile-location"><?= htmlspecialchars($candidat['ville'] ?? 'Ouagadougou') ?></div>
            </div>
            

            <!-- Profil complété -->
            <div class="card">
                <div class="progress-wrap">
                    <div class="progress-label">
                        <h4>Profil complété</h4>
                        <span><?= $profil_pct ?>%</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width:<?= $profil_pct ?>%;"></div>
                    </div>
                </div>
            </div>

            <!-- CV -->
            <div class="card cv-card">
                <h4>Mon CV</h4>
                <?php if (!empty($candidat['cv_numerique'])): ?>
                    <div class="cv-file">
                        <div class="cv-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="cv-info">
                            <div class="cv-name"><?= htmlspecialchars(substr($candidat['cv_numerique'], 0, 20)) ?>...</div>
                            <div class="cv-size">PDF • 245 KB</div>
                        </div>
                        <div class="cv-delete" onclick="deleteCV()">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" style="margin:0;">
                    <input type="hidden" name="action" value="upload_cv">
                    <input type="file" name="cv_file" id="cv-upload" accept=".pdf,.doc,.docx" style="display:none;" onchange="this.form.submit()">
                    <label for="cv-upload" class="btn-upload">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <?= !empty($candidat['cv_numerique']) ? 'Remplacer le CV' : 'Télécharger un CV' ?>
                    </label>
                </form>
                <p style="font-size:12px;color:#7a7a8e;text-align:center;margin-top:8px;">PDF ou DOC, max 5 MB</p>
            </div>
        </div>

        <!-- ════ MAIN CONTENT ════ -->
        <div class="main-content">
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" data-tab="informations">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    Informations
                </button>
                <button class="tab-btn" data-tab="competences">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Compétences
                </button>
                <button class="tab-btn" data-tab="formation">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/><path d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/></svg>
                    Formation
                </button>
                <button class="tab-btn" data-tab="experience">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Expérience
                </button>
            </div>

            <!-- ─── TAB: Informations ─── -->
            <div class="tab-content active" id="tab-informations">
                <div class="section-card">
                    <div class="section-header">
                        <h3>Informations personnelles</h3>
                        <button class="btn-modifier" onclick="toggleEdit('info-form')">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span class="edit-text">Modifier</span>
                        </button>
                    </div>

                                       <form method="POST" id="info-form">
                        <input type="hidden" name="action" value="update_infos">
                        <div class="form-grid">
                            <div class="form-field">
                                <label>Prénom</label>
                                <input type="text" name="prenom" value="<?= htmlspecialchars($candidat['prenom'] ?? '') ?>" required disabled>
                            </div>
                            <div class="form-field">
                                <label>Nom</label>
                                <input type="text" name="nom" value="<?= htmlspecialchars($candidat['nom'] ?? '') ?>" required disabled>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-field">
                                <label>Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($candidat['email'] ?? '') ?>" required disabled>
                            </div>
                            <div class="form-field">
                                <label>Téléphone</label>
                                <input type="tel" name="telephone" value="+226 70 12 34 56" placeholder="+226 70 12 34 56" disabled>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-field">
                                <label>Date de naissance</label>
                                <input type="date" name="date_naissance" value="<?= htmlspecialchars($candidat['date_naissance'] ?? '') ?>" disabled>
                            </div>
                            <div class="form-field">
                                <label>Ville</label>
                                <input type="text" list="villes" name="ville" value="<?= htmlspecialchars($candidat['ville'] ?? '') ?>" placeholder="Ex: Ouagadougou" disabled>
                                <datalist id="villes">
                                    <option value="Ouagadougou">
                                    <option value="Bobo-Dioulasso">
                                    <option value="Koudougou">
                                    <option value="Banfora">
                                </datalist>
                            </div>
                        </div>

                        <div class="form-field">
                            <label>Adresse</label>
                            <input type="text" name="adresse" placeholder="123 Rue de..." value="<?= htmlspecialchars($candidat['adresse'] ?? '') ?>" disabled>
                        </div>

                        <div class="form-grid">
                            <div class="form-field">
                                <label>Domaine de compétence</label>
                                <input type="text" list="domaines" name="domaine" value="<?= htmlspecialchars($candidat['domaine'] ?? '') ?>" placeholder="Ex: Informatique & Tech" disabled>
                                <datalist id="domaines">
                                    <option value="Informatique & Tech">
                                    <option value="Finance">
                                    <option value="Marketing">
                                    <option value="Ressources Humaines">
                                    <option value="Vente">
                                </datalist>
                            </div>

                            <div class="form-field">
                                <label>Niveau d'études</label>
                                <input type="text" list="niveaux" name="niveau_etudes" value="<?= htmlspecialchars($candidat['niveau_etudes'] ?? '') ?>" placeholder="Ex: BAC+5" disabled>
                                <datalist id="niveaux">
                                    <option value="BAC">
                                    <option value="BAC+2">
                                    <option value="BAC+3">
                                    <option value="BAC+5">
                                    <option value="Doctorat">
                                </datalist>
                            </div>
                        </div>

                        <div class="form-field">
                            <label>Bio / Présentation</label>
                            <textarea name="bio" placeholder="Parlez-nous de vous..." disabled><?= htmlspecialchars($candidat['bio'] ?? '') ?></textarea>
                            <div class="char-count">148/500 caractères</div>
                        </div>

                        <button type="submit" class="btn-save" id="save-btn" disabled>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                            Enregistrer
                        </button>
                    </form>


                </div>
            </div>

            <!-- ─── TAB: Compétences ─── -->
            <div class="tab-content" id="tab-competences">
                <div class="section-card">
                    <h3 style="font-size:18px;font-weight:700;margin-bottom:20px;">Mes compétences</h3>

                    <div class="competences-list">
                        <?php if (!empty($competences)): ?>
                            <?php foreach ($competences as $comp): ?>
                                <span class="competence-tag" data-id="<?= htmlspecialchars($comp['id']) ?>">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <?= htmlspecialchars($comp['competence']) ?>
                                    <span class="remove" onclick="removeCompetence(<?= $comp['id'] ?>)">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                    </span>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="color:#7a7a8e;font-size:13px;">Aucune compétence ajoutée</div>
                        <?php endif; ?>
                    </div>

                    <div class="add-competence">
                        <label style="display:block;font-size:14px;font-weight:600;margin-bottom:10px;">Ajouter une compétence</label>
                        <input type="text" id="comp-input" placeholder="Ex: React, Excel, Management..." maxlength="100">
                        <button class="add-competence-btn" onclick="addCompetence()">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                            Ajouter
                        </button>
                    </div>

                    <div class="suggestions">
                        <h5>Suggestions populaires</h5>
                        <div class="suggestions-list">
                            <span class="suggestion-tag" onclick="addSuggestion('Java')">+ Java</span>
                            <span class="suggestion-tag" onclick="addSuggestion('Excel')">+ Excel</span>
                            <span class="suggestion-tag" onclick="addSuggestion('Word')">+ Word</span>
                            <span class="suggestion-tag" onclick="addSuggestion('PowerPoint')">+ PowerPoint</span>
                            <span class="suggestion-tag" onclick="addSuggestion('Comptabilité')">+ Comptabilité</span>
                            <span class="suggestion-tag" onclick="addSuggestion('Gestion de projet')">+ Gestion de projet</span>
                            <span class="suggestion-tag" onclick="addSuggestion('Communication')">+ Communication</span>
                            <span class="suggestion-tag" onclick="addSuggestion('Marketing digital')">+ Marketing digital</span>
                            <span class="suggestion-tag" onclick="addSuggestion('SEO')">+ SEO</span>
                            <span class="suggestion-tag" onclick="addSuggestion('Photoshop')">+ Photoshop</span>
                            <span class="suggestion-tag" onclick="addSuggestion('Illustrator')">+ Illustrator</span>
                            <span class="suggestion-tag" onclick="addSuggestion('Anglais')">+ Anglais</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── TAB: Formation ─── -->
            <div class="tab-content" id="tab-formation">
                <div class="section-card">
                    <div class="section-header">
                        <h3>Mes formations</h3>
                        <button class="btn-ajouter" onclick="toggleFormationForm()">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                            Ajouter
                        </button>
                    </div>

                    <!-- Formulaire d'ajout (caché par défaut) -->
                    <div id="formation-form" style="display:none; background:#f9f9fc; padding:20px; border-radius:12px; margin-bottom:20px;">
                        <h4 style="font-size:16px; font-weight:600; margin-bottom:16px;">Ajouter une formation</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_formation">
                            <div class="form-field">
                                <label>Titre du diplôme *</label>
                                <input type="text" name="titre" placeholder="Ex: Master en Informatique" required>
                            </div>
                            <div class="form-field">
                                <label>Établissement *</label>
                                <input type="text" name="etablissement" placeholder="Ex: Université Joseph Ki-Zerbo" required>
                            </div>
                            <div class="form-field">
                                <label>Année d'obtention</label>
                                <input type="text" name="annee" placeholder="Ex: 2021">
                            </div>
                            <div class="form-field">
                                <label>Description</label>
                                <textarea name="description" placeholder="Spécialisation, mention, etc." rows="3"></textarea>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button type="submit" class="btn-save">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                    Enregistrer
                                </button>
                                <button type="button" class="btn-modifier" onclick="toggleFormationForm()">Annuler</button>
                            </div>
                        </form>
                    </div>

                    <div class="item-list">
                        <?php if (!empty($formations)): ?>
                            <?php foreach ($formations as $formation): ?>
                                <div class="item-card">
                                    <div class="item-icon">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                                    </div>
                                    <div class="item-content">
                                        <div class="item-title"><?= htmlspecialchars($formation['titre']) ?></div>
                                        <div class="item-subtitle"><?= htmlspecialchars($formation['etablissement']) ?></div>
                                        <?php if (!empty($formation['annee'])): ?>
                                            <div class="item-date">
                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <?= htmlspecialchars($formation['annee']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($formation['description'])): ?>
                                            <div class="item-desc"><?= htmlspecialchars($formation['description']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cv-delete" onclick="deleteFormation(<?= $formation['id'] ?>)" style="margin-left:auto;">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding:40px; color:#7a7a8e;">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:48px; height:48px; margin:0 auto 16px; color:#ccc;"><path d="M12 14l9-5-9-5-9 5 9 5z"/></svg>
                                <p>Aucune formation ajoutée pour le moment</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ─── TAB: Expérience ─── -->
            <div class="tab-content" id="tab-experience">
                <div class="section-card">
                    <div class="section-header">
                        <h3>Mes expériences professionnelles</h3>
                        <button class="btn-ajouter" onclick="toggleExperienceForm()">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                            Ajouter
                        </button>
                    </div>

                    <!-- Formulaire d'ajout (caché par défaut) -->
                    <div id="experience-form" style="display:none; background:#f9f9fc; padding:20px; border-radius:12px; margin-bottom:20px;">
                        <h4 style="font-size:16px; font-weight:600; margin-bottom:16px;">Ajouter une expérience</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_experience">
                            <div class="form-field">
                                <label>Poste occupé *</label>
                                <input type="text" name="poste" placeholder="Ex: Développeur Full-Stack" required>
                            </div>
                            <div class="form-field">
                                <label>Entreprise *</label>
                                <input type="text" name="entreprise" placeholder="Ex: Tech Solutions BF" required>
                            </div>
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>Date de début</label>
                                    <input type="month" name="date_debut">
                                </div>
                                <div class="form-field">
                                    <label>Date de fin</label>
                                    <input type="month" name="date_fin" id="date-fin-input">
                                </div>
                            </div>
                            <div class="form-field">
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                    <input type="checkbox" name="en_cours" id="en-cours-checkbox" onchange="toggleDateFin()">
                                    Poste actuel
                                </label>
                            </div>
                            <div class="form-field">
                                <label>Description</label>
                                <textarea name="description" placeholder="Décrivez vos missions et réalisations..." rows="4"></textarea>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button type="submit" class="btn-save">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                                    Enregistrer
                                </button>
                                <button type="button" class="btn-modifier" onclick="toggleExperienceForm()">Annuler</button>
                            </div>
                        </form>
                    </div>

                    <div class="item-list">
                        <?php if (!empty($experiences)): ?>
                            <?php foreach ($experiences as $exp): ?>
                                <div class="item-card">
                                    <div class="item-icon">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div class="item-content">
                                        <div class="item-title"><?= htmlspecialchars($exp['poste']) ?></div>
                                        <div class="item-subtitle"><?= htmlspecialchars($exp['entreprise']) ?></div>
                                        <div class="item-date">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <?= htmlspecialchars($exp['date_debut'] ?: 'Non spécifié') ?> - 
                                            <?= $exp['en_cours'] ? 'Présent' : htmlspecialchars($exp['date_fin'] ?: 'Non spécifié') ?>
                                            <?php if ($exp['en_cours']): ?>
                                                <span class="item-badge">En cours</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($exp['description'])): ?>
                                            <div class="item-desc"><?= htmlspecialchars($exp['description']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cv-delete" onclick="deleteExperience(<?= $exp['id'] ?>)" style="margin-left:auto;">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding:40px; color:#7a7a8e;">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:48px; height:48px; margin:0 auto 16px; color:#ccc;"><path d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <p>Aucune expérience ajoutée pour le moment</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        btn.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
    });
});

// Toggle edit mode for form
let isEditing = false;
function toggleEdit(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
    const saveBtn = document.getElementById('save-btn');
    const editBtn = document.querySelector('.btn-modifier .edit-text');
    
    isEditing = !isEditing;
    
    inputs.forEach(input => {
        input.disabled = !isEditing;
    });
    
    saveBtn.disabled = !isEditing;
    editBtn.textContent = isEditing ? 'Annuler' : 'Modifier';
}

// Character counter for bio
const bioTextarea = document.getElementById('bio-textarea');
const charCounter = document.getElementById('char-counter');
if (bioTextarea && charCounter) {
    bioTextarea.addEventListener('input', () => {
        charCounter.textContent = bioTextarea.value.length;
    });
}

// Delete CV via POST
function deleteCV() {
    if (!confirm('Êtes-vous sûr de vouloir supprimer votre CV ?')) return;
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'delete_cv' })
    }).then(r => r.text()).then(() => {
        location.reload();
    }).catch(() => alert('Erreur lors de la suppression du CV'));
}

// Add competence
function addCompetence() {
    const input = document.getElementById('comp-input');
    const value = input.value.trim();
    if (!value) {
        alert('Veuillez entrer une compétence');
        return;
    }
    const form = new URLSearchParams();
    form.append('action', 'add_competence');
    form.append('competence', value);
    fetch('', { method: 'POST', body: form })
        .then(r => r.text())
        .then(() => location.reload())
        .catch(() => alert('Erreur lors de l\'ajout de la compétence'));
}

// Add suggestion
function addSuggestion(skill) {
    const form = new URLSearchParams();
    form.append('action', 'add_competence');
    form.append('competence', skill);
    fetch('', { method: 'POST', body: form })
        .then(r => r.text())
        .then(() => location.reload())
        .catch(() => alert('Erreur lors de l\'ajout de la compétence'));
}

// Remove competence
function removeCompetence(compId) {
    if (!confirm('Supprimer cette compétence ?')) return;
    const form = new URLSearchParams();
    form.append('action', 'remove_competence');
    form.append('competence_id', compId);
    fetch('', { method: 'POST', body: form })
        .then(r => r.text())
        .then(() => location.reload())
        .catch(() => alert('Erreur lors de la suppression'));
}

// Upload photo when file selected
const photoInput = document.getElementById('photo-upload');
if (photoInput) {
    photoInput.addEventListener('change', function() {
        if (!this.files || this.files.length === 0) return;
        
        // Validate file size and type
        const file = this.files[0];
        const maxSize = 3 * 1024 * 1024; // 3MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (!allowedTypes.includes(file.type)) {
            alert('Format non supporté. Utilisez JPEG, PNG ou WEBP.');
            return;
        }
        
        if (file.size > maxSize) {
            alert('Fichier trop volumineux. Maximum 3MB.');
            return;
        }
        
        const fd = new FormData();
        fd.append('action', 'upload_photo');
        fd.append('photo', file);
        
        fetch('', { method: 'POST', body: fd })
            .then(() => location.reload())
            .catch(() => alert('Erreur lors de l\'upload de la photo'));
    });
}

// Allow Enter key to add competence
document.getElementById('comp-input')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        addCompetence();
    }
});

// Toggle formation form
function toggleFormationForm() {
    const form = document.getElementById('formation-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Delete formation
function deleteFormation(formationId) {
    if (!confirm('Supprimer cette formation ?')) return;
    const form = new URLSearchParams();
    form.append('action', 'delete_formation');
    form.append('formation_id', formationId);
    fetch('', { method: 'POST', body: form })
        .then(r => r.text())
        .then(() => location.reload())
        .catch(() => alert('Erreur lors de la suppression'));
}

// Toggle experience form
function toggleExperienceForm() {
    const form = document.getElementById('experience-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

// Delete experience
function deleteExperience(expId) {
    if (!confirm('Supprimer cette expérience ?')) return;
    const form = new URLSearchParams();
    form.append('action', 'delete_experience');
    form.append('experience_id', expId);
    fetch('', { method: 'POST', body: form })
        .then(r => r.text())
        .then(() => location.reload())
        .catch(() => alert('Erreur lors de la suppression'));
}

// Toggle date fin when "En cours" is checked
function toggleDateFin() {
    const checkbox = document.getElementById('en-cours-checkbox');
    const dateFin = document.getElementById('date-fin-input');
    dateFin.disabled = checkbox.checked;
    if (checkbox.checked) {
        dateFin.value = '';
    }
}
</script>

</body>
</html>