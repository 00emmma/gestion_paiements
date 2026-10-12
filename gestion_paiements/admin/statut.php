<?php
// admin/statut.php - Statut des utilisateurs (connectés actuellement)
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';

$page_title = 'Statut des utilisateurs - Administrateur';
$db = db();

// ===== VÉRIFIER LA STRUCTURE DE LA TABLE journal_activite =====
$colonnes_journal = [];
try {
    $stmt = $db->query("DESCRIBE journal_activite");
    $colonnes_journal = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignorer
}

$colonne_date = 'dateAction';
if (!in_array('dateAction', $colonnes_journal)) {
    if (in_array('date_activite', $colonnes_journal)) {
        $colonne_date = 'date_activite';
    } elseif (in_array('created_at', $colonnes_journal)) {
        $colonne_date = 'created_at';
    }
}

// ===== DÉFINIR LE SEUIL D'INACTIVITÉ (30 minutes pour être plus tolérant) =====
$temps_inactivite = 30; // 30 minutes
$temps_limite = date('Y-m-d H:i:s', strtotime("-$temps_inactivite minutes"));

// ===== RÉCUPÉRER TOUS LES UTILISATEURS =====
$sql = "
    SELECT u.idUtilisateur, u.nom, u.postNom, u.prenom, u.username, u.email, 
           u.telephone, u.sexe, u.statut, u.idRole,
           r.nomRole as role,
           MAX(j.$colonne_date) as derniere_activite
    FROM utilisateurs u
    LEFT JOIN roles r ON u.idRole = r.idRole
    LEFT JOIN journal_activite j ON u.idUtilisateur = j.idUtilisateur
    GROUP BY u.idUtilisateur
    ORDER BY u.nom, u.prenom
";
$stmt = $db->query($sql);
$tous_utilisateurs = $stmt->fetchAll();

// ===== DÉTERMINER QUI EST EN LIGNE =====
$en_ligne = 0;
$hors_ligne = 0;
$utilisateur_actuel_en_ligne = false;

foreach ($tous_utilisateurs as &$u) {
    // Vérifier si l'utilisateur a une activité récente
    if ($u['derniere_activite'] && $u['derniere_activite'] >= $temps_limite) {
        $u['est_en_ligne'] = true;
        $en_ligne++;
        // Vérifier si c'est l'utilisateur connecté
        if ($u['idUtilisateur'] == $_SESSION['user_id']) {
            $utilisateur_actuel_en_ligne = true;
        }
    } else {
        $u['est_en_ligne'] = false;
        $hors_ligne++;
    }
}
unset($u);

// ===== SI L'UTILISATEUR CONNECTÉ N'EST PAS EN LIGNE, LE FORCER =====
// Cela permet de s'assurer que l'administrateur connecté apparaît en ligne
if (!$utilisateur_actuel_en_ligne) {
    foreach ($tous_utilisateurs as &$u) {
        if ($u['idUtilisateur'] == $_SESSION['user_id']) {
            $u['est_en_ligne'] = true;
            $u['derniere_activite'] = date('Y-m-d H:i:s');
            $en_ligne++;
            $hors_ligne--;
            break;
        }
    }
    unset($u);
}

