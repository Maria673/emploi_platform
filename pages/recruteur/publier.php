<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'recruteur') {
    header('Location: ../../auth/login.php');
    exit();
}

// ✅ CORRECT : $_SESSION['user_id'] contient déjà l'id_recruteur (voir login.php ligne 44)
$id_recruteur = $_SESSION['user_id'];

$error = '';
$success = '';

// Récupérer les infos du recruteur pour l'affichage navbar
try {
    $stmt = $pdo->prepare("SELECT id_recruteur, nom_entreprise FROM recruteurs WHERE id_recruteur = :id");
    $stmt->execute([':id' => $id_recruteur]);
    $recruteur = $stmt->fetch();

    if (!$recruteur) {
        header('Location: ../../auth/logout.php');
        exit();
    }
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Récupérer les domaines
try {
    $stmt = $pdo->query("SELECT * FROM domaines ORDER BY nom_domaine");
    $domaines = $stmt->fetchAll();
} catch (PDOException $e) {
    $domaines = [];
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'publier') {
        $titre_emploi      = trim($_POST['titre_emploi'] ?? '');
        $id_domaine        = $_POST['id_domaine'] ?? null;
        $localisation      = trim($_POST['localisation'] ?? '');
        $type_contrat      = trim($_POST['type_contrat'] ?? '');
        $niveau_experience = trim($_POST['niveau_experience'] ?? '');
        $salaire_min       = $_POST['salaire_min'] ?? null;
        $salaire_max       = $_POST['salaire_max'] ?? null;
        $date_limite       = $_POST['date_limite'] ?? null;
        $description_offre = trim($_POST['description_offre'] ?? '');
        $profil_recherche  = trim($_POST['profil_recherche'] ?? '');
        $avantages         = trim($_POST['avantages'] ?? '');
        $competences       = $_POST['competences'] ?? [];

        if (empty($titre_emploi) || empty($id_domaine) || empty($localisation) || empty($description_offre)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif (count($competences) < 3) {
            $error = "Veuillez ajouter au moins 3 compétences requises.";
        } elseif (strlen($description_offre) < 100) {
            $error = "La description doit contenir au moins 100 caractères.";
        } else {
            try {
                $competences_str = implode(', ', $competences);

                $stmt = $pdo->prepare("
                    INSERT INTO offres_emploi (
                        id_recruteur, id_domaine, titre_emploi, localisation,
                        description_offre, competences_requises, salaire_min,
                        salaire_max, avantages, profil_recherche,
                        date_publication, statut_recherche, date_limite
                    ) VALUES (
                        :id_recruteur, :id_domaine, :titre_emploi, :localisation,
                        :description_offre, :competences_requises, :salaire_min,
                        :salaire_max, :avantages, :profil_recherche,
                        NOW(), :statut_recherche, :date_limite
                    )
                ");

                $stmt->execute([
                    ':id_recruteur'         => $id_recruteur,
                    ':titre_emploi'         => $titre_emploi,
                    ':id_domaine'           => $id_domaine,
                    ':localisation'         => $localisation,
                    ':description_offre'    => $description_offre,
                    ':competences_requises' => $competences_str,
                    ':salaire_min'          => $salaire_min ?: null,
                    ':salaire_max'          => $salaire_max ?: null,
                    ':avantages'            => $avantages ?: null,
                    ':profil_recherche'     => $profil_recherche ?: null,
                    ':statut_recherche'     => $type_contrat ?: 'CDI',
                    ':date_limite'          => $date_limite ?: null,
                ]);

                $success = "Offre publiée avec succès !";
                header("refresh:2;url=dashboard.php");

            } catch (PDOException $e) {
                $error = "Erreur lors de la publication : " . $e->getMessage();
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
    <title>Publier une offre - NextCareer</title>
    
    <!-- Tailwind CSS CDN -->
    <link rel="stylesheet" href="../../assets/css/tailwind.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#50C878',
                        'primary-dark': '#2EAD5A',
                        'primary-light': '#7FD89F',
                        'primary-pale': '#E8F7F0',
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
        
        /* Custom color palette based on #50C878 */
        .bg-primary { background-color: #50C878; }
        .bg-primary-dark { background-color: #2EAD5A; }
        .bg-primary-light { background-color: #7FD89F; }
        .bg-primary-pale { background-color: #E8F7F0; }
        .text-primary { color: #50C878; }
        .text-primary-dark { color: #3A8B5C; }
        .border-primary { border-color: #50C878; }
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

                <!-- Center Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/index.php" class="text-gray-600 hover:text-green-600 transition font-medium">Accueil</a>
                    <a href="/offres.php" class="text-gray-600 hover:text-green-600 transition font-medium">Offres d'emploi</a>
                    <a href="/entreprises.php" class="text-gray-600 hover:text-green-600 transition font-medium">Entreprises</a>
                </div>

                <!-- Right Side - User Info -->
                 <div class="flex items-center space-x-4">
                <div class="hidden sm:flex items-center space-x-3 border-r border-gray-300 pr-4">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                        <span class="text-lg font-bold text-green-600">
                            <?= strtoupper(substr($recruteur['nom_entreprise'] ?? 'E', 0, 1)) ?>
                        </span>
                    </div>
                    <div class="text-sm">
                        <p class="font-medium text-gray-900"><?= htmlspecialchars($recruteur['nom_entreprise'] ?? 'Entreprise') ?></p>
                        <p class="text-xs text-gray-500">Recruteur</p>
                    </div>
                </div>

               <a href="../../auth/logout.php" class="gradient-bg text-white px-6 py-2 rounded-full hover:opacity-90 transition font-medium text-sm">
                    Déconnexion
                </a>
            </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
         <!-- Back link -->
    <a href="dashboard.php" class="inline-flex items-center gap-2 hover:opacity-80 mb-6 text-sm font-medium text-green-600">
        ← Retour au tableau de bord
    </a>
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <h1 class="text-3xl font-bold text-gray-900">Publier une offre d'emploi</h1>
            </div>
            <p class="text-gray-600">Créez une offre attractive pour attirer les meilleurs talents</p>
        </div>

        <!-- Progress Bar -->
        <div class="bg-white rounded-xl p-4 mb-6 border border-gray-200">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-700">Progression du formulaire</span>
                <span class="text-sm font-bold text-green-600" id="progress-percentage">0%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="h-2 rounded-full transition-all duration-300" id="progress-bar" style="width: 0%; background: linear-gradient(90deg, #50C878 0%, #2EAD5A 100%);"></div>
            </div>
            <p class="text-xs text-gray-500 mt-2">Continuez à remplir le formulaire...</p>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-t-xl border-b border-gray-200">
            <div class="flex">
                <button onclick="showTab('edition')" id="tab-edition" class="px-6 py-3 font-medium border-b-2" style="color: #50C878; border-color: #50C878;">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Édition
                </button>
                <button onclick="showTab('preview')" id="tab-preview" class="px-6 py-3 font-medium text-gray-600 transition" onmouseover="if(!this.classList.contains('active')) this.style.color='#50C878'" onmouseout="if(!this.classList.contains('active')) this.style.color='#6b7280'">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Prévisualisation
                </button>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 px-4 py-3 rounded-xl bg-green-50 border border-green-200 text-green-700">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="job-form">
            <div id="edition-content">
                <div class="grid lg:grid-cols-3 gap-6">
                    <!-- Left Column (2/3) -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Informations de base -->
                        <div class="bg-white rounded-xl p-6 border border-gray-200">
                            <div class="flex items-center mb-6">
                                <h2 class="text-xl font-bold text-gray-900">Informations de base</h2>
                            </div>

                            <!-- Titre du poste -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Titre du poste <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    name="titre_emploi"
                                    id="titre_emploi"
                                    required
                                    maxlength="100"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                                    placeholder="Ex: Développeur Full-Stack React/Node.js"
                                    oninput="updateCharCount(this, 'title-count', 100); updateProgress();"
                                    onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                                    onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                                >
                                <p class="text-xs text-gray-500 mt-1"><span id="title-count">0</span>/100 caractères</p>
                            </div>

                            <div class="grid md:grid-cols-2 gap-4">
                                <!-- Domaine d'activité -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Domaine d'activité <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="id_domaine_input"
                                            autocomplete="off"
                                            placeholder="Tapez ou sélectionnez un domaine"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                                            oninput="filterDomaines()"
                                            onfocus="showDomaineSuggestions(); this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                                            onblur="setTimeout(() => {this.style.borderColor='#d1d5db'; this.style.boxShadow='none'}, 200)"
                                        >
                                        <input type="hidden" id="id_domaine" name="id_domaine" required>
                                        <div id="domaines-suggestions" class="absolute top-full left-0 right-0 bg-white border border-gray-300 rounded-xl mt-1 max-h-48 overflow-y-auto z-10 hidden shadow-lg"></div>
                                    </div>
                                </div>

                                <!-- Type de contrat -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Type de contrat <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="type_contrat_input"
                                            autocomplete="off"
                                            placeholder="Tapez ou sélectionnez un type"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                                            oninput="filterTypeContrat()"
                                            onfocus="showTypeContratSuggestions(); this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                                            onblur="setTimeout(() => {this.style.borderColor='#d1d5db'; this.style.boxShadow='none'}, 200)"
                                        >
                                        <input type="hidden" id="type_contrat" name="type_contrat" required>
                                        <div id="type-contrat-suggestions" class="absolute top-full left-0 right-0 bg-white border border-gray-300 rounded-xl mt-1 max-h-48 overflow-y-auto z-10 hidden shadow-lg"></div>
                                    </div>
                                </div>

                                <!-- Localisation -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Localisation <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="localisation_input"
                                            autocomplete="off"
                                            placeholder="Tapez ou sélectionnez une ville"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                                            oninput="filterLocalisation()"
                                            onfocus="showLocalisationSuggestions(); this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                                            onblur="setTimeout(() => {this.style.borderColor='#d1d5db'; this.style.boxShadow='none'}, 200)"
                                        >
                                        <input type="hidden" id="localisation" name="localisation" required>
                                        <div id="localisation-suggestions" class="absolute top-full left-0 right-0 bg-white border border-gray-300 rounded-xl mt-1 max-h-48 overflow-y-auto z-10 hidden shadow-lg"></div>
                                    </div>
                                </div>

                                <!-- Niveau d'expérience -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Niveau d'expérience <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="text" 
                                            id="niveau_experience_input"
                                            autocomplete="off"
                                            placeholder="Tapez ou sélectionnez un niveau"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                                            oninput="filterNiveauExperience()"
                                            onfocus="showNiveauExperienceSuggestions(); this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                                            onblur="setTimeout(() => {this.style.borderColor='#d1d5db'; this.style.boxShadow='none'}, 200)"
                                        >
                                        <input type="hidden" id="niveau_experience" name="niveau_experience" required>
                                        <div id="niveau-experience-suggestions" class="absolute top-full left-0 right-0 bg-white border border-gray-300 rounded-xl mt-1 max-h-48 overflow-y-auto z-10 hidden shadow-lg"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description du poste -->
                        <div class="bg-white rounded-xl p-6 border border-gray-200">
                            <div class="flex items-center mb-6">
                                <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h2 class="text-xl font-bold text-gray-900">Description du poste</h2>
                            </div>

                            <!-- Description détaillée -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Description détaillée <span class="text-red-500">*</span>
                                </label>
                                <textarea 
                                    name="description_offre"
                                    id="description_offre"
                                    required
                                    rows="6"
                                    maxlength="5000"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2"
                                    placeholder="Décrivez le poste, les missions principales, le contexte de travail..."
                                    oninput="updateCharCount(this, 'desc-count', 5000); updateProgress();"
                                    onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                                    onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                                ></textarea>
                                <p class="text-xs text-gray-500 mt-1"><span id="desc-count">0</span>/5000 caractères (min. 100)</p>
                            </div>
                        </div>

                        <!-- Compétences requises -->
                        <div class="bg-white rounded-xl p-6 border border-gray-200">
                            <div class="flex items-center mb-6">
                                <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                                <h2 class="text-xl font-bold text-gray-900">Compétences requises</h2>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Ajouter des compétences <span class="text-red-500">*</span>
                                </label>
                                <div class="flex gap-2">
                                    <input 
                                        type="text" 
                                        id="competence-input"
                                        class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:border-transparent"
                                        placeholder="Tapez une compétence et appuyez sur Entrée"
                                        onkeypress="if(event.key==='Enter'){event.preventDefault();addCompetence();}"
                                        onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                                        onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                                    >
                                    <button 
                                        type="button"
                                        onclick="addCompetence()"
                                        class="px-6 py-3 text-white rounded-xl transition font-medium"
                                        style="background: linear-gradient(135deg, #50C878 0%, #2EAD5A 100%);"
                                        onmouseover="this.style.background='linear-gradient(135deg, #2EAD5A 0%, #3A8B5C 100%)'"
                                        onmouseout="this.style.background='linear-gradient(135deg, #50C878 0%, #2EAD5A 100%)'">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1"><span id="comp-count">0</span>/15 compétences (min. 3 requises)</p>
                            </div>

                            <!-- Liste des compétences ajoutées -->
                            <div id="competences-list" class="flex flex-wrap gap-2 mb-4"></div>

                            <!-- Suggestions prédéfinies -->
                            <div class="mb-4">
                                <p class="text-sm font-medium text-gray-700 mb-2">Suggestions rapides :</p>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" onclick="addCompetence('JavaScript')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm transition" onmouseover="this.style.backgroundColor='#E8F7F0'; this.style.color='#3A8B5C'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'">+ JavaScript</button>
                                    <button type="button" onclick="addCompetence('React')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm transition" onmouseover="this.style.backgroundColor='#E8F7F0'; this.style.color='#3A8B5C'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'">+ React</button>
                                    <button type="button" onclick="addCompetence('Node.js')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm transition" onmouseover="this.style.backgroundColor='#E8F7F0'; this.style.color='#3A8B5C'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'">+ Node.js</button>
                                    <button type="button" onclick="addCompetence('Python')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm transition" onmouseover="this.style.backgroundColor='#E8F7F0'; this.style.color='#3A8B5C'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'">+ Python</button>
                                    <button type="button" onclick="addCompetence('Java')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm transition" onmouseover="this.style.backgroundColor='#E8F7F0'; this.style.color='#3A8B5C'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'">+ Java</button>
                                    <button type="button" onclick="addCompetence('PHP')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm transition" onmouseover="this.style.backgroundColor='#E8F7F0'; this.style.color='#3A8B5C'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'">+ PHP</button>
                                    <button type="button" onclick="addCompetence('SQL')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm transition" onmouseover="this.style.backgroundColor='#E8F7F0'; this.style.color='#3A8B5C'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'">+ SQL</button>
                                    <button type="button" onclick="addCompetence('Excel')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm transition" onmouseover="this.style.backgroundColor='#E8F7F0'; this.style.color='#3A8B5C'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'">+ Excel</button>
                                    <button type="button" onclick="addCompetence('Word')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm transition" onmouseover="this.style.backgroundColor='#E8F7F0'; this.style.color='#3A8B5C'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'">+ Word</button>
                                    <button type="button" onclick="addCompetence('PowerPoint')" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm transition" onmouseover="this.style.backgroundColor='#E8F7F0'; this.style.color='#3A8B5C'" onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.color='#374151'">+ PowerPoint</button>
                                </div>
                            </div>

                            <!-- Message d'avertissement -->
                            <div id="competence-warning" class="hidden mt-4 bg-orange-50 border border-orange-200 text-orange-700 px-4 py-3 rounded-xl text-sm">
                                <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Ajoutez au moins 3 compétences pour publier l'offre
                            </div>

                            <!-- Message de limite atteinte -->
                            <div id="competence-max-warning" class="hidden mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                                <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                Vous avez atteint le nombre maximum de 15 compétences
                            </div>
                        </div>
                    </div>

                    <!-- Right Column (1/3) -->
                    <div class="space-y-6">
                        <!-- Rémunération -->
                        <div class="bg-white rounded-xl p-6 border border-gray-200">
                            <div class="flex items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-900">Rémunération</h3>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Salaire minimum (FCFA)
                                    </label>
                                    <input 
                                        type="number" 
                                        name="salaire_min"
                                        min="0"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2"
                                        placeholder="Ex: 200000"
                                        onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                                        onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                                    >
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Salaire maximum (FCFA)
                                    </label>
                                    <input 
                                        type="number" 
                                        name="salaire_max"
                                        min="0"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2"
                                        placeholder="Ex: 500000"
                                        onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                                        onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                                    >
                                </div>

                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                    <p class="text-xs text-yellow-800">
                                        <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        Indiquer un salaire augmente vos chances d'attirer des candidats qualifiés
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Date limite -->
                        <div class="bg-white rounded-xl p-6 border border-gray-200">
                            <div class="flex items-center mb-4">
                                <h3 class="text-lg font-bold text-gray-900">Date limite</h3>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Date limite de candidature <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="date" 
                                    name="date_limite"
                                    required
                                    min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2"
                                    onchange="updateProgress()"
                                    onfocus="this.style.borderColor='#50C878'; this.style.boxShadow='0 0 0 3px rgba(80, 200, 120, 0.1)'"
                                    onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                                >
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="bg-white rounded-xl p-6 border border-gray-200">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">Actions</h3>
                            
                            <div class="space-y-3">
                                <button 
                                    type="button"
                                    onclick="showTab('preview')"
                                    class="w-full flex items-center justify-center px-4 py-3 bg-white border-2 rounded-xl transition font-medium"
                                    style="border-color: #50C878; color: #50C878;"
                                    onmouseover="this.style.backgroundColor='#E8F7F0'"
                                    onmouseout="this.style.backgroundColor='white'">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Prévisualiser
                                </button>

                                <button 
                                    type="button"
                                    class="w-full flex items-center justify-center px-4 py-3 bg-white border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                    </svg>
                                    Sauvegarder brouillon
                                </button>

                                <button 
                                    type="submit"
                                    name="action"
                                    value="publier"
                                    id="publish-btn"
                                    disabled
                                    class="w-full flex items-center justify-center px-4 py-3 text-white rounded-xl transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                                    style="background: linear-gradient(135deg, #50C878 0%, #2EAD5A 100%);"
                                    onmouseover="if(!this.disabled) this.style.background='linear-gradient(135deg, #2EAD5A 0%, #3A8B5C 100%)'"
                                    onmouseout="if(!this.disabled) this.style.background='linear-gradient(135deg, #50C878 0%, #2EAD5A 100%)'">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                    Publier l'offre
                                </button>

                                <p class="text-xs text-center text-gray-500">Complétez le formulaire pour pouvoir publier</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Tab -->
            <div id="preview-content" class="hidden">
                <div class="bg-white rounded-xl p-8 border border-gray-200">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Prévisualisation de l'offre</h2>
                    <div id="preview-html">
                        <p class="text-gray-500">Remplissez le formulaire pour voir la prévisualisation...</p>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        let competences = [];
        const maxCompetences = 15;
        const minCompetences = 3;

        // Données des domaines (récupérées du PHP)
        const domainesData = [
            <?php foreach ($domaines as $domaine): ?>
                { id: '<?= $domaine['id_domaine'] ?>', nom: '<?= htmlspecialchars($domaine['nom_domaine']) ?>' },
            <?php endforeach; ?>
        ];

        // Données des types de contrat
        const typeContratData = [
            { id: 'CDI', nom: 'CDI' },
            { id: 'CDD', nom: 'CDD' },
            { id: 'Stage', nom: 'Stage' },
            { id: 'Freelance', nom: 'Freelance' },
            { id: 'Temps partiel', nom: 'Temps partiel' }
        ];

        // Données des localisations
        const localisationData = [
            { id: 'Ouagadougou', nom: 'Ouagadougou' },
            { id: 'Bobo-Dioulasso', nom: 'Bobo-Dioulasso' },
            { id: 'Koudougou', nom: 'Koudougou' },
            { id: 'Ouahigouya', nom: 'Ouahigouya' },
            { id: 'Banfora', nom: 'Banfora' }
        ];

        // Données des niveaux d'expérience
        const niveauExperienceData = [
            { id: 'Débutant', nom: 'Débutant (0-2 ans)' },
            { id: 'Intermédiaire', nom: 'Intermédiaire (2-5 ans)' },
            { id: 'Confirmé', nom: 'Confirmé (5-10 ans)' },
            { id: 'Expert', nom: 'Expert (10+ ans)' }
        ];

        // Filtrer domaines
        function filterDomaines() {
            const input = document.getElementById('id_domaine_input');
            const filter = input.value.toLowerCase();
            const filtered = domainesData.filter(item => item.nom.toLowerCase().includes(filter));
            showSuggestions(filtered, 'domaines-suggestions', 'id_domaine', 'id_domaine_input');
        }

        function showDomaineSuggestions() {
            const input = document.getElementById('id_domaine_input');
            if (input.value.length === 0) {
                showSuggestions(domainesData, 'domaines-suggestions', 'id_domaine', 'id_domaine_input');
            }
        }

        // Filtrer type contrat
        function filterTypeContrat() {
            const input = document.getElementById('type_contrat_input');
            const filter = input.value.toLowerCase();
            const filtered = typeContratData.filter(item => item.nom.toLowerCase().includes(filter));
            showSuggestions(filtered, 'type-contrat-suggestions', 'type_contrat', 'type_contrat_input');
        }

        function showTypeContratSuggestions() {
            const input = document.getElementById('type_contrat_input');
            if (input.value.length === 0) {
                showSuggestions(typeContratData, 'type-contrat-suggestions', 'type_contrat', 'type_contrat_input');
            }
        }

        // Filtrer localisation
        function filterLocalisation() {
            const input = document.getElementById('localisation_input');
            const filter = input.value.toLowerCase();
            const filtered = localisationData.filter(item => item.nom.toLowerCase().includes(filter));
            showSuggestions(filtered, 'localisation-suggestions', 'localisation', 'localisation_input');
        }

        function showLocalisationSuggestions() {
            const input = document.getElementById('localisation_input');
            if (input.value.length === 0) {
                showSuggestions(localisationData, 'localisation-suggestions', 'localisation', 'localisation_input');
            }
        }

        // Filtrer niveau expérience
        function filterNiveauExperience() {
            const input = document.getElementById('niveau_experience_input');
            const filter = input.value.toLowerCase();
            const filtered = niveauExperienceData.filter(item => item.nom.toLowerCase().includes(filter));
            showSuggestions(filtered, 'niveau-experience-suggestions', 'niveau_experience', 'niveau_experience_input');
        }

        function showNiveauExperienceSuggestions() {
            const input = document.getElementById('niveau_experience_input');
            if (input.value.length === 0) {
                showSuggestions(niveauExperienceData, 'niveau-experience-suggestions', 'niveau_experience', 'niveau_experience_input');
            }
        }

        // Afficher les suggestions
        function showSuggestions(data, suggestionId, hiddenFieldId, inputFieldId) {
            const suggestionsList = document.getElementById(suggestionId);
            
            if (data.length === 0) {
                suggestionsList.classList.add('hidden');
                return;
            }

            suggestionsList.innerHTML = data.map(item => `
                <div class="px-4 py-3 hover:bg-primary-pale cursor-pointer border-b border-gray-100 transition" 
                     onclick="selectSuggestion('${item.id}', '${item.nom}', '${hiddenFieldId}', '${inputFieldId}', '${suggestionId}')">
                    <p class="text-sm text-gray-900 font-medium">${item.nom}</p>
                </div>
            `).join('');

            suggestionsList.classList.remove('hidden');
        }

        // Sélectionner une suggestion
        function selectSuggestion(id, nom, hiddenFieldId, inputFieldId, suggestionId) {
            document.getElementById(hiddenFieldId).value = id;
            document.getElementById(inputFieldId).value = nom;
            document.getElementById(suggestionId).classList.add('hidden');
            updateProgress();
        }

        // Fermer les suggestions
        document.addEventListener('click', function(event) {
            const suggestionBoxes = document.querySelectorAll('[id$="-suggestions"]');
            suggestionBoxes.forEach(box => {
                if (!box.parentElement.contains(event.target)) {
                    box.classList.add('hidden');
                }
            });
        });

        // ==================== COMPÉTENCES ====================
        
        function updateCharCount(element, countId, max) {
            document.getElementById(countId).textContent = element.value.length;
        }

        function addCompetence(competenceValue) {
            const input = document.getElementById('competence-input');
            const value = (competenceValue || input.value || '').trim();
            
            if (!value) {
                input.focus();
                input.style.borderColor = '#ef4444';
                setTimeout(() => {
                    input.style.borderColor = '';
                }, 2000);
                return;
            }
            
            if (competences.includes(value)) {
                alert('Cette compétence est déjà ajoutée');
                return;
            }
            
            if (competences.length >= maxCompetences) {
                document.getElementById('competence-max-warning').classList.remove('hidden');
                return;
            }
            
            competences.push(value);
            updateCompetencesList();
            input.value = '';
            input.focus();
            updateProgress();
            document.getElementById('competence-max-warning').classList.add('hidden');
        }

        function removeCompetence(index) {
            competences.splice(index, 1);
            updateCompetencesList();
            updateProgress();
        }

        function updateCompetencesList() {
            const list = document.getElementById('competences-list');
            const warning = document.getElementById('competence-warning');
            
            list.innerHTML = competences.map((comp, index) => `
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-green-100 text-green-700">
                    <input type="hidden" name="competences[]" value="${comp}">
                    <span class="text-sm font-medium">${comp}</span>
                    <button type="button" onclick="removeCompetence(${index})" class="rounded-full p-0.5 transition hover:bg-opacity-80">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            `).join('');

            document.getElementById('comp-count').textContent = competences.length;

            if (competences.length < minCompetences) {
                warning.classList.remove('hidden');
            } else {
                warning.classList.add('hidden');
            }
        }

        function updateProgress() {
            const form = document.getElementById('job-form');
            const requiredFields = form.querySelectorAll('[required]');
            let completed = 0;
            let total = requiredFields.length + 1;

            requiredFields.forEach(field => {
                if (field.value.trim()) completed++;
            });

            if (competences.length >= minCompetences) completed++;

            const percentage = Math.round((completed / total) * 100);
            document.getElementById('progress-bar').style.width = percentage + '%';
            document.getElementById('progress-percentage').textContent = percentage + '%';

            const publishBtn = document.getElementById('publish-btn');
            if (percentage === 100) {
                publishBtn.disabled = false;
            } else {
                publishBtn.disabled = true;
            }
        }

        function showTab(tab) {
            if (tab === 'edition') {
                document.getElementById('edition-content').classList.remove('hidden');
                document.getElementById('preview-content').classList.add('hidden');
                document.getElementById('tab-edition').style.color = '#50C878';
                document.getElementById('tab-edition').style.borderColor = '#50C878';
                document.getElementById('tab-edition').classList.add('active', 'border-b-2');
                document.getElementById('tab-preview').style.color = '#6b7280';
                document.getElementById('tab-preview').style.borderColor = 'transparent';
                document.getElementById('tab-preview').classList.remove('active', 'border-b-2');
            } else {
                document.getElementById('edition-content').classList.add('hidden');
                document.getElementById('preview-content').classList.remove('hidden');
                document.getElementById('tab-preview').style.color = '#50C878';
                document.getElementById('tab-preview').style.borderColor = '#50C878';
                document.getElementById('tab-preview').classList.add('active', 'border-b-2');
                document.getElementById('tab-edition').style.color = '#6b7280';
                document.getElementById('tab-edition').style.borderColor = 'transparent';
                document.getElementById('tab-edition').classList.remove('active', 'border-b-2');
                generatePreview();
            }
        }

        function generatePreview() {
            const titre = document.getElementById('titre_emploi').value;
            const description = document.getElementById('description_offre').value;
            const profil = document.querySelector('[name="profil_recherche"]')?.value || '';
            const avantages = document.querySelector('[name="avantages"]')?.value || '';
            
            let html = '<div class="space-y-6">';
            
            if (titre) {
                html += `<h3 class="text-2xl font-bold text-gray-900">${titre}</h3>`;
            }
            
            if (description) {
                html += `
                    <div>
                        <h4 class="font-bold text-gray-900 mb-2">Description du poste</h4>
                        <p class="text-gray-700 whitespace-pre-line">${description}</p>
                    </div>
                `;
            }
            
            if (profil) {
                html += `
                    <div>
                        <h4 class="font-bold text-gray-900 mb-2">Profil recherché</h4>
                        <p class="text-gray-700 whitespace-pre-line">${profil}</p>
                    </div>
                `;
            }
            
            if (competences.length > 0) {
                html += `
                    <div>
                        <h4 class="font-bold text-gray-900 mb-2">Compétences requises</h4>
                        <div class="flex flex-wrap gap-2">
                            ${competences.map(c => `<span class="px-3 py-1 rounded-full text-sm bg-green-100 text-green-700">${c}</span>`).join('')}
                        </div>
                    </div>
                `;
            }
            
            if (avantages) {
                html += `
                    <div>
                        <h4 class="font-bold text-gray-900 mb-2">Avantages</h4>
                        <p class="text-gray-700 whitespace-pre-line">${avantages}</p>
                    </div>
                `;
            }
            
            html += '</div>';
            
            document.getElementById('preview-html').innerHTML = html || '<p class="text-gray-500">Remplissez le formulaire pour voir la prévisualisation...</p>';
        }

        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', updateProgress);
            field.addEventListener('change', updateProgress);
        });

        updateProgress();
    </script>
</body>
</html>