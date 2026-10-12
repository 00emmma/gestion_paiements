<?php
// caissier/eleves/index.php - Liste des élèves
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

$page_title = 'Liste des élèves - Caissier';
$db = db();

// Récupérer les filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtre_classe = isset($_GET['classe']) ? intval($_GET['classe']) : 0;

// Construction de la requête
$sql = "SELECT e.*, 
               c.nomClasse, n.nomNiveau,
               (SELECT COUNT(*) FROM inscriptions i2 WHERE i2.idEleve = e.idEleve AND i2.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)) as est_inscrit
        FROM eleves e
        LEFT JOIN inscriptions i ON i.idEleve = e.idEleve AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
        LEFT JOIN classes c ON i.idClasse = c.idClasse
        LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (e.matricule LIKE :search OR e.nom LIKE :search OR e.prenom LIKE :search OR e.telephone LIKE :search)";
    $params['search'] = "%$search%";
}

if ($filtre_classe > 0) {
    $sql .= " AND i.idClasse = :classe";
    $params['classe'] = $filtre_classe;
}

$sql .= " GROUP BY e.idEleve ORDER BY e.nom ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$eleves = $stmt->fetchAll();

// Récupérer les classes pour le filtre
$stmt = $db->query("SELECT c.*, n.nomNiveau FROM classes c JOIN niveaux n ON c.idNiveau = n.idNiveau ORDER BY c.nomClasse");
$classes = $stmt->fetchAll();

// Récupérer l'année active
$stmt = $db->query("SELECT * FROM annees_scolaires WHERE active = 1");
$annee_active = $stmt->fetch();

// Statistiques
$total_eleves = count($eleves);
$total_inscrits = 0;
$total_filles = 0;
$total_garcons = 0;

foreach ($eleves as $e) {
    if ($e['est_inscrit'] > 0) $total_inscrits++;
    if ($e['sexe'] === 'F') $total_filles++;
    if ($e['sexe'] === 'M') $total_garcons++;
}

