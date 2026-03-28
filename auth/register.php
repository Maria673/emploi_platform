<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un compte - NextCareer</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: #ffffff;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .container {
      background: #fff;
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
      max-width: 1000px;
      width: 100%;
      display: grid;
      grid-template-columns: 1fr 1fr;
      overflow: hidden;
    }

    /* Left side - Form */
    .form-side {
      padding: 48px 40px;
    }

   

    .title {
      font-size: 25px;
      font-weight: 700;
      color: #1e1e2e;
      margin-bottom: 10px;
    }

    .subtitle {
      font-size: 14px;
      color: #7a7a8e;
      margin-bottom: 30px;
    }

    .account-options {
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin-bottom: 28px;
    }

    .account-option {
      border: 2px solid #e5e7eb;
      border-radius: 16px;
      padding: 20px 24px;
      cursor: pointer;
      transition: all 0.2s;
      text-decoration: none;
      display: block;
    }

    .account-option:hover {
      border-color: #667eea;
      background: #f8f9ff;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    }

    .option-header {
      margin-bottom: 8px;
    }

    .option-title {
      font-size: 18px;
      font-weight: 600;
      color: #1e1e2e;
      margin-bottom: 8px;
    }

    .option-description {
      font-size: 14px;
      color: #7a7a8e;
      line-height: 1.5;
    }

    .login-link {
      text-align: center;
      font-size: 14px;
      color: #7a7a8e;
    }

    .login-link a {
      color: #667eea;
      font-weight: 600;
      text-decoration: none;
    }

    .login-link a:hover {
      text-decoration: underline;
    }

    /* Right side - Promo */
    .promo-side {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 48px 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      color: #fff;
      position: relative;
      overflow: hidden;
    }

    .promo-side::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
      pointer-events: none;
    }

    .promo-content {
      position: relative;
      z-index: 1;
    }

    .promo-title {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 16px;
      line-height: 1.3;
    }

    .promo-text {
      font-size: 15px;
      line-height: 1.7;
      margin-bottom: 32px;
      opacity: 0.95;
    }

    .promo-features {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .feature {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .feature-text {
      font-size: 14px;
      line-height: 1.6;
    }

    .feature-title {
      font-weight: 600;
      margin-bottom: 2px;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .container {
        grid-template-columns: 1fr;
      }

      .promo-side {
        display: none;
      }

      .form-side {
        padding: 32px 24px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    
    <!-- Left side - Choix du compte -->
    <div class="form-side">
      <!-- Logo -->
      <div class="flex items-center space-x-2">
          <div style="font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 700; color: #1a202c; position: relative; display: inline-block;">
              Next<span style="color: #667eea;">Career</span>
              <div style="position: absolute; bottom: 4px; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 2px;"></div>
          </div>
      </div>

      <h2 class="title">Créer un compte</h2>
      <p class="subtitle">Choisissez le type de compte que vous souhaitez créer</p>

      <div class="account-options">
        <!-- Candidat -->
        <a href="register-candidat.php" class="account-option">
          <div class="option-header">
            <h3 class="option-title">Je suis candidat</h3>
          </div>
          <p class="option-description">
            Je cherche un emploi et je souhaite postuler aux offres disponibles
          </p>
        </a>

        <!-- Recruteur -->
        <a href="register-recruteur.php" class="account-option">
          <div class="option-header">
            <h3 class="option-title">Je suis recruteur</h3>
          </div>
          <p class="option-description">
            Je représente une entreprise et je souhaite publier des offres d'emploi
          </p>
        </a>
      </div>

      <div class="login-link">
        Vous avez déjà un compte ? <a href="login.php">Connectez-vous</a>
      </div>
    </div>

    <!-- Right side - Promo -->
    <div class="promo-side">
      <div class="promo-content">
        <h3 class="promo-title">Rejoignez NextCareer aujourd'hui</h3>
        <p class="promo-text">
          La plateforme de recrutement au Burkina Faso. Connectez talents et opportunités.
        </p>

        <div class="promo-features">
          <div class="feature">
            <div class="feature-text">
              <div class="feature-title">• Milliers d'offres</div>
              <div>Accédez aux meilleures opportunités d'emploi</div>
            </div>
          </div>

          <div class="feature">
            <div class="feature-text">
              <div class="feature-title">• Processus simplifié</div>
              <div>Postulez en quelques clics avec votre profil</div>
            </div>
          </div>

          <div class="feature">
            <div class="feature-text">
              <div class="feature-title">• Entreprises vérifiées</div>
              <div>Toutes nos entreprises partenaires sont authentiques</div>
            </div>
          </div>

          <div class="feature">
            <div class="feature-text">
              <div class="feature-title">• 100% gratuit</div>
              <div>Aucun frais caché, créez votre compte gratuitement</div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</body>
</html>
