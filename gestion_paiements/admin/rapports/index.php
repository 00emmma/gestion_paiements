<?php
// admin/rapports/index.php - Rapports avec filtres et analyse (CORRIGÉ)
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Rapports - Administrateur';
$db = db();

// ===== RÉCUPÉRER LES FILTRES =====
$annee_filtre = isset($_GET['annee']) ? intval($_GET['annee']) : 0;
$type_rapport = isset($_GET['type']) ? $_GET['type'] : 'global';

// ===== RÉCUPÉRER TOUTES LES ANNÉES SCOLAIRES =====
$stmt = $db->query("SELECT * FROM annees_scolaires ORDER BY idAnnee DESC");
$annees = $stmt->fetchAll();

// ===== RÉCUPÉRER L'ANNÉE ACTIVE =====
$stmt = $db->query("SELECT * FROM annees_scolaires WHERE active = 1");
$annee_active = $stmt->fetch();

// Si aucune année filtrée, utiliser l'année active
if ($annee_filtre == 0 && $annee_active) {
    $annee_filtre = $annee_active['idAnnee'];
}

// Récupérer le libellé de l'année filtrée
$libelle_annee = '';
foreach ($annees as $a) {
    if ($a['idAnnee'] == $annee_filtre) {
        $libelle_annee = $a['libelle'];
        break;
    }
}

// ===== STATISTIQUES GLOBALES =====
// Total élèves
$stmt = $db->query("SELECT COUNT(*) as total FROM eleves");
$stats['eleves'] = $stmt->fetch()['total'];

// Total classes
$stmt = $db->query("SELECT COUNT(*) as total FROM classes");
$stats['classes'] = $stmt->fetch()['total'];

// Total frais
$stmt = $db->query("SELECT COUNT(*) as total FROM frais");
$stats['frais'] = $stmt->fetch()['total'];

// Total utilisateurs
$stmt = $db->query("SELECT COUNT(*) as total FROM utilisateurs");
$stats['utilisateurs'] = $stmt->fetch()['total'];

