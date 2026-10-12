<?php
// receptionniste/eleves/voir.php - Voir les détails d'un élève
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Réceptionniste') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Détails de l\'Élève';
$db = db();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$id) {
    header('Location: index.php');
    exit();
}

// ===== RÉCUPÉRER L'ÉLÈVE =====
$stmt = $db->prepare("SELECT * FROM eleves WHERE idEleve = :id");
$stmt->execute(['id' => $id]);
$eleve = $stmt->fetch();

if (!$eleve) {
    $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Élève non trouvé.'];
    header('Location: index.php');
    exit();
}

// ===== RÉCUPÉRER LES INSCRIPTIONS =====
$stmt = $db->prepare("
    SELECT i.*, c.nomClasse, n.nomNiveau, a.libelle as annee
    FROM inscriptions i
    JOIN classes c ON i.idClasse = c.idClasse
    LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE i.idEleve = :idEleve
    ORDER BY i.idAnnee DESC
");
$stmt->execute(['idEleve' => $id]);
$inscriptions = $stmt->fetchAll();

// ===== RÉCUPÉRER L'INSCRIPTION ACTIVE =====
$inscriptionActuelle = null;
foreach ($inscriptions as $ins) {
    if ($ins['statut'] === 'Actif') {
        $inscriptionActuelle = $ins;
        break;
    }
}

// ===== RÉCUPÉRER LES PAIEMENTS =====
$stmt = $db->prepare("
    SELECT p.*, f.libelle as frais_libelle
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    JOIN frais f ON p.idFrais = f.idFrais
    WHERE i.idEleve = :idEleve
    ORDER BY p.datePaiement DESC
    LIMIT 10
");
$stmt->execute(['idEleve' => $id]);
$paiements = $stmt->fetchAll();

// ===== STATISTIQUES =====
$totalPaiements = 0;
$nbPaiements = count($paiements);
foreach ($paiements as $p) {
    $totalPaiements += $p['montantPaye'];
}

// ===== NOTIFICATIONS NON LUES =====
$nbNonLues = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE idUtilisateur = :id AND est_lu = 0");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $nbNonLues = $stmt->fetch()['total'];
} catch (PDOException $e) {}

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
.header-bar .btn-back {
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.8rem;
    color: #4a5568;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.header-bar .btn-back:hover { background: #edf2f7; border-color: #cbd5e0; }
.header-bar .btn-edit {
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
.header-bar .btn-edit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(66,153,225,0.35);
    color: white !important;
    text-decoration: none;
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

/* ===== PROFIL ===== */
.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
.profile-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    padding: 20px 24px;
}
.profile-card .card-title {
    font-weight: 700;
    color: #2d3748;
    font-size: 0.9rem;
    padding-bottom: 12px;
    margin-bottom: 15px;
    border-bottom: 2px solid #f0f2f5;
}
.profile-card .card-title i { color: #4299e1; margin-right: 8px; }
.profile-card .info-row {
    display: flex;
    padding: 6px 0;
    border-bottom: 1px solid #f0f2f5;
    font-size: 0.85rem;
}
.profile-card .info-row:last-child { border-bottom: none; }
.profile-card .info-label {
    width: 140px;
    font-weight: 600;
    color: #4a5568;
    font-size: 0.75rem;
    flex-shrink: 0;
}
.profile-card .info-value {
    flex: 1;
    color: #1a202c;
    font-size: 0.85rem;
}
.badge-statut {
    padding: 2px 12px;
    border-radius: 16px;
    font-size: 0.6rem;
    font-weight: 600;
    display: inline-block;
}
.badge-statut.actif { background: #f0fff4; color: #276749; }
.badge-statut.inactif { background: #fff5f5; color: #9b2c2c; }

/* ===== TABLEAU ===== */
.table-box {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
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

.badge-paiement {
    padding: 2px 12px;
    border-radius: 16px;
    font-size: 0.6rem;
    font-weight: 600;
    display: inline-block;
}
.badge-paiement.success { background: #f0fff4; color: #276749; }

.empty-state {
    text-align: center;
    padding: 30px 20px;
    color: #a0aec0;
}
.empty-state i { font-size: 2rem; opacity: 0.3; margin-bottom: 8px; display: block; }
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
    .header-bar .btn-group { width: 100%; }
    .header-bar .btn-back, .header-bar .btn-edit { flex: 1; justify-content: center; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .stat-card { padding: 12px 14px; }
    .stat-card .number { font-size: 1.2rem; }
    .profile-grid { grid-template-columns: 1fr; }
    .profile-card .info-row { flex-direction: column; }
    .profile-card .info-label { width: 100%; margin-bottom: 2px; }
    .table { font-size: 0.75rem; }
    .table th, .table td { padding: 6px 10px; }
}

@media (max-width: 576px) {
    .main-content { padding: 10px !important; }
    .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-card { padding: 10px 12px; }
    .stat-card .number { font-size: 1rem; }
    .stat-card .label { font-size: 0.65rem; }
    .header-bar h2 { font-size: 0.9rem; }
    .header-bar small { font-size: 0.7rem; }
    .profile-card { padding: 15px; }
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
    <li><a href="../dashboard.php"><i class="fas fa-chart-pie"></i> Tableau de bord</a></li>
    <li><a href="index.php"><i class="fas fa-users"></i> Élèves</a></li>
    <li><a href="../inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
    <li><a href="../classes/index.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
    <li><a href="../annees/index.php"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
    <li><a href="../historique.php"><i class="fas fa-history"></i> Historique</a></li>
    <li><a href="../profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
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
                <h2><i class="fas fa-user-graduate"></i> Détails de l'élève</h2>
                <small>Matricule : <strong><?php echo htmlspecialchars($eleve['matricule']); ?></strong></small>
            </div>
            <div class="btn-group d-flex gap-2">
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <a href="modifier.php?id=<?php echo $eleve['idEleve']; ?>" class="btn-edit">
                    <i class="fas fa-edit"></i> Modifier
                </a>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $nbPaiements; ?></div>
                <div class="label">Total paiements</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo number_format($totalPaiements, 0, ',', ' '); ?> $</div>
                <div class="label">Total payé</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo count($inscriptions); ?></div>
                <div class="label">Inscriptions</div>
            </div>
            <div class="stat-card purple">
                <div class="number">
                    <?php 
                        $actif = array_filter($inscriptions, function($i) { return $i['statut'] === 'Actif'; });
                        echo count($actif) > 0 ? '✅' : '❌';
                    ?>
                </div>
                <div class="label">Inscription active</div>
            </div>
        </div>

        <!-- ===== PROFIL ===== -->
        <div class="profile-grid">
            <!-- Informations personnelles -->
            <div class="profile-card">
                <div class="card-title"><i class="fas fa-user"></i> Informations personnelles</div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-id-card me-1"></i> Matricule</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($eleve['matricule']); ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user me-1"></i> Nom</div>
                    <div class="info-value"><?php echo htmlspecialchars($eleve['nom']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user me-1"></i> Post-Nom</div>
                    <div class="info-value"><?php echo htmlspecialchars($eleve['postNom'] ?? '-'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user me-1"></i> Prénom</div>
                    <div class="info-value"><?php echo htmlspecialchars($eleve['prenom']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-venus-mars me-1"></i> Sexe</div>
                    <div class="info-value">
                        <?php if ($eleve['sexe'] === 'M'): ?>
                            <span class="badge bg-info" style="font-size:0.7rem;">Masculin</span>
                        <?php elseif ($eleve['sexe'] === 'F'): ?>
                            <span class="badge" style="background:#d53f8c; color:white; font-size:0.7rem;">Féminin</span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-calendar-alt me-1"></i> Date naissance</div>
                    <div class="info-value"><?php echo $eleve['dateNaissance'] ? date('d/m/Y', strtotime($eleve['dateNaissance'])) : '-'; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-map-pin me-1"></i> Lieu naissance</div>
                    <div class="info-value"><?php echo htmlspecialchars($eleve['lieuNaissance'] ?? '-'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-globe-africa me-1"></i> Nationalité</div>
                    <div class="info-value"><?php echo htmlspecialchars($eleve['nationalite'] ?? '-'); ?></div>
                </div>
            </div>

            <!-- Contact et scolarité -->
            <div class="profile-card">
                <div class="card-title"><i class="fas fa-address-book"></i> Contact & Scolarité</div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-phone me-1"></i> Téléphone</div>
                    <div class="info-value"><?php echo htmlspecialchars($eleve['telephone'] ?? '-'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-map-marker-alt me-1"></i> Adresse</div>
                    <div class="info-value"><?php echo htmlspecialchars($eleve['adresse'] ?? '-'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user-tie me-1"></i> Nom du père</div>
                    <div class="info-value"><?php echo htmlspecialchars($eleve['nomPere'] ?? '-'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-user-tie me-1"></i> Nom de la mère</div>
                    <div class="info-value"><?php echo htmlspecialchars($eleve['nomMere'] ?? '-'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-graduation-cap me-1"></i> Classe actuelle</div>
                    <div class="info-value">
                        <?php if ($inscriptionActuelle): ?>
                            <span class="badge-statut actif">
                                <i class="fas fa-check-circle me-1" style="font-size:8px;"></i>
                                <?php echo htmlspecialchars($inscriptionActuelle['nomClasse']); ?>
                                <span style="font-weight:400; color:#718096; font-size:0.7rem;">
                                    (<?php echo htmlspecialchars($inscriptionActuelle['nomNiveau']); ?>)
                                </span>
                            </span>
                            <span style="font-size:0.7rem; color:#718096; margin-left:6px;">
                                <?php echo htmlspecialchars($inscriptionActuelle['annee']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#ed8936; font-size:0.8rem;">
                                <i class="fas fa-exclamation-triangle me-1"></i> Non inscrit
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-clock me-1"></i> Compte créé le</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($eleve['dateCreation'] ?? 'now')); ?></div>
                </div>
            </div>
        </div>

        <!-- ===== HISTORIQUE DES INSCRIPTIONS ===== -->
        <div class="table-box" style="margin-bottom:24px;">
            <div class="table-header">
                <span class="title"><i class="fas fa-book-open"></i> Historique des inscriptions</span>
                <span class="text-muted" style="font-size:0.7rem;">
                    <?php echo count($inscriptions); ?> inscription(s)
                </span>
            </div>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Année</th>
                            <th>Classe</th>
                            <th>Niveau</th>
                            <th>Date d'inscription</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inscriptions)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-book"></i>
                                        <p>Aucune inscription</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inscriptions as $ins): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ins['annee']); ?></td>
                                    <td><?php echo htmlspecialchars($ins['nomClasse']); ?></td>
                                    <td><?php echo htmlspecialchars($ins['nomNiveau']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($ins['dateInscription'])); ?></td>
                                    <td>
                                        <span class="badge-statut <?php echo strtolower($ins['statut']); ?>">
                                            <?php echo $ins['statut']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== DERNIERS PAIEMENTS ===== -->
        <div class="table-box">
            <div class="table-header">
                <span class="title"><i class="fas fa-money-bill-wave"></i> Derniers paiements</span>
                <span class="text-muted" style="font-size:0.7rem;">
                    <?php echo $nbPaiements; ?> paiement(s)
                </span>
            </div>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Reçu</th>
                            <th>Frais</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paiements)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-receipt"></i>
                                        <p>Aucun paiement</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paiements as $p): ?>
                                <tr>
                                    <td><span class="badge bg-primary" style="font-size:0.65rem;"><?php echo $p['numeroRecu']; ?></span></td>
                                    <td><?php echo htmlspecialchars($p['frais_libelle']); ?></td>
                                    <td class="fw-bold text-success"><?php echo number_format($p['montantPaye'], 0, ',', ' '); ?> $</td>
                                    <td><?php echo date('d/m/Y', strtotime($p['datePaiement'])); ?></td>
                                    <td>
                                        <span class="badge-paiement success">
                                            <i class="fas fa-check-circle me-1" style="font-size:8px;"></i>
                                            Payé
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