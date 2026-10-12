<?php
// caissier/recus/listerecus.php - Liste des reçus avec statistiques
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Caissier') {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Liste des reçus - Caissier';
$db = db();

// Récupérer les paramètres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// ================================================================
// STATISTIQUES DES REÇUS
// ================================================================

// Total des reçus
$stmt = $db->query("SELECT COUNT(*) as total FROM paiements WHERE numeroRecu IS NOT NULL AND numeroRecu != ''");
$total_recus = $stmt->fetch()['total'];

// Reçus du jour
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM paiements 
    WHERE numeroRecu IS NOT NULL AND numeroRecu != '' 
    AND DATE(datePaiement) = CURDATE()
");
$recus_jour = $stmt->fetch()['total'];

// Reçus de la semaine
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM paiements 
    WHERE numeroRecu IS NOT NULL AND numeroRecu != '' 
    AND YEARWEEK(datePaiement) = YEARWEEK(CURDATE())
");
$recus_semaine = $stmt->fetch()['total'];

// Reçus du mois
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM paiements 
    WHERE numeroRecu IS NOT NULL AND numeroRecu != '' 
    AND MONTH(datePaiement) = MONTH(CURDATE()) 
    AND YEAR(datePaiement) = YEAR(CURDATE())
");
$recus_mois = $stmt->fetch()['total'];

// Reçus de l'année
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM paiements 
    WHERE numeroRecu IS NOT NULL AND numeroRecu != '' 
    AND YEAR(datePaiement) = YEAR(CURDATE())
");
$recus_annee = $stmt->fetch()['total'];

