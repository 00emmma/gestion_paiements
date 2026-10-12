<?php
// admin/dashboard.php - Dashboard Professionnel
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Administrateur') {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';

$page_title = 'Tableau de Bord - Administrateur';
$db = db();

// Statistiques
$stats = [];

$stmt = $db->query("SELECT COUNT(*) as total FROM eleves");
$stats['eleves'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM inscriptions 
                    WHERE idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)");
$stats['inscrits'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM classes");
$stats['classes'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT SUM(montantPaye) as total FROM paiements 
                    WHERE DATE(datePaiement) = CURDATE()");
$stats['paiements_jour'] = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT SUM(montantPaye) as total FROM paiements 
                    WHERE MONTH(datePaiement) = MONTH(CURDATE()) 
                    AND YEAR(datePaiement) = YEAR(CURDATE())");
$stats['paiements_mois'] = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM paiements 
                    WHERE DATE(datePaiement) = CURDATE()");
$stats['nb_paiements_jour'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM utilisateurs");
$stats['utilisateurs'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM niveaux");
$stats['niveaux'] = $stmt->fetch()['total'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM notifications WHERE est_lu = 0");
$stats['notifications_non_lues'] = $stmt->fetch()['total'] ?? 0;

// Activités récentes
$stmt = $db->query("SELECT j.*, u.nom, u.prenom, u.username 
                    FROM journal_activite j
                    JOIN utilisateurs u ON j.idUtilisateur = u.idUtilisateur
                    ORDER BY j.dateAction DESC
                    LIMIT 5");
$activites = $stmt->fetchAll();

// Derniers paiements
$stmt = $db->query("SELECT p.*, e.matricule, e.nom, e.prenom, f.libelle as frais
                    FROM paiements p
                    JOIN inscriptions i ON p.idInscription = i.idInscription
                    JOIN eleves e ON i.idEleve = e.idEleve
                    JOIN frais f ON p.idFrais = f.idFrais
                    ORDER BY p.datePaiement DESC
                    LIMIT 5");
$derniers_paiements = $stmt->fetchAll();

// Graphiques
$stmt = $db->query("SELECT 
                    MONTH(datePaiement) as mois,
                    SUM(montantPaye) as total
                    FROM paiements
                    WHERE YEAR(datePaiement) = YEAR(CURDATE())
                    GROUP BY MONTH(datePaiement)
                    ORDER BY mois ASC");
$paiements_par_mois = $stmt->fetchAll();

$stmt = $db->query("SELECT 
                    c.nomClasse,
                    SUM(p.montantPaye) as total
                    FROM paiements p
                    JOIN inscriptions i ON p.idInscription = i.idInscription
                    JOIN classes c ON i.idClasse = c.idClasse
                    WHERE YEAR(p.datePaiement) = YEAR(CURDATE())
                    GROUP BY c.idClasse
                    ORDER BY total DESC
                    LIMIT 6");
$paiements_par_classe = $stmt->fetchAll();

$mois_labels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
$mois_donnees = array_fill(0, 12, 0);
foreach ($paiements_par_mois as $p) {
    $mois_donnees[$p['mois'] - 1] = (float)$p['total'];
}
$mois_donnees_json = json_encode($mois_donnees);

$classe_labels = [];
$classe_donnees = [];
foreach ($paiements_par_classe as $c) {
    $classe_labels[] = $c['nomClasse'];
    $classe_donnees[] = (float)$c['total'];
}
$classe_labels_json = json_encode($classe_labels);
$classe_donnees_json = json_encode($classe_donnees);

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* ===== SIDEBAR FIXE ===== */
    .sidebar-admin {
        position: fixed;
        left: 0;
        top: 0;
        width: 260px;
        height: 100vh;
        background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
        padding: 20px 16px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        z-index: 1000;
        transition: transform 0.3s ease-in-out;
    }

    .sidebar-admin::-webkit-scrollbar {
        width: 3px;
    }
    .sidebar-admin::-webkit-scrollbar-thumb {
        background: #4a5568;
        border-radius: 4px;
    }

    /* ===== CONTENU PRINCIPAL ===== */
    .main-content {
        margin-left: 260px;
        padding: 25px 30px;
        background: #f0f4f8;
        min-height: 100vh;
        width: calc(100% - 260px);
        box-sizing: border-box;
    }

    /* ===== CENTRAGE DU CONTENU ===== */
    .content-wrapper {
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
    }

    /* ===== HEADER ===== */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 30px;
    }
    .dashboard-header h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .dashboard-header h1 i {
        color: #667eea;
        margin-right: 12px;
    }
    .dashboard-header .header-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .dashboard-header .header-date {
        background: white;
        padding: 8px 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        font-weight: 500;
        color: #4a5568;
        font-size: 0.85rem;
    }
    .dashboard-header .header-date i {
        color: #667eea;
        margin-right: 8px;
    }

    /* ===== NOTIFICATIONS ===== */
    .notif-icon {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        background: white;
        border-radius: 50%;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        color: #4a5568;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
    }
    .notif-icon:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        color: #667eea;
    }
    .notif-icon .notif-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #fc8181;
        color: white;
        font-size: 0.6rem;
        font-weight: 700;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #f0f4f8;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }

    /* ===== NOTIFICATION DROPDOWN ===== */
    .notif-dropdown {
        position: absolute;
        top: 55px;
        right: 0;
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        width: 380px;
        max-height: 420px;
        overflow-y: auto;
        display: none;
        z-index: 1002;
        padding: 8px 0;
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
        padding: 12px 20px;
        border-bottom: 1px solid #edf2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .notif-dropdown .notif-header h6 {
        font-weight: 700;
        margin: 0;
        color: #1a202c;
    }
    .notif-dropdown .notif-header a {
        font-size: 0.75rem;
        color: #667eea;
        text-decoration: none;
    }
    .notif-item {
        padding: 10px 20px;
        border-bottom: 1px solid #f7fafc;
        transition: 0.3s;
        cursor: pointer;
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }
    .notif-item:hover {
        background: #f7fafc;
    }
    .notif-item .notif-icon-small {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #ebf8ff;
        color: #2b6cb0;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.8rem;
    }
    .notif-item .notif-content {
        flex: 1;
    }
    .notif-item .notif-content .notif-title {
        font-weight: 600;
        font-size: 0.85rem;
        color: #1a202c;
    }
    .notif-item .notif-content .notif-desc {
        font-size: 0.8rem;
        color: #718096;
    }
    .notif-item .notif-content .notif-time {
        font-size: 0.7rem;
        color: #a0aec0;
    }
    .notif-item.non-lu {
        background: #ebf8ff;
    }
    .notif-empty {
        padding: 30px 20px;
        text-align: center;
        color: #a0aec0;
    }
    .notif-empty i {
        font-size: 2rem;
        display: block;
        margin-bottom: 10px;
        color: #e2e8f0;
    }

    /* ===== STAT CARDS ===== */
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 22px 24px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        height: 100%;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
    }
    .stat-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: 800;
        color: #1a202c;
        line-height: 1.2;
    }
    .stat-card .stat-label {
        font-size: 0.85rem;
        color: #718096;
        font-weight: 500;
        margin-top: 4px;
    }
    .stat-card .stat-change {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 20px;
        display: inline-block;
        margin-top: 6px;
    }
    .stat-card .stat-change.up {
        background: #f0fff4;
        color: #276749;
    }
    .stat-card .stat-change.down {
        background: #fff5f5;
        color: #9b2c2c;
    }
    .stat-card .stat-change.neutral {
        background: #f7fafc;
        color: #4a5568;
    }

    /* ===== WIDGETS ===== */
    .widget {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        padding: 24px;
        height: 100%;
    }
    .widget .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .widget .widget-header h5 {
        font-size: 1rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .widget .widget-header h5 i {
        color: #667eea;
        margin-right: 10px;
    }
    .widget .widget-header .widget-action {
        color: #667eea;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
    }
    .widget .widget-header .widget-action:hover {
        text-decoration: underline;
    }

    /* ===== TABLEAU ===== */
    .table-custom {
        font-size: 0.85rem;
    }
    .table-custom thead th {
        background: #f7fafc;
        color: #4a5568;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
        padding: 10px 12px;
    }
    .table-custom tbody td {
        padding: 10px 12px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
    }
    .table-custom tbody tr:hover {
        background: #f7fafc;
    }

    /* ===== CHART CONTAINER ===== */
    .chart-container {
        position: relative;
        height: 280px;
    }

    /* ===== ACTIVITY TIMELINE ===== */
    .timeline-item {
        display: flex;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid #edf2f7;
    }
    .timeline-item:last-child {
        border-bottom: none;
    }
    .timeline-item .timeline-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
        background: #ebf8ff;
        color: #2b6cb0;
    }
    .timeline-item .timeline-content {
        flex: 1;
    }
    .timeline-item .timeline-content .title {
        font-weight: 600;
        font-size: 0.85rem;
        color: #1a202c;
    }
    .timeline-item .timeline-content .desc {
        font-size: 0.8rem;
        color: #718096;
    }
    .timeline-item .timeline-content .time {
        font-size: 0.7rem;
        color: #a0aec0;
    }

    /* ===== SIDEBAR OVERLAY MOBILE ===== */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        z-index: 999;
        display: none;
    }
    .sidebar-overlay.active {
        display: block;
    }

    .sidebar-toggle-mobile {
        display: none;
        position: fixed;
        bottom: 20px;
        left: 20px;
        z-index: 1001;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.5);
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.3s ease;
        align-items: center;
        justify-content: center;
    }
    .sidebar-toggle-mobile:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
    }

    .sidebar-close {
        display: none;
        background: none;
        border: none;
        color: #a0aec0;
        font-size: 1rem;
        cursor: pointer;
        padding: 4px 8px;
    }
    .sidebar-close:hover {
        color: white;
    }

    /* ============================================================ */
    /* ===== RESPONSIVE ===== */
    /* ============================================================ */

    @media (max-width: 991px) {
        .sidebar-admin {
            transform: translateX(-100%);
            width: 280px;
        }
        .sidebar-admin.open {
            transform: translateX(0);
        }
        .sidebar-close {
            display: block !important;
        }
        .sidebar-toggle-mobile {
            display: flex !important;
        }
        .sidebar-overlay.active {
            display: block !important;
        }

        .main-content {
            margin-left: 0;
            width: 100%;
            padding: 15px;
        }
        .dashboard-header {
            flex-direction: column;
            text-align: center;
        }
        .dashboard-header .header-right {
            flex-wrap: wrap;
            justify-content: center;
        }
        .stat-card .stat-number {
            font-size: 1.4rem;
        }
        .widget {
            padding: 16px;
        }
        .chart-container {
            height: 200px;
        }
        .notif-dropdown {
            width: 320px;
            right: -10px;
        }
    }

    @media (max-width: 576px) {
        .main-content {
            padding: 10px;
        }
        .stat-card {
            padding: 16px;
        }
        .stat-card .stat-number {
            font-size: 1.2rem;
        }
        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 1.1rem;
        }
        .dashboard-header h1 {
            font-size: 1rem;
        }
        .widget .widget-header h5 {
            font-size: 0.9rem;
        }
        .chart-container {
            height: 180px;
        }
        .table-custom {
            font-size: 0.75rem;
        }
        .table-custom thead th,
        .table-custom tbody td {
            padding: 6px 8px;
        }
        .sidebar-toggle-mobile {
            width: 44px;
            height: 44px;
            font-size: 1rem;
            bottom: 15px;
            left: 15px;
        }
        .notif-dropdown {
            width: 290px;
            right: -20px;
            max-height: 350px;
        }
        .notif-item {
            padding: 8px 14px;
        }
    }

    @media (min-width: 992px) {
        .sidebar-toggle-mobile {
            display: none !important;
        }
        .sidebar-overlay {
            display: none !important;
        }
        .sidebar-close {
            display: none !important;
        }
    }
</style>

<!-- ============================================================ -->
<!-- SIDEBAR -->
<!-- ============================================================ -->
<div class="sidebar-admin" id="sidebarAdmin">
    <div class="sidebar-logo" style="display:flex; align-items:center; gap:12px; padding-bottom:20px; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.06);">
        <div style="width:40px; height:40px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius:10px; display:flex; align-items:center; justify-content:center; color:white; font-size:1.2rem;">
            <i class="fas fa-school"></i>
        </div>
        <span style="font-size:1.1rem; font-weight:700; color:white; flex:1;">GestionScolaire</span>
        <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
    </div>

    <ul style="list-style:none; padding:0; margin:0; flex:1;">
        <li style="margin-bottom:2px;">
            <a href="dashboard.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; box-shadow:0 4px 15px rgba(102,126,234,0.4);">
                <i class="fas fa-tachometer-alt" style="width:20px; text-align:center;"></i> Tableau de bord
            </a>
        </li>
        <li style="margin-bottom:2px;"><a href="eleves/index.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;"><i class="fas fa-users" style="width:20px; text-align:center;"></i> Élèves</a></li>
        <li style="margin-bottom:2px;"><a href="inscriptions/index.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;"><i class="fas fa-book-open" style="width:20px; text-align:center;"></i> Inscriptions</a></li>
        <li style="margin-bottom:2px;"><a href="paiements/index.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;"><i class="fas fa-money-bill-wave" style="width:20px; text-align:center;"></i> Paiements</a></li>
        <li style="margin-bottom:2px;"><a href="frais/index.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;"><i class="fas fa-tag" style="width:20px; text-align:center;"></i> Frais</a></li>
        <li style="margin-bottom:2px;"><a href="classes/index.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;"><i class="fas fa-chalkboard" style="width:20px; text-align:center;"></i> Classes</a></li>
        <li style="margin-bottom:2px;"><a href="niveaux/index.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;">
            <i class="fas fa-layer-group" style="width:20px; text-align:center;"></i> Niveaux
            <span style="margin-left:auto; background:rgba(255,255,255,0.15); color:white; font-size:0.7rem; padding:2px 10px; border-radius:20px;"><?php echo $stats['niveaux']; ?></span>
        </a></li>
        <li style="margin-bottom:2px;"><a href="annees/index.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;"><i class="fas fa-calendar-alt" style="width:20px; text-align:center;"></i> Années scolaires</a></li>
        <li style="margin-bottom:2px;"><a href="utilisateurs/index.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;"><i class="fas fa-user-cog" style="width:20px; text-align:center;"></i> Utilisateurs</a></li>
        <li style="margin-bottom:2px;"><a href="rapports/index.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;"><i class="fas fa-file-alt" style="width:20px; text-align:center;"></i> Rapports</a></li>
        <li style="margin-bottom:2px;"><a href="journal/index.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;"><i class="fas fa-history" style="width:20px; text-align:center;"></i> Journal</a></li>
        <li style="margin-bottom:2px;"><a href="statut.php" style="display:flex; align-items:center; gap:12px; padding:10px 16px; border-radius:10px; color:#a0aec0; text-decoration:none; font-weight:500; font-size:0.85rem; transition:all 0.3s ease;"><i class="fas fa-user-check" style="width:20px; text-align:center;"></i> Statut</a></li>
        
    </ul>

    <div style="padding-top:16px; margin-top:16px; border-top:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:space-between; gap:8px;">
        <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:0;">
            <div style="width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:0.9rem; flex-shrink:0;"><?php echo strtoupper(substr($_SESSION['prenom'] ?? 'A', 0, 1) . substr($_SESSION['nom'] ?? 'A', 0, 1)); ?></div>
            <div style="display:flex; flex-direction:column; overflow:hidden;">
                <strong style="color:white; font-size:0.8rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo $_SESSION['prenom'] ?? 'Admin'; ?> <?php echo $_SESSION['nom'] ?? ''; ?></strong>
                <span style="color:#a0aec0; font-size:0.7rem;"><?php echo $_SESSION['role'] ?? 'Administrateur'; ?></span>
            </div>
        </div>
        <a href="../logout.php" style="background:rgba(255,255,255,0.08); color:#fc8181; border:none; border-radius:8px; padding:8px 12px; transition:all 0.3s ease; text-decoration:none; flex-shrink:0;" title="Déconnexion">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>

<!-- ============================================================ -->
<!-- OVERLAY MOBILE -->
<!-- ============================================================ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<button class="sidebar-toggle-mobile" id="sidebarToggle" title="Menu">
    <i class="fas fa-bars"></i>
</button>

<!-- ============================================================ -->
<!-- CONTENU PRINCIPAL -->
<!-- ============================================================ -->
<div class="main-content">
    <div class="content-wrapper">

        <!-- ===== HEADER ===== -->
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt"></i> Tableau de Bord</h1>
            <div class="header-right">
                <div style="position:relative;">
                    <button class="notif-icon" id="notifToggle" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i>
                        <?php if ($stats['notifications_non_lues'] > 0): ?>
                            <span class="notif-badge"><?php echo $stats['notifications_non_lues']; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                        </div>
                        <div class="notif-empty">
                            <i class="fas fa-bell-slash"></i>
                            <div>Aucune notification</div>
                        </div>
                    </div>
                </div>
                <div class="header-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('l d F Y, H:i'); ?>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="row g-4 mb-4" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
            <div class="stat-card">
                <div>
                    <div class="stat-number"><?php echo $stats['eleves']; ?></div>
                    <div class="stat-label">Total Élèves</div>
                    <span class="stat-change neutral"><i class="fas fa-users me-1"></i> Inscrits</span>
                </div>
                <div class="stat-icon" style="background:rgba(102,126,234,0.15); color:#667eea;"><i class="fas fa-user-graduate"></i></div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-number"><?php echo $stats['inscrits']; ?></div>
                    <div class="stat-label">Inscrits cette année</div>
                    <span class="stat-change up"><i class="fas fa-arrow-up me-1"></i> +<?php echo round(($stats['inscrits'] / max($stats['eleves'], 1)) * 100); ?>%</span>
                </div>
                <div class="stat-icon" style="background:rgba(72,187,120,0.15); color:#48bb78;"><i class="fas fa-book-open"></i></div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-number"><?php echo $stats['classes']; ?></div>
                    <div class="stat-label">Classes</div>
                    <span class="stat-change neutral"><i class="fas fa-chalkboard me-1"></i> Total</span>
                </div>
                <div class="stat-icon" style="background:rgba(237,137,54,0.15); color:#ed8936;"><i class="fas fa-chalkboard-teacher"></i></div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-number">$<?php echo number_format($stats['paiements_jour'], 2); ?></div>
                    <div class="stat-label">Paiements aujourd'hui</div>
                    <span class="stat-change up"><i class="fas fa-arrow-up me-1"></i> +<?php echo $stats['nb_paiements_jour']; ?> transactions</span>
                </div>
                <div class="stat-icon" style="background:rgba(237,137,54,0.15); color:#ed8936;"><i class="fas fa-dollar-sign"></i></div>
            </div>
        </div>

        <!-- ===== DEUXIÈME LIGNE ===== -->
        <div class="row g-4 mb-4" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
            <div class="stat-card">
                <div>
                    <div class="stat-number">$<?php echo number_format($stats['paiements_mois'], 2); ?></div>
                    <div class="stat-label">Paiements du mois</div>
                    <span class="stat-change up"><i class="fas fa-calendar-alt me-1"></i> Mois en cours</span>
                </div>
                <div class="stat-icon" style="background:rgba(72,187,120,0.15); color:#48bb78;"><i class="fas fa-calendar-check"></i></div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-number"><?php echo $stats['nb_paiements_jour']; ?></div>
                    <div class="stat-label">Transactions aujourd'hui</div>
                    <span class="stat-change neutral"><i class="fas fa-receipt me-1"></i> Paiements</span>
                </div>
                <div class="stat-icon" style="background:rgba(102,126,234,0.15); color:#667eea;"><i class="fas fa-receipt"></i></div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-number"><?php echo $stats['utilisateurs']; ?></div>
                    <div class="stat-label">Utilisateurs</div>
                    <span class="stat-change neutral"><i class="fas fa-user-cog me-1"></i> Actifs</span>
                </div>
                <div class="stat-icon" style="background:rgba(237,137,54,0.15); color:#ed8936;"><i class="fas fa-user-cog"></i></div>
            </div>
        </div>

        <!-- ===== GRAPHIQUES ===== -->
        <div class="row g-4 mb-4" style="display:grid; grid-template-columns:2fr 1fr; gap:16px;">
            <div class="widget">
                <div class="widget-header">
                    <h5><i class="fas fa-chart-bar"></i> Évolution des paiements (<?php echo date('Y'); ?>)</h5>
                    <span class="badge" style="background:rgba(102,126,234,0.1); color:#667eea; padding:4px 12px; border-radius:20px; font-size:0.75rem;"><i class="fas fa-dollar-sign me-1"></i> USD</span>
                </div>
                <div class="chart-container"><canvas id="chartPaiements"></canvas></div>
            </div>
            <div class="widget">
                <div class="widget-header">
                    <h5><i class="fas fa-chart-pie"></i> Paiements par classe</h5>
                    <span class="badge" style="background:rgba(72,187,120,0.1); color:#276749; padding:4px 12px; border-radius:20px; font-size:0.75rem;"><i class="fas fa-dollar-sign me-1"></i> USD</span>
                </div>
                <div class="chart-container"><canvas id="chartPaiementsClasse"></canvas></div>
            </div>
        </div>

        <!-- ===== DERNIERS PAIEMENTS & ACTIVITÉS ===== -->
        <div class="row g-4 mb-4" style="display:grid; grid-template-columns:1.5fr 1fr; gap:16px;">
            <div class="widget">
              <a href="/admin/paiements/index.php" class="widget-action">Voir tout <i class="fas fa-arrow-right ms-1"></i></a>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead><tr><th>Matricule</th><th>Élève</th><th>Frais</th><th>Montant</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php if (empty($derniers_paiements)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3"><i class="fas fa-inbox me-2"></i>Aucun paiement récent</td></tr>
                            <?php else: ?>
                                <?php foreach ($derniers_paiements as $p): ?>
                                    <tr>
                                        <td><strong><?php echo $p['matricule']; ?></strong></td>
                                        <td><?php echo $p['nom'] . ' ' . $p['prenom']; ?></td>
                                        <td><span style="background:rgba(102,126,234,0.1); color:#667eea; padding:2px 10px; border-radius:20px; font-size:0.75rem;"><?php echo $p['frais']; ?></span></td>
                                        <td><strong>$<?php echo number_format($p['montantPaye'], 2); ?></strong></td>
                                        <td><small><?php echo date('d/m/Y H:i', strtotime($p['datePaiement'])); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="widget">
                <div class="widget-header">
                    <h5><i class="fas fa-history"></i> Dernières activités</h5>
                    <span style="background:rgba(102,126,234,0.1); color:#667eea; padding:2px 10px; border-radius:20px; font-size:0.7rem;">En direct</span>
                </div>
                <?php if (empty($activites)): ?>
                    <div class="text-center text-muted py-3"><i class="fas fa-inbox me-2"></i>Aucune activité récente</div>
                <?php else: ?>
                    <?php foreach ($activites as $a): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon"><i class="fas fa-user"></i></div>
                            <div class="timeline-content">
                                <div class="title"><strong><?php echo $a['username']; ?></strong> <span class="text-muted">•</span> <?php echo htmlspecialchars($a['action']); ?></div>
                                <div class="desc"><?php echo $a['nom'] . ' ' . $a['prenom']; ?></div>
                                <div class="time">
                                    <i class="far fa-clock me-1"></i>
                                    <?php 
                                        $date = new DateTime($a['dateAction']);
                                        $now = new DateTime();
                                        $diff = $now->diff($date);
                                        echo ($diff->days > 0) ? $date->format('d/m/Y H:i') : 'Il y a ' . $diff->h . 'h' . $diff->i . 'min';
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
        document.getElementById('notifDropdown').classList.toggle('show');
    }
    document.addEventListener('click', function(e) {
        const container = document.querySelector('[style*="position:relative;"]');
        if (container && !container.contains(e.target)) {
            document.getElementById('notifDropdown').classList.remove('show');
        }
    });

    // ===== GRAPHIQUE PAIEMENTS =====
    new Chart(document.getElementById('chartPaiements'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($mois_labels); ?>,
            datasets: [{
                label: 'Montant encaissé (USD)',
                data: <?php echo $mois_donnees_json; ?>,
                backgroundColor: 'rgba(102,126,234,0.7)',
                borderColor: 'rgba(102,126,234,1)',
                borderWidth: 2,
                borderRadius: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { callback: v => '$' + v } },
                x: { grid: { display: false } }
            }
        }
    });

    // ===== GRAPHIQUE CAMEMBERT =====
    const colors = ['#667eea', '#48bb78', '#ed8936', '#fc8181', '#9f7aea', '#68d391'];
    new Chart(document.getElementById('chartPaiementsClasse'), {
        type: 'doughnut',
        data: {
            labels: <?php echo $classe_labels_json; ?> ? <?php echo $classe_labels_json; ?> : ['Aucune donnée'],
            datasets: [{
                data: <?php echo $classe_donnees_json; ?> ? <?php echo $classe_donnees_json; ?> : [1],
                backgroundColor: colors,
                borderWidth: 3,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, font: { size: 10 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = ((ctx.parsed / total) * 100).toFixed(1);
                            return '$ ' + ctx.parsed.toFixed(2) + ' (' + pct + '%)';
                        }
                    }
                }
            },
            cutout: '55%'
        }
    });

    // ===== SIDEBAR MOBILE =====
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebarAdmin');
        const toggleBtn = document.getElementById('sidebarToggle');
        const closeBtn = document.getElementById('sidebarClose');
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

        toggleBtn?.addEventListener('click', openSidebar);
        closeBtn?.addEventListener('click', closeSidebar);
        overlay?.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
        });
    });
</script>
</body>
</html>