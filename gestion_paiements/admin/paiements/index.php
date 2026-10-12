<?php
// admin/paiements/index.php - Gestion des paiements
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Gestion des paiements - Administrateur';
$db = db();

// Récupérer les filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtre_date = isset($_GET['date']) ? $_GET['date'] : '';
$filtre_frais = isset($_GET['frais']) ? intval($_GET['frais']) : 0;
$filtre_mois = isset($_GET['mois']) ? $_GET['mois'] : '';
$filtre_classe = isset($_GET['classe']) ? intval($_GET['classe']) : 0;

// Construction de la requête
$sql = "SELECT p.*, e.matricule, e.nom, e.prenom, e.postNom, 
               f.libelle as frais_libelle, 
               c.nomClasse, n.nomNiveau,
               a.libelle as annee,
               u.nom as caissier_nom, u.prenom as caissier_prenom
        FROM paiements p
        JOIN inscriptions i ON p.idInscription = i.idInscription
        JOIN eleves e ON i.idEleve = e.idEleve
        JOIN frais f ON p.idFrais = f.idFrais
        JOIN classes c ON i.idClasse = c.idClasse
        JOIN niveaux n ON c.idNiveau = n.idNiveau
        JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
        JOIN utilisateurs u ON p.idCaissier = u.idUtilisateur
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (e.matricule LIKE :search OR e.nom LIKE :search OR e.prenom LIKE :search OR p.numeroRecu LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($filtre_date)) {
    $sql .= " AND DATE(p.datePaiement) = :date";
    $params['date'] = $filtre_date;
}

if ($filtre_frais > 0) {
    $sql .= " AND p.idFrais = :frais";
    $params['frais'] = $filtre_frais;
}

if (!empty($filtre_mois)) {
    $sql .= " AND p.mois = :mois";
    $params['mois'] = $filtre_mois;
}

if ($filtre_classe > 0) {
    $sql .= " AND i.idClasse = :classe";
    $params['classe'] = $filtre_classe;
}

$sql .= " ORDER BY p.datePaiement DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$paiements = $stmt->fetchAll();

// Récupérer les frais pour le filtre
$stmt = $db->query("SELECT idFrais, libelle FROM frais ORDER BY libelle");
$frais_list = $stmt->fetchAll();

// Récupérer les classes pour le filtre
$stmt = $db->query("SELECT idClasse, nomClasse FROM classes ORDER BY nomClasse");
$classes = $stmt->fetchAll();

