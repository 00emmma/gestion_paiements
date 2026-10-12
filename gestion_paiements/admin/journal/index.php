<?php
// admin/journal/index.php - Journal des activités (CORRIGÉ)
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Journal des activités - Administrateur';
$db = db();

// ===== DÉTECTER LES COLONNES DISPONIBLES =====
$colonnes = [];
try {
    $stmt = $db->query("DESCRIBE journal_activite");
    $colonnes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignorer
}

$has_type = in_array('type', $colonnes);
$has_details = in_array('details', $colonnes) || in_array('detail', $colonnes);

// ===== RÉCUPÉRER LES FILTRES =====
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtre_date = isset($_GET['date']) ? $_GET['date'] : '';
$filtre_type = isset($_GET['type']) ? $_GET['type'] : '';
$filtre_utilisateur = isset($_GET['utilisateur']) ? intval($_GET['utilisateur']) : 0;

// ===== RÉCUPÉRER LES UTILISATEURS POUR LE FILTRE =====
$stmt = $db->query("SELECT idUtilisateur, nom, prenom, username FROM utilisateurs ORDER BY nom");
$utilisateurs = $stmt->fetchAll();

// ===== CONSTRUCTION DE LA REQUÊTE =====
$sql = "SELECT j.*, u.nom, u.prenom, u.username
        FROM journal_activite j
        JOIN utilisateurs u ON j.idUtilisateur = u.idUtilisateur
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (j.action LIKE :search OR u.username LIKE :search OR u.nom LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($filtre_date)) {
    $sql .= " AND DATE(j.dateAction) = :date";
    $params['date'] = $filtre_date;
}

if (!empty($filtre_type) && $has_type) {
    $sql .= " AND j.type = :type";
    $params['type'] = $filtre_type;
}

if ($filtre_utilisateur > 0) {
    $sql .= " AND j.idUtilisateur = :utilisateur";
    $params['utilisateur'] = $filtre_utilisateur;
}

$sql .= " ORDER BY j.dateAction DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$activites = $stmt->fetchAll();

// ===== STATISTIQUES =====
$total_activites = count($activites);

// Activités aujourd'hui
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM journal_activite 
    WHERE DATE(dateAction) = CURDATE()
");
$stats_aujourdhui = $stmt->fetch()['total'] ?? 0;

