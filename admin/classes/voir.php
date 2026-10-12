<?php
// admin/classes/voir.php - Voir les détails d'une classe et ses élèves
session_start();

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Détails de la classe';
$db = db();

// ===== RÉCUPÉRER L'ID DE LA CLASSE DEPUIS L'URL =====
$idClasse = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Si l'ID est invalide, rediriger vers la liste des classes
if ($idClasse <= 0) {
    header('Location: index.php');
    exit();
}

// ===== RÉCUPÉRER LES INFORMATIONS DE LA CLASSE =====
$sql_classe = "
    SELECT c.idClasse, c.nomClasse, c.idNiveau,
           n.nomNiveau
    FROM classes c
    LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau
    WHERE c.idClasse = :idClasse
";
$stmt = $db->prepare($sql_classe);
$stmt->execute(['idClasse' => $idClasse]);
$classe = $stmt->fetch();

// Si la classe n'existe pas, rediriger vers la liste
if (!$classe) {
    header('Location: index.php');
    exit();
}

// ===== RÉCUPÉRER LES ÉLÈVES DE LA CLASSE =====
// Note: La colonne idParent n'existe pas, on utilise la table inscriptions
$sql_eleves = "
    SELECT 
        e.idEleve, 
        e.nom, 
        e.postNom, 
        e.prenom, 
        e.sexe, 
        e.telephone, 
        e.dateNaissance, 
        e.lieuNaissance, 
        e.adresse,
        i.idInscription, 
        i.dateInscription
    FROM eleves e
    INNER JOIN inscriptions i ON e.idEleve = i.idEleve
    WHERE i.idClasse = :idClasse
    ORDER BY e.nom, e.prenom
";
$stmt_eleves = $db->prepare($sql_eleves);
$stmt_eleves->execute(['idClasse' => $idClasse]);
$eleves = $stmt_eleves->fetchAll();

// ===== CALCULER LES STATISTIQUES =====
$total_eleves = count($eleves);
$garcons = 0;
$filles = 0;

foreach ($eleves as $e) {
    if ($e['sexe'] === 'M') {
        $garcons++;
    } elseif ($e['sexe'] === 'F') {
        $filles++;
    }
}

