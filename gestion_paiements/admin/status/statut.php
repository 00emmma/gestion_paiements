<?php
// admin/journal/admin/statut.php - Statut des utilisateurs
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../../login.php');
    exit();
}

require_once __DIR__ . '/../../../config/database.php';

$page_title = 'Statut des utilisateurs - Administrateur';
$db = db();

// ===== RÉCUPÉRER TOUS LES UTILISATEURS =====
$stmt = $db->query("
    SELECT idUtilisateur, nom, prenom, username, email, role, 
           derniere_activite, dateCreation, statut 
    FROM utilisateurs 
    ORDER BY nom, prenom
");
$utilisateurs = $stmt->fetchAll();

// ===== DÉFINIR LE STATUT EN LIGNE (15 minutes d'inactivité) =====
$temps_inactivite = 15; // minutes
$temps_limite = date('Y-m-d H:i:s', strtotime("-$temps_inactivite minutes"));

// ===== COMPTER LES STATISTIQUES =====
$total_utilisateurs = count($utilisateurs);
$en_ligne = 0;
$hors_ligne = 0;

foreach ($utilisateurs as $u) {
    if ($u['derniere_activite'] && $u['derniere_activite'] >= $temps_limite) {
        $en_ligne++;
    } else {
        $hors_ligne++;
    }
}

include __DIR__ . '/../../../includes/header.php';
?>

<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: #f8fafc;
        font-size: 14px;
    }

    .app-layout { display: flex; min-height: 100vh; }

    .sidebar-admin {
        width: 240px;
        flex-shrink: 0;
        background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
        min-height: 100vh;
        padding: 18px 12px;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease-in-out;
        z-index: 1000;
    }
    .sidebar-admin::-webkit-scrollbar { width: 3px; }
    .sidebar-admin::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }

    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 16px;
        margin-bottom: 16px;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .sidebar-logo i { font-size: 1.3rem; color: #667eea; }
    .sidebar-logo span { font-size: 1rem; font-weight: 700; color: white; flex: 1; }

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

    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.4);
        z-index: 999;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .sidebar-overlay.active { display: block; opacity: 1; }

    .sidebar-toggle-mobile {
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
        display: none;
        align-items: center;
        justify-content: center;
    }
    .sidebar-toggle-mobile:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 25px rgba(102,126,234,0.5);
    }

    .sidebar-close {
        background: none;
        border: none;
        color: #a0aec0;
        font-size: 1rem;
        cursor: pointer;
        display: none;
        padding: 4px 8px;
    }
    .sidebar-close:hover { color: white; }

    .main-content {
        flex: 1;
        padding: 20px 25px;
        background: #f8fafc;
        min-height: 100vh;
        max-height: 100vh;
        overflow-y: auto;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .page-header h2 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .page-header h2 i {
        color: #667eea;
        margin-right: 10px;
    }
    .page-header .header-date {
        background: white;
        padding: 6px 16px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        font-weight: 500;
        color: #4a5568;
        font-size: 0.8rem;
    }
    .page-header .header-date i {
        color: #667eea;
        margin-right: 6px;
    }

    .btn-retour {
        background: #667eea;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-retour:hover {
        background: #5a67d8;
        color: white;
        text-decoration: none;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(102,126,234,0.3);
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        padding: 12px 18px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .stat-item .stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .stat-item .stat-info .number {
        font-size: 1.2rem;
        font-weight: 800;
        color: #1a202c;
        line-height: 1.2;
    }
    .stat-item .stat-info .label {
        font-size: 0.7rem;
        color: #718096;
    }

    .status-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 10px;
        animation: pulse 2s infinite;
        border: 2px solid rgba(255,255,255,0.8);
        box-shadow: 0 0 10px rgba(72, 187, 120, 0.3);
    }
    .status-dot.online {
        background: #48bb78;
        box-shadow: 0 0 15px rgba(72, 187, 120, 0.6);
    }
    .status-dot.offline {
        background: #a0aec0;
        animation: none;
        box-shadow: none;
    }

    @keyframes pulse {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(0.9); }
        100% { opacity: 1; transform: scale(1); }
    }

    .table-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .table-responsive { overflow-x: auto; }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        min-width: 700px;
    }
    .table thead {
        background: #f7fafc;
        border-bottom: 2px solid #e2e8f0;
    }
    .table thead th {
        padding: 12px 15px;
        text-align: left;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #4a5568;
        white-space: nowrap;
    }
    .table tbody tr { 
        border-bottom: 1px solid #f0f2f5; 
        transition: 0.3s; 
    }
    .table tbody tr:hover { background: #f7fafc; }
    .table tbody td { 
        padding: 12px 15px; 
        vertical-align: middle; 
    }

    .badge-role {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-block;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-role.admin { background: #fed7d7; color: #9b2c2c; }
    .badge-role.user { background: #bee3f8; color: #2a69ac; }
    .badge-role.enseignant { background: #fefcbf; color: #975a16; }
    .badge-role.eleve { background: #c6f6d5; color: #276749; }

    .status-text {
        font-weight: 600;
        font-size: 0.85rem;
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
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        margin-left: 8px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .online-badge i {
        font-size: 0.5rem;
    }

    .user-card {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 1rem;
    }
    .user-avatar.online {
        border: 2px solid #48bb78;
    }
    .user-avatar.offline {
        border: 2px solid #a0aec0;
    }

    .user-info {
        display: flex;
        flex-direction: column;
    }
    .user-info .name {
        font-weight: 600;
        color: #1a202c;
    }
    .user-info .username {
        font-size: 0.75rem;
        color: #718096;
    }

    .last-activity {
        font-size: 0.8rem;
        color: #4a5568;
    }
    .last-activity .time-ago {
        font-size: 0.7rem;
        color: #a0aec0;
    }

    .widget {
        background: white;
        border-radius: 10px;
        padding: 16px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    @media (max-width: 991px) {
        .sidebar-admin {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            transform: translateX(-100%);
            width: 260px;
            z-index: 1000;
            height: 100vh;
            border-radius: 0;
        }
        .sidebar-admin.open { transform: translateX(0); }
        .sidebar-close { display: block !important; }
        .sidebar-toggle-mobile { display: flex !important; }
        .sidebar-overlay.active { display: block !important; opacity: 1; }
        
        .main-content {
            padding: 15px !important;
            max-height: none !important;
        }

        .page-header {
            flex-direction: column;
            text-align: center;
        }

        .stats-row { grid-template-columns: 1fr 1fr; }
        .table { font-size: 0.8rem; min-width: 600px; }
        .table thead th, .table tbody td { padding: 8px 10px; }
        .user-avatar { width: 32px; height: 32px; font-size: 0.8rem; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1rem; }
        .stats-row { grid-template-columns: 1fr; }
        .stat-item { padding: 10px 14px; }
        .stat-item .stat-info .number { font-size: 1rem; }
        .table { font-size: 0.7rem; min-width: 500px; }
        .table thead th, .table tbody td { padding: 6px 8px; }
        .sidebar-toggle-mobile {
            width: 42px;
            height: 42px;
            font-size: 0.9rem;
            bottom: 15px;
            left: 15px;
        }
        .user-avatar { width: 28px; height: 28px; font-size: 0.7rem; }
        .online-badge { font-size: 0.55rem; padding: 2px 8px; }
    }

    @media (min-width: 992px) {
        .sidebar-toggle-mobile { display: none !important; }
        .sidebar-overlay { display: none !important; }
        .sidebar-close { display: none !important; }
    }
</style>

<!-- ============================================================ -->
<!-- LAYOUT PRINCIPAL -->
<!-- ============================================================ -->
<div class="app-layout">

    <!-- SIDEBAR -->
    <div class="sidebar-admin" id="sidebarAdmin">
        <div class="sidebar-logo">
            <i class="fas fa-school"></i>
            <span>GestionScolaire</span>
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <ul class="sidebar-menu">
            <li class="menu-section"><i class="fas fa-cog"></i> Administration</li>
            <li><a href="../../../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="../../../eleves/index.php"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="../../../inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="../../../paiements/index.php"><i class="fas fa-money-bill-wave"></i> Paiements</a></li>
            <li><a href="../../../frais/index.php"><i class="fas fa-tag"></i> Frais</a></li>
            <li><a href="../../../classes/index.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="../../../annees/index.php"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
            <li><a href="../../../utilisateurs/index.php"><i class="fas fa-user-cog"></i> Utilisateurs</a></li>
            <li class="menu-section"><i class="fas fa-file-alt"></i> Rapports</li>
            <li><a href="../../../rapports/index.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
            <li><a href="../index.php"><i class="fas fa-history"></i> Journal</a></li>
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
            <a href="../../../logout.php" class="btn-logout" title="Déconnexion">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <!-- OVERLAY MOBILE -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- TOGGLE MOBILE -->
    <button class="sidebar-toggle-mobile" id="sidebarToggle" title="Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- CONTENU PRINCIPAL -->
    <div class="main-content">

        <!-- ===== HEADER ===== -->
        <div class="page-header">
            <h2>
                <i class="fas fa-user-check"></i>
                Statut des utilisateurs
                <span style="font-size:0.8rem; font-weight:400; color:#48bb78; margin-left:12px;">
                    <i class="fas fa-circle" style="font-size:0.6rem;"></i>
                    <?php echo $en_ligne; ?> en ligne
                </span>
                <span style="font-size:0.8rem; font-weight:400; color:#a0aec0; margin-left:8px;">
                    <i class="fas fa-circle" style="font-size:0.6rem;"></i>
                    <?php echo $hors_ligne; ?> hors ligne
                </span>
            </h2>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <a href="../index.php" class="btn-retour">
                    <i class="fas fa-arrow-left"></i>
                    Retour au journal
                </a>
                <div class="header-date">
                    <i class="fas fa-sync-alt"></i>
                    Mis à jour: <?php echo date('H:i:s'); ?>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(102, 126, 234, 0.15); color: #667eea;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_utilisateurs; ?></div>
                    <div class="label">Total utilisateurs</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="number" style="color: #48bb78;"><?php echo $en_ligne; ?></div>
                    <div class="label">En ligne</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(160, 174, 192, 0.15); color: #a0aec0;">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="number" style="color: #a0aec0;"><?php echo $hors_ligne; ?></div>
                    <div class="label">Hors ligne</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="number" style="font-size:0.8rem;"><?php echo $temps_inactivite; ?> min</div>
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
                            <th><i class="fas fa-hashtag"></i></th>
                            <th><i class="fas fa-user"></i> Utilisateur</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-user-tag"></i> Rôle</th>
                            <th><i class="fas fa-circle"></i> Statut</th>
                            <th><i class="fas fa-clock"></i> Dernière activité</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($utilisateurs)): ?>
                            <tr>
                                <td colspan="6">
                                    <div style="text-align:center; padding:40px 20px; color:#a0aec0;">
                                        <i class="fas fa-users" style="font-size:2.5rem; margin-bottom:10px; color:#e2e8f0;"></i>
                                        <p>Aucun utilisateur trouvé</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($utilisateurs as $index => $u): 
                                $est_en_ligne = ($u['derniere_activite'] && $u['derniere_activite'] >= $temps_limite);
                                $statut_label = $est_en_ligne ? 'En ligne' : 'Hors ligne';
                                $statut_class = $est_en_ligne ? 'online' : 'offline';
                                
                                $role_class = 'user';
                                if ($u['role'] === 'Administrateur') $role_class = 'admin';
                                elseif ($u['role'] === 'Enseignant') $role_class = 'enseignant';
                                elseif ($u['role'] === 'Élève') $role_class = 'eleve';

                                // Initiales pour l'avatar
                                $initials = strtoupper(substr($u['prenom'], 0, 1) . substr($u['nom'], 0, 1));
                            ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="user-card">
                                            <div class="user-avatar <?php echo $statut_class; ?>">
                                                <?php echo $initials; ?>
                                            </div>
                                            <div class="user-info">
                                                <div class="name">
                                                    <?php echo htmlspecialchars($u['nom'] . ' ' . $u['prenom']); ?>
                                                    <?php if ($est_en_ligne): ?>
                                                        <span class="online-badge">
                                                            <i class="fas fa-circle"></i> En ligne
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="username">
                                                    @<?php echo htmlspecialchars($u['username']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($u['email'] ?? '-'); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-role <?php echo $role_class; ?>">
                                            <?php echo htmlspecialchars($u['role'] ?? 'Utilisateur'); ?>
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
                                            <?php if ($u['derniere_activite']): ?>
                                                <div><?php echo date('d/m/Y H:i:s', strtotime($u['derniere_activite'])); ?></div>
                                                <div class="time-ago">
                                                    <?php 
                                                        $diff = time() - strtotime($u['derniere_activite']);
                                                        if ($diff < 60) {
                                                            echo 'Il y a ' . $diff . ' secondes';
                                                        } elseif ($diff < 3600) {
                                                            echo 'Il y a ' . floor($diff/60) . ' minutes';
                                                        } elseif ($diff < 86400) {
                                                            echo 'Il y a ' . floor($diff/3600) . ' heures';
                                                        } else {
                                                            echo 'Il y a ' . floor($diff/86400) . ' jours';
                                                        }
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:#a0aec0;">Jamais</span>
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

        <?php include __DIR__ . '/../../../includes/footer.php'; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebarAdmin');
        const toggleBtn = document.getElementById('sidebarToggle');
        const closeBtn = document.getElementById('sidebarClose');
        const overlay = document.getElementById('sidebarOverlay');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.add('open');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });

        // Auto-refresh toutes les 30 secondes pour mettre à jour les statuts
        setTimeout(function() {
            location.reload();
        }, 30000);
    });
</script>
</body>
</html>