<?php
// eleve/profil.php - Profil de l'élève
session_start();

if (!isset($_SESSION['eleve_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';

$db = db();
$eleve_id = $_SESSION['eleve_id'];
$mode_edition = isset($_GET['edit']) && $_GET['edit'] === '1';

// Récupérer les informations de l'élève
$stmt = $db->prepare("SELECT * FROM eleves WHERE idEleve = :id");
$stmt->execute(['id' => $eleve_id]);
$eleve = $stmt->fetch();

// Récupérer l'inscription en cours
$stmt = $db->prepare("
    SELECT i.*, c.nomClasse, n.nomNiveau, a.libelle as annee
    FROM inscriptions i
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN niveaux n ON c.idNiveau = n.idNiveau
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE i.idEleve = :idEleve 
    AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
    LIMIT 1
");
$stmt->execute(['idEleve' => $eleve_id]);
$inscription = $stmt->fetch();

$message = '';
$error = '';
$success = '';

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_all') {
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $lieuNaissance = trim($_POST['lieuNaissance'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $nationalite = trim($_POST['nationalite'] ?? '');
    $nomPere = trim($_POST['nomPere'] ?? '');
    $nomMere = trim($_POST['nomMere'] ?? '');
    
    $stmt = $db->prepare("UPDATE eleves SET 
        telephone = :telephone, 
        adresse = :adresse,
        lieuNaissance = :lieuNaissance,
        province = :province,
        nationalite = :nationalite,
        nomPere = :nomPere,
        nomMere = :nomMere
        WHERE idEleve = :id");
    
    if ($stmt->execute([
        'telephone' => $telephone,
        'adresse' => $adresse,
        'lieuNaissance' => $lieuNaissance,
        'province' => $province,
        'nationalite' => $nationalite,
        'nomPere' => $nomPere,
        'nomMere' => $nomMere,
        'id' => $eleve_id
    ])) {
        $success = '✅ Vos informations ont été mises à jour avec succès !';
        $mode_edition = false;
        $stmt = $db->prepare("SELECT * FROM eleves WHERE idEleve = :id");
        $stmt->execute(['id' => $eleve_id]);
        $eleve = $stmt->fetch();
    } else {
        $error = '❌ Erreur lors de la mise à jour.';
    }
}

// Changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $ancienMdp = $_POST['ancienMdp'] ?? '';
    $nouveauMdp = $_POST['nouveauMdp'] ?? '';
    $confirmerMdp = $_POST['confirmerMdp'] ?? '';
    
    $stmt = $db->prepare("SELECT motDePasse FROM eleves WHERE idEleve = :id");
    $stmt->execute(['id' => $eleve_id]);
    $current = $stmt->fetch();
    
    if (!password_verify($ancienMdp, $current['motDePasse'])) {
        $error = '❌ L\'ancien mot de passe est incorrect.';
    } elseif (strlen($nouveauMdp) < 6) {
        $error = '❌ Le nouveau mot de passe doit contenir au moins 6 caractères.';
    } elseif ($nouveauMdp !== $confirmerMdp) {
        $error = '❌ Les mots de passe ne correspondent pas.';
    } else {
        $hash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE eleves SET motDePasse = :mdp WHERE idEleve = :id");
        if ($stmt->execute(['mdp' => $hash, 'id' => $eleve_id])) {
            $success = '✅ Mot de passe changé avec succès !';
        } else {
            $error = '❌ Erreur lors du changement de mot de passe.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Élève</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            font-size: 14px;
        }

        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
            padding: 18px 12px;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            color: white;
            transition: transform 0.3s ease-in-out;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar .logo {
            font-size: 1.1rem;
            font-weight: 700;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 16px;
        }
        .sidebar .logo i { color: #667eea; margin-right: 10px; }
        .sidebar .logo span { color: #667eea; }
        .sidebar .menu { list-style: none; padding: 0; }
        .sidebar .menu li { margin-bottom: 3px; }
        .sidebar .menu li a {
            color: #a0aec0;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            display: block;
            transition: 0.3s;
            font-size: 0.85rem;
        }
        .sidebar .menu li a:hover { background: rgba(255,255,255,0.08); color: white; }
        .sidebar .menu li a.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .sidebar .menu li a i { width: 18px; margin-right: 10px; }

        .sidebar .footer {
            border-top: 1px solid rgba(255,255,255,0.06);
            padding-top: 16px;
            margin-top: 16px;
        }
        .sidebar .footer .user {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar .footer .user i {
            font-size: 1.5rem;
            color: #667eea;
        }
        .sidebar .footer .user .name {
            font-size: 0.8rem;
            font-weight: 600;
        }
        .sidebar .footer .user .matricule {
            font-size: 0.65rem;
            color: #a0aec0;
        }
        .sidebar .footer .btn-logout {
            background: rgba(255,255,255,0.08);
            color: #fc8181;
            border: none;
            border-radius: 6px;
            padding: 6px 10px;
            text-decoration: none;
            display: inline-block;
            margin-top: 8px;
            width: 100%;
            text-align: center;
            transition: 0.3s;
            font-size: 0.8rem;
        }
        .sidebar .footer .btn-logout:hover {
            background: rgba(255,255,255,0.15);
            color: #feb2b2;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }
        .sidebar-overlay.active { display: block; }

        .hamburger-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1001;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .hamburger-btn:hover { transform: scale(1.05); box-shadow: 0 8px 25px rgba(102,126,234,0.5); }

        .main-content {
            margin-left: 240px;
            padding: 20px 25px;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 12px 18px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a202c;
            margin: 0;
        }
        .header h2 i { color: #667eea; margin-right: 8px; }
        .header .badge {
            background: #ebf8ff;
            color: #2b6cb0;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .header .logout-header { display: none; }

        .profile-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 20px 25px;
            max-width: 750px;
        }
        .profile-card .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            margin: 0 auto 12px;
        }
        .profile-card .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.85rem;
        }
        .profile-card .info-row:last-child { border-bottom: none; }
        .profile-card .info-label {
            width: 130px;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.75rem;
            flex-shrink: 0;
        }
        .profile-card .info-value {
            flex: 1;
            color: #1a202c;
            font-size: 0.85rem;
        }
        .profile-card .info-value .badge-statut {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .profile-card .info-value .badge-statut.actif { background: #f0fff4; color: #276749; }

        .profile-card .form-control {
            border-radius: 6px;
            border: 2px solid #e2e8f0;
            padding: 6px 12px;
            font-size: 0.85rem;
            transition: 0.3s;
            width: 100%;
        }
        .profile-card .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .btn-edit-main {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: 0.3s;
            width: 100%;
            margin-top: 10px;
        }
        .btn-edit-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.35);
            color: white;
        }
        .btn-edit-main i { margin-right: 8px; }

        .btn-save {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: 0.3s;
            width: 100%;
            margin-top: 10px;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(72,187,120,0.35);
            color: white;
        }
        .btn-save i { margin-right: 8px; }

        .btn-cancel-edit {
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: 0.3s;
            width: 100%;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-cancel-edit:hover {
            background: #cbd5e0;
            color: #2d3748;
        }

        .btn-password {
            background: #ed8936;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: 0.3s;
            white-space: nowrap;
        }
        .btn-password:hover {
            background: #dd6b20;
            color: white;
        }
        .btn-password i { margin-right: 4px; }

        .alert-custom {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            max-width: 750px;
            margin-bottom: 15px;
        }
        .alert-custom.success { background: #f0fff4; color: #276749; border: 1px solid #c6f6d5; }
        .alert-custom.danger { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; }

        .edit-mode .info-row {
            display: block;
            padding: 6px 0;
        }
        .edit-mode .info-label {
            width: 100%;
            margin-bottom: 3px;
        }
        .edit-mode .info-value {
            width: 100%;
        }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .hamburger-btn { display: flex !important; }
            .main-content { margin-left: 0; padding: 15px; }
            .header .logout-header { display: inline-block; }
            .profile-card { padding: 15px 18px; }
            .profile-card .info-row { flex-direction: column; padding: 4px 0; }
            .profile-card .info-label { width: 100%; margin-bottom: 2px; }
            .profile-card .info-value { width: 100%; }
            .btn-password { width: 100%; justify-content: center; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .header h2 { font-size: 0.95rem; }
            .header .badge { font-size: 0.65rem; padding: 3px 10px; }
            .profile-card { padding: 12px 14px; }
            .profile-card .info-row { font-size: 0.8rem; }
            .profile-card .info-label { font-size: 0.7rem; }
            .profile-card .info-value { font-size: 0.8rem; }
            .hamburger-btn { width: 42px; height: 42px; font-size: 1rem; bottom: 15px; left: 15px; }
        }

        @media (min-width: 992px) {
            .hamburger-btn { display: none !important; }
            .sidebar-overlay { display: none !important; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<button class="hamburger-btn" id="hamburgerBtn" title="Menu">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
    <div class="logo">
        <i class="fas fa-school"></i>
        Gestion<span>Scolaire</span>
    </div>
    <ul class="menu">
        <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i> Tableau de bord</a></li>
        <li><a href="paiements/historique.php"><i class="fas fa-money-bill-wave"></i> Mes paiements</a></li>
        <li><a href="profil.php" class="active"><i class="fas fa-user"></i> Mon profil</a></li>
    </ul>
    <div class="footer">
        <div class="user">
            <i class="fas fa-user-circle"></i>
            <div>
                <div class="name"><?php echo $_SESSION['eleve_nom'] . ' ' . $_SESSION['eleve_prenom']; ?></div>
                <div class="matricule"><?php echo $_SESSION['eleve_matricule']; ?></div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
        </a>
    </div>
</div>

<div class="main-content">

    <div class="header">
        <h2><i class="fas fa-user"></i> Mon Profil</h2>
        <div>
            <span class="badge"><i class="fas fa-id-card me-1"></i> <?php echo $_SESSION['eleve_matricule']; ?></span>
            <a href="../logout.php" class="btn btn-sm btn-outline-danger ms-2 logout-header" style="border-radius:6px; padding:4px 12px; font-size:0.75rem;">
                <i class="fas fa-sign-out-alt me-1"></i> Quitter
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert-custom success"><i class="fas fa-check-circle me-2"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-custom danger"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="profile-card <?php echo $mode_edition ? 'edit-mode' : ''; ?>">
        <div class="avatar"><i class="fas fa-user-graduate"></i></div>

        <?php if ($mode_edition): ?>
            <!-- ===== MODE EDITION ===== -->
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_all">

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-id-card me-1"></i> Matricule</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($eleve['matricule']); ?></strong></div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user me-1"></i> Nom</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($eleve['nom']); ?></strong></div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user me-1"></i> Post-Nom</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($eleve['postNom'] ?? '-'); ?></strong></div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user me-1"></i> Prénom</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($eleve['prenom']); ?></strong></div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-venus-mars me-1"></i> Sexe</div>
                    <div class="info-value">
                        <?php if ($eleve['sexe'] === 'M'): ?>
                            <span class="badge bg-info" style="font-size:0.7rem;">Masculin</span>
                        <?php elseif ($eleve['sexe'] === 'F'): ?>
                            <span class="badge" style="background:#d53f8c; color:white; font-size:0.7rem;">Féminin</span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-calendar-alt me-1"></i> Date naissance</div>
                    <div class="info-value"><strong><?php echo $eleve['dateNaissance'] ? date('d/m/Y', strtotime($eleve['dateNaissance'])) : '-'; ?></strong></div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-map-pin me-1"></i> Lieu naissance</div>
                    <div class="info-value">
                        <input type="text" class="form-control" name="lieuNaissance" 
                               value="<?php echo htmlspecialchars($eleve['lieuNaissance'] ?? ''); ?>" 
                               placeholder="Lieu de naissance">
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-map-marker-alt me-1"></i> Province</div>
                    <div class="info-value">
                        <input type="text" class="form-control" name="province" 
                               value="<?php echo htmlspecialchars($eleve['province'] ?? ''); ?>" 
                               placeholder="Province d'origine">
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-globe-africa me-1"></i> Nationalité</div>
                    <div class="info-value">
                        <input type="text" class="form-control" name="nationalite" 
                               value="<?php echo htmlspecialchars($eleve['nationalite'] ?? ''); ?>" 
                               placeholder="Nationalité">
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user-tie me-1"></i> Nom du père</div>
                    <div class="info-value">
                        <input type="text" class="form-control" name="nomPere" 
                               value="<?php echo htmlspecialchars($eleve['nomPere'] ?? ''); ?>" 
                               placeholder="Nom du père">
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user-tie me-1"></i> Nom de la mère</div>
                    <div class="info-value">
                        <input type="text" class="form-control" name="nomMere" 
                               value="<?php echo htmlspecialchars($eleve['nomMere'] ?? ''); ?>" 
                               placeholder="Nom de la mère">
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-phone me-1"></i> Téléphone</div>
                    <div class="info-value">
                        <input type="tel" class="form-control" name="telephone" 
                               value="<?php echo htmlspecialchars($eleve['telephone'] ?? ''); ?>" 
                               placeholder="+243 812 345 678">
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-map-marker-alt me-1"></i> Adresse</div>
                    <div class="info-value">
                        <input type="text" class="form-control" name="adresse" 
                               value="<?php echo htmlspecialchars($eleve['adresse'] ?? ''); ?>" 
                               placeholder="Adresse complète">
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label"><i class="fas fa-graduation-cap me-1"></i> Classe</div>
                    <div class="info-value">
                        <?php if ($inscription): ?>
                            <span class="badge-statut actif">
                                <i class="fas fa-check-circle me-1" style="font-size:8px;"></i>
                                <?php echo htmlspecialchars($inscription['nomClasse']); ?>
                                <span style="font-weight:400; color:#718096; font-size:0.7rem;">
                                    (<?php echo htmlspecialchars($inscription['nomNiveau']); ?>)
                                </span>
                            </span>
                            <span style="font-size:0.7rem; color:#718096; margin-left:6px;">
                                <?php echo htmlspecialchars($inscription['annee']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#ed8936; font-size:0.8rem;">
                                <i class="fas fa-exclamation-triangle me-1"></i> Non inscrit
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="profil.php" class="btn-cancel-edit"><i class="fas fa-times me-2"></i> Annuler</a>
                    <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i> Enregistrer</button>
                </div>
            </form>

        <?php else: ?>
            <!-- ===== MODE CONSULTATION ===== -->
            <div class="info-row">
                <div class="info-label"><i class="fas fa-id-card me-1"></i> Matricule</div>
                <div class="info-value"><strong><?php echo htmlspecialchars($eleve['matricule']); ?></strong></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-user me-1"></i> Nom</div>
                <div class="info-value"><?php echo htmlspecialchars($eleve['nom']); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-user me-1"></i> Post-Nom</div>
                <div class="info-value"><?php echo htmlspecialchars($eleve['postNom'] ?? '-'); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-user me-1"></i> Prénom</div>
                <div class="info-value"><?php echo htmlspecialchars($eleve['prenom']); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-venus-mars me-1"></i> Sexe</div>
                <div class="info-value">
                    <?php if ($eleve['sexe'] === 'M'): ?>
                        <span class="badge bg-info" style="font-size:0.7rem;">Masculin</span>
                    <?php elseif ($eleve['sexe'] === 'F'): ?>
                        <span class="badge" style="background:#d53f8c; color:white; font-size:0.7rem;">Féminin</span>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-calendar-alt me-1"></i> Date naissance</div>
                <div class="info-value"><?php echo $eleve['dateNaissance'] ? date('d/m/Y', strtotime($eleve['dateNaissance'])) : '-'; ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-map-pin me-1"></i> Lieu naissance</div>
                <div class="info-value"><?php echo htmlspecialchars($eleve['lieuNaissance'] ?? '-'); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-map-marker-alt me-1"></i> Province</div>
                <div class="info-value"><?php echo htmlspecialchars($eleve['province'] ?? '-'); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-globe-africa me-1"></i> Nationalité</div>
                <div class="info-value"><?php echo htmlspecialchars($eleve['nationalite'] ?? '-'); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-user-tie me-1"></i> Nom du père</div>
                <div class="info-value"><?php echo htmlspecialchars($eleve['nomPere'] ?? '-'); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-user-tie me-1"></i> Nom de la mère</div>
                <div class="info-value"><?php echo htmlspecialchars($eleve['nomMere'] ?? '-'); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-phone me-1"></i> Téléphone</div>
                <div class="info-value"><?php echo htmlspecialchars($eleve['telephone'] ?? '-'); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-map-marker-alt me-1"></i> Adresse</div>
                <div class="info-value"><?php echo htmlspecialchars($eleve['adresse'] ?? '-'); ?></div>
            </div>

            <div class="info-row">
                <div class="info-label"><i class="fas fa-graduation-cap me-1"></i> Classe</div>
                <div class="info-value">
                    <?php if ($inscription): ?>
                        <span class="badge-statut actif">
                            <i class="fas fa-check-circle me-1" style="font-size:8px;"></i>
                            <?php echo htmlspecialchars($inscription['nomClasse']); ?>
                            <span style="font-weight:400; color:#718096; font-size:0.7rem;">
                                (<?php echo htmlspecialchars($inscription['nomNiveau']); ?>)
                            </span>
                        </span>
                        <span style="font-size:0.7rem; color:#718096; margin-left:6px;">
                            <?php echo htmlspecialchars($inscription['annee']); ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#ed8936; font-size:0.8rem;">
                            <i class="fas fa-exclamation-triangle me-1"></i> Non inscrit
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ===== BOUTON MODIFIER ===== -->
            <div class="info-row" style="border-top: 2px solid #edf2f7; margin-top: 8px; padding-top: 12px;">
                <div class="info-label"><i class="fas fa-edit me-1"></i> Actions</div>
                <div class="info-value">
                    <a href="?edit=1" class="btn-edit-main">
                        <i class="fas fa-edit"></i> Modifier mes informations
                    </a>
                    <button class="btn-password mt-2" data-bs-toggle="modal" data-bs-target="#passwordModal" style="width:100%;">
                        <i class="fas fa-key"></i> Changer le mot de passe
                    </button>
                </div>
            </div>

        <?php endif; ?>

    </div>

</div>

<!-- ===== MODAL CHANGER MOT DE PASSE ===== -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header" style="border-bottom:2px solid #f0f2f5;">
                <h5 class="modal-title">
                    <i class="fas fa-key" style="color:#667eea;"></i> Changer le mot de passe
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label" style="font-weight:600; font-size:0.85rem;">Ancien mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="ancienMdp" placeholder="Entrez votre ancien mot de passe" required style="border-radius:8px; border:2px solid #e2e8f0; padding:10px 14px;">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label" style="font-weight:600; font-size:0.85rem;">Nouveau mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="nouveauMdp" placeholder="Minimum 6 caractères" required style="border-radius:8px; border:2px solid #e2e8f0; padding:10px 14px;">
                        <small class="text-muted" style="font-size:0.7rem;"><i class="fas fa-info-circle me-1"></i>Le mot de passe doit contenir au moins 6 caractères</small>
                    </div>
                    <div class="form-group mb-2">
                        <label class="form-label" style="font-weight:600; font-size:0.85rem;">Confirmer le mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirmerMdp" placeholder="Confirmez votre mot de passe" required style="border-radius:8px; border:2px solid #e2e8f0; padding:10px 14px;">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:2px solid #f0f2f5;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px;">Annuler</button>
                    <button type="submit" class="btn" style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; border:none; border-radius:8px; padding:8px 25px; font-weight:600;">
                        <i class="fas fa-save me-1"></i> Changer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', openSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991) {
                    closeSidebar();
                }
            });
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>