// ===== INCLURE L'EN-TÊTE =====
include __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ===== RESET ET BASE ===== */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: #f8fafc;
        font-size: 14px;
    }

    /* ===== LAYOUT PRINCIPAL ===== */
    .app-layout { display: flex; min-height: 100vh; }

    /* ===== SIDEBAR - MENU LATÉRAL GAUCHE ===== */
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

    /* Overlay pour fermer le menu mobile */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        z-index: 999;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .sidebar-overlay.active { 
        display: block; 
        opacity: 1; 
    }

    /* Bouton pour ouvrir le menu sur mobile */
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

    /* Logo du sidebar */
    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 16px;
        margin-bottom: 16px;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .sidebar-logo i { 
        font-size: 1.3rem; 
        color: #667eea; 
    }
    .sidebar-logo span { 
        font-size: 1rem; 
        font-weight: 700; 
        color: white; 
        flex: 1; 
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

    /* Menu du sidebar */
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
    .sidebar-menu li { 
        margin-bottom: 2px; 
    }
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
        background: rgba(255, 255, 255, 0.08);
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

    /* Pied de page du sidebar (info utilisateur) */
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
    .sidebar-footer .user-info i { 
        font-size: 1.4rem; 
        color: #667eea; 
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
        font-size: 0.65rem;
    }
    .sidebar-footer .btn-logout {
        background: rgba(255, 255, 255, 0.08);
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
        background: rgba(255, 255, 255, 0.15);
        color: #feb2b2;
    }

    /* ===== CONTENU PRINCIPAL ===== */
    .main-content {
        flex: 1;
        padding: 20px 25px;
        background: #f8fafc;
        min-height: 100vh;
        margin-left: 0;
    }

    /* ===== EN-TÊTE DE LA PAGE ===== */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
        background: white;
        padding: 14px 20px;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .page-header h3 {
        font-size: 1rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .page-header h3 i {
        color: #667eea;
        margin-right: 8px;
    }
    .page-header small {
        color: #718096;
        font-size: 0.75rem;
        display: block;
        font-weight: 400;
    }
    .page-header .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    /* Boutons */
    .btn-back {
        background: #667eea;
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
        cursor: pointer;
    }
    .btn-back:hover {
        background: #5a67d8;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102,126,234,0.35);
        color: white !important;
        text-decoration: none;
    }

    .btn-edit {
        background: #f6e05e;
        color: #975a16 !important;
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
        cursor: pointer;
    }
    .btn-edit:hover {
        background: #ecc94b;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(246, 224, 94, 0.4);
        color: #975a16 !important;
        text-decoration: none;
    }

    /* ===== INFOS CLASSE ===== */
    .info-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        padding: 20px 25px;
        margin-bottom: 25px;
    }
    .info-card .info-title {
        font-weight: 600;
        color: #2d3748;
        font-size: 0.9rem;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f2f5;
    }
    .info-card .info-title i {
        color: #667eea;
        margin-right: 6px;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    .info-item {
        display: flex;
        flex-direction: column;
    }
    .info-item .label {
        font-size: 0.7rem;
        color: #718096;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .info-item .value {
        font-size: 1rem;
        font-weight: 600;
        color: #1a202c;
        margin-top: 2px;
    }
    .info-item .value .badge-niveau {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .badge-niveau.primaire { background: #e3f2fd; color: #0d47a1; }
    .badge-niveau.secondaire { background: #fff3e0; color: #e65100; }
    .badge-niveau.maternelle { background: #fce4ec; color: #c62828; }
    .badge-niveau.default { background: #f5f5f5; color: #616161; }

    /* ===== STATISTIQUES ===== */
    .stats-eleves {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-eleve {
        background: white;
        padding: 12px 16px;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        text-align: center;
        border-left: 3px solid #667eea;
    }
    .stat-eleve .number {
        font-size: 1.3rem;
        font-weight: 700;
        color: #1a202c;
    }
    .stat-eleve .label {
        font-size: 0.7rem;
        color: #718096;
        margin-top: 2px;
    }
    .stat-eleve.green { border-color: #48bb78; }
    .stat-eleve.blue { border-color: #4299e1; }
    .stat-eleve.pink { border-color: #ed64a6; }

    /* ===== TABLEAU DES ÉLÈVES ===== */
    .table-box {
        background: white;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .table-header {
        padding: 10px 16px;
        border-bottom: 1px solid #edf2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }
    .table-header .title {
        font-weight: 600;
        color: #2d3748;
        font-size: 0.85rem;
    }
    .table-header .title i {
        color: #667eea;
        margin-right: 6px;
    }

    /* Barre de recherche */
    .search-box {
        display: flex;
        align-items: center;
        background: #f7fafc;
        border-radius: 6px;
        padding: 3px 12px;
        border: 1px solid #e2e8f0;
    }
    .search-box input {
        border: none;
        background: transparent;
        padding: 5px 8px;
        outline: none;
        font-size: 0.8rem;
        width: 160px;
    }
    .search-box i {
        color: #a0aec0;
        font-size: 0.8rem;
    }

    /* Le tableau */
    .table-scroll { 
        overflow-x: auto; 
    }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }
    .table th {
        background: #f7fafc;
        color: #4a5568;
        font-weight: 600;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding: 8px 12px;
        border-bottom: 2px solid #edf2f7;
        text-align: left;
    }
    .table td {
        padding: 8px 12px;
        border-bottom: 1px solid #f0f2f5;
        vertical-align: middle;
    }
    .table tbody tr:hover td {
        background: #f7fafc;
    }

    /* Badges */
    .badge-sexe {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-sexe.M { 
        background: #dbeafe; 
        color: #1e40af; 
    }
    .badge-sexe.F { 
        background: #fce4ec; 
        color: #c62828; 
    }

    /* État vide */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }
    .empty-state i {
        font-size: 2rem;
        opacity: 0.3;
        margin-bottom: 8px;
        display: block;
    }
    .empty-state h5 {
        color: #4a5568;
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 4px;
    }
    .empty-state p {
        font-size: 0.8rem;
    }

    .text-muted {
        color: #718096;
    }

    /* ===== RESPONSIVE ===== */
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
            opacity: 1; 
        }
        .main-content { 
            padding: 12px !important; 
            margin-left: 0 !important; 
        }
        .page-header { 
            flex-direction: column; 
            text-align: center; 
        }
        .page-header .header-actions {
            justify-content: center;
        }
        .stats-eleves { 
            grid-template-columns: 1fr 1fr; 
        }
        .table th, .table td { 
            padding: 5px 8px; 
            font-size: 0.7rem; 
        }
        .info-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 480px) {
        .stats-eleves { 
            grid-template-columns: 1fr; 
        }
        .info-grid {
            grid-template-columns: 1fr;
        }
        .table th, .table td { 
            padding: 4px 6px; 
            font-size: 0.65rem; 
        }
        .page-header h3 { 
            font-size: 0.9rem; 
        }
        .search-box input {
            width: 100%;
        }
        .table-header {
            flex-direction: column;
            align-items: stretch;
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
<!-- ===== BOUTON MOBILE POUR OUVRIR LE MENU ===== -->
<!-- ============================================================ -->
<button class="sidebar-toggle-mobile" id="sidebarToggle" title="Menu">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ============================================================ -->
<!-- ===== LAYOUT PRINCIPAL ===== -->
<!-- ============================================================ -->
<div class="app-layout">

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar-admin" id="sidebarAdmin">
        <!-- Logo -->
        <div class="sidebar-logo">
            <i class="fas fa-school"></i>
            <span>GestionScolaire</span>
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Menu de navigation -->
        <ul class="sidebar-menu">
            <li class="menu-section"><i class="fas fa-cog"></i> Administration</li>
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="../eleves/index.php"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="../inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="../paiements/index.php"><i class="fas fa-money-bill-wave"></i> Paiements</a></li>
            <li><a href="../frais/index.php"><i class="fas fa-tag"></i> Frais</a></li>
            <li><a href="index.php" class="active"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="../annees/index.php"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
            <li><a href="../utilisateurs/index.php"><i class="fas fa-user-cog"></i> Utilisateurs</a></li>
            <li class="menu-section"><i class="fas fa-file-alt"></i> Rapports</li>
            <li><a href="../rapports/index.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
            <li><a href="../journal/index.php"><i class="fas fa-history"></i> Journal</a></li>
            <li><a href="../statut.php"><i class="fas fa-user-check"></i> Statut</a></li>
        </ul>
        
        <!-- Pied de page avec l'utilisateur connecté -->
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

    <!-- ===== CONTENU PRINCIPAL ===== -->
    <div class="main-content">

        <!-- ===== EN-TÊTE ===== -->
        <div class="page-header">
            <div>
                <h3>
                    <i class="fas fa-eye"></i>
                    Détails de la classe
                    <small><?php echo htmlspecialchars($classe['nomClasse']); ?></small>
                </h3>
            </div>
            <div class="header-actions">
                <a href="modifier.php?id=<?php echo $idClasse; ?>" class="btn-edit">
                    <i class="fas fa-edit"></i> Modifier
                </a>
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <!-- ===== INFORMATIONS DE LA CLASSE ===== -->
        <div class="info-card">
            <div class="info-title"><i class="fas fa-info-circle"></i> Informations générales</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label"><i class="fas fa-tag"></i> Nom de la classe</span>
                    <span class="value"><?php echo htmlspecialchars($classe['nomClasse']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-layer-group"></i> Niveau</span>
                    <span class="value">
                        <?php 
                            $niveauClass = 'default';
                            $niveau = strtolower($classe['nomNiveau'] ?? '');
                            if (strpos($niveau, 'primaire') !== false) $niveauClass = 'primaire';
                            elseif (strpos($niveau, 'secondaire') !== false) $niveauClass = 'secondaire';
                            elseif (strpos($niveau, 'maternelle') !== false) $niveauClass = 'maternelle';
                        ?>
                        <span class="badge-niveau <?php echo $niveauClass; ?>">
                            <?php echo htmlspecialchars($classe['nomNiveau'] ?? 'Non défini'); ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-user-graduate"></i> Total élèves</span>
                    <span class="value"><?php echo $total_eleves; ?></span>
                </div>
                <div class="info-item">
                    <span class="label"><i class="fas fa-calendar-alt"></i> Date de création</span>
                    <span class="value"><?php echo date('d/m/Y'); ?></span>
                </div>
            </div>
        </div>

        <!-- ===== STATISTIQUES DES ÉLÈVES ===== -->
        <div class="stats-eleves">
            <div class="stat-eleve">
                <div class="number"><?php echo $total_eleves; ?></div>
                <div class="label">Total élèves</div>
            </div>
            <div class="stat-eleve green">
                <div class="number"><?php echo $garcons; ?></div>
                <div class="label">Garçons</div>
            </div>
            <div class="stat-eleve blue">
                <div class="number"><?php echo $filles; ?></div>
                <div class="label">Filles</div>
            </div>
            <div class="stat-eleve pink">
                <div class="number">
                    <?php echo $total_eleves > 0 ? round(($garcons / $total_eleves) * 100) : 0; ?>%
                </div>
                <div class="label">% Garçons</div>
            </div>
        </div>

        <!-- ===== LISTE DES ÉLÈVES ===== -->
        <div class="table-box">
            <div class="table-header">
                <span class="title">
                    <i class="fas fa-user-graduate"></i> 
                    Liste des élèves (<?php echo $total_eleves; ?>)
                </span>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Rechercher...">
                </div>
            </div>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:38px;">#</th>
                            <th>Nom complet</th>
                            <th>Sexe</th>
                            <th>Téléphone</th>
                            <th>Date naissance</th>
                            <th>Date inscription</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eleves)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-user-graduate"></i>
                                        <h5>Aucun élève dans cette classe</h5>
                                        <p>Inscrivez des élèves dans cette classe</p>
                                        <a href="../inscriptions/index.php?classe=<?php echo $idClasse; ?>" class="btn-back" style="margin-top:10px; display:inline-flex;">
                                            <i class="fas fa-plus"></i> Inscrire un élève
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eleves as $i => $e): 
                                // Construire le nom complet
                                $nom_complet = trim($e['nom'] . ' ' . ($e['postNom'] ?? '') . ' ' . $e['prenom']);
                                // Date de naissance
                                $dateNaissance = $e['dateNaissance'] ? date('d/m/Y', strtotime($e['dateNaissance'])) : '-';
                                // Date d'inscription
                                $dateInscription = $e['dateInscription'] ? date('d/m/Y', strtotime($e['dateInscription'])) : '-';
                            ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($nom_complet); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge-sexe <?php echo $e['sexe']; ?>">
                                            <?php echo $e['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($e['telephone'] ?? '-'); ?></td>
                                    <td><?php echo $dateNaissance; ?></td>
                                    <td><?php echo $dateInscription; ?></td>
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
<!-- ===== SCRIPTS JAVASCRIPT ===== -->
<!-- ============================================================ -->
<script>
/**
 * Gestion du sidebar mobile
 * Permet d'ouvrir/fermer le menu sur les petits écrans
 */
document.addEventListener('DOMContentLoaded', function() {
    // Récupérer les éléments DOM
    const sidebar = document.getElementById('sidebarAdmin');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');

    /**
     * Fonction pour ouvrir le sidebar
     */
    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Fonction pour fermer le sidebar
     */
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Événements pour ouvrir/fermer
    if (toggleBtn) {
        toggleBtn.addEventListener('click', openSidebar);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Fermer le sidebar avec la touche Échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    /**
     * Recherche en temps réel dans le tableau des élèves
     * Filtre les lignes en fonction du texte saisi
     */
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const term = this.value.toLowerCase();
            // Parcourir toutes les lignes du tableau
            document.querySelectorAll('tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                // Afficher ou masquer la ligne
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    }
});
</script>
</body>
</html>