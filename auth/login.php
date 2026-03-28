<?php
session_start();

// Inclure la connexion à la base de données
require_once '../config/db.php';

$error = '';
$success = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        try {
            // Vérifier si l'utilisateur existe
            $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Mot de passe correct
                
                // Récupérer les infos selon le rôle
                if ($user['role'] === 'candidat') {
                    // Faire une jointure pour récupérer le candidat via id_user
                    $stmt = $pdo->prepare("SELECT * FROM candidats WHERE id_user = :id_user");
                    $stmt->bindParam(':id_user', $user['id_user']);
                    $stmt->execute();
                    $profile = $stmt->fetch();
                    
                    if ($profile) {
                        $_SESSION['user_id'] = $profile['id_candidat'];
                        $_SESSION['user_type'] = 'candidat';
                        $_SESSION['prenom'] = $profile['prenom'];
                        $_SESSION['nom'] = $profile['nom'];
                        $_SESSION['email'] = $user['email'];
                        
                        header('Location: ../pages/candidat/dashboard.php');
                        exit();
                    } else {
                        $error = "Profil candidat introuvable. Veuillez contacter l'administrateur.";
                    }
                } elseif ($user['role'] === 'recruteur') {
                    // Faire une jointure pour récupérer le recruteur via id_user
                    $stmt = $pdo->prepare("SELECT * FROM recruteurs WHERE id_user = :id_user");
                    $stmt->bindParam(':id_user', $user['id_user']);
                    $stmt->execute();
                    $profile = $stmt->fetch();
                    
                    if ($profile) {
                        $_SESSION['user_id'] = $profile['id_recruteur'];
                        $_SESSION['user_type'] = 'recruteur';
                        $_SESSION['nom_entreprise'] = $profile['nom_entreprise'];
                        $_SESSION['email'] = $user['email'];
                        
                        header('Location: ../pages/recruteur/dashboard.php');
                        exit();
                    } else {
                        $error = "Profil recruteur introuvable. Veuillez contacter l'administrateur.";
                    }
                } elseif ($user['role'] === 'admin') {
                    $_SESSION['user_id'] = $user['id_user'];
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['nom_user'] = $user['nom_user'];
                    $_SESSION['email'] = $user['email'];
                    
                    header('Location: ../pages/admin/dashboard.php');
                    exit();
                }
            } else {
                $error = "Email ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Erreur de connexion : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - NextCareer</title>
    
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
                <h2 class="text-3xl font-bold text-gray-900">Connexion</h2>
                <p class="mt-2 text-gray-600">Accédez à votre espace personnel</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <?= htmlspecialchars($error) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <?= htmlspecialchars($success) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <form method="POST" action="login.php" class="space-y-6">
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Adresse email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                required
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="votreemail@exemple.com"
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Mot de passe
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="••••••••"
                            >
                        </div>
                    </div>

                    <!-- Remember & Forgot -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input 
                                id="remember" 
                                name="remember" 
                                type="checkbox"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            >
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                Se souvenir de moi
                            </label>
                        </div>
                        <a href="forgot-password.php" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                            Mot de passe oublié ?
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit"
                        class="w-full gradient-bg text-white py-3 px-4 rounded-xl font-medium hover:opacity-90 transition duration-200 flex items-center justify-center"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        Se connecter
                    </button>
                </form>

                <!-- Divider -->
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">Nouveau sur NextCareer ?</span>
                        </div>
                    </div>
                </div>

                <!-- Register Links -->
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <a href="register-candidat.php" class="flex items-center justify-center px-4 py-3 border-2 border-indigo-600 text-indigo-600 rounded-xl hover:bg-indigo-50 transition font-medium">
                        Candidat
                    </a>
                    <a href="register-recruteur.php" class="flex items-center justify-center px-4 py-3 border-2 border-green-600 text-green-600 rounded-xl hover:bg-green-50 transition font-medium">
                        Recruteur
                    </a>
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

            <!-- Test Info -->
            <div class="mt-8 bg-blue-50 border border-blue-200 rounded-xl p-4">
                <h3 class="text-sm font-semibold text-blue-900 mb-2"> Comptes existants</h3>
                <div class="space-y-2 text-xs text-blue-800">
                    <p>Utilisez les comptes déjà créés dans votre base de données.</p>
                    <p class="font-medium">Vérifiez la table <code class="bg-blue-100 px-2 py-1 rounded">utilisateurs</code> dans phpMyAdmin.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>