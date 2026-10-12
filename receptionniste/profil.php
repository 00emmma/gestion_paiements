<?php
// receptionniste/profil.php - Profil du réceptionniste (Lecture seule)
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Réceptionniste') {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';

$page_title = 'Mon Profil - Réceptionniste';
$db = db();

// ===== RÉCUPÉRER L'ID DE L'UTILISATEUR =====
$idUtilisateur = $_SESSION['user_id'];

// ===== RÉCUPÉRER LES INFORMATIONS DE L'UTILISATEUR =====
$stmt = $db->prepare("
    SELECT u.*, r.nomRole 
    FROM utilisateurs u
    LEFT JOIN roles r ON u.idRole = r.idRole
    WHERE u.idUtilisateur = :id
");
$stmt->execute(['id' => $idUtilisateur]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../login.php');
    exit();
}

// ===== RÉCUPÉRER LE NOM DE L'ÉCOLE =====
$nom_ecole = 'Gestion Scolaire';
try {
    $stmt = $db->query("SELECT valeur FROM parametres WHERE nom = 'nom_ecole'");
    $row = $stmt->fetch();
    if ($row) {
        $nom_ecole = $row['valeur'];
    }
} catch (PDOException $e) {}

// ===== COMPTER LES NOTIFICATIONS NON LUES =====
$nbNonLues = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE idUtilisateur = :id AND est_lu = 0");
    $stmt->execute(['id' => $idUtilisateur]);
    $nbNonLues = $stmt->fetch()['total'];
} catch (PDOException $e) {}

// ===== STATISTIQUES DU RÉCEPTIONNISTE =====
$total_inscriptions = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM inscriptions WHERE idReceptionniste = :id");
    $stmt->execute(['id' => $idUtilisateur]);
    $total_inscriptions = $stmt->fetch()['total'];
} catch (PDOException $e) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM inscriptions WHERE idCaissier = :id");
        $stmt->execute(['id' => $idUtilisateur]);
        $total_inscriptions = $stmt->fetch()['total'];
    } catch (PDOException $e2) {
        $total_inscriptions = 0;
    }
}

// Inscriptions du jour
$inscriptions_jour = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM inscriptions 
        WHERE idReceptionniste = :id AND DATE(dateInscription) = CURDATE()
    ");
    $stmt->execute(['id' => $idUtilisateur]);
    $inscriptions_jour = $stmt->fetch()['total'];
} catch (PDOException $e) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM inscriptions 
            WHERE idCaissier = :id AND DATE(dateInscription) = CURDATE()
        ");
        $stmt->execute(['id' => $idUtilisateur]);
        $inscriptions_jour = $stmt->fetch()['total'];
    } catch (PDOException $e2) {
        $inscriptions_jour = 0;
    }
}

// Inscriptions du mois
$inscriptions_mois = 0;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM inscriptions 
        WHERE idReceptionniste = :id 
        AND MONTH(dateInscription) = MONTH(CURDATE()) 
        AND YEAR(dateInscription) = YEAR(CURDATE())
    ");
    $stmt->execute(['id' => $idUtilisateur]);
    $inscriptions_mois = $stmt->fetch()['total'];
} catch (PDOException $e) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM inscriptions 
            WHERE idCaissier = :id 
            AND MONTH(dateInscription) = MONTH(CURDATE()) 
            AND YEAR(dateInscription) = YEAR(CURDATE())
        ");
        $stmt->execute(['id' => $idUtilisateur]);
        $inscriptions_mois = $stmt->fetch()['total'];
    } catch (PDOException $e2) {
        $inscriptions_mois = 0;
    }
}