include __DIR__ . '/../../includes/header.php';
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
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 25px;
    }
    .page-header h2 {
        font-size: 1.4rem;
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
    .page-header .btn-print {
        background: #38a169;
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
        cursor: pointer;
    }
    .page-header .btn-print:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(56, 161, 105, 0.35);
        color: white;
    }
    .page-header .btn-pdf {
        background: #e53e3e;
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
        cursor: pointer;
    }
    .page-header .btn-pdf:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(229, 62, 62, 0.35);
        color: white;
    }

    /* ===== STATS ===== */
    .stats-row {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        padding: 12px 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 150px;
    }
    .stat-item .stat-icon {
        width: 36px;
        height: 36px;
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
        font-size: 0.75rem;
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
        padding: 15px 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .filters input,
    .filters select {
        padding: 8px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.85rem;
        background: white;
        flex: 1;
        min-width: 150px;
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
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
    }
    .filters .btn-filter:hover { background: #5a67d8; }
    .filters .btn-reset {
        background: #e2e8f0;
        color: #4a5568;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
    }
    .filters .btn-reset:hover { background: #cbd5e0; }

    /* ===== TABLE ===== */
    .table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
        background: #f7fafc;
        border-bottom: 2px solid #e2e8f0;
    }
    .table thead th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #4a5568;
        white-space: nowrap;
    }
    .table tbody tr { border-bottom: 1px solid #f0f2f5; transition: 0.3s; }
    .table tbody tr:hover { background: #f7fafc; }
    .table tbody td { padding: 10px 14px; vertical-align: middle; }

    .badge-inscrit {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-inscrit.oui { background: #f0fff4; color: #276749; }
    .badge-inscrit.non { background: #fff5f5; color: #9b2c2c; }

    .badge-sex {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-sex.m { background: #ebf8ff; color: #2b6cb0; }
    .badge-sex.f { background: #faf5ff; color: #6b46c1; }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }
    .empty-state i { font-size: 2.5rem; margin-bottom: 10px; color: #e2e8f0; }

    .btn-actions {
        display: flex;
        gap: 4px;
    }
    .btn-actions .btn-sm {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        border: none;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-actions .btn-view {
        background: #ebf8ff;
        color: #2b6cb0;
    }
    .btn-actions .btn-view:hover { background: #bee3f8; }
    .btn-actions .btn-situation {
        background: #fefcbf;
        color: #975a16;
    }
    .btn-actions .btn-situation:hover { background: #f6c23e; }

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

        .page-header {
            flex-direction: column;
            text-align: center;
        }
        .page-header .header-right {
            flex-wrap: wrap;
            justify-content: center;
        }
        .page-header .header-right .btn-pdf,
        .page-header .header-right .btn-print {
            width: 100%;
            justify-content: center;
        }

        .stats-row { flex-direction: column; }
        .stat-item { min-width: auto; }
        .filters { flex-direction: column; }
        .filters input, .filters select { width: 100%; min-width: auto; }
        .filters .btn-filter, .filters .btn-reset { width: 100%; justify-content: center; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1.1rem; }
        .stat-item { padding: 10px 14px; }
        .stat-item .stat-info .number { font-size: 1rem; }
        .table { font-size: 0.75rem; min-width: 550px; }
        .table thead th, .table tbody td { padding: 6px 8px; }
        .sidebar-toggle-mobile {
            width: 40px;
            height: 40px;
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
    <div class="sidebar-caissier" id="sidebarCaissier">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class="fas fa-coins"></i></div>
            <span>GestionScolaire</span>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>

        <ul class="sidebar-menu">
            <!-- Tableau de bord -->
            <li><a href="../dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a></li>

            <!-- Paiements -->
            <li class="menu-section"><i class="fas fa-money-bill-wave"></i> Paiements</li>
            <li><a href="../paiements/index.php">
                <i class="fas fa-list"></i> Liste des paiements
            </a></li>

            <!-- Élèves -->
            <li class="menu-section"><i class="fas fa-users"></i> Élèves</li>
            <li><a href="index.php" class="active">
                <i class="fas fa-user-graduate"></i> Liste des élèves
            </a></li>
            <li><a href="situation_financiere.php">
                <i class="fas fa-chart-pie"></i> Situation financière
            </a></li>

            <!-- Reçus -->
            <li class="menu-section"><i class="fas fa-file-invoice"></i> Reçus</li>
            <li><a href="../recus/generer.php">
                <i class="fas fa-file-invoice"></i> Générer un reçu
            </a></li>
            <li><a href="../recus/historique.php">
                <i class="fas fa-history"></i> Historique des reçus
            </a></li>

            <!-- Mon profil -->
            <li class="menu-section"><i class="fas fa-user-circle"></i> Compte</li>
            <li><a href="../profil/index.php">
                <i class="fas fa-user-circle"></i> Mon profil
            </a></li>
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
                <i class="fas fa-user-graduate"></i>
                Liste des élèves
            </h2>
            <div class="header-right">
                <button class="btn-pdf" onclick="genererPDF()">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button class="btn-print" onclick="imprimer()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(102, 126, 234, 0.15); color: #667eea;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_eleves; ?></div>
                    <div class="label">Total élèves</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_inscrits; ?></div>
                    <div class="label">Inscrits (<?php echo $annee_active['libelle'] ?? 'en cours'; ?>)</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                    <i class="fas fa-mars"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_garcons; ?></div>
                    <div class="label">Garçons</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                    <i class="fas fa-venus"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_filles; ?></div>
                    <div class="label">Filles</div>
                </div>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <form method="GET" action="" class="filters">
            <input type="text" name="search" placeholder="Rechercher (matricule, nom, prénom, téléphone)" 
                   value="<?php echo htmlspecialchars($search); ?>">
            <select name="classe">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?php echo $c['idClasse']; ?>" <?php echo $filtre_classe == $c['idClasse'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['nomClasse'] . ' (' . $c['nomNiveau'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="index.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>

        <!-- ===== TABLE ===== -->
        <div class="table-container" id="tableContainer">
            <div class="table-responsive">
                <table class="table" id="eleveTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i></th>
                            <th><i class="fas fa-id-card"></i> Matricule</th>
                            <th><i class="fas fa-user"></i> Nom complet</th>
                            <th><i class="fas fa-venus-mars"></i> Sexe</th>
                            <th><i class="fas fa-phone"></i> Téléphone</th>
                            <th><i class="fas fa-chalkboard"></i> Classe</th>
                            <th><i class="fas fa-check-circle"></i> Statut</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eleves)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun élève trouvé</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eleves as $index => $e): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($e['matricule']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($e['nom'] . ' ' . $e['postNom'] . ' ' . $e['prenom']); ?></td>
                                    <td>
                                        <span class="badge-sex <?php echo $e['sexe'] === 'M' ? 'm' : 'f'; ?>">
                                            <?php echo $e['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($e['telephone'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($e['nomClasse'] ?? 'Non inscrit'); ?></td>
                                    <td>
                                        <span class="badge-inscrit <?php echo $e['est_inscrit'] > 0 ? 'oui' : 'non'; ?>">
                                            <?php echo $e['est_inscrit'] > 0 ? 'Inscrit' : 'Non inscrit'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-actions">
                                            <a href="voir.php?id=<?php echo $e['idEleve']; ?>" class="btn-sm btn-view" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="situation_financiere.php?id=<?php echo $e['idEleve']; ?>" class="btn-sm btn-situation" title="Situation financière">
                                                <i class="fas fa-chart-pie"></i>
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

        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    // ===== PDF =====
    function genererPDF() {
        window.open('export_pdf.php' + window.location.search, '_blank');
    }

    // ===== IMPRIMER =====
    function imprimer() {
        const contenu = document.getElementById('tableContainer').innerHTML;
        const titre = '<h2 style="text-align:center;margin-bottom:20px;"><i class="fas fa-user-graduate"></i> Liste des élèves</h2>';
        const styles = `
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                th { background: #f0f0f0; padding: 8px; text-align: left; border: 1px solid #ddd; }
                td { padding: 8px; border: 1px solid #ddd; }
                .badge-inscrit { padding: 2px 8px; border-radius: 4px; font-size: 10px; }
                .badge-inscrit.oui { background: #d4edda; color: #155724; }
                .badge-inscrit.non { background: #f8d7da; color: #721c24; }
                .badge-sex { padding: 2px 8px; border-radius: 4px; font-size: 10px; }
                .badge-sex.m { background: #d6edff; color: #004085; }
                .badge-sex.f { background: #f5e6ff; color: #6b21a5; }
                .text-center { text-align: center; }
                .text-muted { color: #6c757d; }
                .btn-actions { display: none; }
            </style>
        `;
        const imprimerWindow = window.open('', '_blank');
        imprimerWindow.document.write(styles + titre + contenu);
        imprimerWindow.document.close();
        imprimerWindow.print();
    }

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