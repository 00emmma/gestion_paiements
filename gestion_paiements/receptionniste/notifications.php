<?php
// receptionniste/notifications.php - Toutes les notifications
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

$page_title = 'Mes Notifications';
$db = db();
$notificationModel = new NotificationModel();

$idUtilisateur = $_SESSION['user_id'];

// Marquer une notification comme lue
if (isset($_GET['marquer_lu']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $notificationModel->marquerCommeLu($id);
    header('Location: notifications.php');
    exit();
}

// Marquer toutes les notifications comme lues
if (isset($_GET['marquer_tout'])) {
    $notificationModel->marquerToutCommeLu($idUtilisateur);
    header('Location: notifications.php');
    exit();
}

// Supprimer une notification
if (isset($_GET['supprimer']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $notificationModel->delete($id);
    header('Location: notifications.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Récupérer les notifications
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE idUtilisateur = :idUtilisateur 
    ORDER BY dateCreation DESC 
    LIMIT :offset, :perPage
");
$stmt->bindValue(':idUtilisateur', $idUtilisateur, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll();

// Compter le total
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE idUtilisateur = :idUtilisateur");
$stmt->execute(['idUtilisateur' => $idUtilisateur]);
$total = $stmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

// Compter les non lues
$nbNonLues = $notificationModel->countNonLues($idUtilisateur);

include __DIR__ . '/../includes/header.php';
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
    max-width: 1200px;
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

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.btn-action {
    padding: 8px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-action.primary {
    background: linear-gradient(135deg, #4299e1, #3182ce);
    color: white;
}
.btn-action.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(66,153,225,0.35);
    color: white;
}
.btn-action.success {
    background: #48bb78;
    color: white;
}
.btn-action.success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(72,187,120,0.35);
    color: white;
}

/* ===== STATISTIQUES ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
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

/* ===== NOTIFICATIONS ===== */
.notification-item {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    padding: 14px 20px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    transition: all 0.3s;
    border-left: 4px solid transparent;
}
.notification-item:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}
.notification-item.unread {
    border-left-color: #4299e1;
    background: #f7fafc;
}
.notification-item .icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.notification-item .icon.inscription { background: #ebf8ff; color: #2b6cb0; }
.notification-item .icon.modification { background: #fefcbf; color: #975a16; }
.notification-item .icon.paiement { background: #f0fff4; color: #276749; }
.notification-item .icon.utilisateur { background: #faf5ff; color: #6b46c1; }
.notification-item .icon.classe { background: #fefcbf; color: #975a16; }

.notification-item .content {
    flex: 1;
    min-width: 200px;
}
.notification-item .content .message {
    font-size: 0.9rem;
    color: #2d3748;
    margin: 0;
}
.notification-item .content .date {
    font-size: 0.7rem;
    color: #a0aec0;
}
.notification-item .content .type {
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #4299e1;
    background: #ebf8ff;
    padding: 1px 10px;
    border-radius: 10px;
    display: inline-block;
    margin-top: 3px;
}

.notification-item .actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}
.notification-item .actions .btn {
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 0.7rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.notification-item .actions .btn-lu {
    background: #ebf8ff;
    color: #2b6cb0;
}
.notification-item .actions .btn-lu:hover {
    background: #bee3f8;
}
.notification-item .actions .btn-supprimer {
    background: #fff5f5;
    color: #c53030;
}
.notification-item .actions .btn-supprimer:hover {
    background: #fed7d7;
}
.notification-item .actions .btn-lien {
    background: #edf2f7;
    color: #4a5568;
}
.notification-item .actions .btn-lien:hover {
    background: #e2e8f0;
}

/* ===== PAGINATION ===== */
.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.pagination .page-link {
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: white;
    color: #4a5568;
    text-decoration: none;
    transition: all 0.3s;
}
.pagination .page-link:hover {
    background: #4299e1;
    color: white;
    border-color: #4299e1;
}
.pagination .page-link.active {
    background: #4299e1;
    color: white;
    border-color: #4299e1;
}

/* ===== EMPTY ===== */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #a0aec0;
}
.empty-state i {
    font-size: 3rem;
    opacity: 0.3;
    margin-bottom: 15px;
    display: block;
}
.empty-state h3 {
    color: #4a5568;
    font-size: 1.1rem;
    margin-bottom: 5px;
}
.empty-state p {
    font-size: 0.9rem;
    margin: 0;
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
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .notification-item { flex-direction: column; align-items: stretch; }
    .notification-item .actions { justify-content: flex-end; }
}

@media (max-width: 576px) {
    .main-content { padding: 10px !important; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-card { padding: 10px 12px; }
    .stat-card .number { font-size: 1rem; }
    .stat-card .label { font-size: 0.6rem; }
    .header-bar h2 { font-size: 0.9rem; }
    .header-bar small { font-size: 0.7rem; }
    .notification-item { padding: 12px 14px; }
    .notification-item .content .message { font-size: 0.8rem; }
    .header-actions { width: 100%; }
    .header-actions .btn-action { flex: 1; justify-content: center; }
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
            <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i> Tableau de bord</a></li>
            <li><a href="eleves/index.php"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="classes/index.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="annees/index.php"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
            <li><a href="historique.php"><i class="fas fa-history"></i> Historique</a></li>
            <li><a href="profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <div>
                    <strong><?php echo $_SESSION['nom'] . ' ' . $_SESSION['prenom']; ?></strong>
                    <span><?php echo $_SESSION['role']; ?></span>
                </div>
            </div>
            <a href="../logout.php" class="btn-logout" title="Déconnexion">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <!-- ===== CONTENU PRINCIPAL ===== -->
    <div class="main-content">

        <!-- ===== EN-TÊTE ===== -->
        <div class="header-bar">
            <div>
                <h2><i class="fas fa-bell"></i> Mes Notifications</h2>
                <small>Consultez toutes vos notifications</small>
            </div>
            <div class="header-actions">
                <?php if ($nbNonLues > 0): ?>
                    <a href="?marquer_tout=1" class="btn-action success" onclick="return confirm('Marquer toutes les notifications comme lues ?')">
                        <i class="fas fa-check-double"></i> Tout marquer comme lu
                    </a>
                <?php endif; ?>
              
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $total; ?></div>
                <div class="label">Total notifications</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo $nbNonLues; ?></div>
                <div class="label">Non lues</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo $total - $nbNonLues; ?></div>
                <div class="label">Lues</div>
            </div>
        </div>

        <!-- ===== LISTE DES NOTIFICATIONS ===== -->
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Aucune notification</h3>
                <p>Vous n'avez pas encore de notifications.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo $notif['est_lu'] == 0 ? 'unread' : ''; ?>">
                    <div class="icon <?php echo $notif['type']; ?>">
                        <i class="fas <?php 
                            echo $notif['type'] == 'inscription' ? 'fa-user-plus' : 
                                ($notif['type'] == 'modification' ? 'fa-edit' : 
                                ($notif['type'] == 'paiement' ? 'fa-check-circle' : 
                                ($notif['type'] == 'utilisateur' ? 'fa-user-cog' : 'fa-bell'))); 
                        ?>"></i>
                    </div>
                    
                    <div class="content">
                        <p class="message"><?php echo htmlspecialchars($notif['message']); ?></p>
                        <div>
                            <span class="type"><?php echo $notif['type']; ?></span>
                            <span class="date"><?php echo date('d/m/Y H:i', strtotime($notif['dateCreation'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <?php if ($notif['est_lu'] == 0): ?>
                            <a href="?marquer_lu=1&id=<?php echo $notif['idNotification']; ?>" class="btn btn-lu" title="Marquer comme lu">
                                <i class="fas fa-check"></i> Lu
                            </a>
                        <?php endif; ?>
                        <?php if ($notif['lien']): ?>
                            <a href="<?php echo $notif['lien']; ?>" class="btn btn-lien" title="Voir">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                        <?php endif; ?>
                        <a href="?supprimer=1&id=<?php echo $notif['idNotification']; ?>" class="btn btn-supprimer" onclick="return confirm('Supprimer cette notification ?')" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- ===== PAGINATION ===== -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

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
});
</script>
</body>
</html>