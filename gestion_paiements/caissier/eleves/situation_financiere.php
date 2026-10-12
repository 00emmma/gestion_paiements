<?php
// caissier/eleves/situation_financiere.php - Situation financière des élèves
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

$page_title = 'Situation financière - Caissier';
$db = db();

// Récupérer l'ID de l'élève si spécifié
$idEleve = isset($_GET['id']) ? intval($_GET['id']) : 0;
$eleve_selectionne = null;

if ($idEleve > 0) {
    $stmt = $db->prepare("SELECT e.* FROM eleves e WHERE e.idEleve = :id");
    $stmt->execute(['id' => $idEleve]);
    $eleve_selectionne = $stmt->fetch();
}

// Récupérer les filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtre_classe = isset($_GET['classe']) ? intval($_GET['classe']) : 0;

// Récupérer les classes pour le filtre
$stmt = $db->query("SELECT c.*, n.nomNiveau FROM classes c JOIN niveaux n ON c.idNiveau = n.idNiveau ORDER BY c.nomClasse");
$classes = $stmt->fetchAll();

// Récupérer l'année active
$stmt = $db->query("SELECT * FROM annees_scolaires WHERE active = 1");
$annee_active = $stmt->fetch();

// Construction de la requête pour la liste des élèves avec leur situation
$sql = "SELECT e.idEleve, e.matricule, e.nom, e.prenom, e.postNom,
               c.nomClasse, n.nomNiveau,
               (SELECT COUNT(*) FROM paiements p 
                JOIN inscriptions i ON p.idInscription = i.idInscription 
                WHERE i.idEleve = e.idEleve) as nb_paiements,
               (SELECT SUM(p.montantPaye) FROM paiements p 
                JOIN inscriptions i ON p.idInscription = i.idInscription 
                WHERE i.idEleve = e.idEleve) as total_paye,
               (SELECT SUM(f.montant) FROM frais f 
                JOIN paiements p ON p.idFrais = f.idFrais 
                JOIN inscriptions i ON p.idInscription = i.idInscription 
                WHERE i.idEleve = e.idEleve) as total_du
        FROM eleves e
        LEFT JOIN inscriptions i ON i.idEleve = e.idEleve AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
        LEFT JOIN classes c ON i.idClasse = c.idClasse
        LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (e.matricule LIKE :search OR e.nom LIKE :search OR e.prenom LIKE :search)";
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

// Si un élève est sélectionné, récupérer ses paiements détaillés
$paiements_detail = [];
$total_paye_detail = 0;
$total_du_detail = 0;

