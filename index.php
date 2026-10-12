<?php
// index.php - Point d'entrée principal
session_start();

// ============================================================
// 1. VÉRIFIER SI L'ÉLÈVE EST CONNECTÉ
// ============================================================
if (isset($_SESSION['eleve_id'])) {
    header('Location: eleve/dashboard.php');
    exit();
}

// ============================================================
// 2. VÉRIFIER SI LE PERSONNEL EST CONNECTÉ
// ============================================================
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    switch ($role) {
        case 'Administrateur':
            header('Location: admin/dashboard.php');
            break;
        case 'Caissier':
            header('Location: caissier/dashboard.php');
            break;
        case 'Réceptionniste':
            header('Location: receptionniste/dashboard.php');
            break;
        default:
            session_destroy();
            header('Location: login.php');
            break;
    }
    exit();
}

// ============================================================
// 3. SI NON CONNECTÉ : PAGE D'ACCUEIL
// ============================================================
// Ne pas faire de requêtes SQL pour éviter les erreurs
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Scolaire - Système de Paiement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===== NAVBAR ===== */
        .navbar {
            background: white !important;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
            padding: 12px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.3rem;
            color: #1a202c !important;
        }
        .navbar-brand i {
            color: #667eea;
            margin-right: 10px;
        }
        .navbar-brand span {
            color: #667eea;
        }

        .btn-login-nav {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            border-radius: 50px;
            padding: 8px 28px !important;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
        }
        .btn-login-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white !important;
        }

        /* ===== HERO ===== */
        .hero {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 80px;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9edf5 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.06) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 900;
            color: #1a202c;
            margin-bottom: 12px;
        }

        .hero h1 .highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.15rem;
            color: #4a5568;
            max-width: 500px;
            margin: 0 auto 30px;
            line-height: 1.8;
        }

        .btn-hero-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 45px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }

        .hero-icon {
            font-size: 5rem;
            color: #667eea;
            opacity: 0.15;
            position: absolute;
            bottom: 20px;
            right: 30px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 767px) {
            .hero {
                min-height: auto;
                padding: 100px 0 60px;
            }
            .hero h1 {
                font-size: 2rem;
            }
            .hero p {
                font-size: 1rem;
                padding: 0 15px;
            }
            .btn-hero-primary {
                padding: 12px 30px;
                font-size: 0.9rem;
            }
            .hero-icon {
                font-size: 3rem;
                bottom: 10px;
                right: 15px;
            }
        }

        @media (max-width: 480px) {
            .navbar-brand {
                font-size: 1.1rem;
            }
            .btn-login-nav {
                padding: 6px 18px !important;
                font-size: 0.8rem;
            }
            .hero h1 {
                font-size: 1.6rem;
            }
            .hero p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

    <!-- ======================================== -->
    <!-- NAVBAR -->
    <!-- ======================================== -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-school"></i>
                Gestion<span>Scolaire</span>
            </a>
            <a href="login.php" class="btn-login-nav">
                <i class="fas fa-sign-in-alt me-2"></i>
                Connexion
            </a>
        </div>
    </nav>

    <!-- ======================================== -->
    <!-- HERO -->
    <!-- ======================================== -->
    <section class="hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 hero-content">
                    <h1>
                        Bienvenue sur <br>
                        <span class="highlight">Gestion Scolaire</span>
                    </h1>
                    <p>
                        La plateforme de gestion des paiements et des inscriptions scolaires.
                        Connectez-vous pour accéder à votre espace.
                    </p>
                    <a href="login.php" class="btn-hero-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Se connecter
                    </a>
                </div>
            </div>
        </div>
        <div class="hero-icon">
            <i class="fas fa-graduation-cap"></i>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>