$total_utilisateurs = count($tous_utilisateurs);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f5;
            font-size: 14px;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
            padding: 20px 15px;
            overflow-y: auto;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease-in-out;
        }
        .sidebar::-webkit-scrollbar { width: 3px; }
        .sidebar::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 16px;
            margin-bottom: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-logo i { font-size: 1.3rem; color: #667eea; }
        .sidebar-logo span { font-size: 1rem; font-weight: 700; color: white; }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            flex: 1;
        }
        .sidebar-menu .menu-section {
            color: #a0aec0;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 10px 12px 4px;
            font-weight: 700;
            opacity: 0.4;
        }
        .sidebar-menu li { margin-bottom: 2px; }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 8px;
            color: #a0aec0;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        .sidebar-menu li a:hover {
            background: rgba(255,255,255,0.08);
            color: white;
            text-decoration: none;
        }
        .sidebar-menu li a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .sidebar-menu li a i {
            width: 18px;
            text-align: center;
            font-size: 0.85rem;
        }

        .sidebar-footer {
            padding-top: 16px;
            margin-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }
        .sidebar-footer .user-info i { font-size: 1.4rem; color: #667eea; flex-shrink: 0; }
        .sidebar-footer .user-info div {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar-footer .user-info div strong {
            color: white;
            font-size: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-footer .user-info div span {
            color: #a0aec0;
            font-size: 0.65rem;
        }
        .sidebar-footer .btn-logout {
            background: rgba(255,255,255,0.08);
            color: #fc8181;
            border: none;
            border-radius: 6px;
            padding: 6px 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            flex-shrink: 0;
            font-size: 0.8rem;
        }
        .sidebar-footer .btn-logout:hover {
            background: rgba(255,255,255,0.15);
            color: #feb2b2;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 250px;
            padding: 20px 25px;
            min-height: 100vh;
            background: #f0f2f5;
        }

        /* ===== TOGGLE MOBILE ===== */
        .sidebar-toggle {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1001;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            align-items: center;
            justify-content: center;
        }
        .sidebar-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(102,126,234,0.5);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 999;
        }
        .sidebar-overlay.active { display: block; }

        /* ===== PAGE HEADER ===== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            background: white;
            padding: 14px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .page-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a202c;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-header h2 i { color: #667eea; }
        .page-header .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-retour {
            background: #667eea;
            color: white;
            border: none;
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-retour:hover {
            background: #5a67d8;
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102,126,234,0.3);
        }

        .header-date {
            background: #f7fafc;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 0.8rem;
        }
        .header-date i {
            color: #667eea;
            margin-right: 6px;
        }

        /* ===== STATS ROW ===== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-item {
            background: white;
            padding: 14px 18px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
        }
        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-item .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .stat-item .stat-info .number {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1a202c;
            line-height: 1.2;
        }
        .stat-item .stat-info .label {
            font-size: 0.7rem;
            color: #718096;
            font-weight: 500;
        }

        /* ===== TABLE ===== */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .table-responsive { overflow-x: auto; }
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 700px;
        }
        .table thead {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }
        .table thead th {
            padding: 12px 16px;
            text-align: left;
            font-weight: 700;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a5568;
            white-space: nowrap;
        }
        .table tbody tr { 
            border-bottom: 1px solid #f0f2f5; 
            transition: 0.2s; 
        }
        .table tbody tr:hover { background: #f7fafc; }
        .table tbody td { 
            padding: 12px 16px; 
            vertical-align: middle; 
        }
        .table tbody tr.online-row {
            background: #f0fff4 !important;
        }
        .table tbody tr.online-row:hover {
            background: #e6ffed !important;
        }

        /* ===== BADGES ===== */
        .badge-role {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-role.admin { background: #fee2e2; color: #991b1b; }
        .badge-role.user { background: #dbeafe; color: #1e40af; }
        .badge-role.enseignant { background: #fef3c7; color: #92400e; }
        .badge-role.eleve { background: #d1fae5; color: #065f46; }
        .badge-role.default { background: #e2e8f0; color: #4a5568; }

        .status-label {
            font-size: 0.6rem;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 600;
            margin-left: 6px;
        }
        .status-label.actif {
            background: #d1fae5;
            color: #065f46;
        }
        .status-label.inactif {
            background: #fee2e2;
            color: #991b1b;
        }

        /* ===== STATUS ===== */
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            border: 2px solid rgba(255,255,255,0.8);
            flex-shrink: 0;
        }
        .status-dot.online {
            background: #48bb78;
            box-shadow: 0 0 12px rgba(72, 187, 120, 0.4);
            animation: pulse 2s infinite;
        }
        .status-dot.offline {
            background: #cbd5e0;
            box-shadow: none;
            animation: none;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }

        .status-text {
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
        }
        .status-text.online {
            color: #48bb78;
        }
        .status-text.offline {
            color: #a0aec0;
        }

        .online-badge {
            background: #48bb78;
            color: white;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.6rem;
            font-weight: 700;
            margin-left: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .online-badge i {
            font-size: 0.4rem;
        }

        /* ===== USER CARD ===== */
        .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .user-avatar.admin {
            background: linear-gradient(135deg, #f87171 0%, #991b1b 100%);
        }
        .user-avatar.enseignant {
            background: linear-gradient(135deg, #fbbf24 0%, #92400e 100%);
        }
        .user-avatar.eleve {
            background: linear-gradient(135deg, #34d399 0%, #065f46 100%);
        }
        .user-avatar.default {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .user-avatar.online {
            border: 2px solid #48bb78;
        }
        .user-avatar.offline {
            border: 2px solid #cbd5e0;
            opacity: 0.6;
        }

        .user-info .name {
            font-weight: 600;
            color: #1a202c;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
        }
        .user-info .username {
            font-size: 0.7rem;
            color: #718096;
        }

        .last-activity {
            font-size: 0.75rem;
            color: #4a5568;
        }
        .last-activity .time-ago {
            font-size: 0.65rem;
            color: #a0aec0;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }
        .empty-state i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 10px;
            color: #e2e8f0;
        }

        /* ===== YOU BADGE ===== */
        .you-badge {
            background: #667eea;
            color: white;
            font-size: 0.55rem;
            padding: 1px 8px;
            border-radius: 12px;
            font-weight: 600;
            margin-left: 4px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .sidebar-toggle {
                display: flex !important;
            }
            .sidebar-overlay.active {
                display: block;
            }
            .main-content {
                margin-left: 0;
                padding: 12px;
            }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .table { font-size: 0.75rem; min-width: 600px; }
            .table thead th, .table tbody td { padding: 8px 10px; }
            .user-avatar { width: 30px; height: 30px; font-size: 0.7rem; }
            .page-header {
                flex-direction: column;
                text-align: center;
            }
            .page-header .header-actions {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-row { grid-template-columns: 1fr; }
            .stat-item { padding: 10px 14px; }
            .stat-item .stat-info .number { font-size: 1rem; }
            .table { font-size: 0.65rem; min-width: 500px; }
            .table thead th, .table tbody td { padding: 6px 8px; }
            .user-avatar { width: 26px; height: 26px; font-size: 0.6rem; }
            .online-badge { font-size: 0.5rem; padding: 1px 8px; }
            .header-date { font-size: 0.7rem; padding: 4px 10px; }
            .btn-retour { font-size: 0.7rem; padding: 5px 12px; }
            .page-header h2 { font-size: 0.95rem; }
            .sidebar-toggle {
                width: 38px;
                height: 38px;
                font-size: 0.8rem;
                bottom: 12px;
                left: 12px;
            }
        }
    </style>
</head>
<body>

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-school"></i>
            <span>GestionScolaire</span>
        </div>

        <ul class="sidebar-menu">
            <li class="menu-section"><i class="fas fa-cog"></i> Administration</li>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="eleves/index.php"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="paiements/index.php"><i class="fas fa-money-bill-wave"></i> Paiements</a></li>
            <li><a href="frais/index.php"><i class="fas fa-tag"></i> Frais</a></li>
            <li><a href="classes/index.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="annees/index.php"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
            <li><a href="utilisateurs/index.php"><i class="fas fa-user-cog"></i> Utilisateurs</a></li>
            <li class="menu-section"><i class="fas fa-file-alt"></i> Rapports</li>
            <li><a href="rapports/index.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
            <li><a href="journal/index.php"><i class="fas fa-history"></i> Journal</a></li>
            <li><a href="statut.php" class="active"><i class="fas fa-user-check"></i> Statut</a></li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <strong><?php echo $_SESSION['nom'] . ' ' . $_SESSION['prenom']; ?></strong>
                    <span><?php echo $_SESSION['role']; ?></span>
                </div>
            </div>
            <a href="../logout.php" class="btn-logout" title="Déconnexion">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <!-- OVERLAY MOBILE -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- TOGGLE MOBILE -->
    <button class="sidebar-toggle" id="sidebarToggle" title="Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">

        <!-- ===== HEADER ===== -->
        <div class="page-header">
            <h2>
                <i class="fas fa-user-check"></i>
                Utilisateurs connectés
                <span style="font-size:0.75rem; font-weight:400; color:#48bb78; margin-left:8px;">
                    <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                    <?php echo $en_ligne; ?> en ligne
                </span>
                <span style="font-size:0.75rem; font-weight:400; color:#a0aec0; margin-left:4px;">
                    <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                    <?php echo $hors_ligne; ?> hors ligne
                </span>
            </h2>
            <div class="header-actions">
                <a href="journal/index.php" class="btn-retour">
                    <i class="fas fa-arrow-left"></i>
                    Journal
                </a>
                <div class="header-date">
                    <i class="fas fa-sync-alt"></i>
                    <?php echo date('H:i:s'); ?>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-icon" style="background: #eef2ff; color: #667eea;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_utilisateurs; ?></div>
                    <div class="label">Total utilisateurs</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: #ecfdf5; color: #48bb78;">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="number" style="color: #48bb78;"><?php echo $en_ligne; ?></div>
                    <div class="label">Connectés</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: #f1f5f9; color: #94a3b8;">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="number" style="color: #94a3b8;"><?php echo $hors_ligne; ?></div>
                    <div class="label">Hors ligne</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: #fefce8; color: #f59e0b;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="number" style="font-size:0.9rem;"><?php echo $temps_inactivite; ?> min</div>
                    <div class="label">Seuil d'inactivité</div>
                </div>
            </div>
        </div>

        <!-- ===== TABLEAU DES UTILISATEURS ===== -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Dernière activité</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tous_utilisateurs)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <p>Aucun utilisateur trouvé</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tous_utilisateurs as $index => $u): 
                                $est_en_ligne = $u['est_en_ligne'] ?? false;
                                $statut_label = $est_en_ligne ? 'En ligne' : 'Hors ligne';
                                $statut_class = $est_en_ligne ? 'online' : 'offline';
                                $row_class = $est_en_ligne ? 'online-row' : '';
                                
                                $est_utilisateur_actuel = ($u['idUtilisateur'] == $_SESSION['user_id']);
                                
                                $role_display = $u['role'] ?? 'Utilisateur';
                                $role_class = 'default';
                                if (stripos($role_display, 'admin') !== false) $role_class = 'admin';
                                elseif (stripos($role_display, 'enseignant') !== false) $role_class = 'enseignant';
                                elseif (stripos($role_display, 'élève') !== false || stripos($role_display, 'eleve') !== false) $role_class = 'eleve';
                                elseif (stripos($role_display, 'user') !== false) $role_class = 'user';

                                $prenom = $u['prenom'] ?? '';
                                $postNom = $u['postNom'] ?? '';
                                $nom_complet = trim($u['nom'] . ' ' . $postNom . ' ' . $prenom);
                                $initials = strtoupper(substr($u['nom'], 0, 1) . substr($prenom ?: $u['nom'], 0, 1));
                            ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="user-card">
                                            <div class="user-avatar <?php echo $role_class . ' ' . $statut_class; ?>">
                                                <?php echo $initials; ?>
                                            </div>
                                            <div class="user-info">
                                                <div class="name">
                                                    <?php echo htmlspecialchars($nom_complet); ?>
                                                    <?php if ($est_utilisateur_actuel): ?>
                                                        <span class="you-badge">● Vous</span>
                                                    <?php endif; ?>
                                                    <?php if ($est_en_ligne): ?>
                                                        <span class="online-badge">
                                                            <i class="fas fa-circle"></i> En ligne
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="username">
                                                    @<?php echo htmlspecialchars($u['username']); ?>
                                                    <span class="status-label <?php echo ($u['statut'] === 'Actif') ? 'actif' : 'inactif'; ?>">
                                                        <?php echo $u['statut'] ?? 'Inactif'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($u['email'] ?? '-'); ?></small>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($u['telephone'] ?? '-'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-role <?php echo $role_class; ?>">
                                            <?php echo htmlspecialchars($role_display); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-text <?php echo $statut_class; ?>">
                                            <span class="status-dot <?php echo $statut_class; ?>"></span>
                                            <?php echo $statut_label; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="last-activity">
                                            <?php if ($u['derniere_activite'] && $u['derniere_activite'] != '-'): ?>
                                                <div><?php echo date('d/m/Y H:i', strtotime($u['derniere_activite'])); ?></div>
                                                <div class="time-ago">
                                                    <?php 
                                                        $diff = time() - strtotime($u['derniere_activite']);
                                                        if ($diff < 60) {
                                                            echo 'Il y a ' . $diff . 's';
                                                        } elseif ($diff < 3600) {
                                                            echo 'Il y a ' . floor($diff/60) . ' min';
                                                        } elseif ($diff < 86400) {
                                                            echo 'Il y a ' . floor($diff/3600) . ' h';
                                                        } else {
                                                            echo 'Il y a ' . floor($diff/86400) . ' j';
                                                        }
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:#a0aec0;">Aucune activité</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ============================================================ -->
    <!-- SCRIPTS -->
    <!-- ============================================================ -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebarToggle');
            const overlay = document.getElementById('sidebarOverlay');

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                    overlay.classList.toggle('active');
                });
            }

            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                });
            }

            // Auto-refresh toutes les 30 secondes
            setTimeout(function() {
                location.reload();
            }, 30000);
        });
    </script>
</body>
</html>