// Liste des mois
$mois_liste = ['Septembre', 'Octobre', 'Novembre', 'Décembre', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin'];

// Statistiques
$total_paiements = count($paiements);
$montant_total = array_sum(array_column($paiements, 'montantPaye'));
$montant_moyen = $total_paiements > 0 ? $montant_total / $total_paiements : 0;

// Statistiques supplémentaires
$stmt = $db->query("SELECT COUNT(*) as total, SUM(montantPaye) as total_montant FROM paiements WHERE DATE(datePaiement) = CURDATE()");
$stats_jour = $stmt->fetch();

$stmt = $db->query("SELECT COUNT(*) as total, SUM(montantPaye) as total_montant FROM paiements WHERE MONTH(datePaiement) = MONTH(CURDATE()) AND YEAR(datePaiement) = YEAR(CURDATE())");
$stats_mois = $stmt->fetch();

$stmt = $db->query("SELECT COUNT(*) as total FROM paiements WHERE numeroRecu IS NOT NULL AND numeroRecu != ''");
$stats_recus = $stmt->fetch();

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

    /* ===== SIDEBAR ===== */
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

    /* ===== CONTENU PRINCIPAL ===== */
    .main-content {
        flex: 1;
        padding: 20px 25px;
        background: #f8fafc;
        min-height: 100vh;
        max-height: 100vh;
        overflow-y: auto;
    }

    /* ===== HEADER ===== */
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
    .page-header .header-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .btn-add {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white !important;
        padding: 7px 18px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s;
        border: none;
    }
    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102,126,234,0.35);
        color: white !important;
        text-decoration: none;
    }

    /* ===== STATS ===== */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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

    /* ===== FILTRES ===== */
    .filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        align-items: center;
        background: white;
        padding: 12px 16px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .filters input,
    .filters select {
        padding: 7px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.8rem;
        background: white;
        flex: 1;
        min-width: 120px;
        transition: 0.3s;
    }
    .filters input:focus,
    .filters select:focus {
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    .filters .btn-filter {
        background: #667eea;
        color: white;
        border: none;
        padding: 7px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: 0.3s;
    }
    .filters .btn-filter:hover { background: #5a67d8; }
    .filters .btn-reset {
        background: #e2e8f0;
        color: #4a5568;
        border: none;
        padding: 7px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
    }
    .filters .btn-reset:hover { background: #cbd5e0; }

    /* ===== TABLE ===== */
    .table-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .table-responsive { overflow-x: auto; }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 800px;
    }
    .table thead {
        background: #f7fafc;
        border-bottom: 2px solid #e2e8f0;
    }
    .table thead th {
        padding: 8px 12px;
        text-align: left;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #4a5568;
        white-space: nowrap;
        position: sticky;
        top: 0;
        background: #f7fafc;
        z-index: 10;
    }
    .table tbody tr { border-bottom: 1px solid #f0f2f5; transition: 0.3s; }
    .table tbody tr:hover { background: #f7fafc; }
    .table tbody td { padding: 8px 12px; vertical-align: middle; }

    .badge-recu {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        background: #ebf8ff;
        color: #2b6cb0;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .badge-recu .fa-check-circle { color: #48bb78; }

    .badge-mois {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #f7fafc;
        color: #4a5568;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }
    .empty-state i { font-size: 2.5rem; margin-bottom: 10px; color: #e2e8f0; }

    .btn-actions {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    .btn-actions .btn-sm {
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        border: none;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-actions .btn-view { background: #ebf8ff; color: #2b6cb0; }
    .btn-actions .btn-view:hover { background: #bee3f8; }
    .btn-actions .btn-edit { background: #fefcbf; color: #975a16; }
    .btn-actions .btn-edit:hover { background: #f6c23e; }
    .btn-actions .btn-delete { background: #fff5f5; color: #9b2c2c; }
    .btn-actions .btn-delete:hover { background: #fed7d7; }

    /* ============================================================ */
    /* ===== RESPONSIVE ===== */
    /* ============================================================ */

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
        .page-header .header-right {
            flex-wrap: wrap;
            justify-content: center;
        }
        .btn-add { width: 100%; justify-content: center; }

        .stats-row { grid-template-columns: 1fr 1fr; }
        .filters { flex-direction: column; }
        .filters input, .filters select { width: 100%; min-width: auto; }
        .filters .btn-filter, .filters .btn-reset { width: 100%; justify-content: center; }
        .btn-actions { flex-direction: column; }
        .btn-actions .btn-sm { width: 100%; justify-content: center; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1rem; }
        .stats-row { grid-template-columns: 1fr; }
        .stat-item { padding: 10px 14px; }
        .stat-item .stat-info .number { font-size: 1rem; }
        .table { font-size: 0.7rem; min-width: 600px; }
        .table thead th, .table tbody td { padding: 4px 6px; }
        .sidebar-toggle-mobile {
            width: 42px;
            height: 42px;
            font-size: 0.9rem;
            bottom: 15px;
            left: 15px;
        }
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
            <li class="menu-section">Administration</li>
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="../eleves/index.php"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="../inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="index.php" class="active"><i class="fas fa-money-bill-wave"></i> Paiements</a></li>
            <li><a href="../frais/index.php"><i class="fas fa-tag"></i> Frais</a></li>
            <li><a href="../classes/index.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="../annees/index.php"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
            <li><a href="../utilisateurs/index.php"><i class="fas fa-user-cog"></i> Utilisateurs</a></li>
            <li class="menu-section">Rapports</li>
            <li><a href="../rapports/index.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
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
                <i class="fas fa-money-bill-wave"></i>
                Gestion des paiements
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2">
                    <?php echo $total_paiements; ?> paiement(s)
                </span>
            </h2>
            <div class="header-right">
                <a href="ajouter.php" class="btn-add">
                    <i class="fas fa-plus"></i> Nouveau paiement
                </a>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(102, 126, 234, 0.15); color: #667eea;">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_paiements; ?></div>
                    <div class="label">Total paiements</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <div class="number">$<?php echo number_format($montant_total, 2); ?></div>
                    <div class="label">Montant total</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $stats_jour['total'] ?? 0; ?></div>
                    <div class="label">Aujourd'hui ($<?php echo number_format($stats_jour['total_montant'] ?? 0, 2); ?>)</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $stats_mois['total'] ?? 0; ?></div>
                    <div class="label">Ce mois ($<?php echo number_format($stats_mois['total_montant'] ?? 0, 2); ?>)</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $stats_recus['total'] ?? 0; ?></div>
                    <div class="label">Reçus générés</div>
                </div>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <form method="GET" action="" class="filters">
            <input type="text" name="search" placeholder="🔍 Rechercher..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <input type="date" name="date" value="<?php echo htmlspecialchars($filtre_date); ?>" title="Date">
            <select name="mois">
                <option value="">Tous les mois</option>
                <?php foreach ($mois_liste as $m): ?>
                    <option value="<?php echo $m; ?>" <?php echo $filtre_mois == $m ? 'selected' : ''; ?>>
                        <?php echo $m; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="frais">
                <option value="">Tous les frais</option>
                <?php foreach ($frais_list as $f): ?>
                    <option value="<?php echo $f['idFrais']; ?>" <?php echo $filtre_frais == $f['idFrais'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($f['libelle']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="classe">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['idClasse']; ?>" <?php echo $filtre_classe == $c['idClasse'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nomClasse']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="index.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>

        <!-- ===== TABLE ===== -->
        <div class="table-container" id="tableContainer">
            <div class="table-responsive">
                <table class="table" id="paiementTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i></th>
                            <th><i class="fas fa-id-card"></i> Matricule</th>
                            <th><i class="fas fa-user"></i> Élève</th>
                            <th><i class="fas fa-tag"></i> Frais</th>
                            <th><i class="fas fa-calendar-alt"></i> Mois</th>
                            <th><i class="fas fa-dollar-sign"></i> Montant</th>
                            <th><i class="fas fa-chalkboard"></i> Classe</th>
                            <th><i class="fas fa-user-cog"></i> Caissier</th>
                            <th><i class="fas fa-clock"></i> Date</th>
                            <th><i class="fas fa-file-invoice"></i> Reçu</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paiements)): ?>
                            <tr>
                                <td colspan="11">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun paiement trouvé</p>
                                        <a href="ajouter.php" class="btn btn-primary btn-sm mt-2">
                                            <i class="fas fa-plus"></i> Enregistrer un paiement
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paiements as $index => $p): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($p['matricule']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?>
                                        <?php if (!empty($p['postNom'])): ?>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($p['postNom']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['frais_libelle']); ?></td>
                                    <td>
                                        <?php if (!empty($p['mois'])): ?>
                                            <span class="badge-mois">📅 <?php echo htmlspecialchars($p['mois']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>$<?php echo number_format($p['montantPaye'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['nomClasse']); ?></td>
                                    <td>
                                        <small><?php echo htmlspecialchars($p['caissier_nom'] . ' ' . $p['caissier_prenom']); ?></small>
                                    </td>
                                    <td><small><?php echo date('d/m/Y H:i', strtotime($p['datePaiement'])); ?></small></td>
                                    <td>
                                        <?php if (!empty($p['numeroRecu'])): ?>
                                            <span class="badge-recu">
                                                <i class="fas fa-check-circle"></i>
                                                <?php echo htmlspecialchars($p['numeroRecu']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:0.7rem;">
                                                <i class="fas fa-clock me-1"></i> Non généré
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-actions">
                                            <a href="voir.php?id=<?php echo $p['idPaiement']; ?>" class="btn-sm btn-view" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="modifier.php?id=<?php echo $p['idPaiement']; ?>" class="btn-sm btn-edit" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-sm btn-delete" title="Supprimer" onclick="supprimer(<?php echo $p['idPaiement']; ?>, '<?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== PAGINATION INFO ===== -->
        <?php if (!empty($paiements)): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted" style="font-size:0.8rem;">
                Affichage de <strong><?php echo count($paiements); ?></strong> paiement(s)
            </span>
            <span class="text-muted" style="font-size:0.8rem;">
                Total: <strong>$<?php echo number_format($montant_total, 2); ?></strong>
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
    // ===== SUPPRIMER =====
    function supprimer(id, nom) {
        if (confirm('Voulez-vous vraiment supprimer le paiement de ' + nom + ' ?\nCette action est irréversible.')) {
            window.location.href = 'supprimer.php?id=' + id;
        }
    }

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
</script>
</body>
</html>