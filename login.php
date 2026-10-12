<?php
// login.php - Authentification unique pour tous
session_start();

// Si déjà connecté, rediriger
if (isset($_SESSION['user_id']) || isset($_SESSION['eleve_id'])) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['identifiant'] ?? '');
    $motDePasse = $_POST['motDePasse'] ?? '';
    
    if (!empty($identifiant) && !empty($motDePasse)) {
        $db = db();
        
        // ============================================================
        // 1. TENTATIVE DE CONNEXION PERSONNEL (Admin, Caissier, Récep.)
        // ============================================================
        $query = "SELECT u.*, r.nomRole FROM utilisateurs u 
                  LEFT JOIN roles r ON u.idRole = r.idRole 
                  WHERE u.username = :identifiant LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['identifiant' => $identifiant]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($motDePasse, $user['motDePasse'])) {
            $_SESSION['user_id'] = $user['idUtilisateur'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['postNom'] = $user['postNom'];
            $_SESSION['prenom'] = $user['prenom'];
            $_SESSION['role'] = $user['nomRole'] ?? 'Utilisateur';
            $_SESSION['idRole'] = $user['idRole'];
            
            // Journaliser la connexion
            try {
                $sqlLog = "INSERT INTO journal_activite (idUtilisateur, action, type, dateAction) 
                           VALUES (:idUser, :action, :type, NOW())";
                $stmtLog = $db->prepare($sqlLog);
                $stmtLog->execute([
                    'idUser' => $user['idUtilisateur'],
                    'action' => "Connexion de l'utilisateur " . $user['username'],
                    'type' => 'connexion'
                ]);
            } catch (PDOException $e) {
                // Ignorer les erreurs de journalisation
            }
            
            header('Location: index.php');
            exit();
        }
        
        // ============================================================
        // 2. TENTATIVE DE CONNEXION ÉLÈVE (Matricule)
        // ============================================================
        $query = "SELECT * FROM eleves WHERE matricule = :identifiant LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['identifiant' => $identifiant]);
        $eleve = $stmt->fetch();
        
        if ($eleve && password_verify($motDePasse, $eleve['motDePasse'])) {
            $_SESSION['eleve_id'] = $eleve['idEleve'];
            $_SESSION['eleve_matricule'] = $eleve['matricule'];
            $_SESSION['eleve_nom'] = $eleve['nom'];
            $_SESSION['eleve_prenom'] = $eleve['prenom'];
            $_SESSION['role'] = 'Élève';
            
            // Journaliser la connexion
            try {
                $sqlLog = "INSERT INTO journal_activite (idUtilisateur, action, type, dateAction) 
                           VALUES (NULL, :action, :type, NOW())";
                $stmtLog = $db->prepare($sqlLog);
                $stmtLog->execute([
                    'action' => "Connexion de l'élève " . $eleve['matricule'],
                    'type' => 'connexion'
                ]);
            } catch (PDOException $e) {
                // Ignorer les erreurs de journalisation
            }
            
            header('Location: index.php');
            exit();
        }
        
        // ============================================================
        // 3. AUCUN MATCH
        // ============================================================
        $error = 'Identifiant ou mot de passe incorrect.';
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion Scolaire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f4f8;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Arrière-plan avec des formes géométriques */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        body::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(118, 75, 162, 0.06) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }
        
        .login-card {
            background: #ffffff;
            border-radius: 28px;
            padding: 50px 45px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.06), 0 8px 20px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.8);
            animation: fadeIn 0.6s ease;
            transition: all 0.3s ease;
        }
        
        .login-card:hover {
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.08);
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ===== HEADER ===== */
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .login-header .logo-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 24px;
            margin-bottom: 18px;
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.25);
            transition: transform 0.3s ease;
        }
        
        .login-header .logo-wrapper:hover {
            transform: scale(1.05) rotate(-2deg);
        }
        
        .login-header .logo-wrapper i {
            font-size: 2.2rem;
            color: white;
        }
        
        .login-header h4 {
            font-weight: 800;
            color: #1a202c;
            margin-bottom: 4px;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
        }
        
        .login-header p {
            color: #718096;
            font-size: 0.85rem;
            margin: 0;
            font-weight: 400;
        }
        
        /* ===== FORMULAIRE ===== */
        .form-group {
            margin-bottom: 22px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.85rem;
            margin-bottom: 8px;
            display: block;
        }
        
        .form-group label i {
            color: #667eea;
            margin-right: 6px;
        }
        
        .input-group {
            border-radius: 14px;
            overflow: hidden;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            background: #f7fafc;
        }
        
        .input-group:focus-within {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.08);
            background: #ffffff;
        }
        
        .input-group-text {
            background: transparent;
            border: none;
            color: #a0aec0;
            padding: 14px 16px 14px 18px;
            font-size: 1rem;
        }
        
        .form-control {
            border: none;
            padding: 14px 18px 14px 0;
            font-size: 0.95rem;
            background: transparent;
            color: #1a202c;
        }
        
        .form-control:focus {
            box-shadow: none;
            background: transparent;
        }
        
        .form-control::placeholder {
            color: #a0aec0;
            font-weight: 400;
        }
        
        .toggle-password {
            background: transparent;
            border: none;
            color: #a0aec0;
            padding: 14px 18px 14px 0;
            cursor: pointer;
            transition: color 0.3s ease;
            font-size: 1rem;
        }
        
        .toggle-password:hover {
            color: #667eea;
        }
        
        .help-text {
            font-size: 0.7rem;
            color: #a0aec0;
            margin-top: 6px;
            text-align: center;
        }
        
        .help-text i {
            margin-right: 4px;
            color: #667eea;
        }
        
        /* ===== BOUTON ===== */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.6s ease;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.35);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login i {
            margin-right: 10px;
        }
        
        /* ===== ALERTE ===== */
        .alert {
            border-radius: 14px;
            padding: 14px 18px;
            font-size: 0.9rem;
            border: none;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #fff5f5;
            color: #e53e3e;
            border: 1px solid #fed7d7;
        }
        
        .alert-danger i {
            color: #fc8181;
        }
        
        .alert .btn-close {
            font-size: 0.7rem;
        }
        
        /* ===== FOOTER ===== */
        .login-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #edf2f7;
        }
        
        .login-footer small {
            color: #a0aec0;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .login-footer small i {
            color: #48bb78;
        }
        
        .back-home {
            display: inline-block;
            margin-top: 12px;
            color: #a0aec0;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .back-home:hover {
            color: #667eea;
            text-decoration: none;
        }
        
        .back-home i {
            margin-right: 6px;
            transition: transform 0.3s ease;
        }
        
        .back-home:hover i {
            transform: translateX(-3px);
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            .login-card {
                padding: 35px 25px 30px;
                border-radius: 20px;
            }
            
            .login-header .logo-wrapper {
                width: 65px;
                height: 65px;
                border-radius: 18px;
            }
            
            .login-header .logo-wrapper i {
                font-size: 1.8rem;
            }
            
            .login-header h4 {
                font-size: 1.2rem;
            }
            
            .input-group {
                border-radius: 12px;
            }
            
            .input-group-text {
                padding: 12px 14px 12px 16px;
            }
            
            .form-control {
                padding: 12px 14px 12px 0;
                font-size: 0.9rem;
            }
            
            .btn-login {
                padding: 14px;
                border-radius: 12px;
                font-size: 0.95rem;
            }
            
            .login-footer {
                margin-top: 20px;
                padding-top: 18px;
            }
        }
        
        @media (max-width: 360px) {
            .login-card {
                padding: 25px 18px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            
            <!-- ===== HEADER ===== -->
            <div class="login-header">
                <div class="logo-wrapper">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h4>Bienvenue</h4>
                <p>Connectez-vous à votre espace</p>
            </div>

            <!-- ===== ERREUR ===== -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- ===== FORMULAIRE ===== -->
            <form method="POST" action="">
                <div class="form-group">
                    <label for="identifiant">
                        <i class="fas fa-user"></i>
                        Identifiant
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-id-card"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               id="identifiant" 
                               name="identifiant" 
                               placeholder="Nom d'utilisateur ou matricule"
                               value="<?php echo isset($_POST['identifiant']) ? htmlspecialchars($_POST['identifiant']) : ''; ?>"
                               autofocus
                               required>
                    </div>
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Personnel : nom d'utilisateur &bull; Élève : matricule
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        Mot de passe
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-key"></i>
                        </span>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="motDePasse" 
                               placeholder="Entrez votre mot de passe"
                               required>
                        <button type="button" class="toggle-password" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>
            </form>

            <!-- ===== FOOTER ===== -->
            <div class="login-footer">
                <small>
                    <i class="fas fa-shield-alt"></i>
                    Connexion sécurisée
                </small>
                <br>
                <a href="index.php" class="back-home">
                    <i class="fas fa-arrow-left"></i>
                    Retour à l'accueil
                </a>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== TOGGLE PASSWORD VISIBILITY =====
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // ===== SOUMETTRE AVEC ENTREE =====
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>