// Total élèves inscrits
$stmt = $db->query("SELECT COUNT(*) as total FROM eleves");
$total_eleves = $stmt->fetch()['total'];

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* ===== STYLES ===== */
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
    .sidebar-logo i { font-size: 1.3rem; color: #4299e1; }
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

    /* ===== CONTENU PRINCIPAL ===== */
    .main-content {
        flex: 1;
        padding: 25px 30px;
        background: #f8fafc;
        min-height: 100vh;
        max-width: 800px;
        margin: 0 auto;
        width: 100%;
    }

    /* ===== EN-TÊTE ===== */
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

    .btn-back {
        background: #edf2f7;
        color: #4a5568 !important;
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
    }
    .btn-back:hover {
        background: #e2e8f0;
        color: #2d3748 !important;
    }

    /* ===== PROFIL ===== */
    .profile-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        padding: 25px 30px;
    }
    .profile-card .avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: white;
        margin: 0 auto 15px;
    }
    .section-title {
        font-weight: 700;
        color: #4299e1;
        font-size: 0.9rem;
        margin: 20px 0 12px 0;
    }
    .section-title i { margin-right: 8px; }
    .section-divider {
        margin: 0 0 15px 0;
        border: 0;
        border-top: 1px solid #edf2f7;
    }

    .info-group {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid #f0f2f5;
    }
    .info-group:last-child {
        border-bottom: none;
    }
    .info-group .label {
        width: 150px;
        font-weight: 600;
        font-size: 0.85rem;
        color: #2d3748;
        flex-shrink: 0;
    }
    .info-group .value {
        font-size: 0.9rem;
        color: #1a202c;
        flex: 1;
    }
    .info-group .value i {
        color: #4299e1;
        margin-right: 6px;
    }
    .info-group .value .text-muted {
        color: #a0aec0;
    }

    .badge-role {
        background: #4299e1;
        color: white;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        display: inline-block;
    }

    .info-note {
        background: #f7fafc;
        padding: 12px 16px;
        border-radius: 8px;
        margin-top: 20px;
        border-left: 3px solid #4299e1;
        font-size: 0.85rem;
        color: #718096;
    }
    .info-note i {
        color: #4299e1;
        margin-right: 6px;
    }

    .profile-stats {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 10px;
        margin: 15px 0;
    }
    .profile-stats .stat-item {
        background: #f7fafc;
        padding: 10px;
        border-radius: 8px;
        text-align: center;
    }
    .profile-stats .stat-item .number {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a202c;
    }
    .profile-stats .stat-item .label {
        font-size: 0.65rem;
        color: #718096;
    }
    .stat-item.blue {
        background: #ebf8ff;
    }
    .stat-item.blue .number {
        color: #2b6cb0;
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
        .header-bar { flex-direction: column; text-align: center; padding: 12px 16px; }
        .header-bar h2 { font-size: 1rem; }
        .header-actions { width: 100%; justify-content: center; }
        .profile-card { padding: 20px; }
        .info-group {
            flex-direction: column;
        }
        .info-group .label {
            width: 100%;
            margin-bottom: 4px;
        }
        .profile-stats {
            grid-template-columns: 1fr 1fr;
        }
        .profile-card .avatar {
            width: 60px;
            height: 60px;
            font-size: 2rem;
        }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .profile-card { padding: 15px; }
        .header-bar h2 { font-size: 0.9rem; }
        .header-bar small { font-size: 0.7rem; }
        .profile-stats {
            grid-template-columns: 1fr 1fr;
        }
        .profile-card .avatar {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }
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
            <li class="menu-section"><i class="fas fa-cog"></i> Navigation</li>
            <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i> Tableau de bord</a></li>
            <li><a href="eleves/index.php"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="classes/index.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="annees/index.php"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
            <li><a href="historique.php"><i class="fas fa-history"></i> Historique</a></li>
            <li><a href="profil.php" class="active"><i class="fas fa-user"></i> Mon profil</a></li>
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
                <h2><i class="fas fa-user"></i> Mon Profil</h2>
                <small>Consultez vos informations personnelles</small>
            </div>
            <div class="header-actions">
                <div class="notification-wrapper">
                    <a href="notifications.php" class="btn-notification" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($nbNonLues > 0): ?>
                            <span class="notification-badge"><?php echo $nbNonLues; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <a href="dashboard.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <!-- ===== PROFIL ===== -->
        <div class="profile-card">
            <div class="avatar">
                <?php echo strtoupper(substr($user['prenom'] ?? 'A', 0, 1) . substr($user['nom'] ?? 'A', 0, 1)); ?>
            </div>

            <!-- ===== STATISTIQUES ===== -->
            <div class="profile-stats">
                <div class="stat-item blue">
                    <div class="number"><?php echo $total_inscriptions; ?></div>
                    <div class="label">Total inscriptions</div>
                </div>
                <div class="stat-item blue">
                    <div class="number"><?php echo $inscriptions_jour; ?></div>
                    <div class="label">Aujourd'hui</div>
                </div>
                <div class="stat-item blue">
                    <div class="number"><?php echo $inscriptions_mois; ?></div>
                    <div class="label">Ce mois</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php echo number_format($total_eleves); ?></div>
                    <div class="label">Total élèves</div>
                </div>
            </div>

            <hr class="section-divider">

            <!-- ===== INFORMATIONS ===== -->
            <div class="section-title"><i class="fas fa-info-circle"></i> Mes informations</div>
            <hr class="section-divider">

            <div class="info-group">
                <div class="label"><i class="fas fa-user"></i> Nom</div>
                <div class="value"><?php echo htmlspecialchars($user['nom']); ?></div>
            </div>

            <div class="info-group">
                <div class="label"><i class="fas fa-user"></i> Post-Nom</div>
                <div class="value"><?php echo htmlspecialchars($user['postNom'] ?? 'Non renseigné'); ?></div>
            </div>

            <div class="info-group">
                <div class="label"><i class="fas fa-user"></i> Prénom</div>
                <div class="value"><?php echo htmlspecialchars($user['prenom']); ?></div>
            </div>

            <div class="info-group">
                <div class="label"><i class="fas fa-user-tag"></i> Nom d'utilisateur</div>
                <div class="value"><strong><?php echo htmlspecialchars($user['username']); ?></strong></div>
            </div>

            <div class="info-group">
                <div class="label"><i class="fas fa-user-tag"></i> Rôle</div>
                <div class="value">
                    <span class="badge-role">
                        <?php echo htmlspecialchars($user['nomRole'] ?? 'Réceptionniste'); ?>
                    </span>
                </div>
            </div>

            <div class="info-group">
                <div class="label"><i class="fas fa-phone"></i> Téléphone</div>
                <div class="value">
                    <?php if (!empty($user['telephone'])): ?>
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['telephone']); ?>
                    <?php else: ?>
                        <span class="text-muted">Non renseigné</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-group">
                <div class="label"><i class="fas fa-envelope"></i> Email</div>
                <div class="value">
                    <?php if (!empty($user['email'])): ?>
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                    <?php else: ?>
                        <span class="text-muted">Non renseigné</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-group">
                <div class="label"><i class="fas fa-calendar-alt"></i> Date création</div>
                <div class="value"><?php echo date('d/m/Y à H:i', strtotime($user['dateCreation'] ?? 'now')); ?></div>
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
});
</script>
</body>
</html>