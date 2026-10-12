<?php
// caissier/profil/index.php - Profil du caissier (Lecture seule)
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Caissier') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Mon Profil - Caissier';
$db = db();

// ===== RÉCUPÉRER LES INFORMATIONS DE L'UTILISATEUR =====
$stmt = $db->prepare("
    SELECT u.*, r.nomRole 
    FROM utilisateurs u
    LEFT JOIN roles r ON u.idRole = r.idRole
    WHERE u.idUtilisateur = :id
");
$stmt->execute(['id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../../login.php');
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

// ===== STATISTIQUES DU CAISSIER =====
// Nombre total de paiements effectués
$stmt = $db->prepare("SELECT COUNT(*) as total FROM paiements WHERE idCaissier = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$total_paiements = $stmt->fetch()['total'];

// Montant total encaissé
$stmt = $db->prepare("SELECT SUM(montantPaye) as total FROM paiements WHERE idCaissier = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$montant_total = $stmt->fetch()['total'] ?? 0;

// Paiements du jour
$stmt = $db->prepare("
    SELECT COUNT(*) as total, SUM(montantPaye) as montant 
    FROM paiements 
    WHERE idCaissier = :id AND DATE(datePaiement) = CURDATE()
");
$stmt->execute(['id' => $_SESSION['user_id']]);
$paiements_jour = $stmt->fetch();
$nb_paiements_jour = $paiements_jour['total'] ?? 0;
$montant_jour = $paiements_jour['montant'] ?? 0;

include __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ===== STYLES ===== */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: #f0f4f8;
        font-size: 14px;
    }

    .app-layout { display: flex; min-height: 100vh; }

    /* ===== SIDEBAR ===== */
    .sidebar-caissier {
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
    .sidebar-caissier::-webkit-scrollbar { width: 3px; }
    .sidebar-caissier::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }

    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 16px;
        margin-bottom: 16px;
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

    /* ===== MAIN CONTENT ===== */
    .main-content {
        flex: 1;
        padding: 25px 30px;
        background: #f0f4f8;
        min-height: 100vh;
        width: 100%;
    }

    /* ===== HEADER ===== */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 25px;
        background: white;
        padding: 18px 24px;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
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
    .page-header small {
        color: #718096;
        font-size: 0.75rem;
        display: block;
        font-weight: 400;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white !important;
        padding: 10px 30px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
        color: white !important;
    }

    /* ===== PROFIL ===== */
    .profile-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 25px;
    }

    /* ===== CARTE PROFIL ===== */
    .profile-card {
        background: white;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        padding: 25px;
        text-align: center;
        height: fit-content;
    }
    .profile-card .avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: white;
        font-weight: 700;
        margin: 0 auto 15px;
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.25);
    }
    .profile-card .name {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1a202c;
    }
    .profile-card .role {
        color: #718096;
        font-size: 0.85rem;
        margin-top: 2px;
    }
    .profile-card .username {
        color: #667eea;
        font-weight: 600;
        font-size: 0.9rem;
        margin-top: 5px;
    }
    .profile-card .divider {
        border: none;
        border-top: 1px solid #edf2f7;
        margin: 18px 0;
    }

    /* ===== STATS PROFIL ===== */
    .profile-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
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

    /* ===== INFO PROFIL (Lecture seule) ===== */
    .info-container {
        background: white;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        padding: 25px 30px;
    }
    .info-container .info-title {
        font-weight: 700;
        color: #2d3748;
        font-size: 1rem;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f0f2f5;
    }
    .info-container .info-title i {
        color: #667eea;
        margin-right: 8px;
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
    .info-group .value .text-muted {
        color: #a0aec0;
    }
    .info-group .value i {
        color: #667eea;
        margin-right: 6px;
    }

    .info-note {
        background: #f7fafc;
        padding: 12px 16px;
        border-radius: 8px;
        margin-top: 15px;
        border-left: 3px solid #667eea;
        font-size: 0.85rem;
        color: #718096;
    }
    .info-note i {
        color: #667eea;
        margin-right: 6px;
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
            width: 260px;
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
        }
        .page-header {
            flex-direction: column;
            text-align: center;
        }
        .profile-container {
            grid-template-columns: 1fr;
        }
        .info-group {
            flex-direction: column;
        }
        .info-group .label {
            width: 100%;
            margin-bottom: 4px;
        }
        .profile-card .avatar {
            width: 100px;
            height: 100px;
            font-size: 2.5rem;
        }
    }

    @media (max-width: 480px) {
        .main-content { padding: 10px !important; }
        .info-container { padding: 18px; }
        .page-header h2 { font-size: 1rem; }
        .page-header small { font-size: 0.65rem; }
        .profile-stats {
            grid-template-columns: 1fr 1fr;
        }
        .profile-card .avatar {
            width: 80px;
            height: 80px;
            font-size: 2rem;
        }
    }

    @media (min-width: 992px) {
        .sidebar-toggle-mobile { display: none !important; }
        .sidebar-overlay { display: none !important; }
        .sidebar-close { display: none !important; }
    }
</style>

<!-- ============================================================ -->
<!-- OVERLAY ET TOGGLE -->
<!-- ============================================================ -->
<button class="sidebar-toggle-mobile" id="sidebarToggle" title="Menu">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ============================================================ -->
<!-- LAYOUT PRINCIPAL -->
<!-- ============================================================ -->
<div class="app-layout">

    <!-- ===== SIDEBAR CAISSIER ===== -->
    <div class="sidebar-caissier" id="sidebarCaissier">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class="fas fa-coins"></i></div>
            <span>GestionScolaire</span>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>

        <ul class="sidebar-menu">
            <!-- Tableau de bord -->
            <li><a href="../dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </a></li>

            <!-- Paiements -->
            <li class="menu-section"><i class="fas fa-money-bill-wave"></i> Paiements</li>
            <li><a href="../paiements/index.php">
                <i class="fas fa-list"></i> Liste des paiements
            </a></li>

            <!-- Élèves -->
            <li class="menu-section"><i class="fas fa-users"></i> Élèves</li>
            <li><a href="../eleves/index.php">
                <i class="fas fa-user-graduate"></i> Liste des élèves
            </a></li>
            <li><a href="../eleves/situation_financiere.php">
                <i class="fas fa-chart-pie"></i> Situation financière
            </a></li>

            <!-- Reçus -->
            <li class="menu-section"><i class="fas fa-file-invoice"></i> Reçus</li>
            <li><a href="../recus/generer.php">
                <i class="fas fa-file-invoice"></i> Générer un reçu
            </a></li>
            <li><a href="../recus/historique.php">
                <i class="fas fa-history"></i> Historique des reçus
            </a></li>

            <!-- Mon profil -->
            <li class="menu-section"><i class="fas fa-user-circle"></i> Compte</li>
            <li><a href="index.php" class="active">
                <i class="fas fa-user-circle"></i> Mon profil
            </a></li>
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

        <!-- ===== HEADER ===== -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-user-circle"></i>
                    Mon Profil
                    <small>Consultez vos informations personnelles</small>
                </h2>
            </div>
            <div>
                <a href="../dashboard.php" class="btn-primary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <!-- ===== PROFIL ===== -->
        <div class="profile-container">

            <!-- ===== CARTE PROFIL ===== -->
            <div class="profile-card">
                <div class="avatar">
                    <?php echo strtoupper(substr($user['prenom'] ?? 'A', 0, 1) . substr($user['nom'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="name"><?php echo htmlspecialchars($user['nom'] . ' ' . ($user['postNom'] ?? '') . ' ' . $user['prenom']); ?></div>
                <div class="role"><?php echo htmlspecialchars($user['nomRole'] ?? 'Caissier'); ?></div>
                <div class="username">@<?php echo htmlspecialchars($user['username']); ?></div>

                <hr class="divider">

                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="number"><?php echo number_format($total_paiements); ?></div>
                        <div class="label">Total paiements</div>
                    </div>
                    <div class="stat-item">
                        <div class="number"><?php echo number_format($montant_total, 0); ?> $</div>
                        <div class="label">Montant total</div>
                    </div>
                    <div class="stat-item" style="background:#ebf8ff;">
                        <div class="number" style="color:#2b6cb0;"><?php echo $nb_paiements_jour; ?></div>
                        <div class="label">Aujourd'hui</div>
                    </div>
                    <div class="stat-item" style="background:#ebf8ff;">
                        <div class="number" style="color:#2b6cb0;"><?php echo number_format($montant_jour, 0); ?> $</div>
                        <div class="label">Montant jour</div>
                    </div>
                </div>

                <hr class="divider">

                <div style="text-align:left; font-size:0.85rem; color:#718096;">
                    <p><i class="fas fa-phone me-2" style="color:#667eea; width:20px;"></i> <?php echo htmlspecialchars($user['telephone'] ?? 'Non renseigné'); ?></p>
                    <p><i class="fas fa-envelope me-2" style="color:#667eea; width:20px;"></i> <?php echo htmlspecialchars($user['email'] ?? 'Non renseigné'); ?></p>
                    <p><i class="fas fa-calendar-alt me-2" style="color:#667eea; width:20px;"></i> Membre depuis le <?php echo date('d/m/Y', strtotime($user['dateCreation'] ?? 'now')); ?></p>
                </div>
            </div>

            <!-- ===== INFORMATIONS (Lecture seule) ===== -->
            <div class="info-container">
                <div class="info-title">
                    <i class="fas fa-info-circle"></i> Mes informations
                </div>

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
                        <span class="badge" style="background:#667eea; color:white; padding:3px 12px; border-radius:20px; font-size:0.75rem;">
                            <?php echo htmlspecialchars($user['nomRole'] ?? 'Caissier'); ?>
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

                <div class="info-note">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note :</strong> Pour modifier vos informations personnelles, veuillez contacter l'administrateur.
                </div>
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
        const sidebar = document.getElementById('sidebarCaissier');
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

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
    });
</script>
</body>
</html>