if ($eleve_selectionne) {
    // Récupérer les paiements de l'élève
    $stmt = $db->prepare("
        SELECT p.*, f.libelle as frais_libelle, c.nomClasse, a.libelle as annee
        FROM paiements p
        JOIN inscriptions i ON p.idInscription = i.idInscription
        JOIN frais f ON p.idFrais = f.idFrais
        JOIN classes c ON i.idClasse = c.idClasse
        JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
        WHERE i.idEleve = :id
        ORDER BY p.datePaiement DESC
    ");
    $stmt->execute(['id' => $idEleve]);
    $paiements_detail = $stmt->fetchAll();
    
    $total_paye_detail = array_sum(array_column($paiements_detail, 'montantPaye'));
    
    // Calculer le total dû (montant des frais)
    $stmt = $db->prepare("
        SELECT SUM(f.montant) as total
        FROM frais f
        JOIN paiements p ON p.idFrais = f.idFrais
        JOIN inscriptions i ON p.idInscription = i.idInscription
        WHERE i.idEleve = :id
    ");
    $stmt->execute(['id' => $idEleve]);
    $total_du_detail = $stmt->fetch()['total'] ?? 0;
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
        font-size: 1.3rem;
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
        gap: 8px;
        flex-wrap: wrap;
    }
    .page-header .btn-print {
        background: #38a169;
        color: white;
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
        cursor: pointer;
    }
    .page-header .btn-print:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(56, 161, 105, 0.35);
        color: white;
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
        min-width: 140px;
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
        margin-bottom: 20px;
    }
    .table-responsive { overflow-x: auto; }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 650px;
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
    .table tbody tr { border-bottom: 1px solid #f0f2f5; transition: 0.3s; cursor: pointer; }
    .table tbody tr:hover { background: #f7fafc; }
    .table tbody tr.active { background: #ebf8ff; }
    .table tbody td { padding: 8px 12px; vertical-align: middle; }

    .badge-situation {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .badge-situation.a_jour { background: #f0fff4; color: #276749; }
    .badge-situation.retard { background: #fff5f5; color: #9b2c2c; }
    .badge-situation.partiel { background: #fffbeb; color: #975a16; }

    .empty-state {
        text-align: center;
        padding: 30px 20px;
        color: #a0aec0;
    }
    .empty-state i { font-size: 2rem; margin-bottom: 8px; color: #e2e8f0; }

    /* ===== DÉTAILS ÉLÈVE ===== */
    .detail-eleve {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 16px 20px;
        margin-bottom: 20px;
        display: <?php echo $eleve_selectionne ? 'block' : 'none'; ?>;
    }
    .detail-eleve .detail-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid #edf2f7;
    }
    .detail-eleve .detail-header h4 {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .detail-eleve .detail-header h4 i {
        color: #667eea;
        margin-right: 8px;
    }
    .detail-eleve .detail-header .btn-close-detail {
        background: #f7fafc;
        border: 1px solid #e2e8f0;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.3s;
        color: #4a5568;
        text-decoration: none;
    }
    .detail-eleve .detail-header .btn-close-detail:hover {
        background: #edf2f7;
    }

    .detail-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin-bottom: 15px;
    }
    .detail-stats .stat-item {
        background: #f7fafc;
        padding: 8px 12px;
        border-radius: 8px;
        text-align: center;
    }
    .detail-stats .stat-item .number {
        font-size: 1.2rem;
        font-weight: 800;
        color: #1a202c;
    }
    .detail-stats .stat-item .label {
        font-size: 0.65rem;
        color: #718096;
        text-transform: uppercase;
    }
    .detail-stats .stat-item .number.positive { color: #48bb78; }
    .detail-stats .stat-item .number.negative { color: #fc8181; }
    .detail-stats .stat-item .number.neutral { color: #667eea; }

    .table-paiements {
        font-size: 0.78rem;
    }
    .table-paiements thead th {
        background: #f7fafc;
        padding: 6px 10px;
        font-size: 0.6rem;
        text-transform: uppercase;
        color: #4a5568;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-paiements tbody td {
        padding: 6px 10px;
        border-bottom: 1px solid #edf2f7;
    }
    .table-paiements tbody tr:last-child td { border-bottom: none; }

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
            max-height: none;
        }

        .page-header {
            flex-direction: column;
            text-align: center;
        }
        .page-header .header-right {
            flex-wrap: wrap;
            justify-content: center;
        }

        .filters { flex-direction: column; }
        .filters input, .filters select { width: 100%; min-width: auto; }
        .filters .btn-filter, .filters .btn-reset { width: 100%; justify-content: center; }
        
        .detail-stats { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1.1rem; }
        .table { font-size: 0.7rem; min-width: 500px; }
        .table thead th, .table tbody td { padding: 4px 6px; }
        .detail-stats { grid-template-columns: 1fr; }
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
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li class="menu-section"><i class="fas fa-money-bill-wave"></i> Paiements</li>
            <li><a href="../paiements/index.php"><i class="fas fa-list"></i> Liste des paiements</a></li>
            <li class="menu-section"><i class="fas fa-users"></i> Élèves</li>
            <li><a href="index.php"><i class="fas fa-user-graduate"></i> Liste des élèves</a></li>
            <li><a href="situation_financiere.php" class="active"><i class="fas fa-chart-pie"></i> Situation financière</a></li>
            <li class="menu-section"><i class="fas fa-file-invoice"></i> Reçus</li>
            <li><a href="../recus/generer.php"><i class="fas fa-file-invoice"></i> Générer un reçu</a></li>
            <li><a href="../recus/historique.php"><i class="fas fa-history"></i> Historique des reçus</a></li>
            <li class="menu-section"><i class="fas fa-user-circle"></i> Compte</li>
            <li><a href="../profil/index.php"><i class="fas fa-user-circle"></i> Mon profil</a></li>
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
                <i class="fas fa-chart-pie"></i>
                Situation financière des élèves
            </h2>
            <div class="header-right">
                <button class="btn-print" onclick="imprimer()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <form method="GET" action="" class="filters">
            <input type="text" name="search" placeholder="Rechercher (matricule, nom, prénom)" 
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
            <a href="situation_financiere.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>

        <!-- ===== LISTE DES ÉLÈVES ===== -->
        <div class="table-container" id="tableContainer">
            <div class="table-responsive">
                <table class="table" id="situationTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i></th>
                            <th><i class="fas fa-id-card"></i> Matricule</th>
                            <th><i class="fas fa-user"></i> Élève</th>
                            <th><i class="fas fa-chalkboard"></i> Classe</th>
                            <th><i class="fas fa-receipt"></i> Paiements</th>
                            <th><i class="fas fa-dollar-sign"></i> Total payé</th>
                            <th><i class="fas fa-info-circle"></i> Situation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eleves)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun élève trouvé</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eleves as $index => $e): 
                                $total_paye = $e['total_paye'] ?? 0;
                                $total_du = $e['total_du'] ?? 0;
                                $solde = $total_du - $total_paye;
                                
                                if ($solde <= 0) {
                                    $situation = 'À jour';
                                    $situation_class = 'a_jour';
                                } elseif ($solde > 0 && $e['nb_paiements'] > 0) {
                                    $situation = 'Partiel';
                                    $situation_class = 'partiel';
                                } else {
                                    $situation = 'Aucun paiement';
                                    $situation_class = 'retard';
                                }
                            ?>
                                <tr onclick="window.location.href='situation_financiere.php?id=<?php echo $e['idEleve']; ?>'" 
                                    class="<?php echo $idEleve == $e['idEleve'] ? 'active' : ''; ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($e['matricule']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($e['nom'] . ' ' . $e['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($e['nomClasse'] ?? 'Non inscrit'); ?></td>
                                    <td><?php echo $e['nb_paiements'] ?? 0; ?></td>
                                    <td><strong>$<?php echo number_format($total_paye, 2); ?></strong></td>
                                    <td>
                                        <span class="badge-situation <?php echo $situation_class; ?>">
                                            <?php echo $situation; ?>
                                            <?php if ($solde > 0): ?>
                                                (Solde: $<?php echo number_format($solde, 2); ?>)
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== DÉTAILS DE L'ÉLÈVE SÉLECTIONNÉ ===== -->
        <?php if ($eleve_selectionne): ?>
        <div class="detail-eleve" id="detailEleve">
            <div class="detail-header">
                <h4>
                    <i class="fas fa-user-graduate"></i>
                    Détails - <?php echo htmlspecialchars($eleve_selectionne['nom'] . ' ' . $eleve_selectionne['prenom']); ?>
                    <small style="font-weight:400;color:#718096;font-size:0.8rem;">
                        (<?php echo htmlspecialchars($eleve_selectionne['matricule']); ?>)
                    </small>
                </h4>
                <a href="situation_financiere.php" class="btn-close-detail" title="Fermer">
                    <i class="fas fa-times"></i>
                </a>
            </div>

            <!-- Statistiques -->
            <div class="detail-stats">
                <div class="stat-item">
                    <div class="number neutral"><?php echo count($paiements_detail); ?></div>
                    <div class="label">Paiements</div>
                </div>
                <div class="stat-item">
                    <div class="number positive">$<?php echo number_format($total_paye_detail, 2); ?></div>
                    <div class="label">Total payé</div>
                </div>
                <div class="stat-item">
                    <div class="number neutral">$<?php echo number_format($total_du_detail, 2); ?></div>
                    <div class="label">Total dû</div>
                </div>
                <div class="stat-item">
                    <div class="number <?php echo ($total_du_detail - $total_paye_detail) <= 0 ? 'positive' : 'negative'; ?>">
                        $<?php echo number_format($total_du_detail - $total_paye_detail, 2); ?>
                    </div>
                    <div class="label">Solde</div>
                </div>
            </div>

            <!-- Liste des paiements -->
            <h6 style="font-weight:700;margin-bottom:10px;color:#1a202c;">
                <i class="fas fa-list"></i> Historique des paiements
            </h6>
            <div class="table-responsive">
                <table class="table table-paiements">
                    <thead>
                        <tr>
                            <th>Frais</th>
                            <th>Montant</th>
                            <th>Classe</th>
                            <th>Année</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paiements_detail)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-2">
                                    <i class="fas fa-inbox me-1"></i> Aucun paiement
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paiements_detail as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['frais_libelle']); ?></td>
                                    <td><strong>$<?php echo number_format($p['montantPaye'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['nomClasse']); ?></td>
                                    <td><?php echo htmlspecialchars($p['annee']); ?></td>
                                    <td><small><?php echo date('d/m/Y', strtotime($p['datePaiement'])); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
    // ===== IMPRIMER =====
    function imprimer() {
        const contenu = document.getElementById('tableContainer').innerHTML;
        <?php if ($eleve_selectionne): ?>
        const detail = document.getElementById('detailEleve').innerHTML;
        <?php else: ?>
        const detail = '';
        <?php endif; ?>
        
        const titre = `
            <h2 style="text-align:center;margin-bottom:15px;color:#1a202c;">
                <i class="fas fa-chart-pie"></i> Situation financière des élèves
            </h2>
            <p style="text-align:center;color:#718096;margin-bottom:20px;">
                <?php echo date('d/m/Y H:i'); ?>
            </p>
        `;
        const styles = `
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; background: white; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                th { background: #f0f0f0; padding: 6px 8px; text-align: left; border: 1px solid #ddd; }
                td { padding: 6px 8px; border: 1px solid #ddd; }
                .badge-situation { padding: 2px 8px; border-radius: 4px; font-size: 10px; }
                .badge-situation.a_jour { background: #d4edda; color: #155724; }
                .badge-situation.retard { background: #f8d7da; color: #721c24; }
                .badge-situation.partiel { background: #fff3cd; color: #856404; }
                .text-center { text-align: center; }
                .text-muted { color: #6c757d; }
                .detail-eleve { margin-top: 20px; border-top: 2px solid #667eea; padding-top: 15px; }
                .detail-stats { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 15px; }
                .stat-item { background: #f8f9fa; padding: 8px; border-radius: 6px; text-align: center; }
                .stat-item .number { font-size: 1.1rem; font-weight: 700; }
                .stat-item .label { font-size: 0.6rem; color: #6c757d; text-transform: uppercase; }
                .stat-item .number.positive { color: #28a745; }
                .stat-item .number.negative { color: #dc3545; }
                .stat-item .number.neutral { color: #667eea; }
                .btn-close-detail, .btn-actions { display: none; }
                .btn-print, .no-print { display: none; }
            </style>
        `;
        const imprimerWindow = window.open('', '_blank');
        imprimerWindow.document.write(styles + titre + contenu + detail);
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