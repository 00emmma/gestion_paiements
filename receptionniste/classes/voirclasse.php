<?php
// receptionniste/classes/voirclasse.php - Voir une classe avec ses élèves
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Réceptionniste') {
    header('Location: ../../index.php');
    exit();
}

$idClasse = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idClasse <= 0) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Notification.php';

$db = db();
$notificationModel = new NotificationModel();

// Récupérer les notifications non lues
$nbNonLues = $notificationModel->countNonLues($_SESSION['user_id']);

// Récupérer les informations de la classe
$sql = "
    SELECT c.*, n.nomNiveau 
    FROM classes c
    JOIN niveaux n ON c.idNiveau = n.idNiveau
    WHERE c.idClasse = :id
";
$stmt = $db->prepare($sql);
$stmt->execute(['id' => $idClasse]);
$classe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$classe) {
    header('Location: index.php');
    exit();
}

// Récupérer les élèves de la classe
$sql = "
    SELECT e.*, i.idInscription, i.dateInscription, i.statut
    FROM eleves e
    JOIN inscriptions i ON e.idEleve = i.idEleve
    WHERE i.idClasse = :idClasse
    AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
    ORDER BY e.nom, e.prenom
";
$stmt = $db->prepare($sql);
$stmt->execute(['idClasse' => $idClasse]);
$eleves = $stmt->fetchAll();

// Compter le nombre d'élèves
$totalEleves = count($eleves);

$page_title = 'Détails de la classe - ' . htmlspecialchars($classe['nomClasse']);
include __DIR__ . '/../../includes/header.php';
?>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    background: #f8fafc;
    font-size: 14px;
}

