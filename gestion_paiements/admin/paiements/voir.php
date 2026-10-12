<?php
// admin/paiements/voir.php - Voir les détails d'un paiement
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();
$idPaiement = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idPaiement <= 0) {
    header('Location: index.php');
    exit();
}

// Récupérer les informations du paiement avec l'ID de l'élève
$stmt = $db->prepare("
    SELECT p.*, 
           e.idEleve, e.matricule, e.nom, e.prenom, e.postNom, e.adresse, e.telephone,
           f.libelle as frais_libelle, f.montant as frais_montant,
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
    WHERE p.idPaiement = :id
");
$stmt->execute(['id' => $idPaiement]);
$paiement = $stmt->fetch();

if (!$paiement) {
    header('Location: index.php');
    exit();
}

// Récupérer les autres paiements du même élève
$stmt = $db->prepare("
    SELECT p.*, f.libelle as frais_libelle
    FROM paiements p
    JOIN frais f ON p.idFrais = f.idFrais
    WHERE p.idInscription = :inscription AND p.idPaiement != :id
    ORDER BY p.datePaiement DESC
    LIMIT 5
");
$stmt->execute([
    'inscription' => $paiement['idInscription'],
    'id' => $idPaiement
]);
$autres_paiements = $stmt->fetchAll();

$page_title = 'Détails du paiement - Administrateur';

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
    .page-header .btn-edit {
        background: #fefcbf;
        color: #975a16;
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
    .page-header .btn-edit:hover {
        background: #f6e05e;
        color: #975a16;
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
    .detail-card .card-body .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }
    .detail-card .card-body .info-item {
        display: flex;
        flex-direction: column;
    }
    .detail-card .card-body .info-item .label {
        font-size: 0.7rem;
        color: #a0aec0;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .detail-card .card-body .info-item .value {
        font-size: 0.95rem;
        font-weight: 500;
        color: #1a202c;
        margin-top: 2px;
    }
    .detail-card .card-body .info-item .value strong {
        font-weight: 700;
        color: #667eea;
    }
    .detail-card .card-body .info-item .value .montant {
        font-size: 1.3rem;
        font-weight: 800;
        color: #48bb78;
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
    }
    .table-custom tbody td {
        padding: 8px 10px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
    }
    .table-custom tbody tr:hover {
        background: #f7fafc;
    }
    .table-custom tbody tr:last-child td {
        border-bottom: none;
    }

    .badge-mois {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        background: #f7fafc;
        color: #4a5568;
    }

    .badge-recu {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        background: #ebf8ff;
        color: #2b6cb0;
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

        .detail-card .card-body .info-grid {
            grid-template-columns: 1fr 1fr;
        }
        .detail-card .card-header {
            flex-direction: column;
            text-align: center;
        }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1rem; }
        .detail-card .card-body { padding: 16px; }
        .detail-card .card-body .info-grid { grid-template-columns: 1fr; }
        .detail-card .card-body .info-item .value .montant { font-size: 1.1rem; }
        .widget { padding: 14px; }
        .table-custom { font-size: 0.7rem; }
        .table-custom thead th, .table-custom tbody td { padding: 4px 6px; }
        .sidebar-toggle-mobile {
            width: 42px;
            height: 42px;
            font-size: 0.9rem;
            bottom: 15px;
            left: 15px;
        }
        .page-header .btn-back,
        .page-header .btn-edit {
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
                <i class="fas fa-receipt"></i>
                Détails du paiement
            </h2>
            <div class="header-right">
                <a href="modifier.php?id=<?php echo $idPaiement; ?>" class="btn-edit">
                    <i class="fas fa-edit"></i> Modifier
                </a>
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
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label"><i class="fas fa-user-graduate me-1"></i> Élève</span>
                        <span class="value">
                            <?php echo htmlspecialchars($paiement['nom'] . ' ' . ($paiement['postNom'] ?? '') . ' ' . $paiement['prenom']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-id-card me-1"></i> Matricule</span>
                        <span class="value"><strong><?php echo htmlspecialchars($paiement['matricule']); ?></strong></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-tag me-1"></i> Frais</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['frais_libelle']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-calendar-alt me-1"></i> Mois</span>
                        <span class="value">
                            <?php if (!empty($paiement['mois'])): ?>
                                <span class="badge-mois"><?php echo htmlspecialchars($paiement['mois']); ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-chalkboard me-1"></i> Classe</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['nomClasse'] . ' (' . $paiement['nomNiveau'] . ')'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-calendar-alt me-1"></i> Année scolaire</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['annee']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-dollar-sign me-1"></i> Montant payé</span>
                        <span class="value">
                            <span class="montant">$<?php echo number_format($paiement['montantPaye'], 2); ?></span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="label"><i class="fas fa-user-cog me-1"></i> Caissier</span>
                        <span class="value"><?php echo htmlspecialchars($paiement['caissier_nom'] . ' ' . $paiement['caissier_prenom']); ?></span>
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
                    Autres paiements du même élève
                </h5>
                <?php if (isset($paiement['idEleve'])): ?>
                    <a href="../eleves/voir.php?id=<?php echo $paiement['idEleve']; ?>" class="widget-action">
                        Voir l'élève <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-custom">
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
                                        <p>Aucun autre paiement trouvé</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($autres_paiements as $p): ?>
                                <tr>
                                    <td>
                                        <span class="badge-recu">
                                            <?php echo htmlspecialchars($p['numeroRecu'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['frais_libelle']); ?></td>
                                    <td>
                                        <?php if (!empty($p['mois'])): ?>
                                            <span class="badge-mois"><?php echo htmlspecialchars($p['mois']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>$<?php echo number_format($p['montantPaye'], 2); ?></strong></td>
                                    <td><small><?php echo date('d/m/Y', strtotime($p['datePaiement'])); ?></small></td>
                                    <td>
                                        <a href="voir.php?id=<?php echo $p['idPaiement']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
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