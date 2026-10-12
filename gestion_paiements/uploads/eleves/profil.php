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

$stmt = $db->prepare("SELECT * FROM eleves WHERE idEleve = :id");
$stmt->execute(['id' => $eleve_id]);
$eleve = $stmt->fetch();

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

// Traitement de la mise à jour des informations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Mise à jour téléphone / adresse
    if ($action === 'update_info') {
        $telephone = trim($_POST['telephone'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        
        $stmt = $db->prepare("UPDATE eleves SET telephone = :telephone, adresse = :adresse WHERE idEleve = :id");
        if ($stmt->execute(['telephone' => $telephone, 'adresse' => $adresse, 'id' => $eleve_id])) {
            $message = '✅ Informations mises à jour !';
            $stmt = $db->prepare("SELECT * FROM eleves WHERE idEleve = :id");
            $stmt->execute(['id' => $eleve_id]);
            $eleve = $stmt->fetch();
        } else {
            $error = '❌ Erreur lors de la mise à jour.';
        }
    }
    
    // Upload photo
    if ($action === 'upload_photo' && isset($_FILES['photo'])) {
        $file = $_FILES['photo'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            if (!in_array($file['type'], $allowed)) {
                $error = '❌ Format non autorisé. Utilisez JPG, PNG, GIF ou WEBP.';
            } elseif ($file['size'] > $maxSize) {
                $error = '❌ L\'image est trop lourde. Maximum 2MB.';
            } else {
                // Générer un nom unique
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'eleve_' . $eleve_id . '_' . time() . '.' . $ext;
                $uploadPath = __DIR__ . '/../uploads/eleves/' . $filename;
                
                // Créer le dossier si inexistant
                if (!is_dir(__DIR__ . '/../uploads/eleves/')) {
                    mkdir(__DIR__ . '/../uploads/eleves/', 0777, true);
                }
                
                // Supprimer l'ancienne photo
                if (!empty($eleve['photo']) && file_exists(__DIR__ . '/../uploads/eleves/' . $eleve['photo'])) {
                    unlink(__DIR__ . '/../uploads/eleves/' . $eleve['photo']);
                }
                
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $stmt = $db->prepare("UPDATE eleves SET photo = :photo WHERE idEleve = :id");
                    if ($stmt->execute(['photo' => $filename, 'id' => $eleve_id])) {
                        $message = '✅ Photo mise à jour avec succès !';
                        $stmt = $db->prepare("SELECT * FROM eleves WHERE idEleve = :id");
                        $stmt->execute(['id' => $eleve_id]);
                        $eleve = $stmt->fetch();
                    } else {
                        $error = '❌ Erreur lors de l\'enregistrement.';
                    }
                } else {
                    $error = '❌ Erreur lors du téléchargement.';
                }
            }
        } else {
            $error = '❌ Erreur lors de l\'upload.';
        }
    }
}

