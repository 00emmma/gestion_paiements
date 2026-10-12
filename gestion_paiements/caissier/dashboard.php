<?php
// caissier/dashboard.php - Dashboard Caissier (AVEC ICONES BOOTSTRAP)
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Caissier') {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';

$page_title = 'Tableau de Bord - Caissier';
$db = db();

// Statistiques
$stats = [];

// Total paiements aujourd'hui
$stmt = $db->query("SELECT SUM(montantPaye) as total, COUNT(*) as nombre 
                    FROM paiements 
                    WHERE DATE(datePaiement) = CURDATE()");
$paiements_jour = $stmt->fetch();
$stats['paiements_jour_montant'] = $paiements_jour['total'] ?? 0;
$stats['paiements_jour_nombre'] = $paiements_jour['nombre'] ?? 0;

// Total paiements du mois
$stmt = $db->query("SELECT SUM(montantPaye) as total, COUNT(*) as nombre 
                    FROM paiements 
                    WHERE MONTH(datePaiement) = MONTH(CURDATE()) 
                    AND YEAR(datePaiement) = YEAR(CURDATE())");
$paiements_mois = $stmt->fetch();
$stats['paiements_mois_montant'] = $paiements_mois['total'] ?? 0;
$stats['paiements_mois_nombre'] = $paiements_mois['nombre'] ?? 0;

// Total élèves
$stmt = $db->query("SELECT COUNT(*) as total FROM eleves");
$stats['eleves'] = $stmt->fetch()['total'];

// Total classes
$stmt = $db->query("SELECT COUNT(*) as total FROM classes");
$stats['classes'] = $stmt->fetch()['total'];

// Total inscriptions (année en cours)
$stmt = $db->query("SELECT COUNT(*) as total FROM inscriptions 
                    WHERE idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)");
$stats['inscrits'] = $stmt->fetch()['total'];

// Total reçus générés
$stmt = $db->query("SELECT COUNT(*) as total FROM paiements WHERE numeroRecu IS NOT NULL");
$stats['recus'] = $stmt->fetch()['total'] ?? 0;

// Nombre de notifications non lues
try {
    $stmt = $db->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->fetch()) {
        $stmt = $db->query("SELECT COUNT(*) as total FROM notifications WHERE est_lu = 0");
        $stats['notifications_non_lues'] = $stmt->fetch()['total'] ?? 0;
    } else {
        $stats['notifications_non_lues'] = 0;
    }
} catch (PDOException $e) {
    $stats['notifications_non_lues'] = 0;
}

// Derniers paiements (5 derniers)
$stmt = $db->query("SELECT p.*, e.matricule, e.nom, e.prenom, f.libelle as frais_libelle,
                           c.nomClasse
                    FROM paiements p
                    JOIN inscriptions i ON p.idInscription = i.idInscription
                    JOIN eleves e ON i.idEleve = e.idEleve
                    JOIN frais f ON p.idFrais = f.idFrais
                    JOIN classes c ON i.idClasse = c.idClasse
                    ORDER BY p.datePaiement DESC
                    LIMIT 5");
$derniers_paiements = $stmt->fetchAll();

// Paiements par mois (graphique)
$stmt = $db->query("SELECT 
                    MONTH(datePaiement) as mois,
                    SUM(montantPaye) as total
                    FROM paiements
                    WHERE YEAR(datePaiement) = YEAR(CURDATE())
                    GROUP BY MONTH(datePaiement)
                    ORDER BY mois ASC");
$paiements_par_mois = $stmt->fetchAll();

// Préparer les données pour le graphique
$mois_labels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
$mois_donnees = array_fill(0, 12, 0);
foreach ($paiements_par_mois as $p) {
    $mois_donnees[$p['mois'] - 1] = (float)$p['total'];
}
$mois_donnees_json = json_encode($mois_donnees);

// Récupérer l'année active
$annee_active = null;
try {
    $stmt = $db->query("SELECT * FROM annees_scolaires WHERE active = 1 LIMIT 1");
    $annee_active = $stmt->fetch();
} catch (PDOException $e) {
    $annee_active = null;
}

// Dernières notifications
$dernieres_notifications = [];
try {
    $stmt = $db->query("SHOW TABLES LIKE 'notifications'");
    if ($stmt->fetch()) {
        $stmt = $db->query("SELECT * FROM notifications ORDER BY date_creation DESC LIMIT 5");
        $dernieres_notifications = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $dernieres_notifications = [];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* ===== STYLES COMPLETS ===== */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: #f0f4f8;
        font-size: 14px;
    }

    .app-layout { display: flex; min-height: 100vh; }

    /* ===== SIDEBAR ===== */
    .sidebar-caissier {
        width: 230px;
        flex-shrink: 0;
        background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
        min-height: 100vh;
        padding: 16px 12px;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease-in-out;
        z-index: 1000;
    }
    .sidebar-caissier::-webkit-scrollbar { width: 3px; }
    .sidebar-caissier::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }

    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 14px;
        margin-bottom: 14px;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .sidebar-logo .logo-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .sidebar-logo span {
        font-size: 1rem;
        font-weight: 700;
        color: white;
        flex: 1;
    }

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
    .sidebar-menu li { margin-bottom: 1px; }
    .sidebar-menu li a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 12px;
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
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .sidebar-menu li a i {
        width: 18px;
        text-align: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .sidebar-footer {
        padding-top: 12px;
        margin-top: 12px;
        border-top: 1px solid rgba(255,255,255,0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
    }
    .sidebar-footer .user-info {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        min-width: 0;
    }
    .sidebar-footer .user-info .avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
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
        font-size: 0.6rem;
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
        font-size: 0.75rem;
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
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.5);
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: none;
        align-items: center;
        justify-content: center;
    }
    .sidebar-toggle-mobile:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
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

    /* ===== CONTENU PRINCIPAL ===== */
    .main-content {
        flex: 1;
        padding: 20px 25px;
        background: #f0f4f8;
        min-height: 100vh;
        width: 100%;
        margin-left: 0;
    }

    /* ===== HEADER ===== */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 25px;
    }
    .dashboard-header h1 {
        font-size: 1.4rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .dashboard-header h1 i {
        color: #667eea;
        margin-right: 10px;
    }
    .dashboard-header .header-right {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .dashboard-header .header-date {
        background: white;
        padding: 6px 16px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        font-weight: 500;
        color: #4a5568;
        font-size: 0.8rem;
    }
    .dashboard-header .header-date i {
        color: #667eea;
        margin-right: 6px;
    }

    /* ===== NOTIFICATIONS ===== */
    .notif-icon {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        background: white;
        border-radius: 50%;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        color: #4a5568;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        border: none;
    }
    .notif-icon:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        color: #667eea;
    }
    .notif-icon .notif-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #fc8181;
        color: white;
        font-size: 0.55rem;
        font-weight: 700;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #f0f4f8;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    .notif-dropdown {
        position: absolute;
        top: 50px;
        right: 0;
        background: white;
        border-radius: 14px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        width: 340px;
        max-height: 380px;
        overflow-y: auto;
        display: none;
        z-index: 1002;
        padding: 6px 0;
    }
    .notif-dropdown.show {
        display: block;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .notif-dropdown .notif-header {
        padding: 10px 16px;
        border-bottom: 1px solid #edf2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .notif-dropdown .notif-header h6 {
        font-weight: 700;
        margin: 0;
        font-size: 0.85rem;
        color: #1a202c;
    }
    .notif-dropdown .notif-header a {
        font-size: 0.7rem;
        color: #667eea;
        text-decoration: none;
    }
    .notif-dropdown .notif-header a:hover {
        text-decoration: underline;
    }
    .notif-item {
        padding: 8px 16px;
        border-bottom: 1px solid #f7fafc;
        transition: 0.3s;
        cursor: pointer;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }
    .notif-item:hover {
        background: #f7fafc;
    }
    .notif-item .notif-icon-small {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #ebf8ff;
        color: #2b6cb0;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.7rem;
    }
    .notif-item .notif-content {
        flex: 1;
    }
    .notif-item .notif-content .notif-title {
        font-weight: 600;
        font-size: 0.8rem;
        color: #1a202c;
    }
    .notif-item .notif-content .notif-desc {
        font-size: 0.75rem;
        color: #718096;
    }
    .notif-item .notif-content .notif-time {
        font-size: 0.65rem;
        color: #a0aec0;
    }
    .notif-item.non-lu {
        background: #ebf8ff;
    }
    .notif-item.non-lu:hover {
        background: #d6edff;
    }
    .notif-empty {
        padding: 25px 16px;
        text-align: center;
        color: #a0aec0;
    }
    .notif-empty i {
        font-size: 1.8rem;
        display: block;
        margin-bottom: 8px;
        color: #e2e8f0;
    }

    /* ===== STAT CARDS ===== */
    .stat-card {
        background: white;
        border: none;
        border-radius: 14px;
        padding: 18px 20px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    }
    .stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .stat-card .stat-number {
        font-size: 1.6rem;
        font-weight: 800;
        color: #1a202c;
        line-height: 1.2;
    }
    .stat-card .stat-label {
        font-size: 0.8rem;
        color: #718096;
        font-weight: 500;
        margin-top: 2px;
    }
    .stat-card .stat-change {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 20px;
        display: inline-block;
        margin-top: 4px;
    }
    .stat-card .stat-change.up { background: #f0fff4; color: #276749; }
    .stat-card .stat-change.down { background: #fff5f5; color: #9b2c2c; }
    .stat-card .stat-change.neutral { background: #f7fafc; color: #4a5568; }

    /* ===== WIDGETS ===== */
    .widget {
        background: white;
        border-radius: 14px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 20px;
        height: 100%;
    }
    .widget .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .widget .widget-header h5 {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .widget .widget-header h5 i {
        color: #667eea;
        margin-right: 8px;
    }
    .widget .widget-header .widget-action {
        color: #667eea;
        font-size: 0.75rem;
        font-weight: 600;
        text-decoration: none;
    }
    .widget .widget-header .widget-action:hover {
        text-decoration: underline;
    }

    /* ===== TABLEAU ===== */
    .table-custom {
        font-size: 0.8rem;
    }
    .table-custom thead th {
        background: #f7fafc;
        color: #4a5568;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
        padding: 8px 10px;
    }
    .table-custom tbody td {
        padding: 8px 10px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
    }
    .table-custom tbody tr:hover {
        background: #f7fafc;
    }

    .chart-container {
        position: relative;
        height: 250px;
    }

    .info-annee {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    .info-annee .item {
        background: #f7fafc;
        padding: 10px 16px;
        border-radius: 8px;
        flex: 1;
        min-width: 150px;
    }
    .info-annee .item small {
        display: block;
        color: #a0aec0;
        font-size: 0.7rem;
    }
    .info-annee .item strong {
        font-size: 1rem;
        color: #1a202c;
    }

    /* ============================================================ */
    /* ===== RESPONSIVE ===== */
    /* ============================================================ */

    @media (max-width: 991px) {
        .sidebar-caissier {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            transform: translateX(-100%);
            width: 280px;
            z-index: 1000;
            height: 100vh;
            border-radius: 0;
        }
        .sidebar-caissier.open { transform: translateX(0); }
        .sidebar-close { display: block !important; }
        .sidebar-toggle-mobile { display: flex !important; }
        .sidebar-overlay.active { display: block !important; opacity: 1; }
        
        .main-content {
            padding: 15px !important;
            width: 100% !important;
        }

        .dashboard-header {
            flex-direction: column;
            text-align: center;
        }
        .dashboard-header h1 { font-size: 1.2rem; }
        .dashboard-header .header-right {
            flex-wrap: wrap;
            justify-content: center;
        }

        .stat-card .stat-number { font-size: 1.3rem; }
        .widget { padding: 14px; }
        .chart-container { height: 180px; }
        .info-annee { flex-direction: column; }

        .notif-dropdown {
            width: 300px;
            right: -10px;
        }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .stat-card { padding: 14px; }
        .stat-card .stat-number { font-size: 1.1rem; }
        .stat-card .stat-icon { width: 36px; height: 36px; font-size: 1rem; }
        .dashboard-header h1 { font-size: 1rem; }
        .widget .widget-header h5 { font-size: 0.85rem; }
        .chart-container { height: 160px; }
        .table-custom { font-size: 0.7rem; }
        .table-custom thead th, .table-custom tbody td { padding: 4px 6px; }
        .sidebar-toggle-mobile {
            width: 40px;
            height: 40px;
            font-size: 0.9rem;
            bottom: 15px;
            left: 15px;
        }
        .notif-dropdown {
            width: 270px;
            right: -20px;
            max-height: 320px;
        }
        .notif-item { padding: 6px 12px; }
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
    <div class="sidebar-caissier" id="sidebarCaissier">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class="fas fa-coins"></i></div>
            <span>GestionScolaire</span>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>

        <ul class="sidebar-menu">
            <!-- Tableau de bord -->
            <li><a href="dashboard.php" class="active">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a></li>

            <!-- Paiements -->
            <li class="menu-section"><i class="fas fa-money-bill-wave"></i> Paiements</li>
            <li><a href="paiements/index.php">
                <i class="fas fa-list"></i> Liste des paiements
            </a></li>

            <!-- Élèves -->
            <li class="menu-section"><i class="fas fa-users"></i> Élèves</li>
            <li><a href="eleves/index.php">
                <i class="fas fa-user-graduate"></i> Liste des élèves
            </a></li>
            <li><a href="eleves/situation_financiere.php">
                <i class="fas fa-chart-pie"></i> Situation financière
            </a></li>

            <!-- Reçus -->
            <li class="menu-section"><i class="fas fa-file-invoice"></i> Reçus</li>
            <li><a href="recus/generer.php">
                <i class="fas fa-file-invoice"></i> Générer un reçu
            </a></li>
            <li><a href="recus/historique.php">
                <i class="fas fa-history"></i> Historique des reçus
            </a></li>

            <!-- Mon profil -->
            <li class="menu-section"><i class="fas fa-user-circle"></i> Compte</li>
            <li><a href="profil/index.php">
                <i class="fas fa-user-circle"></i> Mon profil
            </a></li>
        </ul>

        <!-- Footer Sidebar -->
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['prenom'] ?? 'C', 0, 1) . substr($_SESSION['nom'] ?? 'A', 0, 1)); ?></div>
                <div>
                    <strong><?php echo $_SESSION['prenom'] ?? 'Caissier'; ?> <?php echo $_SESSION['nom'] ?? ''; ?></strong>
                    <span><?php echo $_SESSION['role'] ?? 'Caissier'; ?></span>
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
    <button class="sidebar-toggle-mobile" id="sidebarToggle" title="Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- CONTENU PRINCIPAL -->
    <div class="main-content">

        <!-- ===== HEADER ===== -->
        <div class="dashboard-header">
            <h1>
                <i class="fas fa-tachometer-alt"></i>
                Tableau de Bord
            </h1>
            <div class="header-right">
                <!-- ===== ICONE NOTIFICATION ===== -->
                <div style="position: relative;">
                    <button class="notif-icon" id="notifToggle" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i>
                        <?php if ($stats['notifications_non_lues'] > 0): ?>
                            <span class="notif-badge"><?php echo $stats['notifications_non_lues']; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- ===== DROPDOWN NOTIFICATIONS ===== -->
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                            <?php if ($stats['notifications_non_lues'] > 0): ?>
                                <a href="#" onclick="marquerToutLu()">Tout marquer comme lu</a>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($dernieres_notifications)): ?>
                            <div class="notif-empty">
                                <i class="fas fa-bell-slash"></i>
                                <div>Aucune notification</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($dernieres_notifications as $notif): ?>
                                <div class="notif-item <?php echo isset($notif['est_lu']) && $notif['est_lu'] == 0 ? 'non-lu' : ''; ?>" 
                                     onclick="marquerLu(<?php echo $notif['idNotification'] ?? 0; ?>)">
                                    <div class="notif-icon-small">
                                        <i class="fas fa-<?php echo $notif['type'] ?? 'info-circle'; ?>"></i>
                                    </div>
                                    <div class="notif-content">
                                        <div class="notif-title"><?php echo htmlspecialchars($notif['titre'] ?? 'Notification'); ?></div>
                                        <div class="notif-desc"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></div>
                                        <div class="notif-time">
                                            <?php 
                                                $date = new DateTime($notif['date_creation'] ?? 'now');
                                                echo $date->format('d/m/Y H:i');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== DATE ===== -->
                <div class="header-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES - LIGNE 1 ===== -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number">$<?php echo number_format($stats['paiements_jour_montant'], 2); ?></div>
                            <div class="stat-label">Paiements aujourd'hui</div>
                            <span class="stat-change up">
                                <i class="fas fa-arrow-up me-1"></i> <?php echo $stats['paiements_jour_nombre']; ?> transactions
                            </span>
                        </div>
                        <div class="stat-icon" style="background: rgba(102, 126, 234, 0.15); color: #667eea;">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number">$<?php echo number_format($stats['paiements_mois_montant'], 2); ?></div>
                            <div class="stat-label">Paiements du mois</div>
                            <span class="stat-change up">
                                <i class="fas fa-calendar-alt me-1"></i> <?php echo $stats['paiements_mois_nombre']; ?> paiements
                            </span>
                        </div>
                        <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo $stats['eleves']; ?></div>
                            <div class="stat-label">Total Élèves</div>
                            <span class="stat-change neutral">
                                <i class="fas fa-users me-1"></i> <?php echo $stats['inscrits']; ?> inscrits
                            </span>
                        </div>
                        <div class="stat-icon" style="background: rgba(102, 126, 234, 0.15); color: #667eea;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo $stats['classes']; ?></div>
                            <div class="stat-label">Total Classes</div>
                            <span class="stat-change neutral">
                                <i class="fas fa-chalkboard me-1"></i> En activité
                            </span>
                        </div>
                        <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES - LIGNE 2 ===== -->
        <div class="row g-3 mb-4">
            <div class="col-xl-4 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo $stats['recus']; ?></div>
                            <div class="stat-label">Reçus générés</div>
                            <span class="stat-change neutral">
                                <i class="fas fa-file-invoice me-1"></i> Total
                            </span>
                        </div>
                        <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo $stats['paiements_jour_nombre']; ?></div>
                            <div class="stat-label">Transactions aujourd'hui</div>
                            <span class="stat-change up">
                                <i class="fas fa-arrow-up me-1"></i> En temps réel
                            </span>
                        </div>
                        <div class="stat-icon" style="background: rgba(102, 126, 234, 0.15); color: #667eea;">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-number"><?php echo $stats['paiements_mois_nombre']; ?></div>
                            <div class="stat-label">Paiements ce mois</div>
                            <span class="stat-change up">
                                <i class="fas fa-calendar-alt me-1"></i> <?php echo date('F Y'); ?>
                            </span>
                        </div>
                        <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== GRAPHIQUE ===== -->
        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <div class="widget">
                    <div class="widget-header">
                        <h5>
                            <i class="fas fa-chart-bar"></i>
                            Évolution des paiements (<?php echo date('Y'); ?>)
                        </h5>
                        <span class="badge bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-dollar-sign me-1"></i> USD
                        </span>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartPaiements"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="widget">
                    <div class="widget-header">
                        <h5>
                            <i class="fas fa-calendar-alt"></i>
                            Année scolaire en cours
                        </h5>
                        <span class="badge bg-success bg-opacity-10 text-success">
                            <i class="fas fa-check-circle me-1"></i> Active
                        </span>
                    </div>
                    <div class="info-annee">
                        <?php if ($annee_active): ?>
                            <div class="item">
                                <small><i class="fas fa-tag me-1"></i>Libellé</small>
                                <strong><?php echo htmlspecialchars($annee_active['libelle'] ?? 'N/A'); ?></strong>
                            </div>
                            <div class="item">
                                <small><i class="fas fa-info-circle me-1"></i>Statut</small>
                                <strong><span class="text-success"><i class="fas fa-check-circle me-1"></i>En cours</span></strong>
                            </div>
                        <?php else: ?>
                            <div class="item text-center text-muted py-3 w-100">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Aucune année scolaire active
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== DERNIERS PAIEMENTS ===== -->
        <div class="row g-3">
            <div class="col-xl-12">
                <div class="widget">
                    <div class="widget-header">
                        <h5>
                            <i class="fas fa-clock"></i>
                            Derniers paiements
                        </h5>
                        <a href="paiements/index.php" class="widget-action">
                            Voir tout <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag me-1"></i>N° Reçu</th>
                                    <th><i class="fas fa-id-card me-1"></i>Matricule</th>
                                    <th><i class="fas fa-user me-1"></i>Élève</th>
                                    <th><i class="fas fa-tag me-1"></i>Frais</th>
                                    <th><i class="fas fa-calendar-alt me-1"></i>Mois</th>
                                    <th><i class="fas fa-dollar-sign me-1"></i>Montant</th>
                                    <th><i class="fas fa-chalkboard me-1"></i>Classe</th>
                                    <th><i class="fas fa-clock me-1"></i>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($derniers_paiements)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-3">
                                            <i class="fas fa-inbox me-2"></i>Aucun paiement récent
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($derniers_paiements as $p): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($p['numeroRecu'] ?? 'N/A'); ?></strong></td>
                                            <td><strong><?php echo htmlspecialchars($p['matricule']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?></td>
                                            <td><?php echo htmlspecialchars($p['frais_libelle']); ?></td>
                                            <td><?php echo htmlspecialchars($p['mois'] ?? '-'); ?></td>
                                            <td><strong>$<?php echo number_format($p['montantPaye'], 2); ?></strong></td>
                                            <td><?php echo htmlspecialchars($p['nomClasse']); ?></td>
                                            <td><small><?php echo date('d/m/Y H:i', strtotime($p['datePaiement'])); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
</div>

<!-- ===== SCRIPTS ===== -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // ===== NOTIFICATIONS =====
    function toggleNotifications() {
        const dropdown = document.getElementById('notifDropdown');
        dropdown.classList.toggle('show');
    }

    document.addEventListener('click', function(event) {
        const notifContainer = document.querySelector('.notif-icon')?.closest('div');
        if (notifContainer && !notifContainer.contains(event.target)) {
            document.getElementById('notifDropdown').classList.remove('show');
        }
    });

    function marquerLu(id) {
        if (id > 0) {
            fetch('../api/notifications/marquer_lu.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    }

    function marquerToutLu() {
        fetch('../api/notifications/marquer_tout_lu.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    // ===== GRAPHIQUE =====
    const ctx = document.getElementById('chartPaiements').getContext('2d');
    const moisLabels = <?php echo json_encode($mois_labels); ?>;
    const moisDonnees = <?php echo $mois_donnees_json; ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: moisLabels,
            datasets: [{
                label: 'Montant encaissé (USD)',
                data: moisDonnees,
                backgroundColor: 'rgba(102, 126, 234, 0.7)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '$ ' + context.parsed.y.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString('en-US', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // ===== SIDEBAR MOBILE =====
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebarCaissier');
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
    });
</script>
</body>
</html>