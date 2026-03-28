<?php
session_start();

// Inclure la connexion à la base de données
require_once '../config/db.php';

$error = '';
$success = '';

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id_user FROM utilisateurs WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                $error = "Cet email est déjà utilisé.";
            } else {
                // Commencer une transaction
                $pdo->beginTransaction();
                
                // Hasher le mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Créer un nom d'utilisateur à partir du nom
                $nom_user = strtoupper($nom);
                
                // Générer un tel_user aléatoire si non fourni
                $tel_user = !empty($telephone) ? $telephone : '70' . rand(100000, 999999);
                
                // Insérer dans la table utilisateurs
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateurs (nom_user, email, password, role, tel_user) 
                    VALUES (:nom_user, :email, :password, 'candidat', :tel_user)
                ");
                $stmt->bindParam(':nom_user', $nom_user);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':tel_user', $tel_user);
                $stmt->execute();
                
                // Récupérer l'ID de l'utilisateur créé
                $id_user = $pdo->lastInsertId();
                
                // Vérifier que l'ID a bien été récupéré
                if (!$id_user) {
                    throw new Exception("Impossible de récupérer l'ID de l'utilisateur créé");
                }
                
                // Insérer dans la table candidats (SANS email, AVEC id_user)
                $stmt = $pdo->prepare("
                    INSERT INTO candidats (id_user, nom, prenom, date_naissance, adresse, ville, cv_numerique) 
                    VALUES (:id_user, :nom, :prenom, NULL, NULL, :ville, NULL)
                ");
                $stmt->bindParam(':id_user', $id_user);
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':prenom', $prenom);
                $stmt->bindParam(':ville', $ville);
                $stmt->execute();
                
                // Valider la transaction
                $pdo->commit();
                
                // Message de succès avec détails
                $success = "Inscription réussie ! Votre compte a été créé.<br>";
                $success .= "Email: <strong>" . htmlspecialchars($email) . "</strong><br>";
                $success .= "Vous pouvez maintenant vous connecter.";
                
                // Rediriger vers la page de connexion après 3 secondes
                header("refresh:3;url=login.php");
            }
        } catch (PDOException $e) {
            // Annuler la transaction en cas d'erreur
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Erreur lors de l'inscription : " . $e->getMessage();
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Erreur : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Candidat - NextCareer</title>
    
    <!-- Tailwind CSS CDN -->
    <link rel="stylesheet" href="../assets/css/tailwind.min.css">
    
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
        body {
            font-family: 'Inter', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="flex items-center justify-center space-x-2 mb-4">
                   <div style="font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: #1a202c; position: relative; display: inline-block;">
                        Next<span style="color: #667eea;">Career</span>
                        <div style="position: absolute; bottom: 4px; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 2px;"></div>
                    </div>
                </div>
                <h2 class="text-3xl font-bold text-gray-900">Inscription Candidat</h2>
                <p class="mt-2 text-gray-600">Créez votre compte pour trouver un emploi</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div><?= $error ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div><?= $success ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <form method="POST" action="register-candidat.php" class="space-y-4">
                    <!-- Nom -->
                    <div>
                        <label for="nom" class="block text-sm font-medium text-gray-700 mb-2">
                            Nom <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="nom" 
                            name="nom" 
                            required
                            value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="KABORE"
                        >
                    </div>

                    <!-- Prénom -->
                    <div>
                        <label for="prenom" class="block text-sm font-medium text-gray-700 mb-2">
                            Prénom <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="prenom" 
                            name="prenom" 
                            required
                            value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Aminata"
                        >
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Adresse email <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="votre.email@exemple.com"
                        >
                    </div>

                    <!-- Téléphone -->
                    <div>
                        <label for="telephone" class="block text-sm font-medium text-gray-700 mb-2">
                            Téléphone
                        </label>
                        <input 
                            type="tel" 
                            id="telephone" 
                            name="telephone"
                            value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="+226 70 12 34 56"
                        >
                    </div>

                    <!-- Ville -->
                    <div>
                        <label for="ville" class="block text-sm font-medium text-gray-700 mb-2">
                            Ville
                        </label>
                        <select 
                            id="ville" 
                            name="ville"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        >
                            <option value="">Sélectionnez une ville</option>
                            <option value="Ouagadougou" <?= (($_POST['ville'] ?? '') == 'Ouagadougou') ? 'selected' : '' ?>>Ouagadougou</option>
                            <option value="Bobo-Dioulasso" <?= (($_POST['ville'] ?? '') == 'Bobo-Dioulasso') ? 'selected' : '' ?>>Bobo-Dioulasso</option>
                            <option value="Koudougou" <?= (($_POST['ville'] ?? '') == 'Koudougou') ? 'selected' : '' ?>>Koudougou</option>
                            <option value="Ouahigouya" <?= (($_POST['ville'] ?? '') == 'Ouahigouya') ? 'selected' : '' ?>>Ouahigouya</option>
                            <option value="Banfora" <?= (($_POST['ville'] ?? '') == 'Banfora') ? 'selected' : '' ?>>Banfora</option>
                            <option value="Fada N'Gourma" <?= (($_POST['ville'] ?? '') == "Fada N'Gourma") ? 'selected' : '' ?>>Fada N'Gourma</option>
                        </select>
                    </div>

                    <!-- Mot de passe -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Mot de passe <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Minimum 6 caractères"
                        >
                    </div>

                    <!-- Confirmer mot de passe -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirmer le mot de passe <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            required
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Répétez votre mot de passe"
                        >
                    </div>

                    <!-- Terms -->
                    <div class="flex items-start">
                        <input 
                            id="terms" 
                            name="terms" 
                            type="checkbox"
                            required
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded mt-1"
                        >
                        <label for="terms" class="ml-2 block text-sm text-gray-700">
                            J'accepte les <a href="#" class="text-indigo-600 hover:text-indigo-700 font-medium">conditions d'utilisation</a> et la <a href="#" class="text-indigo-600 hover:text-indigo-700 font-medium">politique de confidentialité</a>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit"
                        class="w-full gradient-bg text-white py-3 px-4 rounded-xl font-medium hover:opacity-90 transition duration-200 flex items-center justify-center"
                    >
                        Créer mon compte
                    </button>
                </form>

                <!-- Login Link -->
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        Vous avez déjà un compte ? 
                        <a href="login.php" class="text-indigo-600 hover:text-indigo-700 font-medium">
                            Se connecter
                        </a>
                    </p>
                </div>
            </div>

            <!-- Back to Home -->
            <div class="text-center mt-6">
                <a href="../index.php" class="text-gray-600 hover:text-indigo-600 font-medium inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</body>
</html>