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
require_once __DIR__ . '/config/database.php';
$db = db();

$totalEleves = $db->query("SELECT COUNT(*) as total FROM eleves")->fetch()['total'] ?? 0;
$totalClasses = $db->query("SELECT COUNT(*) as total FROM classes")->fetch()['total'] ?? 0;
$totalInscrits = $db->query("SELECT COUNT(*) as total FROM inscriptions WHERE idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)")->fetch()['total'] ?? 0;
$totalPaiements = $db->query("SELECT SUM(montantPaye) as total FROM paiements WHERE YEAR(datePaiement) = YEAR(CURDATE())")->fetch()['total'] ?? 0;
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
            overflow-x: hidden;
        }

        /* ===== NAVBAR ===== */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
            padding: 12px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 80px;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9edf5 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.06) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 6px 20px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 900;
            line-height: 1.1;
            color: #1a202c;
            margin-bottom: 15px;
        }

        .hero h1 .highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.1rem;
            color: #4a5568;
            max-width: 520px;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn-hero-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 40px;
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

        .btn-hero-secondary {
            background: white;
            color: #1a202c;
            border: 2px solid #e2e8f0;
            padding: 14px 40px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-hero-secondary:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-3px);
            text-decoration: none;
        }

        .hero-illustration {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .hero-illustration i {
            font-size: 20rem;
            color: #667eea;
            opacity: 0.08;
        }

        /* ===== STATS ===== */
        .stats-section {
            padding: 50px 0;
            background: white;
            border-top: 1px solid #edf2f7;
        }

        .stat-item {
            text-align: center;
        }

        .stat-item .number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1a202c;
        }

        .stat-item .number .highlight {
            color: #667eea;
        }

        .stat-item .label {
            color: #718096;
            font-weight: 500;
            font-size: 0.9rem;
            margin-top: 4px;
        }

        /* ===== FEATURES ===== */
        .features-section {
            padding: 70px 0;
            background: #f8fafc;
        }

        .section-header {
            text-align: center;
            margin-bottom: 45px;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1a202c;
        }

        .section-header h2 span {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-header p {
            color: #718096;
            font-size: 1rem;
            max-width: 550px;
            margin: 8px auto 0;
        }

        .feature-card {
            background: white;
            border-radius: 16px;
            padding: 30px 25px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            height: 100%;
            border: 1px solid #edf2f7;
        }

        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08);
            border-color: #e2e8f0;
        }

        .feature-card .icon {
            width: 64px;
            height: 64px;
            background: rgba(102, 126, 234, 0.08);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 1.6rem;
            color: #667eea;
            transition: all 0.3s ease;
        }

        .feature-card:hover .icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.05);
        }

        .feature-card h5 {
            font-weight: 700;
            font-size: 1.05rem;
            color: #1a202c;
            margin-bottom: 10px;
        }

        .feature-card p {
            color: #718096;
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0;
        }

        /* ===== CTA ===== */
        .cta-section {
            padding: 70px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }

        .cta-section h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .cta-section p {
            font-size: 1.05rem;
            opacity: 0.9;
            max-width: 550px;
            margin: 0 auto 30px;
        }

        .btn-cta {
            background: white;
            color: #667eea;
            padding: 14px 45px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            color: #667eea;
            text-decoration: none;
        }

        /* ===== FOOTER ===== */
        .footer {
            background: #1a202c;
            color: #a0aec0;
            padding: 40px 0 25px;
        }

        .footer h5 {
            color: white;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 15px;
        }

        .footer a {
            color: #a0aec0;
            text-decoration: none;
            transition: color 0.3s ease;
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .footer a:hover {
            color: #667eea;
        }

        .footer .social a {
            display: inline-block;
            margin-right: 15px;
            font-size: 1.1rem;
        }

        .footer-bottom {
            border-top: 1px solid #2d3748;
            padding-top: 20px;
            margin-top: 25px;
            text-align: center;
            font-size: 0.85rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991px) {
            .hero h1 {
                font-size: 2.8rem;
            }
            .hero-illustration {
                margin-top: 40px;
            }
            .hero-illustration i {
                font-size: 12rem;
            }
            .section-header h2 {
                font-size: 2rem;
            }
            .cta-section h2 {
                font-size: 2rem;
            }
        }

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
            }
            .hero-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .hero-illustration i {
                font-size: 8rem;
            }
            .stat-item .number {
                font-size: 1.8rem;
            }
            .section-header h2 {
                font-size: 1.6rem;
            }
            .feature-card {
                padding: 20px;
            }
            .cta-section h2 {
                font-size: 1.6rem;
            }
            .btn-hero-primary,
            .btn-hero-secondary,
            .btn-cta {
                text-align: center;
                justify-content: center;
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
            .stat-item .number {
                font-size: 1.4rem;
            }
            .stat-item .label {
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>

    <!-- ======================================== -->
    <!-- NAVBAR -->
    <!-- ======================================== -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-school"></i>
                Gestion<span>Scolaire</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="login.php" class="btn-login-nav">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    Connexion
                </a>
            </div>
        </div>
    </nav>

    <!-- ======================================== -->
    <!-- HERO -->
    <!-- ======================================== -->
    <section class="hero" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <div class="hero-badge">
                        <i class="fas fa-rocket me-2"></i>
                        Solution de gestion scolaire
                    </div>
                    <h1>
                        Gérez les paiements<br>
                        scolaires en <span class="highlight">simplicité</span>
                    </h1>
                    <p>
                        Une plateforme moderne et sécurisée pour gérer les inscriptions,
                        les paiements et le suivi des élèves du primaire et du secondaire.
                    </p>
                    <div class="hero-buttons">
                        <a href="login.php" class="btn-hero-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Se connecter
                        </a>
                        <a href="#features" class="btn-hero-secondary">
                            <i class="fas fa-play-circle"></i>
                            Découvrir
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 hero-illustration">
                    <i class="fas fa-school"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- ======================================== -->
    <!-- STATS -->
    <!-- ======================================== -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-6 col-md-3 stat-item">
                    <div class="number"><?php echo number_format($totalEleves); ?>+</div>
                    <div class="label">Élèves</div>
                </div>
                <div class="col-6 col-md-3 stat-item">
                    <div class="number"><?php echo number_format($totalInscrits); ?></div>
                    <div class="label">Inscrits cette année</div>
                </div>
                <div class="col-6 col-md-3 stat-item">
                    <div class="number"><span class="highlight"><?php echo number_format($totalPaiements, 0, ',', ' '); ?></span></div>
                    <div class="label">FC encaissés</div>
                </div>
                <div class="col-6 col-md-3 stat-item">
                    <div class="number"><?php echo number_format($totalClasses); ?></div>
                    <div class="label">Classes</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ======================================== -->
    <!-- FEATURES -->
    <!-- ======================================== -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="section-header">
                <h2>Fonctionnalités <span>principales</span></h2>
                <p>Une solution complète pour la gestion scolaire moderne</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <h5>Gestion des Élèves</h5>
                        <p>Ajoutez, modifiez et suivez tous vos élèves avec leur historique complet d'inscriptions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                        <h5>Gestion des Paiements</h5>
                        <p>Enregistrez les paiements, générez des reçus et suivez les mensualités en temps réel.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon"><i class="fas fa-book-open"></i></div>
                        <h5>Inscriptions</h5>
                        <p>Inscrivez les élèves par année scolaire avec suivi des classes et niveaux.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ======================================== -->
    <!-- CTA -->
    <!-- ======================================== -->
    <section class="cta-section">
        <div class="container">
            <h2>Prêt à simplifier la gestion de votre école ?</h2>
            <p>Connectez-vous dès maintenant et découvrez comment notre solution peut vous aider.</p>
            <a href="login.php" class="btn-cta">
                <i class="fas fa-sign-in-alt"></i>
                Commencer maintenant
            </a>
        </div>
    </section>

    <!-- ======================================== -->
    <!-- FOOTER -->
    <!-- ======================================== -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-school" style="color:#667eea;"></i> GestionScolaire</h5>
                    <p style="font-size:0.9rem;color:#a0aec0;">Une solution complète pour la gestion des paiements scolaires.</p>
                </div>
                <div class="col-md-2 mb-4">
                    <h5>Liens</h5>
                    <a href="#home">Accueil</a>
                    <a href="#features">Fonctionnalités</a>
                    <a href="login.php">Connexion</a>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Rôles</h5>
                    <a href="login.php">Administrateur</a>
                    <a href="login.php">Caissier</a>
                    <a href="login.php">Réceptionniste</a>
                    <a href="login.php">Élève</a>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Contact</h5>
                    <a href="#"><i class="fas fa-phone me-2"></i>+243 812 345 678</a>
                    <a href="#"><i class="fas fa-envelope me-2"></i>contact@ecole.com</a>
                    <a href="#"><i class="fas fa-map-marker-alt me-2"></i>Kinshasa, RDC</a>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; <?php echo date('Y'); ?> Gestion Scolaire - Tous droits réservés.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>