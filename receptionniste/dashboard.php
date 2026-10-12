<?php
// receptionniste/dashboard.php - Tableau de bord Réceptionniste
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Réceptionniste') {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Notification.php';

$page_title = 'Tableau de Bord - Réceptionniste';
$db = db();
$notificationModel = new NotificationModel();

// Récupérer l'ID de l'utilisateur connecté
$idUtilisateur = $_SESSION['user_id'];

// ===== STATISTIQUES =====

// Total des élèves
$stmt = $db->query("SELECT COUNT(*) as total FROM eleves");
$totalEleves = $stmt->fetch()['total'];

// Inscriptions de l'année en cours
$stmt = $db->query("
    SELECT COUNT(*) as total FROM inscriptions 
    WHERE idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
");
$totalInscrits = $stmt->fetch()['total'];

// Total des classes
$stmt = $db->query("SELECT COUNT(*) as total FROM classes");
$totalClasses = $stmt->fetch()['total'];

// Élèves non inscrits cette année
$stmt = $db->query("
    SELECT COUNT(*) as total FROM eleves e
    WHERE e.idEleve NOT IN (
        SELECT idEleve FROM inscriptions 
        WHERE idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
    )
");
$nonInscrits = $stmt->fetch()['total'];

// ===== NOTIFICATIONS =====
$notifications = $notificationModel->getAll($idUtilisateur, 10);
$nbNonLues = $notificationModel->countNonLues($idUtilisateur);

// ===== DERNIERS INSCRITS =====
$stmt = $db->query("
    SELECT e.*, c.nomClasse, i.dateInscription
    FROM inscriptions i
    JOIN eleves e ON i.idEleve = e.idEleve
    JOIN classes c ON i.idClasse = c.idClasse
    WHERE i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
    ORDER BY i.dateInscription DESC
    LIMIT 5
");
$derniersInscrits = $stmt->fetchAll();

// ===== RÉPARTITION PAR CLASSE =====
$stmt = $db->query("
    SELECT c.nomClasse, COUNT(i.idInscription) as total
    FROM classes c
    LEFT JOIN inscriptions i ON c.idClasse = i.idClasse 
        AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
    GROUP BY c.idClasse
    ORDER BY c.nomClasse ASC
");
$repartitionClasses = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ===== STYLES EXISTANTS ===== */
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

/* ===== OVERLAY ===== */
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

/* ===== TOGGLE ===== */
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

/* ===== CONTENU PRINCIPAL ===== */
.main-content {
    flex: 1;
    padding: 25px 30px;
    background: #f8fafc;
    min-height: 100vh;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
}

/* ===== EN-TÊTE ===== */
.header-bar {
    background: white;
    padding: 16px 24px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
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

/* ===== HEADER ACTIONS ===== */
.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-bar .btn-action {
    background: linear-gradient(135deg, #4299e1, #3182ce);
    color: white !important;
    padding: 8px 20px;
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
.header-bar .btn-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(66,153,225,0.35);
    color: white !important;
    text-decoration: none;
}

/* ===== NOTIFICATIONS ===== */
.notification-wrapper {
    position: relative;
}

.btn-notification {
    background: transparent;
    border: none;
    font-size: 1.2rem;
    color: #4a5568;
    cursor: pointer;
    padding: 8px 10px;
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
    top: 2px;
    right: 2px;
    background: #e53e3e;
    color: white;
    font-size: 0.6rem;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 50%;
    min-width: 18px;
    text-align: center;
    border: 2px solid white;
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 340px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.12);
    border: 1px solid #edf2f7;
    display: none;
    z-index: 1000;
    margin-top: 8px;
    overflow: hidden;
}
.notification-dropdown.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.notification-header {
    padding: 12px 16px;
    border-bottom: 1px solid #edf2f7;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.notification-header span {
    font-weight: 600;
    font-size: 0.85rem;
    color: #1a202c;
}
.notification-header a {
    font-size: 0.7rem;
    color: #4299e1;
    text-decoration: none;
    cursor: pointer;
}
.notification-header a:hover {
    text-decoration: underline;
}

.notification-list {
    max-height: 300px;
    overflow-y: auto;
}
.notification-list::-webkit-scrollbar { width: 4px; }
.notification-list::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 4px; }

.notification-item {
    display: flex;
    gap: 12px;
    padding: 10px 16px;
    border-bottom: 1px solid #f0f2f5;
    transition: background 0.3s ease;
    cursor: pointer;
}
.notification-item:hover {
    background: #f7fafc;
}
.notification-item.unread {
    background: #ebf8ff;
    border-left: 3px solid #4299e1;
}
.notification-item.unread .notification-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #4299e1;
    border-radius: 50%;
    margin-right: 6px;
}
.notification-item i {
    font-size: 1rem;
    color: #4299e1;
    margin-top: 2px;
    flex-shrink: 0;
}
.notification-item div p {
    font-size: 0.8rem;
    color: #2d3748;
    margin: 0;
    line-height: 1.4;
}
.notification-item div small {
    font-size: 0.65rem;
    color: #a0aec0;
}
.notification-item .notification-type {
    font-size: 0.55rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #4299e1;
    background: #ebf8ff;
    padding: 1px 8px;
    border-radius: 10px;
    display: inline-block;
    margin-top: 2px;
}

.notification-footer {
    padding: 10px 16px;
    border-top: 1px solid #edf2f7;
    text-align: center;
}
.notification-footer a {
    font-size: 0.8rem;
    color: #4299e1;
    text-decoration: none;
}
.notification-footer a:hover {
    text-decoration: underline;
}

.notification-empty {
    padding: 30px 20px;
    text-align: center;
    color: #a0aec0;
}
.notification-empty i {
    font-size: 2rem;
    display: block;
    margin-bottom: 10px;
    opacity: 0.3;
}
.notification-empty p {
    font-size: 0.85rem;
    margin: 0;
}

/* ===== STATISTIQUES ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    padding: 16px 20px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    text-align: center;
    border-left: 3px solid #4299e1;
}
.stat-card .number { font-size: 1.5rem; font-weight: 700; color: #1a202c; }
.stat-card .label { font-size: 0.75rem; color: #718096; margin-top: 4px; }
.stat-card.green { border-color: #48bb78; }
.stat-card.orange { border-color: #ed8936; }
.stat-card.purple { border-color: #9f7aea; }

/* ===== TABLEAU ===== */
.table-box {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
    height: 100%;
}
.table-header {
    padding: 12px 18px;
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
    padding: 10px 14px;
    border-bottom: 2px solid #edf2f7;
    text-align: left;
}
.table td {
    padding: 10px 14px;
    border-bottom: 1px solid #f0f2f5;
    vertical-align: middle;
}
.table tbody tr:hover td { background: #f7fafc; }

/* ===== RÉPARTITION ===== */
.class-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f2f5;
}
.class-item:last-child { border-bottom: none; }
.class-item .name {
    font-weight: 500;
    color: #2d3748;
    font-size: 0.85rem;
    min-width: 100px;
}
.class-item .count {
    background: #ebf8ff;
    color: #2b6cb0;
    padding: 2px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 30px;
    text-align: center;
}
.class-item .bar {
    flex: 1;
    height: 6px;
    background: #edf2f7;
    border-radius: 4px;
    margin: 0 12px;
    overflow: hidden;
}
.class-item .bar .fill {
    height: 100%;
    background: linear-gradient(135deg, #4299e1, #3182ce);
    border-radius: 4px;
    transition: width 1s ease;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #a0aec0;
}
.empty-state i { font-size: 2.5rem; opacity: 0.3; margin-bottom: 10px; display: block; }
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
    .header-bar { flex-direction: column; text-align: center; padding: 14px 18px; }
    .header-bar h2 { font-size: 1rem; }
    .header-bar .btn-action { width: 100%; justify-content: center; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .stat-card { padding: 12px 14px; }
    .stat-card .number { font-size: 1.2rem; }
    .notification-dropdown { right: -20px; width: 300px; }
}

@media (max-width: 576px) {
    .main-content { padding: 10px !important; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-card { padding: 10px 12px; }
    .stat-card .number { font-size: 1rem; }
    .stat-card .label { font-size: 0.65rem; }
    .header-bar h2 { font-size: 0.9rem; }
    .header-bar small { font-size: 0.7rem; }
    .table { font-size: 0.7rem; }
    .table th, .table td { padding: 6px 8px; }
    .class-item .name { font-size: 0.75rem; min-width: 60px; }
    .class-item .count { font-size: 0.65rem; padding: 2px 10px; }
    .header-actions { width: 100%; justify-content: center; }
    .notification-dropdown { width: 280px; right: -40px; }
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
                <a href="dashboard.php" class="active">
                    <i class="fas fa-chart-pie"></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="eleves/index.php">
                    <i class="fas fa-users"></i> Élèves
                </a>
            </li>
            <li>
                <a href="inscriptions/index.php">
                    <i class="fas fa-book-open"></i> Inscriptions
                </a>
            </li>
            <li>
                <a href="classes/index.php">
                    <i class="fas fa-chalkboard"></i> Classes
                </a>
            </li>
            <li>
                <a href="annees/index.php">
                    <i class="fas fa-calendar-alt"></i> Années scolaires
                </a>
            </li>
            <li>
                <a href="historique.php">
                    <i class="fas fa-history"></i> Historique
                </a>
            </li>
            <li>
                <a href="profil.php">
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
                <h2><i class="fas fa-door-open"></i> Tableau de Bord</h2>
                <small>Bienvenue <?php echo $_SESSION['prenom']; ?>, gérez les inscriptions et les élèves</small>
            </div>
            <div class="header-actions">
                <!-- 🔔 Notification -->
                <div class="notification-wrapper">
                    <button class="btn-notification" id="notificationBtn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge"><?php echo $nbNonLues; ?></span>
                    </button>
                    <!-- Dropdown notifications -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <a href="#" id="marquerToutLu">Tout marquer comme lu</a>
                        </div>
                        <div class="notification-list">
                            <?php if (empty($notifications)): ?>
                                <div class="notification-empty">
                                    <i class="fas fa-inbox"></i>
                                    <p>Aucune notification</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <div class="notification-item <?php echo $notif['est_lu'] == 0 ? 'unread' : ''; ?>" 
                                         data-id="<?php echo $notif['idNotification']; ?>"
                                         onclick="marquerLu(<?php echo $notif['idNotification']; ?>, '<?php echo $notif['lien']; ?>')">
                                        <i class="fas <?php 
                                            echo $notif['type'] == 'inscription' ? 'fa-user-plus' : 
                                                ($notif['type'] == 'modification' ? 'fa-edit' : 
                                                ($notif['type'] == 'paiement' ? 'fa-check-circle' : 'fa-bell')); 
                                        ?>"></i>
                                        <div>
                                            <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                            <small>
                                                <span class="notification-type"><?php echo $notif['type']; ?></span>
                                                <?php echo date('d/m/Y H:i', strtotime($notif['dateCreation'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="notification-footer">
                            <a href="notifications.php">Voir toutes les notifications</a>
                        </div>
                    </div>
                </div>
               
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $totalEleves; ?></div>
                <div class="label">Total Élèves</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo $totalInscrits; ?></div>
                <div class="label">Inscrits cette année</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo $nonInscrits; ?></div>
                <div class="label">Non inscrits</div>
            </div>
            <div class="stat-card purple">
                <div class="number"><?php echo $totalClasses; ?></div>
                <div class="label">Classes</div>
            </div>
        </div>

        <!-- ===== ROW 2 ===== -->
        <div class="row g-4 justify-content-center">

            <!-- Derniers inscrits -->
            <div class="col-lg-7">
                <div class="table-box">
                    <div class="table-header">
                        <span class="title"><i class="fas fa-user-plus"></i> Derniers inscrits</span>
                        <span class="text-muted" style="font-size:0.7rem;">
                            <i class="fas fa-calendar-alt me-1"></i> Année en cours
                        </span>
                    </div>
                    <div class="table-scroll">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:20%;">Matricule</th>
                                    <th style="width:40%;">Nom complet</th>
                                    <th style="width:25%;">Classe</th>
                                    <th style="width:15%;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($derniersInscrits)): ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="empty-state">
                                                <i class="fas fa-inbox"></i>
                                                <p>Aucune inscription récente</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($derniersInscrits as $eleve): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($eleve['matricule']); ?></strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($eleve['nom'] . ' ' . ($eleve['postNom'] ?? '') . ' ' . $eleve['prenom']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($eleve['nomClasse']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($eleve['dateInscription'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Répartition par classe -->
            <div class="col-lg-5">
                <div class="table-box">
                    <div class="table-header">
                        <span class="title"><i class="fas fa-chart-bar"></i> Répartition par classe</span>
                    </div>
                    <div style="padding: 16px 18px;">
                        <?php if (empty($repartitionClasses) || array_sum(array_column($repartitionClasses, 'total')) == 0): ?>
                            <div class="empty-state">
                                <i class="fas fa-chart-pie"></i>
                                <p>Aucune inscription pour le moment</p>
                            </div>
                        <?php else: ?>
                            <?php 
                                $maxTotal = !empty($repartitionClasses) ? max(array_column($repartitionClasses, 'total')) : 1;
                                $maxTotal = $maxTotal > 0 ? $maxTotal : 1;
                            ?>
                            <?php foreach ($repartitionClasses as $classe): ?>
                                <div class="class-item">
                                    <span class="name"><?php echo htmlspecialchars($classe['nomClasse']); ?></span>
                                    <div class="bar">
                                        <div class="fill" style="width: <?php echo ($classe['total'] / $maxTotal) * 100; ?>%;"></div>
                                    </div>
                                    <span class="count"><?php echo $classe['total']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php include __DIR__ . '/../includes/footer.php'; ?>
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

    // ===== NOTIFICATIONS TOGGLE =====
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');

    if (notificationBtn) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            // Recharger le compteur si nécessaire
            if (notificationDropdown.classList.contains('show')) {
                // Optionnel : recharger les notifications en AJAX
            }
        });
    }

    // Fermer le dropdown en cliquant ailleurs
    document.addEventListener('click', function(e) {
        if (!notificationBtn?.contains(e.target) && !notificationDropdown?.contains(e.target)) {
            notificationDropdown?.classList.remove('show');
        }
    });

    // ===== MARQUER COMME LU =====
    window.marquerLu = function(id, lien) {
        fetch('marquer_lu.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Rediriger si un lien est fourni
                    if (lien) {
                        window.location.href = lien;
                    } else {
                        location.reload();
                    }
                }
            })
            .catch(() => {
                // Si erreur, rediriger quand même
                if (lien) {
                    window.location.href = lien;
                }
            });
    };

    // ===== TOUT MARQUER COMME LU =====
    document.getElementById('marquerToutLu').addEventListener('click', function(e) {
        e.preventDefault();
        fetch('marquer_tout_lu.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
    });
});
</script>
</body>
</html>