/* ===== LAYOUT ===== */
.app-layout {
    display: flex;
    min-height: 100vh;
}

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
.sidebar-logo i { font-size: 1.3rem; color: #4299e1; }
.sidebar-logo span { font-size: 1rem; font-weight: 700; color: white; flex: 1; }

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    flex: 1;
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
.sidebar-menu li a:hover { background: rgba(255,255,255,0.08); color: white; text-decoration: none; }
.sidebar-menu li a.active {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(66,153,225,0.3);
}
.sidebar-menu li a i { width: 18px; text-align: center; font-size: 0.85rem; }

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
.sidebar-footer .user-info i { font-size: 1.4rem; color: #4299e1; flex-shrink: 0; }
.sidebar-footer .user-info div { display: flex; flex-direction: column; overflow: hidden; }
.sidebar-footer .user-info div strong { color: white; font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sidebar-footer .user-info div span { color: #a0aec0; font-size: 0.65rem; }
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
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 15px rgba(66,153,225,0.4);
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: none;
    align-items: center;
    justify-content: center;
}
.sidebar-toggle-mobile:hover { transform: scale(1.05); box-shadow: 0 8px 25px rgba(66,153,225,0.5); }
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

/* ===== MAIN CONTENT ===== */
.main-content {
    flex: 1;
    padding: 25px 30px;
    background: #f8fafc;
    min-height: 100vh;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
}

/* ===== HEADER ===== */
.header-bar {
    background: white;
    padding: 14px 24px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.header-bar h2 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1a202c;
    margin: 0;
}
.header-bar h2 i { color: #4299e1; margin-right: 10px; }
.header-bar small {
    color: #718096;
    font-size: 0.8rem;
    display: block;
    font-weight: 400;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.notification-wrapper {
    position: relative;
}
.btn-notification {
    background: transparent;
    border: none;
    font-size: 1.2rem;
    color: #4a5568;
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 8px;
    transition: all 0.3s ease;
    position: relative;
}
.btn-notification:hover {
    background: #f7fafc;
    color: #4299e1;
}
.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #e53e3e;
    color: white;
    font-size: 0.55rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 50%;
    min-width: 18px;
    text-align: center;
    border: 2px solid white;
}

.btn-print {
    background: #48bb78;
    color: white !important;
    padding: 8px 18px;
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
.btn-print:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(72,187,120,0.35);
    color: white !important;
}

.btn-pdf {
    background: #e53e3e;
    color: white !important;
    padding: 8px 18px;
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
.btn-pdf:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(229,62,62,0.35);
    color: white !important;
}

.btn-back {
    background: #a0aec0;
    color: white !important;
    padding: 8px 18px;
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
    background: #718096;
    transform: translateY(-2px);
    color: white !important;
}

/* ===== STATS ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    padding: 14px 18px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    text-align: center;
    border-left: 3px solid #4299e1;
}
.stat-card .number { font-size: 1.4rem; font-weight: 700; color: #1a202c; }
.stat-card .label { font-size: 0.7rem; color: #718096; margin-top: 3px; }
.stat-card.green { border-color: #48bb78; }
.stat-card.orange { border-color: #ed8936; }
.stat-card.purple { border-color: #9f7aea; }

/* ===== TABLE ===== */
.table-box {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
}
.table-header {
    padding: 10px 18px;
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
.table-header .title i { color: #4299e1; margin-right: 6px; }

.table-scroll {
    overflow-x: auto;
}
.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table th {
    background: #f7fafc;
    color: #4a5568;
    font-weight: 600;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding: 8px 14px;
    border-bottom: 2px solid #edf2f7;
    text-align: left;
}
.table td {
    padding: 8px 14px;
    border-bottom: 1px solid #f0f2f5;
    vertical-align: middle;
}
.table tbody tr:hover td { background: #f7fafc; }

.badge-sexe {
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 0.6rem;
    font-weight: 600;
    display: inline-block;
}
.badge-sexe.M { background: #dbeafe; color: #1e40af; }
.badge-sexe.F { background: #fce4ec; color: #c62828; }

.badge-statut {
    padding: 2px 12px;
    border-radius: 16px;
    font-size: 0.6rem;
    font-weight: 600;
    display: inline-block;
}
.badge-statut.actif { background: #f0fff4; color: #276749; }
.badge-statut.termine { background: #edf2f7; color: #4a5568; }

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #a0aec0;
}
.empty-state i { font-size: 2.5rem; opacity: 0.3; margin-bottom: 10px; display: block; }
.empty-state h5 { color: #4a5568; font-weight: 600; font-size: 0.9rem; margin-bottom: 4px; }
.empty-state p { font-size: 0.85rem; margin: 0; }

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
        width: 280px;
        z-index: 1000;
        height: 100vh;
        border-radius: 0;
    }
    .sidebar-admin.open { transform: translateX(0); }
    .sidebar-close { display: block !important; }
    .sidebar-toggle-mobile { display: flex !important; }
    .sidebar-overlay.active { display: block !important; opacity: 1; }
    .main-content { padding: 15px !important; }
    .header-bar { flex-direction: column; text-align: center; padding: 12px 16px; }
    .header-bar h2 { font-size: 1rem; }
    .header-actions { width: 100%; justify-content: center; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .stat-card { padding: 10px 12px; }
    .stat-card .number { font-size: 1.1rem; }
    .table { font-size: 0.75rem; }
    .table th, .table td { padding: 6px 10px; }
}

@media (max-width: 576px) {
    .main-content { padding: 10px !important; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-card { padding: 8px 10px; }
    .stat-card .number { font-size: 0.95rem; }
    .stat-card .label { font-size: 0.6rem; }
    .header-bar h2 { font-size: 0.9rem; }
    .header-bar small { font-size: 0.7rem; }
    .table { font-size: 0.7rem; }
    .table th, .table td { padding: 4px 6px; }
}

@media (min-width: 992px) {
    .sidebar-toggle-mobile { display: none !important; }
    .sidebar-overlay { display: none !important; }
    .sidebar-close { display: none !important; }
}
</style>

<!-- ===== TOGGLE ET OVERLAY ===== -->
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
        <div class="sidebar-logo">
            <i class="fas fa-school"></i>
            <span>GestionScolaire</span>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="../dashboard.php">
                    <i class="fas fa-chart-pie"></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="../eleves/index.php">
                    <i class="fas fa-users"></i> Élèves
                </a>
            </li>
            <li>
                <a href="../inscriptions/index.php">
                    <i class="fas fa-book-open"></i> Inscriptions
                </a>
            </li>
            <li>
                <a href="index.php" class="active">
                    <i class="fas fa-chalkboard"></i> Classes
                </a>
            </li>
            <li>
                <a href="../annees/index.php">
                    <i class="fas fa-calendar-alt"></i> Années scolaires
                </a>
            </li>
            <li>
                <a href="../historique.php">
                    <i class="fas fa-history"></i> Historique
                </a>
            </li>
            <li>
                <a href="../profil.php">
                    <i class="fas fa-user"></i> Mon profil
                </a>
            </li>
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

    <!-- ===== CONTENU PRINCIPAL ===== -->
    <div class="main-content">

        <!-- ===== EN-TÊTE ===== -->
        <div class="header-bar">
            <div>
                <h2>
                    <i class="fas fa-chalkboard"></i> 
                    Classe : <?php echo htmlspecialchars($classe['nomClasse']); ?>
                    <small style="display:block; font-size:0.75rem; color:#718096; font-weight:400; margin-top:2px;">
                        <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($classe['nomNiveau']); ?>
                    </small>
                </h2>
            </div>
            <div class="header-actions">
                <div class="notification-wrapper">
                    <a href="../notifications.php" class="btn-notification" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($nbNonLues > 0): ?>
                            <span class="notification-badge"><?php echo $nbNonLues; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <!-- Bouton PDF -->
                <a href="pdf_classe.php?id=<?php echo $idClasse; ?>" class="btn-pdf" target="_blank">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Imprimer
                </button>
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $totalEleves; ?></div>
                <div class="label">Total Élèves</div>
            </div>
            <div class="stat-card green">
                <div class="number">
                    <?php 
                        $garcons = array_filter($eleves, function($e) { return $e['sexe'] === 'M'; });
                        echo count($garcons);
                    ?>
                </div>
                <div class="label">Garçons</div>
            </div>
            <div class="stat-card purple">
                <div class="number">
                    <?php 
                        $filles = array_filter($eleves, function($e) { return $e['sexe'] === 'F'; });
                        echo count($filles);
                    ?>
                </div>
                <div class="label">Filles</div>
            </div>
            <div class="stat-card orange">
                <div class="number">
                    <?php 
                        $actifs = array_filter($eleves, function($e) { 
                            return ($e['statut'] ?? 'Actif') === 'Actif'; 
                        });
                        echo count($actifs);
                    ?>
                </div>
                <div class="label">Actifs</div>
            </div>
        </div>

        <!-- ===== TABLEAU ===== -->
        <div class="table-box">
            <div class="table-header">
                <span class="title"><i class="fas fa-users"></i> Liste des élèves</span>
                <span class="text-muted" style="font-size:0.7rem;">
                    <?php echo $totalEleves; ?> élève(s)
                </span>
            </div>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Matricule</th>
                            <th>Nom complet</th>
                            <th>Sexe</th>
                            <th>Téléphone</th>
                            <th style="text-align:center;">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eleves)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h5>Aucun élève</h5>
                                        <p>Aucun élève n'est inscrit dans cette classe pour l'année en cours.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eleves as $i => $eleve): 
                                $nomComplet = trim($eleve['nom'] . ' ' . ($eleve['postNom'] ?? '') . ' ' . $eleve['prenom']);
                            ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($eleve['matricule']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($nomComplet); ?></td>
                                    <td>
                                        <span class="badge-sexe <?php echo $eleve['sexe']; ?>">
                                            <?php echo $eleve['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($eleve['telephone'] ?? '-'); ?></td>
                                    <td style="text-align:center;">
                                        <span class="badge-statut <?php echo strtolower($eleve['statut'] ?? 'actif'); ?>">
                                            <?php echo $eleve['statut'] ?? 'Actif'; ?>
                                        </span>
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

<!-- ===== SCRIPT ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebarAdmin');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991) closeSidebar();
            });
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
});
</script>
</body>
</html>