$photoUrl = !empty($eleve['photo']) ? '../uploads/eleves/' . $eleve['photo'] : '';
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

        /* ===== SIDEBAR ===== */
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

        /* ===== OVERLAY ===== */
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

        /* ===== HAMBURGER ===== */
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

        /* ===== CONTENU PRINCIPAL ===== */
        .main-content {
            margin-left: 240px;
            padding: 20px 25px;
            min-height: 100vh;
        }

        /* ===== HEADER ===== */
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

        /* ===== PROFIL CARD ===== */
        .profile-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 20px 25px;
            max-width: 650px;
        }

        /* ===== PHOTO ===== */
        .photo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f2f5;
        }
        .photo-section .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            overflow: hidden;
            object-fit: cover;
            border: 3px solid #e2e8f0;
        }
        .photo-section .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .photo-section .photo-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .photo-section .photo-actions .btn-photo {
            padding: 5px 16px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-photo.upload { background: #667eea; color: white; }
        .btn-photo.upload:hover { background: #5a6fd6; }
        .btn-photo.remove { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; }
        .btn-photo.remove:hover { background: #fed7d7; }

        .profile-card .info-row {
            display: flex;
            padding: 6px 0;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.85rem;
        }
        .profile-card .info-row:last-child { border-bottom: none; }
        .profile-card .info-label {
            width: 110px;
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
        .profile-card .form-inline {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .profile-card .form-inline .form-control { flex: 1; min-width: 150px; }

        .btn-edit {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            transition: 0.3s;
            white-space: nowrap;
        }
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102,126,234,0.35);
            color: white;
        }
        .btn-edit i { margin-right: 4px; }

        .alert-custom {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            max-width: 650px;
            margin-bottom: 15px;
        }
        .alert-custom.success { background: #f0fff4; color: #276749; border: 1px solid #c6f6d5; }
        .alert-custom.danger { background: #fff5f5; color: #c53030; border: 1px solid #fed7d7; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.open { transform: translateX(0); }
            .hamburger-btn { display: flex !important; }
            .main-content { margin-left: 0; padding: 15px; }
            .header .logout-header { display: inline-block; }
            .profile-card { padding: 15px 18px; }
            .profile-card .info-row { flex-direction: column; padding: 4px 0; }
            .profile-card .info-label { width: 100%; margin-bottom: 2px; }
            .profile-card .form-inline { flex-direction: column; }
            .profile-card .form-inline .form-control { min-width: 100%; }
            .btn-edit { width: 100%; justify-content: center; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 10px; }
            .header h2 { font-size: 0.95rem; }
            .header .badge { font-size: 0.65rem; padding: 3px 10px; }
            .profile-card { padding: 12px 14px; }
            .profile-card .info-row { font-size: 0.8rem; }
            .profile-card .info-label { font-size: 0.7rem; }
            .profile-card .info-value { font-size: 0.8rem; }
            .photo-section .avatar { width: 80px; height: 80px; font-size: 2.2rem; }
        }

        @media (min-width: 992px) {
            .hamburger-btn { display: none !important; }
            .sidebar-overlay { display: none !important; }
        }
    </style>
</head>
<body>

<!-- ===== OVERLAY ===== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ===== HAMBURGER ===== -->
<button class="hamburger-btn" id="hamburgerBtn" title="Menu">
    <i class="fas fa-bars"></i>
</button>

<!-- ===== SIDEBAR ===== -->
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

<!-- ===== CONTENU PRINCIPAL ===== -->
<div class="main-content">

    <!-- Header -->
    <div class="header">
        <h2><i class="fas fa-user"></i> Mon Profil</h2>
        <div>
            <span class="badge"><i class="fas fa-id-card me-1"></i> <?php echo $_SESSION['eleve_matricule']; ?></span>
            <a href="../logout.php" class="btn btn-sm btn-outline-danger ms-2 logout-header" style="border-radius:6px; padding:4px 12px; font-size:0.75rem;">
                <i class="fas fa-sign-out-alt me-1"></i> Quitter
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert-custom success"><i class="fas fa-check-circle me-2"></i> <?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-custom danger"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Profil -->
    <div class="profile-card">

        <!-- ===== PHOTO ===== -->
        <div class="photo-section">
            <div class="avatar">
                <?php if ($photoUrl && file_exists(__DIR__ . '/../' . $photoUrl)): ?>
                    <img src="<?php echo $photoUrl; ?>" alt="Photo de profil">
                <?php else: ?>
                    <i class="fas fa-user-graduate"></i>
                <?php endif; ?>
            </div>
            <div class="photo-actions">
                <form method="POST" action="" enctype="multipart/form-data" style="display:inline;">
                    <input type="hidden" name="action" value="upload_photo">
                    <label for="photoInput" class="btn-photo upload" style="cursor:pointer;">
                        <i class="fas fa-camera me-1"></i> Changer photo
                    </label>
                    <input type="file" id="photoInput" name="photo" accept="image/*" style="display:none;" onchange="this.form.submit()">
                </form>
                <?php if (!empty($eleve['photo'])): ?>
                    <form method="POST" action="" style="display:inline;">
                        <input type="hidden" name="action" value="remove_photo">
                        <button type="submit" class="btn-photo remove">
                            <i class="fas fa-trash me-1"></i> Supprimer
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== INFOS ===== -->
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
            <div class="info-label"><i class="fas fa-calendar-alt me-1"></i> Naissance</div>
            <div class="info-value"><?php echo $eleve['dateNaissance'] ? date('d/m/Y', strtotime($eleve['dateNaissance'])) : '-'; ?></div>
        </div>

        <div class="info-row">
            <div class="info-label"><i class="fas fa-phone me-1"></i> Téléphone</div>
            <div class="info-value">
                <form method="POST" action="" class="form-inline">
                    <input type="hidden" name="action" value="update_info">
                    <input type="tel" class="form-control" name="telephone" 
                           value="<?php echo htmlspecialchars($eleve['telephone'] ?? ''); ?>" 
                           placeholder="+243 812 345 678">
                    <button type="submit" class="btn-edit"><i class="fas fa-save"></i> Mettre à jour</button>
                </form>
            </div>
        </div>

        <div class="info-row">
            <div class="info-label"><i class="fas fa-map-marker-alt me-1"></i> Adresse</div>
            <div class="info-value">
                <form method="POST" action="" class="form-inline">
                    <input type="hidden" name="action" value="update_info">
                    <input type="text" class="form-control" name="adresse" 
                           value="<?php echo htmlspecialchars($eleve['adresse'] ?? ''); ?>" 
                           placeholder="Adresse complète">
                    <button type="submit" class="btn-edit"><i class="fas fa-save"></i> Mettre à jour</button>
                </form>
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

    </div>

</div>

<!-- ===== SCRIPT ===== -->
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

</body>
</html>