// ===== STATISTIQUES PAR ANNÉE =====
if ($annee_filtre > 0) {
    // Inscriptions pour l'année sélectionnée
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM inscriptions WHERE idAnnee = :annee
    ");
    $stmt->execute(['annee' => $annee_filtre]);
    $stats['inscriptions_annee'] = $stmt->fetch()['total'] ?? 0;
    
    // Paiements pour l'année sélectionnée
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_paiements,
            SUM(montantPaye) as total_montant
        FROM paiements p
        JOIN inscriptions i ON p.idInscription = i.idInscription
        WHERE i.idAnnee = :annee
    ");
    $stmt->execute(['annee' => $annee_filtre]);
    $paiements_annee = $stmt->fetch();
    $stats['paiements_annee'] = $paiements_annee['total_paiements'] ?? 0;
    $stats['montant_annee'] = $paiements_annee['total_montant'] ?? 0;
    
    // Élèves inscrits pour l'année sélectionnée
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT idEleve) as total FROM inscriptions WHERE idAnnee = :annee
    ");
    $stmt->execute(['annee' => $annee_filtre]);
    $stats['eleves_inscrits_annee'] = $stmt->fetch()['total'] ?? 0;
    
    // Nombre de classes avec inscriptions
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT idClasse) as total FROM inscriptions WHERE idAnnee = :annee
    ");
    $stmt->execute(['annee' => $annee_filtre]);
    $stats['classes_utilisees_annee'] = $stmt->fetch()['total'] ?? 0;
    
    // Moyenne par paiement
    $stats['moyenne_annee'] = $stats['paiements_annee'] > 0 ? $stats['montant_annee'] / $stats['paiements_annee'] : 0;
    
    // Année précédente pour comparaison
    $stmt = $db->prepare("
        SELECT idAnnee FROM annees_scolaires 
        WHERE idAnnee < :annee 
        ORDER BY idAnnee DESC LIMIT 1
    ");
    $stmt->execute(['annee' => $annee_filtre]);
    $annee_precedente = $stmt->fetch();
    
    if ($annee_precedente) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_paiements,
                SUM(montantPaye) as total_montant
            FROM paiements p
            JOIN inscriptions i ON p.idInscription = i.idInscription
            WHERE i.idAnnee = :annee
        ");
        $stmt->execute(['annee' => $annee_precedente['idAnnee']]);
        $paiements_prec = $stmt->fetch();
        $stats['paiements_prec'] = $paiements_prec['total_paiements'] ?? 0;
        $stats['montant_prec'] = $paiements_prec['total_montant'] ?? 0;
        
        // Calcul de l'évolution
        if ($stats['montant_prec'] > 0) {
            $stats['evolution'] = (($stats['montant_annee'] - $stats['montant_prec']) / $stats['montant_prec']) * 100;
        } else {
            $stats['evolution'] = $stats['montant_annee'] > 0 ? 100 : 0;
        }
    } else {
        $stats['paiements_prec'] = 0;
        $stats['montant_prec'] = 0;
        $stats['evolution'] = 0;
    }
} else {
    // Statistiques globales si aucune année sélectionnée
    $stmt = $db->query("SELECT COUNT(*) as total FROM inscriptions");
    $stats['inscriptions_annee'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(montantPaye) as total_montant FROM paiements");
    $paiements_total = $stmt->fetch();
    $stats['paiements_annee'] = $paiements_total['total'] ?? 0;
    $stats['montant_annee'] = $paiements_total['total_montant'] ?? 0;
    $stats['eleves_inscrits_annee'] = $stats['eleves'];
    $stats['classes_utilisees_annee'] = $stats['classes'];
    $stats['moyenne_annee'] = $stats['paiements_annee'] > 0 ? $stats['montant_annee'] / $stats['paiements_annee'] : 0;
    $stats['paiements_prec'] = 0;
    $stats['montant_prec'] = 0;
    $stats['evolution'] = 0;
}

// ===== PAIEMENTS PAR MOIS POUR L'ANNÉE FILTRÉE =====
if ($annee_filtre > 0) {
    $stmt = $db->prepare("
        SELECT 
            MONTH(p.datePaiement) as mois,
            SUM(p.montantPaye) as total
        FROM paiements p
        JOIN inscriptions i ON p.idInscription = i.idInscription
        WHERE i.idAnnee = :annee
        GROUP BY MONTH(p.datePaiement)
        ORDER BY mois ASC
    ");
    $stmt->execute(['annee' => $annee_filtre]);
    $paiements_par_mois = $stmt->fetchAll();
} else {
    $stmt = $db->query("
        SELECT 
            MONTH(datePaiement) as mois,
            SUM(montantPaye) as total
        FROM paiements
        WHERE YEAR(datePaiement) = YEAR(CURDATE())
        GROUP BY MONTH(datePaiement)
        ORDER BY mois ASC
    ");
    $paiements_par_mois = $stmt->fetchAll();
}

$mois_labels = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
$mois_donnees = array_fill(0, 12, 0);
foreach ($paiements_par_mois as $p) {
    $mois_donnees[$p['mois'] - 1] = (float)$p['total'];
}
$mois_donnees_json = json_encode($mois_donnees);

// ===== PAIEMENTS PAR CLASSE POUR L'ANNÉE FILTRÉE =====
if ($annee_filtre > 0) {
    $stmt = $db->prepare("
        SELECT 
            c.nomClasse,
            COUNT(p.idPaiement) as nb_paiements,
            SUM(p.montantPaye) as total
        FROM paiements p
        JOIN inscriptions i ON p.idInscription = i.idInscription
        JOIN classes c ON i.idClasse = c.idClasse
        WHERE i.idAnnee = :annee
        GROUP BY c.idClasse
        ORDER BY total DESC
        LIMIT 6
    ");
    $stmt->execute(['annee' => $annee_filtre]);
    $paiements_par_classe = $stmt->fetchAll();
} else {
    $stmt = $db->query("
        SELECT 
            c.nomClasse,
            COUNT(p.idPaiement) as nb_paiements,
            SUM(p.montantPaye) as total
        FROM paiements p
        JOIN inscriptions i ON p.idInscription = i.idInscription
        JOIN classes c ON i.idClasse = c.idClasse
        GROUP BY c.idClasse
        ORDER BY total DESC
        LIMIT 6
    ");
    $paiements_par_classe = $stmt->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ===== STYLES COMPLETS ===== */
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

    .filters-bar {
        background: white;
        padding: 12px 18px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 20px;
    }
    .filters-bar label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #2d3748;
        margin: 0;
    }
    .filters-bar select {
        padding: 7px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.85rem;
        background: white;
        min-width: 180px;
    }
    .filters-bar select:focus {
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    .filters-bar .btn-filter {
        background: #667eea;
        color: white;
        border: none;
        padding: 7px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
    }
    .filters-bar .btn-filter:hover {
        background: #5a67d8;
    }
    .filters-bar .btn-reset {
        background: #e2e8f0;
        color: #4a5568;
        border: none;
        padding: 7px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
    }
    .filters-bar .btn-reset:hover {
        background: #cbd5e0;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: white;
        padding: 14px 18px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border-left: 3px solid #667eea;
        text-align: center;
    }
    .stat-card .number {
        font-size: 1.3rem;
        font-weight: 700;
        color: #1a202c;
    }
    .stat-card .label {
        font-size: 0.7rem;
        color: #718096;
        margin-top: 2px;
    }
    .stat-card .change {
        font-size: 0.7rem;
        font-weight: 600;
        margin-top: 4px;
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
    }
    .stat-card .change.up { background: #f0fff4; color: #276749; }
    .stat-card .change.down { background: #fff5f5; color: #9b2c2c; }
    .stat-card .change.neutral { background: #f7fafc; color: #4a5568; }
    .stat-card.green { border-color: #48bb78; }
    .stat-card.orange { border-color: #ed8936; }
    .stat-card.blue { border-color: #4299e1; }
    .stat-card.red { border-color: #fc8181; }
    .stat-card.purple { border-color: #9f7aea; }

    .widget {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 20px;
        margin-bottom: 20px;
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
    .widget .widget-header .badge-count {
        font-size: 0.7rem;
        padding: 3px 12px;
        background: #ebf8ff;
        color: #2b6cb0;
        border-radius: 20px;
    }

    .chart-container {
        position: relative;
        height: 250px;
    }

    .info-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .info-list li {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #edf2f7;
    }
    .info-list li:last-child {
        border-bottom: none;
    }
    .info-list li .label {
        color: #4a5568;
    }
    .info-list li .value {
        font-weight: 600;
        color: #1a202c;
    }

    .link-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 16px 20px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s;
        height: 100%;
        border: 2px solid transparent;
    }
    .link-card:hover {
        border-color: #667eea;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    }
    .link-card .icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .link-card .info .title {
        font-weight: 600;
        color: #1a202c;
        font-size: 0.95rem;
    }
    .link-card .info .desc {
        font-size: 0.75rem;
        color: #718096;
    }
    .link-card .arrow {
        margin-left: auto;
        color: #a0aec0;
        font-size: 0.9rem;
    }

    .analysis-box {
        background: #f7fafc;
        border-radius: 12px;
        padding: 16px 20px;
        border-left: 4px solid #667eea;
        margin-bottom: 20px;
    }
    .analysis-box h6 {
        font-weight: 700;
        color: #1a202c;
        margin: 0 0 8px 0;
    }
    .analysis-box p {
        font-size: 0.9rem;
        color: #4a5568;
        margin: 0;
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

        .filters-bar {
            flex-direction: column;
            align-items: stretch;
        }
        .filters-bar select { width: 100%; min-width: auto; }
        .filters-bar .btn-filter,
        .filters-bar .btn-reset { width: 100%; justify-content: center; }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        .stat-card { padding: 10px 14px; }
        .stat-card .number { font-size: 1.1rem; }

        .chart-container { height: 200px; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
        .stat-card { padding: 8px 10px; }
        .stat-card .number { font-size: 0.95rem; }
        .widget { padding: 14px; }
        .chart-container { height: 160px; }
        .link-card { padding: 12px 14px; }
        .link-card .icon { width: 36px; height: 36px; font-size: 1rem; }
        .sidebar-toggle-mobile {
            width: 42px;
            height: 42px;
            font-size: 0.9rem;
            bottom: 15px;
            left: 15px;
        }
        .analysis-box { padding: 12px 14px; }
        .analysis-box p { font-size: 0.8rem; }
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
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="../eleves/index.php"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="../inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="../paiements/index.php"><i class="fas fa-money-bill-wave"></i> Paiements</a></li>
            <li><a href="../frais/index.php"><i class="fas fa-tag"></i> Frais</a></li>
            <li><a href="../classes/index.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="../annees/index.php"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
            <li><a href="../utilisateurs/index.php"><i class="fas fa-user-cog"></i> Utilisateurs</a></li>
            <li class="menu-section"><i class="fas fa-file-alt"></i> Rapports</li>
            <li><a href="index.php" class="active"><i class="fas fa-file-alt"></i> Rapports</a></li>
            <li><a href="../journal/index.php"><i class="fas fa-history"></i> Journal</a></li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <strong><?php echo $_SESSION['nom'] . ' ' . $_SESSION['prenom']; ?></strong>
                    <span><?php echo $_SESSION['role']; ?></span>
                </div>
            </div>
            <a href="../../logout.php" class="btn-logout" title="Déconnexion">
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
                <i class="fas fa-file-alt"></i>
                Rapports et statistiques
            </h2>
            <div class="header-date">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <form method="GET" action="" class="filters-bar" id="filterForm">
            <label for="anneeSelect"><i class="fas fa-calendar-alt me-1"></i> Année scolaire :</label>
            <select name="annee" id="anneeSelect">
                <option value="0">Toutes les années</option>
                <?php foreach ($annees as $a): ?>
                    <option value="<?php echo $a['idAnnee']; ?>" <?php echo $annee_filtre == $a['idAnnee'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a['libelle']); ?>
                        <?php if ($a['active'] == 1): ?>(Active)<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Appliquer</button>
            <a href="index.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>

        <!-- ===== ANALYSE ===== -->
        <?php if ($annee_filtre > 0 && $stats['evolution'] != 0): ?>
        <div class="analysis-box">
            <h6>
                <i class="fas fa-chart-line me-2" style="color:#667eea;"></i>
                Analyse comparative
            </h6>
            <p>
                <strong>Année <?php echo htmlspecialchars($libelle_annee); ?></strong>
                vs année précédente : 
                <span class="fw-bold <?php echo $stats['evolution'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo $stats['evolution'] >= 0 ? '+' : ''; ?><?php echo number_format($stats['evolution'], 1); ?>%
                </span>
                <?php if ($stats['evolution'] >= 0): ?>
                    <i class="fas fa-arrow-up text-success"></i> 
                    L'année <?php echo htmlspecialchars($libelle_annee); ?> a généré 
                    <span class="fw-bold text-success">$<?php echo number_format($stats['montant_annee'] - $stats['montant_prec'], 2); ?></span> 
                    de plus que l'année précédente.
                <?php else: ?>
                    <i class="fas fa-arrow-down text-danger"></i> 
                    L'année <?php echo htmlspecialchars($libelle_annee); ?> a généré 
                    <span class="fw-bold text-danger">$<?php echo number_format($stats['montant_prec'] - $stats['montant_annee'], 2); ?></span> 
                    de moins que l'année précédente.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo number_format($stats['eleves']); ?></div>
                <div class="label"><i class="fas fa-users me-1"></i> Total élèves</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo number_format($stats['eleves_inscrits_annee'] ?? 0); ?></div>
                <div class="label">
                    <i class="fas fa-user-graduate me-1"></i> Inscrits
                    <?php if ($annee_filtre > 0): ?>
                        <small style="display:block;font-weight:400;color:#718096;">
                            (<?php echo htmlspecialchars($libelle_annee); ?>)
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-card orange">
                <div class="number">$<?php echo number_format($stats['montant_annee'] ?? 0, 2); ?></div>
                <div class="label"><i class="fas fa-dollar-sign me-1"></i> Montant total</div>
                <?php if ($stats['evolution'] != 0): ?>
                    <span class="change <?php echo $stats['evolution'] >= 0 ? 'up' : 'down'; ?>">
                        <i class="fas fa-<?php echo $stats['evolution'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                        <?php echo $stats['evolution'] >= 0 ? '+' : ''; ?><?php echo number_format($stats['evolution'], 1); ?>%
                    </span>
                <?php endif; ?>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo number_format($stats['paiements_annee'] ?? 0); ?></div>
                <div class="label"><i class="fas fa-receipt me-1"></i> Paiements</div>
            </div>
        </div>

        <!-- ===== GRAPHIQUES ===== -->
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="widget">
                    <div class="widget-header">
                        <h5>
                            <i class="fas fa-chart-bar"></i>
                            Évolution des paiements
                            <?php if ($annee_filtre > 0): ?>
                                <small style="font-weight:400;color:#718096;font-size:0.8rem;">
                                    (<?php echo htmlspecialchars($libelle_annee); ?>)
                                </small>
                            <?php else: ?>
                                <small style="font-weight:400;color:#718096;font-size:0.8rem;">
                                    (<?php echo date('Y'); ?>)
                                </small>
                            <?php endif; ?>
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
            <div class="col-lg-4">
                <div class="widget">
                    <div class="widget-header">
                        <h5>
                            <i class="fas fa-chart-pie"></i>
                            Paiements par classe
                        </h5>
                        <span class="badge bg-success bg-opacity-10 text-success">
                            <i class="fas fa-dollar-sign me-1"></i> USD
                        </span>
                    </div>
                    <div class="chart-container">
                        <canvas id="chartPaiementsClasse"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== INFORMATIONS GÉNÉRALES ===== -->
        <div class="row g-3 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="widget">
                    <div class="widget-header">
                        <h5>
                            <i class="fas fa-info-circle"></i>
                            Informations générales
                        </h5>
                        <span class="badge-count">Résumé</span>
                    </div>
                    <ul class="info-list">
                        <li>
                            <span class="label"><i class="fas fa-user-graduate me-1"></i> Total élèves</span>
                            <span class="value"><?php echo number_format($stats['eleves']); ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="fas fa-chalkboard me-1"></i> Total classes</span>
                            <span class="value"><?php echo number_format($stats['classes']); ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="fas fa-tag me-1"></i> Total frais</span>
                            <span class="value"><?php echo number_format($stats['frais']); ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="fas fa-users-cog me-1"></i> Utilisateurs</span>
                            <span class="value"><?php echo number_format($stats['utilisateurs']); ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="fas fa-money-bill-wave me-1"></i> Montant total</span>
                            <span class="value">$<?php echo number_format($stats['montant_annee'] ?? 0, 2); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="widget">
                    <div class="widget-header">
                        <h5>
                            <i class="fas fa-file-invoice"></i>
                            Statistiques des inscriptions
                        </h5>
                        <span class="badge-count">Résumé</span>
                    </div>
                    <ul class="info-list">
                        <li>
                            <span class="label"><i class="fas fa-check-circle text-success me-1"></i> Inscrits (année active)</span>
                            <span class="value">
                                <?php 
                                    $inscrits_actifs = 0;
                                    if ($annee_active) {
                                        $stmt = $db->prepare("SELECT COUNT(DISTINCT idEleve) as total FROM inscriptions WHERE idAnnee = :annee");
                                        $stmt->execute(['annee' => $annee_active['idAnnee']]);
                                        $inscrits_actifs = $stmt->fetch()['total'] ?? 0;
                                    }
                                    echo number_format($inscrits_actifs);
                                ?>
                            </span>
                        </li>
                        <li>
                            <span class="label"><i class="fas fa-calendar-alt me-1"></i> Total inscriptions</span>
                            <span class="value"><?php echo number_format($stats['inscriptions_annee'] ?? 0); ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="fas fa-chalkboard me-1"></i> Classes utilisées</span>
                            <span class="value"><?php echo number_format($stats['classes_utilisees_annee'] ?? 0); ?></span>
                        </li>
                        <li>
                            <span class="label"><i class="fas fa-calculator me-1"></i> Moyenne par paiement</span>
                            <span class="value">$<?php echo number_format($stats['moyenne_annee'] ?? 0, 2); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="widget">
                    <div class="widget-header">
                        <h5>
                            <i class="fas fa-link"></i>
                            Rapports disponibles
                        </h5>
                        <span class="badge-count">Liens</span>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="../paiements/index.php?annee=<?php echo $annee_filtre; ?>" class="link-card">
                            <div class="icon" style="background: rgba(102,126,234,0.15); color:#667eea;">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="info">
                                <div class="title">Rapport des paiements</div>
                                <div class="desc">Voir tous les paiements</div>
                            </div>
                            <span class="arrow"><i class="fas fa-chevron-right"></i></span>
                        </a>
                        <a href="../eleves/index.php" class="link-card">
                            <div class="icon" style="background: rgba(72,187,120,0.15); color:#48bb78;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="info">
                                <div class="title">Rapport des élèves</div>
                                <div class="desc">Liste complète des élèves</div>
                            </div>
                            <span class="arrow"><i class="fas fa-chevron-right"></i></span>
                        </a>
                        <a href="../inscriptions/index.php?annee=<?php echo $annee_filtre; ?>" class="link-card">
                            <div class="icon" style="background: rgba(237,137,54,0.15); color:#ed8936;">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <div class="info">
                                <div class="title">Rapport des inscriptions</div>
                                <div class="desc">Statistiques des inscriptions</div>
                            </div>
                            <span class="arrow"><i class="fas fa-chevron-right"></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // ===== GRAPHIQUE DES PAIEMENTS =====
    const ctx1 = document.getElementById('chartPaiements').getContext('2d');
    const moisLabels = <?php echo json_encode($mois_labels); ?>;
    const moisDonnees = <?php echo $mois_donnees_json; ?>;

    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: moisLabels,
            datasets: [{
                label: 'Montant encaissé (USD)',
                data: moisDonnees,
                backgroundColor: [
                    'rgba(102, 126, 234, 0.7)',
                    'rgba(102, 126, 234, 0.6)',
                    'rgba(102, 126, 234, 0.5)',
                    'rgba(102, 126, 234, 0.4)',
                    'rgba(102, 126, 234, 0.5)',
                    'rgba(102, 126, 234, 0.6)',
                    'rgba(102, 126, 234, 0.7)',
                    'rgba(102, 126, 234, 0.6)',
                    'rgba(102, 126, 234, 0.5)',
                    'rgba(102, 126, 234, 0.4)',
                    'rgba(102, 126, 234, 0.5)',
                    'rgba(102, 126, 234, 0.6)'
                ],
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

    // ===== GRAPHIQUE CAMEMBERT =====
    const ctx2 = document.getElementById('chartPaiementsClasse').getContext('2d');
    const classeLabels = <?php 
        $labels = array_column($paiements_par_classe, 'nomClasse');
        echo json_encode($labels);
    ?>;
    const classeDonnees = <?php 
        $donnees = array_column($paiements_par_classe, 'total');
        echo json_encode($donnees);
    ?>;
    
    const colors = [
        '#667eea', '#48bb78', '#ed8936', '#fc8181', 
        '#9f7aea', '#68d391', '#f6ad55', '#fc8181',
        '#4fd1c5', '#b794f4'
    ];

    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: classeLabels.length > 0 ? classeLabels : ['Aucune donnée'],
            datasets: [{
                data: classeDonnees.length > 0 ? classeDonnees : [1],
                backgroundColor: colors.slice(0, classeDonnees.length > 0 ? classeDonnees.length : 1),
                borderWidth: 3,
                borderColor: '#ffffff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: {
                            size: 10
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return '$ ' + context.parsed.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }) + ' (' + percentage + '%)';
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

    // ===== FILTRE AUTOMATIQUE =====
    document.getElementById('anneeSelect').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
</script>
</body>
</html>