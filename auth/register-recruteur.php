<?php
session_start();

require_once '../config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_entreprise          = trim($_POST['nom_entreprise'] ?? '');
    $email                   = trim($_POST['email'] ?? '');
    $telephone               = trim($_POST['telephone'] ?? '');
    $ville_entreprise        = trim($_POST['ville_entreprise'] ?? '');
    $adresse_professionnelle = trim($_POST['adresse_professionnelle'] ?? '');
    $nombre_employes         = trim($_POST['nombre_employes'] ?? '');
    $description_entreprise  = trim($_POST['description_entreprise'] ?? '');
    $secteur                 = trim($_POST['secteur'] ?? '');
    $site_web                = trim($_POST['site_web'] ?? '');
    $password                = $_POST['password'] ?? '';
    $confirm_password        = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($nom_entreprise) || empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id_user FROM utilisateurs WHERE email = :email");
            $stmt->execute([':email' => $email]);

            if ($stmt->fetch()) {
                $error = "Cet email est déjà utilisé.";
            } else {
                $pdo->beginTransaction();

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $nom_user        = strtoupper($nom_entreprise);
                $tel_user        = !empty($telephone) ? $telephone : '70' . rand(100000, 999999);

                // 1. Insérer dans utilisateurs
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateurs (nom_user, email, password, role, tel_user)
                    VALUES (:nom_user, :email, :password, 'recruteur', :tel_user)
                ");
                $stmt->execute([
                    ':nom_user'  => $nom_user,
                    ':email'     => $email,
                    ':password'  => $hashed_password,
                    ':tel_user'  => $tel_user,
                ]);
                $id_user = $pdo->lastInsertId();

                if (!$id_user) {
                    throw new Exception("Impossible de récupérer l'ID utilisateur.");
                }

                // 2. Insérer dans recruteurs
                $stmt = $pdo->prepare("
                    INSERT INTO recruteurs (id_user, nom_entreprise, ville_entreprise, adresse_professionnelle, nombre_employes, description_entreprise, secteur)
                    VALUES (:id_user, :nom_entreprise, :ville_entreprise, :adresse_professionnelle, :nombre_employes, :description_entreprise, :secteur)
                ");
                $stmt->execute([
                    ':id_user'                 => $id_user,
                    ':nom_entreprise'          => $nom_entreprise,
                    ':ville_entreprise'        => $ville_entreprise,
                    ':adresse_professionnelle' => $adresse_professionnelle,
                    ':nombre_employes'         => $nombre_employes,
                    ':description_entreprise'  => $description_entreprise,
                    ':secteur'                 => $secteur,
                ]);
                $id_recruteur = $pdo->lastInsertId();

                // 3. Insérer dans entreprises (pour apparaître dans la liste)
                $stmt = $pdo->prepare("
                    INSERT INTO entreprises (nom_entreprise, secteur, description, site_web, email_entreprise, telephone, adresse, ville, nombre_employes, logo, statut, id_recruteur)
                    VALUES (:nom_entreprise, :secteur, :description, :site_web, :email_entreprise, :telephone, :adresse, :ville, :nombre_employes, NULL, 'active', :id_recruteur)
                ");
                $stmt->execute([
                    ':nom_entreprise'   => $nom_entreprise,
                    ':secteur'          => $secteur,
                    ':description'      => $description_entreprise,
                    ':site_web'         => !empty($site_web) ? $site_web : null,
                    ':email_entreprise' => $email,
                    ':telephone'        => $telephone,
                    ':adresse'          => $adresse_professionnelle,
                    ':ville'            => $ville_entreprise,
                    ':nombre_employes'  => $nombre_employes,
                    ':id_recruteur'     => $id_recruteur,
                ]);

                $pdo->commit();

                $success = "Inscription réussie ! Votre compte recruteur a été créé.<br>Email : <strong>" . htmlspecialchars($email) . "</strong><br>Vous serez redirigé vers la connexion...";
                header("refresh:3;url=login.php");
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Erreur lors de l'inscription : " . $e->getMessage();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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
    <title>Inscription Recruteur - NextCareer</title>
    <link rel="stylesheet" href="../assets/css/tailwind.min.css">
    <script>
        tailwind.config = {
            theme: { extend: { colors: { primary: '#667eea', secondary: '#764ba2' } } }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    </style>
</head>
<body class="bg-gray-50">
<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl w-full">

        <!-- Logo -->
        <div class="text-center mb-8">
            <div style="font-family:'Poppins',sans-serif;font-size:2rem;font-weight:700;color:#1a202c;position:relative;display:inline-block;">
                Next<span style="color:#667eea;">Career</span>
                <div style="position:absolute;bottom:4px;left:0;width:100%;height:4px;background:linear-gradient(90deg,#667eea 0%,#764ba2 100%);border-radius:2px;"></div>
            </div>
            <h2 class="text-3xl font-bold text-gray-900 mt-4">Inscription Recruteur</h2>
            <p class="mt-2 text-gray-600">Créez votre compte entreprise pour recruter</p>
        </div>

        <!-- Alertes -->
        <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center">
            <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <div><?= $error ?></div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center">
            <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <div><?= $success ?></div>
        </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <form method="POST" action="register-recruteur.php" class="space-y-4">
                <div class="grid md:grid-cols-2 gap-4">

                    <!-- Nom entreprise -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom de l'entreprise <span class="text-red-500">*</span></label>
                        <input type="text" name="nom_entreprise" required
                            value="<?= htmlspecialchars($_POST['nom_entreprise'] ?? '') ?>"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Ex: Digital Solutions BF">
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email professionnel <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="contact@entreprise.com">
                    </div>

                    <!-- Téléphone -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                        <input type="tel" name="telephone"
                            value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="+226 25 XX XX XX">
                    </div>

                    <!-- Secteur -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Secteur d'activité</label>
                        <select name="secteur" class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">Sélectionnez un secteur</option>
                            <?php
                            $secteurs_list = [
                                'Informatique & Tech', 'Banque & Finance', 'Santé & Pharmaceutique',
                                'Agriculture & Élevage', 'Énergie & BTP', 'Commerce & Distribution',
                                'Télécommunications', 'Agroalimentaire', 'Conseil & Formation',
                                'Transport & Logistique', 'Éducation', 'Médias & Communication',
                                'Industrie & Fabrication', 'Services Publics', 'Tourisme & Hôtellerie',
                                'ONG & Humanitaire', 'Immobilier', 'Autre'
                            ];
                            foreach ($secteurs_list as $s):
                                $sel = (($_POST['secteur'] ?? '') == $s) ? 'selected' : '';
                            ?>
                            <option value="<?= $s ?>" <?= $sel ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ville -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Ville</label>
                        <select name="ville_entreprise" class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">Sélectionnez une ville</option>
                            <?php
                            $villes = ['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou', 'Ouahigouya', 'Banfora', 'Dédougou', 'Fada N\'Gourma', 'Kaya', 'Tenkodogo', 'Ziniaré'];
                            foreach ($villes as $v):
                                $sel = (($_POST['ville_entreprise'] ?? '') == $v) ? 'selected' : '';
                            ?>
                            <option value="<?= $v ?>" <?= $sel ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Nombre employés -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre d'employés</label>
                        <select name="nombre_employes" class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">Sélectionnez</option>
                            <?php
                            $tailles = ['1-10', '11-50', '51-100', '101-500', '500-1000', '1000+'];
                            foreach ($tailles as $t):
                                $sel = (($_POST['nombre_employes'] ?? '') == $t) ? 'selected' : '';
                            ?>
                            <option value="<?= $t ?>" <?= $sel ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Site web -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Site web</label>
                        <input type="url" name="site_web"
                            value="<?= htmlspecialchars($_POST['site_web'] ?? '') ?>"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="https://www.monentreprise.com">
                    </div>

                    <!-- Adresse -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Adresse professionnelle</label>
                        <input type="text" name="adresse_professionnelle"
                            value="<?= htmlspecialchars($_POST['adresse_professionnelle'] ?? '') ?>"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Secteur 4, Avenue Kwame N'Krumah, Ouagadougou">
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description de l'entreprise</label>
                        <textarea name="description_entreprise" rows="4"
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Décrivez votre entreprise, ses activités, sa mission..."><?= htmlspecialchars($_POST['description_entreprise'] ?? '') ?></textarea>
                        <p class="text-xs text-gray-400 mt-1">Cette description sera visible sur votre profil entreprise.</p>
                    </div>

                    <!-- Mot de passe -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mot de passe <span class="text-red-500">*</span></label>
                        <input type="password" name="password" required
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Minimum 6 caractères">
                    </div>

                    <!-- Confirmer mot de passe -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirmer le mot de passe <span class="text-red-500">*</span></label>
                        <input type="password" name="confirm_password" required
                            class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            placeholder="Répétez votre mot de passe">
                    </div>
                </div>

                <!-- CGU -->
                <div class="flex items-start">
                    <input id="terms" name="terms" type="checkbox" required
                        class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded mt-1">
                    <label for="terms" class="ml-2 text-sm text-gray-700">
                        J'accepte les <a href="#" class="text-green-600 hover:text-green-700 font-medium">conditions d'utilisation</a>
                        et la <a href="#" class="text-green-600 hover:text-green-700 font-medium">politique de confidentialité</a>
                    </label>
                </div>

                <!-- Bouton -->
                <button type="submit"
                    class="w-full gradient-bg text-white py-3 px-4 rounded-xl font-medium hover:opacity-90 transition duration-200 flex items-center justify-center gap-2">
                    Créer mon compte recruteur
                </button>
            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Vous avez déjà un compte ?
                    <a href="login.php" class="text-green-600 hover:text-green-700 font-medium">Se connecter</a>
                </p>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="../index.php" class="text-gray-600 hover:text-green-600 font-medium inline-flex items-center">
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