// Activités cette semaine
$stmt = $db->query("
    SELECT COUNT(*) as total 
    FROM journal_activite 
    WHERE WEEK(dateAction) = WEEK(CURDATE()) 
    AND YEAR(dateAction) = YEAR(CURDATE())
");
$stats_semaine = $stmt->fetch()['total'] ?? 0;

// Dernière activité
$stmt = $db->query("
    SELECT dateAction FROM journal_activite 
    ORDER BY dateAction DESC 
    LIMIT 1
");
$derniere_activite = $stmt->fetch();
$derniere_activite = $derniere_activite ? date('d/m/Y H:i', strtotime($derniere_activite['dateAction'])) : 'Aucune';

// Répartition par type (si la colonne existe)
$types_stats = [];
if ($has_type) {
    $stmt = $db->query("
        SELECT type, COUNT(*) as total 
        FROM journal_activite 
        GROUP BY type 
        ORDER BY total DESC
    ");
    $types_stats = $stmt->fetchAll();
}

// Couleurs par type
$couleurs_types = [
    'connexion' => '#48bb78',
    'deconnexion' => '#fc8181',
    'creation' => '#667eea',
    'modification' => '#ed8936',
    'suppression' => '#fc8181',
    'consultation' => '#4299e1',
    'paiement' => '#38a169',
    'inscription' => '#9f7aea',
    'autre' => '#a0aec0'
];

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

    .btn-statut {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
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
        box-shadow: 0 2px 10px rgba(56, 161, 105, 0.3);
    }
    .btn-statut:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(56, 161, 105, 0.4);
        color: white;
        text-decoration: none;
    }
    .btn-statut i {
        font-size: 0.9rem;
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
    .filters-bar input,
    .filters-bar select {
        padding: 7px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.8rem;
        background: white;
        flex: 1;
        min-width: 120px;
        transition: 0.3s;
    }
    .filters-bar input:focus,
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
        min-width: 700px;
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
    }
    .table tbody tr { border-bottom: 1px solid #f0f2f5; transition: 0.3s; }
    .table tbody tr:hover { background: #f7fafc; }
    .table tbody td { padding: 8px 12px; vertical-align: middle; }

    .badge-type {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-block;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }
    .empty-state i { font-size: 2.5rem; margin-bottom: 10px; color: #e2e8f0; }

    .badge-user {
        background: #ebf8ff;
        color: #2b6cb0;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }

    .widget {
        background: white;
        border-radius: 10px;
        padding: 16px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .type-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
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
        .filters-bar { flex-direction: column; }
        .filters-bar input,
        .filters-bar select { width: 100%; min-width: auto; }
        .filters-bar .btn-filter,
        .filters-bar .btn-reset { width: 100%; justify-content: center; }
        .table { font-size: 0.7rem; min-width: 600px; }
        .table thead th, .table tbody td { padding: 4px 8px; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1rem; }
        .stats-row { grid-template-columns: 1fr; }
        .stat-item { padding: 10px 14px; }
        .stat-item .stat-info .number { font-size: 1rem; }
        .table { font-size: 0.65rem; min-width: 500px; }
        .table thead th, .table tbody td { padding: 3px 6px; }
        .badge-type { font-size: 0.5rem; padding: 1px 6px; }
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
            <li><a href="../rapports/index.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
            <li><a href="index.php" class="active"><i class="fas fa-history"></i> Journal</a></li>
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
                <i class="fas fa-history"></i>
                Journal des activités
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2">
                    <i class="fas fa-hashtag me-1"></i><?php echo number_format($total_activites); ?>
                </span>
            </h2>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
              <a href="../statut.php" class="btn-statut">
    <i class="fas fa-user-check"></i>
    Voir les statuts
</a>
                <div class="header-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(102, 126, 234, 0.15); color: #667eea;">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo number_format($total_activites); ?></div>
                    <div class="label">Total activités</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo number_format($stats_aujourdhui); ?></div>
                    <div class="label">Aujourd'hui</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo number_format($stats_semaine); ?></div>
                    <div class="label">Cette semaine</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(66, 153, 225, 0.15); color: #4299e1;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="number" style="font-size:0.9rem;"><?php echo $derniere_activite; ?></div>
                    <div class="label">Dernière activité</div>
                </div>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <form method="GET" action="" class="filters-bar" id="filterForm">
            <input type="text" name="search" placeholder="🔍 Rechercher..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <input type="date" name="date" value="<?php echo htmlspecialchars($filtre_date); ?>">
            <?php if ($has_type): ?>
            <select name="type">
                <option value="">Tous les types</option>
                <option value="connexion" <?php echo $filtre_type == 'connexion' ? 'selected' : ''; ?>>Connexion</option>
                <option value="deconnexion" <?php echo $filtre_type == 'deconnexion' ? 'selected' : ''; ?>>Déconnexion</option>
                <option value="creation" <?php echo $filtre_type == 'creation' ? 'selected' : ''; ?>>Création</option>
                <option value="modification" <?php echo $filtre_type == 'modification' ? 'selected' : ''; ?>>Modification</option>
                <option value="suppression" <?php echo $filtre_type == 'suppression' ? 'selected' : ''; ?>>Suppression</option>
                <option value="consultation" <?php echo $filtre_type == 'consultation' ? 'selected' : ''; ?>>Consultation</option>
                <option value="paiement" <?php echo $filtre_type == 'paiement' ? 'selected' : ''; ?>>Paiement</option>
                <option value="inscription" <?php echo $filtre_type == 'inscription' ? 'selected' : ''; ?>>Inscription</option>
            </select>
            <?php endif; ?>
            <select name="utilisateur">
                <option value="">Tous les utilisateurs</option>
                <?php foreach ($utilisateurs as $u): ?>
                    <option value="<?php echo $u['idUtilisateur']; ?>" <?php echo $filtre_utilisateur == $u['idUtilisateur'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['username'] . ' (' . $u['nom'] . ' ' . $u['prenom'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="index.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>

        <!-- ===== TABLEAU ===== -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i></th>
                            <th><i class="fas fa-user"></i> Utilisateur</th>
                            <?php if ($has_type): ?>
                                <th><i class="fas fa-tag"></i> Type</th>
                            <?php endif; ?>
                            <th><i class="fas fa-info-circle"></i> Action</th>
                            <th><i class="fas fa-clock"></i> Date</th>
                            <?php if ($has_details): ?>
                                <th><i class="fas fa-info"></i> Détails</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activites)): ?>
                            <tr>
                                <td colspan="<?php echo 4 + ($has_type ? 1 : 0) + ($has_details ? 1 : 0); ?>">
                                    <div class="empty-state">
                                        <i class="fas fa-history"></i>
                                        <p>Aucune activité trouvée</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activites as $index => $a): 
                                $type_label = ucfirst($a['type'] ?? 'Activité');
                            ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="badge-user">
                                            <i class="fas fa-user-circle me-1"></i>
                                            <?php echo htmlspecialchars($a['username']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($a['nom'] . ' ' . $a['prenom']); ?></small>
                                    </td>
                                    <?php if ($has_type): ?>
                                        <td>
                                            <span class="badge-type" style="background:<?php echo $couleurs_types[$a['type']] ?? '#a0aec0'; ?>20; color:<?php echo $couleurs_types[$a['type']] ?? '#a0aec0'; ?>;">
                                                <?php echo $type_label; ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php echo htmlspecialchars($a['action'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo date('d/m/Y', strtotime($a['dateAction'])); ?>
                                            <br>
                                            <span class="text-muted"><?php echo date('H:i:s', strtotime($a['dateAction'])); ?></span>
                                        </small>
                                    </td>
                                    <?php if ($has_details): ?>
                                        <td>
                                            <?php if (!empty($a['details'])): ?>
                                                <small class="text-muted">
                                                    <?php 
                                                        $details = json_decode($a['details'], true);
                                                        if ($details && is_array($details)) {
                                                            echo implode(', ', array_map(function($k, $v) {
                                                                return $k . ': ' . $v;
                                                            }, array_keys($details), $details));
                                                        } else {
                                                            echo htmlspecialchars($a['details']);
                                                        }
                                                    ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== RÉPARTITION PAR TYPE (SI DISPONIBLE) ===== -->
        <?php if ($has_type && !empty($types_stats)): ?>
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="widget">
                    <h5 style="font-weight:700; font-size:0.9rem; color:#1a202c; margin-bottom:12px;">
                        <i class="fas fa-chart-pie me-2" style="color:#667eea;"></i>
                        Répartition par type d'activité
                    </h5>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($types_stats as $ts): 
                            $color = $couleurs_types[$ts['type']] ?? '#a0aec0';
                            $label = ucfirst($ts['type'] ?? 'autre');
                            $total = $ts['total'];
                            $pourcentage = $total_activites > 0 ? round(($total / $total_activites) * 100, 1) : 0;
                        ?>
                            <div style="display:flex; align-items:center; gap:6px; background:#f7fafc; padding:4px 12px 4px 6px; border-radius:20px;">
                                <span class="type-dot" style="background:<?php echo $color; ?>;"></span>
                                <span style="font-weight:600; font-size:0.8rem;"><?php echo $label; ?></span>
                                <span style="background:white; padding:0 8px; border-radius:12px; font-size:0.7rem; font-weight:700; color:#1a202c;">
                                    <?php echo $total; ?> (<?php echo $pourcentage; ?>%)
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    // ===== FILTRE AUTOMATIQUE =====
    document.querySelectorAll('#filterForm select, #filterForm input').forEach(function(element) {
        element.addEventListener('change', function() {
            if (this.name !== 'search') {
                document.getElementById('filterForm').submit();
            }
        });
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
</script>
</body>
</html>