<?php
// caissier/paiements/voir.php - Voir les détails d'un paiement
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
$idPaiement = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idPaiement <= 0) {
    header('Location: index.php');
    exit();
}

// Récupérer les informations du paiement (sans la table frais qui n'existe pas)
$stmt = $db->prepare("
    SELECT 
        p.idPaiement,
        p.idInscription,
        p.idFrais,
        p.mois,
        p.montantPaye,
        p.datePaiement,
        p.idCaissier,
        p.numeroRecu,
        e.matricule, 
        e.nom, 
        e.prenom, 
        e.postNom, 
        e.adresse, 
        e.telephone,
        c.nomClasse, 
        n.nomNiveau,
        a.libelle as annee,
        u.nom as caissier_nom, 
        u.prenom as caissier_prenom
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    JOIN eleves e ON i.idEleve = e.idEleve
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN niveaux n ON c.idNiveau = n.idNiveau
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    JOIN utilisateurs u ON p.idCaissier = u.idUtilisateur
    WHERE p.idPaiement = :id
");
$stmt->execute(['id' => $idPaiement]);
$paiement = $stmt->fetch();

if (!$paiement) {
    header('Location: index.php');
    exit();
}

// Récupérer les autres paiements du même élève (même inscription)
$stmt = $db->prepare("
    SELECT 
        p.idPaiement,
        p.idFrais,
        p.mois,
        p.montantPaye,
        p.datePaiement,
        p.numeroRecu,
        f.libelle as frais_libelle
    FROM paiements p
    LEFT JOIN frais f ON p.idFrais = f.idFrais
    WHERE p.idInscription = :inscription 
    AND p.idPaiement != :id
    ORDER BY p.datePaiement DESC
    LIMIT 10
");
$stmt->execute([
    'inscription' => $paiement['idInscription'],
    'id' => $idPaiement
]);
$autres_paiements = $stmt->fetchAll();

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
        gap: 10px;
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

    /* ===== DETAIL CARD ===== */
    .detail-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .detail-card .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .detail-card .card-header h3 {
        margin: 0;
        font-weight: 700;
        font-size: 1.1rem;
    }
    .detail-card .card-header h3 i {
        margin-right: 10px;
    }
    .detail-card .card-header .badge-recu {
        background: rgba(255,255,255,0.2);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        color: white;
        border: 1px solid rgba(255,255,255,0.3);
    }
    .detail-card .card-body {
        padding: 24px;
    }

    /* ===== MISE EN PAGE DES INFOS ===== */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .info-grid .info-item {
        display: flex;
        flex-direction: column;
        padding: 12px 16px;
        background: #f7fafc;
        border-radius: 8px;
        border-left: 3px solid #667eea;
    }
    .info-grid .info-item .label {
        font-size: 0.7rem;
        color: #a0aec0;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .info-grid .info-item .value {
        font-size: 1rem;
        font-weight: 600;
        color: #1a202c;
        margin-top: 2px;
    }
    .info-grid .info-item .value .montant {
        font-size: 1.4rem;
        font-weight: 800;
        color: #48bb78;
    }
    .info-grid .info-item .value .mois-label {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 700;
        background: #ebf8ff;
        color: #2b6cb0;
    }

    /* ===== RECU HEADER ===== */
    .recu-header {
        text-align: center;
        padding: 20px 0 15px;
        border-bottom: 2px dashed #e2e8f0;
        margin-bottom: 20px;
    }
    .recu-header .school-name {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1a202c;
        margin: 0;
    }
    .recu-header .recu-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #667eea;
        margin: 5px 0;
    }
    .recu-header .recu-number {
        font-size: 0.9rem;
        color: #718096;
    }

    /* ===== WIDGETS ===== */
    .widget {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 20px;
        margin-bottom: 20px;
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

    .table-custom {
        font-size: 0.8rem;
        width: 100%;
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
        text-align: left;
    }
    .table-custom tbody td {
        padding: 8px 10px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
    }
    .table-custom tbody tr:hover {
        background: #f7fafc;
    }

    .badge-mois {
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        background: #ebf8ff;
        color: #2b6cb0;
        display: inline-block;
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

    .btn-sm {
        padding: 3px 8px;
        font-size: 0.7rem;
        border-radius: 4px;
        text-decoration: none;
        background: #ebf8ff;
        color: #2b6cb0;
        border: 1px solid #bee3f8;
        transition: 0.3s;
    }
    .btn-sm:hover {
        background: #bee3f8;
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

        .info-grid {
            grid-template-columns: 1fr;
        }
        .detail-card .card-header {
            flex-direction: column;
            text-align: center;
        }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1.1rem; }
        .detail-card .card-body { padding: 16px; }
        .info-grid .info-item { padding: 10px 12px; }
        .info-grid .info-item .value .montant { font-size: 1.2rem; }
        .widget { padding: 14px; }
        .table-custom { font-size: 0.7rem; }
        .table-custom thead th, .table-custom tbody td { padding: 4px 6px; }
        .sidebar-toggle-mobile {
            width: 40px;
            height: 40px;
            font-size: 0.9rem;
            bottom: 15px;
            left: 15px;
        }
        .page-header .btn-print {
            width: 100%;
            justify-content: center;
        }
        .recu-header .school-name { font-size: 1.2rem; }
        .recu-header .recu-title { font-size: 0.95rem; }
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
            <li><a href="index.php" class="active"><i class="fas fa-list"></i> Liste des paiements</a></li>
            <li class="menu-section"><i class="fas fa-users"></i> Élèves</li>
            <li><a href="../eleves/index.php"><i class="fas fa-user-graduate"></i> Liste des élèves</a></li>
            <li><a href="../eleves/situation_financiere.php"><i class="fas fa-chart-pie"></i> Situation financière</a></li>
            <li class="menu-section"><i class="fas fa-file-invoice"></i> Reçus</li>
            <li><a href="../recus/historique.php"><i class="fas fa-history"></i> Historique des reçus</a></li>
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
                <i class="fas fa-receipt"></i>
                Détails du paiement
            </h2>
            <div class="header-right">
                <button class="btn-print" onclick="imprimer()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <!-- ===== DÉTAILS ===== -->
        <div class="detail-card" id="detailCard">
            <div class="card-header">
                <h3>
                    <i class="fas fa-file-invoice"></i>
                    Reçu n° <?php echo htmlspecialchars($paiement['numeroRecu'] ?? 'Non généré'); ?>
                </h3>
                <span class="badge-recu">
                    <i class="fas fa-calendar-alt me-1"></i>
                    <?php echo date('d/m/Y H:i', strtotime($paiement['datePaiement'])); ?>
                </span>
            </div>
            <div class="card-body">
                <!-- ===== EN-TÊTE REÇU ===== -->
                <div class="recu-header">
                    <h1 class="school-name">🏫 GestionScolaire</h1>
                    <div class="recu-title">REÇU DE PAIEMENT OFFICIEL</div>
                    <div class="recu-number">
                        N° <strong><?php echo htmlspecialchars($paiement['numeroRecu'] ?? 'Non généré'); ?></strong>
                    </div>
                </div>

                <!-- ===== INFOS PAIEMENT ===== -->
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label"><i class="fas fa-user-graduate me-1"></i> Élève</span>
                        <span class="value">
                            <?php echo htmlspecialchars(trim($paiement['nom'] . ' ' . ($paiement['postNom'] ?? '') . ' ' . $paiement['prenom'])); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-id-card me-1"></i> Matricule</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['matricule']); ?></span>
                    </div>
                    <div class="info-item" style="border-left-color: #48bb78;">
                        <span class="label"><i class="fas fa-tag me-1"></i> Type de frais</span>
                        <span class="value">
                            <?php 
                            // Si la colonne idFrais existe, on peut essayer de récupérer le libellé
                            if (!empty($paiement['idFrais'])) {
                                // Récupérer le libellé du frais depuis la base
                                try {
                                    $stmtFrais = $db->prepare("SELECT libelle FROM frais WHERE idFrais = :id");
                                    $stmtFrais->execute(['id' => $paiement['idFrais']]);
                                    $frais = $stmtFrais->fetch();
                                    echo htmlspecialchars($frais['libelle'] ?? 'Frais de scolarité');
                                } catch (PDOException $e) {
                                    echo 'Frais de scolarité';
                                }
                            } else {
                                echo 'Frais de scolarité';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="info-item" style="border-left-color: #ed8936;">
                        <span class="label"><i class="fas fa-calendar-alt me-1"></i> Mois concerné</span>
                        <span class="value">
                            <?php if (!empty($paiement['mois'])): ?>
                                <span class="mois-label">
                                    📅 <?php echo htmlspecialchars($paiement['mois']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item" style="border-left-color: #667eea;">
                        <span class="label"><i class="fas fa-chalkboard me-1"></i> Classe</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['nomClasse'] . ' (' . $paiement['nomNiveau'] . ')'); ?></span>
                    </div>
                    <div class="info-item" style="border-left-color: #667eea;">
                        <span class="label"><i class="fas fa-calendar-alt me-1"></i> Année scolaire</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['annee']); ?></span>
                    </div>
                    <div class="info-item" style="border-left-color: #48bb78;">
                        <span class="label"><i class="fas fa-dollar-sign me-1"></i> Montant payé</span>
                        <span class="value">
                            <span class="montant">$<?php echo number_format($paiement['montantPaye'], 2); ?></span>
                        </span>
                    </div>
                    <div class="info-item" style="border-left-color: #fc8181;">
                        <span class="label"><i class="fas fa-user-cog me-1"></i> Caissier</span>
                        <span class="value"><?php echo htmlspecialchars(trim(($paiement['caissier_nom'] ?? '') . ' ' . ($paiement['caissier_prenom'] ?? ''))); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-phone me-1"></i> Téléphone</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['telephone'] ?? '-'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-home me-1"></i> Adresse</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['adresse'] ?? '-'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== AUTRES PAIEMENTS DU MÊME ÉLÈVE ===== -->
        <div class="widget">
            <div class="widget-header">
                <h5>
                    <i class="fas fa-history"></i>
                    Historique des paiements - 
                    <?php echo htmlspecialchars(trim($paiement['nom'] . ' ' . $paiement['prenom'])); ?>
                </h5>
                <a href="../eleves/situation_financiere.php?id=<?php echo $paiement['idInscription']; ?>" class="widget-action">
                    Voir tout <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>N° Reçu</th>
                            <th>Frais</th>
                            <th>Mois</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($autres_paiements)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun autre paiement trouvé pour cet élève</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($autres_paiements as $p): ?>
                                <tr>
                                    <td>
                                        <span style="background:#ebf8ff;color:#2b6cb0;padding:2px 8px;border-radius:4px;font-size:0.7rem;">
                                            <?php echo htmlspecialchars($p['numeroRecu'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['frais_libelle'] ?? 'Frais de scolarité'); ?></td>
                                    <td>
                                        <?php if (!empty($p['mois'])): ?>
                                            <span class="badge-mois">📅 <?php echo htmlspecialchars($p['mois']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>$<?php echo number_format($p['montantPaye'], 2); ?></strong></td>
                                    <td><small><?php echo date('d/m/Y', strtotime($p['datePaiement'])); ?></small></td>
                                    <td>
                                        <a href="voir.php?id=<?php echo $p['idPaiement']; ?>" class="btn-sm">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
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
    // ===== IMPRIMER =====
    function imprimer() {
        const contenu = document.getElementById('detailCard').innerHTML;
        const styles = `
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; background: white; }
                .card-header { background: #667eea; color: white; padding: 15px 20px; border-radius: 8px 8px 0 0; }
                .card-body { padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; }
                .recu-header { text-align: center; padding: 15px 0; border-bottom: 2px dashed #ddd; margin-bottom: 15px; }
                .recu-header .school-name { font-size: 1.5rem; font-weight: 800; color: #1a202c; margin: 0; }
                .recu-header .recu-title { font-size: 1.1rem; font-weight: 700; color: #667eea; }
                .recu-header .recu-number { font-size: 0.9rem; color: #6c757d; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
                .info-item { padding: 10px 14px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #667eea; }
                .info-item .label { font-size: 0.65rem; color: #6c757d; text-transform: uppercase; }
                .info-item .value { font-size: 0.95rem; font-weight: 600; color: #1a202c; }
                .info-item .value .montant { font-size: 1.3rem; font-weight: 800; color: #28a745; }
                .info-item .value .mois-label { display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: 700; background: #d6edff; color: #004085; }
                .badge-mois { padding: 2px 8px; border-radius: 4px; font-size: 10px; background: #d6edff; color: #004085; }
                .text-muted { color: #6c757d; }
                .btn-actions, .no-print { display: none; }
                @media print {
                    .btn-actions, .no-print { display: none; }
                    .sidebar-caissier, .sidebar-overlay, .sidebar-toggle-mobile { display: none !important; }
                    .main-content { max-height: none !important; }
                }
            </style>
        `;
        const imprimerWindow = window.open('', '_blank');
        imprimerWindow.document.write(styles + contenu);
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