// Montant total des reçus
$stmt = $db->query("
    SELECT SUM(montantPaye) as total 
    FROM paiements 
    WHERE numeroRecu IS NOT NULL AND numeroRecu != ''
");
$montant_total = $stmt->fetch()['total'] ?? 0;

// Montant du jour
$stmt = $db->query("
    SELECT SUM(montantPaye) as total 
    FROM paiements 
    WHERE numeroRecu IS NOT NULL AND numeroRecu != '' 
    AND DATE(datePaiement) = CURDATE()
");
$montant_jour = $stmt->fetch()['total'] ?? 0;

// Montant du mois
$stmt = $db->query("
    SELECT SUM(montantPaye) as total 
    FROM paiements 
    WHERE numeroRecu IS NOT NULL AND numeroRecu != '' 
    AND MONTH(datePaiement) = MONTH(CURDATE()) 
    AND YEAR(datePaiement) = YEAR(CURDATE())
");
$montant_mois = $stmt->fetch()['total'] ?? 0;

// ================================================================
// REQUÊTE POUR RÉCUPÉRER LES REÇUS
// ================================================================
$sql_count = "
    SELECT COUNT(*) as total
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    JOIN eleves e ON i.idEleve = e.idEleve
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE p.numeroRecu IS NOT NULL AND p.numeroRecu != ''
";

$sql = "
    SELECT p.idPaiement, p.montantPaye, p.datePaiement, p.numeroRecu, p.mois,
           e.matricule, e.nom, e.prenom, e.postNom,
           c.nomClasse,
           a.libelle as annee
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    JOIN eleves e ON i.idEleve = e.idEleve
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE p.numeroRecu IS NOT NULL AND p.numeroRecu != ''
";

$params = [];

// Ajouter la recherche
if (!empty($search)) {
    $sql_count .= " AND (e.nom LIKE :search OR e.prenom LIKE :search OR e.matricule LIKE :search OR p.numeroRecu LIKE :search)";
    $sql .= " AND (e.nom LIKE :search OR e.prenom LIKE :search OR e.matricule LIKE :search OR p.numeroRecu LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY p.datePaiement DESC LIMIT :limit OFFSET :offset";

// Compter le nombre total de reçus
$stmt_count = $db->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_recus_filtered = $stmt_count->fetch()['total'];
$total_pages = ceil($total_recus_filtered / $limit);

// Récupérer les reçus
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue('limit', $limit, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$recus = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<style>
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
    .sidebar-menu li a:hover { background: rgba(255,255,255,0.08); color: white; text-decoration: none; }
    .sidebar-menu li a.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .sidebar-menu li a i { width: 18px; text-align: center; font-size: 0.85rem; flex-shrink: 0; }

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
    .sidebar-footer .user-info div { display: flex; flex-direction: column; overflow: hidden; }
    .sidebar-footer .user-info div strong { color: white; font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sidebar-footer .user-info div span { color: #a0aec0; font-size: 0.6rem; }
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
    .sidebar-footer .btn-logout:hover { background: rgba(255,255,255,0.15); color: #feb2b2; }

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
    .sidebar-toggle-mobile:hover { transform: scale(1.05); box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6); }
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
        font-size: 1.3rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .page-header h2 i { color: #667eea; margin-right: 10px; }
    .page-header .header-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .page-header .btn-back {
        background: #e2e8f0;
        color: #4a5568;
        border: none;
        padding: 6px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.3s;
    }
    .page-header .btn-back:hover { background: #cbd5e0; color: #1a202c; }
    .page-header .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.3s;
    }
    .page-header .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
        color: white;
    }

    /* ===== STATISTIQUES ===== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: white;
        padding: 14px 16px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        text-align: center;
        transition: 0.3s;
        border-left: 3px solid #667eea;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .stat-card .stat-number {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1a202c;
        line-height: 1.2;
    }
    .stat-card .stat-label {
        font-size: 0.65rem;
        color: #718096;
        text-transform: uppercase;
        font-weight: 600;
        margin-top: 2px;
    }
    .stat-card .stat-amount {
        font-size: 0.75rem;
        color: #48bb78;
        font-weight: 600;
        margin-top: 2px;
    }
    .stat-card.green { border-color: #48bb78; }
    .stat-card.orange { border-color: #ed8936; }
    .stat-card.purple { border-color: #9f7aea; }
    .stat-card.blue { border-color: #4299e1; }
    .stat-card.red { border-color: #fc8181; }
    .stat-card.teal { border-color: #38b2ac; }

    /* ===== BARRE DE RECHERCHE ===== */
    .search-bar {
        background: white;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .search-bar .search-info {
        font-size: 0.85rem;
        color: #4a5568;
    }
    .search-bar .search-info strong { color: #1a202c; }
    .search-bar .search-input-group {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .search-bar .search-input-group input {
        padding: 8px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.85rem;
        width: 300px;
        transition: 0.3s;
    }
    .search-bar .search-input-group input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }
    .search-bar .search-input-group .btn-search {
        padding: 8px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .search-bar .search-input-group .btn-search:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
    }
    .search-bar .search-input-group .btn-clear {
        padding: 8px 16px;
        background: #e2e8f0;
        color: #4a5568;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .search-bar .search-input-group .btn-clear:hover { background: #cbd5e0; }

    /* ===== TABLEAU ===== */
    .table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .table-container .table-header {
        padding: 12px 20px;
        border-bottom: 1px solid #edf2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        background: #f7fafc;
    }
    .table-container .table-header .title {
        font-weight: 600;
        color: #2d3748;
        font-size: 0.85rem;
    }
    .table-container .table-header .title i { color: #667eea; margin-right: 6px; }
    .table-scroll { overflow-x: auto; }
    .table-custom {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }
    .table-custom thead th {
        background: #f7fafc;
        color: #4a5568;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 10px 14px;
        border-bottom: 2px solid #e2e8f0;
        text-align: left;
    }
    .table-custom tbody td {
        padding: 10px 14px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
    }
    .table-custom tbody tr:hover td { background: #f7fafc; }
    .table-custom tbody tr:last-child td { border-bottom: none; }

    .badge-recu {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        background: #ebf8ff;
        color: #2b6cb0;
        font-family: monospace;
    }
    .badge-mois {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        background: #ebf8ff;
        color: #2b6cb0;
        display: inline-block;
    }

    .btn-sm {
        padding: 4px 12px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-sm-primary {
        background: #dbeafe;
        color: #1e40af;
    }
    .btn-sm-primary:hover {
        background: #bfdbfe;
        transform: scale(1.05);
    }
    .btn-sm-success {
        background: #d1fae5;
        color: #065f46;
    }
    .btn-sm-success:hover {
        background: #a7f3d0;
        transform: scale(1.05);
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }
    .empty-state i { font-size: 2.5rem; opacity: 0.3; margin-bottom: 10px; display: block; }
    .empty-state h5 { color: #4a5568; font-weight: 600; font-size: 0.9rem; margin-bottom: 4px; }
    .empty-state p { font-size: 0.85rem; margin: 0; }

    /* ===== PAGINATION ===== */
    .pagination-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        padding: 16px 0;
        flex-wrap: wrap;
    }
    .pagination-container .page-link {
        padding: 6px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        color: #4a5568;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.8rem;
        transition: 0.3s;
    }
    .pagination-container .page-link:hover {
        background: #f7fafc;
        border-color: #667eea;
    }
    .pagination-container .page-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }
    .pagination-container .page-link.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }
    .pagination-container .page-info {
        font-size: 0.8rem;
        color: #718096;
        padding: 0 8px;
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
        .main-content { padding: 15px !important; width: 100% !important; max-height: none; }
        .page-header { flex-direction: column; text-align: center; }
        .page-header .header-right { flex-wrap: wrap; justify-content: center; }
        .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .stat-card .stat-number { font-size: 1.2rem; }
        .search-bar { flex-direction: column; text-align: center; }
        .search-bar .search-input-group { width: 100%; flex-direction: column; }
        .search-bar .search-input-group input { width: 100%; }
        .search-bar .search-input-group .btn-search,
        .search-bar .search-input-group .btn-clear { width: 100%; justify-content: center; }
        .table-container .table-header { flex-direction: column; text-align: center; }
        .pagination-container { gap: 5px; }
        .pagination-container .page-link { padding: 4px 10px; font-size: 0.7rem; }
        .table-custom { font-size: 0.7rem; }
        .table-custom thead th, .table-custom tbody td { padding: 6px 8px; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1.1rem; }
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-card { padding: 10px 12px; }
        .stat-card .stat-number { font-size: 1rem; }
        .stat-card .stat-amount { font-size: 0.65rem; }
        .sidebar-toggle-mobile { width: 40px; height: 40px; font-size: 0.9rem; bottom: 15px; left: 15px; }
        .search-bar .search-info { font-size: 0.75rem; }
        .table-custom { font-size: 0.65rem; }
        .table-custom thead th, .table-custom tbody td { padding: 4px 6px; }
        .btn-sm { padding: 2px 8px; font-size: 0.6rem; }
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
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li class="menu-section"><i class="fas fa-money-bill-wave"></i> Paiements</li>
            <li><a href="../paiements/index.php"><i class="fas fa-list"></i> Liste des paiements</a></li>
            <li class="menu-section"><i class="fas fa-users"></i> Élèves</li>
            <li><a href="../eleves/index.php"><i class="fas fa-user-graduate"></i> Liste des élèves</a></li>
            <li><a href="../eleves/situation_financiere.php"><i class="fas fa-chart-pie"></i> Situation financière</a></li>
            <li class="menu-section"><i class="fas fa-file-invoice"></i> Reçus</li>
            <li><a href="generer.php"><i class="fas fa-plus-circle"></i> Générer un reçu</a></li>
            <li><a href="listerecus.php" class="active"><i class="fas fa-list"></i> Liste des reçus</a></li>
            <li><a href="historique.php"><i class="fas fa-history"></i> Historique</a></li>
            <li class="menu-section"><i class="fas fa-user-circle"></i> Compte</li>
            <li><a href="../profil.php"><i class="fas fa-user-circle"></i> Mon profil</a></li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['prenom'] ?? 'C', 0, 1) . substr($_SESSION['nom'] ?? 'A', 0, 1)); ?></div>
                <div>
                    <strong><?php echo $_SESSION['prenom'] ?? 'Caissier'; ?> <?php echo $_SESSION['nom'] ?? ''; ?></strong>
                    <span><?php echo $_SESSION['role'] ?? 'Caissier'; ?></span>
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
                <i class="fas fa-list"></i>
                Liste des reçus
            </h2>
            <div class="header-right">
                <a href="generer.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Générer un reçu
                </a>
                <a href="historique.php" class="btn-back">
                    <i class="fas fa-history"></i> Historique
                </a>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-number"><?php echo $recus_jour; ?></div>
                <div class="stat-label"><i class="fas fa-calendar-day"></i> Aujourd'hui</div>
                <div class="stat-amount">$<?php echo number_format($montant_jour, 0, ',', ' '); ?></div>
            </div>
            <div class="stat-card green">
                <div class="stat-number"><?php echo $recus_semaine; ?></div>
                <div class="stat-label"><i class="fas fa-calendar-week"></i> Cette semaine</div>
                <div class="stat-amount">$<?php echo number_format($montant_jour * 7, 0, ',', ' '); ?></div>
            </div>
            <div class="stat-card orange">
                <div class="stat-number"><?php echo $recus_mois; ?></div>
                <div class="stat-label"><i class="fas fa-calendar-alt"></i> Ce mois</div>
                <div class="stat-amount">$<?php echo number_format($montant_mois, 0, ',', ' '); ?></div>
            </div>
            <div class="stat-card purple">
                <div class="stat-number"><?php echo $recus_annee; ?></div>
                <div class="stat-label"><i class="fas fa-calendar"></i> Cette année</div>
                <div class="stat-amount">$<?php echo number_format($montant_total, 0, ',', ' '); ?></div>
            </div>
            <div class="stat-card teal">
                <div class="stat-number"><?php echo $total_recus; ?></div>
                <div class="stat-label"><i class="fas fa-file-invoice"></i> Total reçus</div>
                <div class="stat-amount">$<?php echo number_format($montant_total, 0, ',', ' '); ?></div>
            </div>
            <div class="stat-card red">
                <div class="stat-number">
                    <?php 
                    $moyenne = $total_recus > 0 ? round($montant_total / $total_recus, 0) : 0;
                    echo number_format($moyenne, 0, ',', ' ');
                    ?>
                </div>
                <div class="stat-label"><i class="fas fa-calculator"></i> Moyenne/reçu</div>
                <div class="stat-amount">$<?php echo number_format($moyenne, 0, ',', ' '); ?></div>
            </div>
        </div>

        <!-- ===== BARRE DE RECHERCHE ===== -->
        <div class="search-bar">
            <div class="search-info">
                <i class="fas fa-file-invoice me-1"></i>
                <strong><?php echo $total_recus_filtered; ?></strong> reçu(s) trouvé(s)
                <?php if (!empty($search)): ?>
                    <span class="text-muted" style="font-size:0.8rem;">
                        - Résultats pour "<strong><?php echo htmlspecialchars($search); ?></strong>"
                    </span>
                <?php endif; ?>
            </div>
            <form method="GET" action="" class="search-input-group">
                <input type="text" name="search" placeholder="Rechercher par nom, matricule ou n° reçu..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Rechercher
                </button>
                <?php if (!empty($search)): ?>
                    <a href="listerecus.php" class="btn-clear">
                        <i class="fas fa-times"></i> Effacer
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ===== TABLEAU ===== -->
        <div class="table-container">
            <div class="table-header">
                <span class="title">
                    <i class="fas fa-list"></i> Reçus générés
                </span>
                <span style="font-size:0.7rem; color:#718096;">
                    <?php echo $total_recus_filtered; ?> reçu(s)
                </span>
            </div>
            <div class="table-scroll">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>N° Reçu</th>
                            <th>Élève</th>
                            <th>Matricule</th>
                            <th>Classe</th>
                            <th>Mois</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recus)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <i class="fas fa-file-invoice"></i>
                                        <h5>Aucun reçu généré</h5>
                                        <p>Commencez par générer des reçus depuis la page "Générer un reçu".</p>
                                        <a href="generer.php" style="display:inline-block;margin-top:10px;padding:8px 20px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border-radius:8px;text-decoration:none;font-weight:600;">
                                            <i class="fas fa-plus"></i> Générer un reçu
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recus as $i => $r): ?>
                                <tr>
                                    <td><?php echo $offset + $i + 1; ?></td>
                                    <td>
                                        <span class="badge-recu">
                                            <?php echo htmlspecialchars($r['numeroRecu']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($r['nom'] . ' ' . ($r['postNom'] ?? '') . ' ' . $r['prenom']); ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($r['matricule']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($r['nomClasse']); ?></td>
                                    <td>
                                        <?php if (!empty($r['mois'])): ?>
                                            <span class="badge-mois"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($r['mois']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>$<?php echo number_format($r['montantPaye'], 2); ?></strong></td>
                                    <td><small><?php echo date('d/m/Y', strtotime($r['datePaiement'])); ?></small></td>
                                    <td style="text-align:center;">
                                        <div style="display:flex; gap:4px; justify-content:center; flex-wrap:wrap;">
                                            <a href="imprimer.php?id=<?php echo $r['idPaiement']; ?>" target="_blank" class="btn-sm btn-sm-primary" title="Imprimer le reçu">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <a href="generer.php?id=<?php echo $r['idPaiement']; ?>" class="btn-sm btn-sm-success" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== PAGINATION ===== -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1): ?>
                <a href="?page=1&search=<?php echo urlencode($search); ?>" class="page-link">1</a>
                <?php if ($start_page > 2): ?>
                    <span class="page-link disabled">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span class="page-link disabled">...</span>
                <?php endif; ?>
                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                    <?php echo $total_pages; ?>
                </a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>

            <span class="page-info">
                Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                (<?php echo $total_recus_filtered; ?> reçus)
            </span>
        </div>
        <?php endif; ?>

        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
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