<?php
// includes/sidebar_admin.php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<div class="sidebar-admin">
    
    <!-- Logo -->
    <div class="sidebar-logo">
        <i class="fas fa-school"></i>
        <span>GestionScolaire</span>
    </div>
    
    <!-- Menu -->
    <ul class="sidebar-menu">
        <li>
            <a href="/admin/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Tableau de bord
            </a>
        </li>
        <li>
            <a href="/admin/eleves/index.php" class="<?php echo $current_dir == 'eleves' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Élèves
            </a>
        </li>
        <li>
            <a href="/admin/inscriptions/index.php" class="<?php echo $current_dir == 'inscriptions' ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i> Inscriptions
            </a>
        </li>
        <li>
            <a href="/admin/paiements/index.php" class="<?php echo $current_dir == 'paiements' ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i> Paiements
            </a>
        </li>
        <li>
            <a href="/admin/frais/index.php" class="<?php echo $current_dir == 'frais' ? 'active' : ''; ?>">
                <i class="fas fa-tag"></i> Frais
            </a>
        </li>
        <li>
            <a href="/admin/classes/index.php" class="<?php echo $current_dir == 'classes' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard"></i> Classes
            </a>
        </li>
        <li>
            <a href="/admin/annees/index.php" class="<?php echo $current_dir == 'annees' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Années scolaires
            </a>
        </li>
        <li>
            <a href="/admin/utilisateurs/index.php" class="<?php echo $current_dir == 'utilisateurs' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i> Utilisateurs
            </a>
        </li>
        <li>
            <a href="/admin/rapports/index.php" class="<?php echo $current_dir == 'rapports' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Rapports
            </a>
        </li>
        <li>
            <a href="/admin/journal/index.php" class="<?php echo $current_dir == 'journal' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Journal
            </a>
        </li>
    </ul>
    
    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <div>
                <strong><?php echo $_SESSION['nom'] . ' ' . $_SESSION['prenom']; ?></strong>
                <span><?php echo $_SESSION['role']; ?></span>
            </div>
        </div>
        <a href="/admin/logout.php" class="btn-logout" title="Déconnexion">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>

<!-- ============================================ -->
<!-- STYLES DE LA SIDEBAR -->
<!-- ============================================ -->
<style>
/* ===== SIDEBAR ===== */
.sidebar-admin {
    width: 240px;
    min-height: 100vh;
    background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
    padding: 18px 12px;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 1000;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease-in-out;
}

.sidebar-admin::-webkit-scrollbar {
    width: 3px;
}
.sidebar-admin::-webkit-scrollbar-thumb {
    background: #4a5568;
    border-radius: 4px;
}

/* ===== LOGO ===== */
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
}

/* ===== MENU ===== */
.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    flex: 1;
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

/* ===== FOOTER ===== */
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

/* ===== OVERLAY ===== */
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

/* ===== TOGGLE BUTTON ===== */
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
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: none;
    align-items: center;
    justify-content: center;
}
.sidebar-toggle-mobile:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
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
.sidebar-close:hover {
    color: white;
}

@media (max-width: 991px) {
    .sidebar-admin {
        transform: translateX(-100%);
        width: 260px;
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

<button class="sidebar-toggle-mobile" id="sidebarToggle" title="Menu">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar-admin');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.querySelector('.sidebar-close');

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