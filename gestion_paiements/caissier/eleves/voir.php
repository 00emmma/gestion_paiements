<?php
// caissier/eleves/voir.php - Voir les détails d'un élève
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

$db = db();
$idEleve = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idEleve <= 0) {
    header('Location: index.php');
    exit();
}

// Récupérer les informations de l'élève
$stmt = $db->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM inscriptions i WHERE i.idEleve = e.idEleve AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)) as est_inscrit
    FROM eleves e
    WHERE e.idEleve = :id
");
$stmt->execute(['id' => $idEleve]);
$eleve = $stmt->fetch();

if (!$eleve) {
    header('Location: index.php');
    exit();
}

// Récupérer les inscriptions de l'élève
$stmt = $db->prepare("
    SELECT i.*, c.nomClasse, n.nomNiveau, a.libelle as annee
    FROM inscriptions i
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN niveaux n ON c.idNiveau = n.idNiveau
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE i.idEleve = :id
    ORDER BY i.idAnnee DESC
");
$stmt->execute(['id' => $idEleve]);
$inscriptions = $stmt->fetchAll();

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
    LIMIT 5
");
$stmt->execute(['id' => $idEleve]);
$paiements = $stmt->fetchAll();

// Statistiques des paiements
$total_paye = array_sum(array_column($paiements, 'montantPaye'));
$nb_paiements = count($paiements);

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
    .page-header .btn-back:hover {
        background: #cbd5e0;
        color: #1a202c;
    }
    .page-header .btn-pdf {
        background: #e53e3e;
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
    .page-header .btn-pdf:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(229, 62, 62, 0.35);
        color: white;
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

    /* ===== PROFIL ===== */
    .profile-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 20px;
        margin-bottom: 20px;
    }
    .profile-card .profile-header {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .profile-card .profile-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
    }
    .profile-card .profile-info h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .profile-card .profile-info .matricule {
        color: #667eea;
        font-weight: 600;
        font-size: 0.85rem;
    }
    .profile-card .profile-info .statut {
        margin-top: 4px;
    }

    .badge-statut {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-statut.inscrit { background: #f0fff4; color: #276749; }
    .badge-statut.non_inscrit { background: #fff5f5; color: #9b2c2c; }

    .profile-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 10px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #edf2f7;
    }
    .profile-details .detail-item {
        display: flex;
        flex-direction: column;
    }
    .profile-details .detail-item .label {
        font-size: 0.65rem;
        color: #a0aec0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    .profile-details .detail-item .value {
        font-size: 0.85rem;
        font-weight: 500;
        color: #1a202c;
        margin-top: 1px;
    }

    /* ===== WIDGETS ===== */
    .widget {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 16px 20px;
        margin-bottom: 20px;
    }
    .widget .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .widget .widget-header h5 {
        font-size: 0.9rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .widget .widget-header h5 i {
        color: #667eea;
        margin-right: 6px;
    }
    .widget .widget-header .badge {
        font-size: 0.7rem;
        padding: 3px 10px;
    }

    .table-custom {
        font-size: 0.78rem;
    }
    .table-custom thead th {
        background: #f7fafc;
        color: #4a5568;
        font-weight: 600;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
        padding: 6px 8px;
    }
    .table-custom tbody td {
        padding: 6px 8px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
    }
    .table-custom tbody tr:hover {
        background: #f7fafc;
    }
    .table-custom tbody tr:last-child td {
        border-bottom: none;
    }

    .empty-state {
        text-align: center;
        padding: 20px;
        color: #a0aec0;
    }
    .empty-state i {
        font-size: 1.5rem;
        margin-bottom: 6px;
        color: #e2e8f0;
    }
    .empty-state p {
        margin: 0;
        font-size: 0.85rem;
    }

    .badge-annee {
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #ebf8ff;
        color: #2b6cb0;
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

        .profile-card .profile-header {
            flex-direction: column;
            text-align: center;
        }
        .profile-details {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1.1rem; }
        .profile-card { padding: 15px; }
        .profile-card .profile-avatar { width: 50px; height: 50px; font-size: 1.2rem; }
        .profile-card .profile-info h3 { font-size: 1rem; }
        .profile-details { grid-template-columns: 1fr; }
        .table-custom { font-size: 0.7rem; }
        .table-custom thead th, .table-custom tbody td { padding: 4px 6px; }
        .widget { padding: 12px 14px; }
        .sidebar-toggle-mobile {
            width: 40px;
            height: 40px;
            font-size: 0.9rem;
            bottom: 15px;
            left: 15px;
        }
        .page-header .btn-pdf,
        .page-header .btn-print {
            width: 100%;
            justify-content: center;
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
            <li><a href="index.php" class="active"><i class="fas fa-user-graduate"></i> Liste des élèves</a></li>
            <li><a href="situation_financiere.php"><i class="fas fa-chart-pie"></i> Situation financière</a></li>
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
                <i class="fas fa-user-graduate"></i>
                Détails de l'élève
            </h2>
            <div class="header-right">
                <button class="btn-pdf" onclick="genererPDF()">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button class="btn-print" onclick="imprimer()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <!-- ===== PROFIL ===== -->
        <div class="profile-card" id="profileCard">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($eleve['prenom'] ?? 'E', 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($eleve['nom'] . ' ' . $eleve['postNom'] . ' ' . $eleve['prenom']); ?></h3>
                    <div class="matricule">
                        <i class="fas fa-id-card me-1"></i>
                        Matricule: <strong><?php echo htmlspecialchars($eleve['matricule']); ?></strong>
                    </div>
                    <div class="statut">
                        <span class="badge-statut <?php echo $eleve['est_inscrit'] > 0 ? 'inscrit' : 'non_inscrit'; ?>">
                            <?php echo $eleve['est_inscrit'] > 0 ? 'Inscrit' : 'Non inscrit'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="profile-details">
                <div class="detail-item">
                    <span class="label"><i class="fas fa-venus-mars me-1"></i> Sexe</span>
                    <span class="value"><?php echo $eleve['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-calendar-alt me-1"></i> Naissance</span>
                    <span class="value"><?php echo $eleve['dateNaissance'] ? date('d/m/Y', strtotime($eleve['dateNaissance'])) : '-'; ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-map-marker-alt me-1"></i> Lieu</span>
                    <span class="value"><?php echo htmlspecialchars($eleve['lieuNaissance'] ?? '-'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-globe-africa me-1"></i> Nationalité</span>
                    <span class="value"><?php echo htmlspecialchars($eleve['nationalite'] ?? '-'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-phone me-1"></i> Téléphone</span>
                    <span class="value"><?php echo htmlspecialchars($eleve['telephone'] ?? '-'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-map-pin me-1"></i> Province</span>
                    <span class="value"><?php echo htmlspecialchars($eleve['province'] ?? '-'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-home me-1"></i> Adresse</span>
                    <span class="value"><?php echo htmlspecialchars($eleve['adresse'] ?? '-'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="label"><i class="fas fa-user-friends me-1"></i> Parents</span>
                    <span class="value">
                        <?php 
                            $parents = [];
                            if (!empty($eleve['nomPere'])) $parents[] = 'Père: ' . htmlspecialchars($eleve['nomPere']);
                            if (!empty($eleve['nomMere'])) $parents[] = 'Mère: ' . htmlspecialchars($eleve['nomMere']);
                            echo !empty($parents) ? implode(' | ', $parents) : '-';
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- ===== INSCRIPTIONS ===== -->
        <div class="widget">
            <div class="widget-header">
                <h5>
                    <i class="fas fa-book-open"></i>
                    Inscriptions
                </h5>
                <span class="badge bg-primary bg-opacity-10 text-primary">
                    <?php echo count($inscriptions); ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Année</th>
                            <th>Classe</th>
                            <th>Niveau</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inscriptions)): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-book"></i>
                                        <p>Aucune inscription</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inscriptions as $insc): ?>
                                <tr>
                                    <td><span class="badge-annee"><?php echo htmlspecialchars($insc['annee']); ?></span></td>
                                    <td><?php echo htmlspecialchars($insc['nomClasse']); ?></td>
                                    <td><?php echo htmlspecialchars($insc['nomNiveau']); ?></td>
                                    <td><small><?php echo date('d/m/Y', strtotime($insc['dateInscription'] ?? 'now')); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== DERNIERS PAIEMENTS ===== -->
        <div class="widget">
            <div class="widget-header">
                <h5>
                    <i class="fas fa-money-bill-wave"></i>
                    Derniers paiements
                </h5>
                <div>
                    <span class="badge bg-success bg-opacity-10 text-success me-1">
                        $<?php echo number_format($total_paye, 2); ?>
                    </span>
                    <span class="badge bg-primary bg-opacity-10 text-primary">
                        <?php echo $nb_paiements; ?>
                    </span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Frais</th>
                            <th>Montant</th>
                            <th>Classe</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paiements)): ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun paiement</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paiements as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['frais_libelle']); ?></td>
                                    <td><strong>$<?php echo number_format($p['montantPaye'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['nomClasse']); ?></td>
                                    <td><small><?php echo date('d/m/Y', strtotime($p['datePaiement'])); ?></small></td>
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
        window.open('export_pdf.php?id=<?php echo $idEleve; ?>', '_blank');
    }

    // ===== IMPRIMER =====
    function imprimer() {
        const contenu = document.getElementById('profileCard').outerHTML + 
                        document.querySelectorAll('.widget')[0].outerHTML + 
                        document.querySelectorAll('.widget')[1].outerHTML;
        
        const titre = `
            <h2 style="text-align:center;margin-bottom:15px;color:#1a202c;">
                <i class="fas fa-user-graduate"></i> Détails de l'élève
            </h2>
        `;
        const styles = `
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; background: white; }
                .profile-card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
                .profile-header { display: flex; align-items: center; gap: 16px; }
                .profile-avatar { width: 60px; height: 60px; border-radius: 50%; background: #667eea; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; font-weight: 700; }
                .profile-info h3 { margin: 0; font-size: 1.2rem; }
                .matricule { color: #667eea; font-weight: 600; font-size: 0.85rem; }
                .badge-statut { padding: 2px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
                .badge-statut.inscrit { background: #d4edda; color: #155724; }
                .badge-statut.non_inscrit { background: #f8d7da; color: #721c24; }
                .profile-details { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd; }
                .detail-item .label { font-size: 0.65rem; color: #6c757d; text-transform: uppercase; }
                .detail-item .value { font-size: 0.85rem; font-weight: 500; color: #1a202c; }
                .widget { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
                .widget-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
                .widget-header h5 { margin: 0; font-size: 0.9rem; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                th { background: #f0f0f0; padding: 6px 8px; text-align: left; border: 1px solid #ddd; }
                td { padding: 6px 8px; border: 1px solid #ddd; }
                .badge-annee { padding: 2px 8px; border-radius: 4px; font-size: 10px; background: #d6edff; color: #004085; }
                .badge { padding: 2px 8px; border-radius: 4px; font-size: 10px; }
                .badge-success { background: #d4edda; color: #155724; }
                .badge-primary { background: #d6edff; color: #004085; }
                .empty-state { text-align: center; padding: 15px; color: #6c757d; }
                .text-center { text-align: center; }
                @media print { .btn-actions, .no-print { display: none; } }
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