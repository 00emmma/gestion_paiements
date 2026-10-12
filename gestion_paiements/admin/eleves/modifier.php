<?php
// admin/eleves/modifier.php - Modifier un élève
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Eleve.php';

$db = db();
$eleveModel = new Eleve();

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

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_eleve') {
    $nom = trim($_POST['nom'] ?? '');
    $postNom = trim($_POST['postNom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $sexe = $_POST['sexe'] ?? '';
    $dateNaissance = $_POST['dateNaissance'] ?? '';
    $lieuNaissance = trim($_POST['lieuNaissance'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $nationalite = trim($_POST['nationalite'] ?? '');
    $nomPere = trim($_POST['nomPere'] ?? '');
    $nomMere = trim($_POST['nomMere'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $motDePasse = trim($_POST['motDePasse'] ?? '');
    
    if (empty($nom) || empty($prenom) || empty($sexe)) {
        $error = 'Le nom, le prénom et le sexe sont obligatoires.';
    } else {
        $data = [
            'nom' => $nom,
            'postNom' => $postNom,
            'prenom' => $prenom,
            'sexe' => $sexe,
            'dateNaissance' => $dateNaissance ?: null,
            'lieuNaissance' => $lieuNaissance,
            'province' => $province,
            'nationalite' => $nationalite,
            'nomPere' => $nomPere,
            'nomMere' => $nomMere,
            'adresse' => $adresse,
            'telephone' => $telephone
        ];
        
        // Si un nouveau mot de passe est fourni
        if (!empty($motDePasse)) {
            $data['motDePasse'] = password_hash($motDePasse, PASSWORD_DEFAULT);
        }
        
        if ($eleveModel->update($idEleve, $data)) {
            $_SESSION['flash'] = [
                'type' => 'success',
                'text' => '✅ Élève modifié avec succès !'
            ];
            header('Location: index.php');
            exit();
        } else {
            $error = 'Erreur lors de la modification de l\'élève.';
        }
    }
}

$page_title = 'Modifier un élève - Administrateur';

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
        color: #ed8936;
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

    /* ===== FORMULAIRE ===== */
    .form-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 24px;
    }
    .form-container .form-group {
        margin-bottom: 16px;
    }
    .form-container .form-group label {
        display: block;
        font-weight: 600;
        font-size: 0.85rem;
        color: #2d3748;
        margin-bottom: 5px;
    }
    .form-container .form-group .required {
        color: #e53e3e;
        margin-left: 2px;
    }
    .form-container .form-group .form-control,
    .form-container .form-group .form-select {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.9rem;
        transition: 0.3s;
        background: white;
    }
    .form-container .form-group .form-control:focus,
    .form-container .form-group .form-select:focus {
        border-color: #ed8936;
        box-shadow: 0 0 0 3px rgba(237, 137, 54, 0.1);
        outline: none;
    }
    .form-container .form-group .form-control[readonly] {
        background: #f7fafc;
        cursor: not-allowed;
    }

    /* ===== ALERTES ===== */
    .alert-box {
        padding: 12px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.5s ease;
    }
    .alert-box.danger {
        background: #fff5f5;
        color: #9b2c2c;
        border: 1px solid #fed7d7;
    }
    .alert-box.success {
        background: #f0fff4;
        color: #276749;
        border: 1px solid #c6f6d5;
    }
    .alert-box i { font-size: 1.1rem; }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ===== INFO ELEVE ===== */
    .info-card {
        background: #f7fafc;
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 20px;
        border-left: 4px solid #ed8936;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .info-card .info-text {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .info-card .info-text .matricule {
        font-weight: 700;
        color: #667eea;
        font-size: 1.1rem;
    }
    .info-card .info-text .nom {
        font-weight: 600;
        color: #1a202c;
    }
    .info-card .badge-statut {
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .info-card .badge-statut.actif { background: #f0fff4; color: #276749; }
    .info-card .badge-statut.inactif { background: #fff5f5; color: #9b2c2c; }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 20px;
        border-top: 2px solid #f0f2f5;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    .btn-cancel {
        padding: 10px 25px;
        border-radius: 10px;
        font-weight: 600;
        border: 2px solid #e2e8f0;
        background: white;
        color: #4a5568;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
    }
    .btn-cancel:hover { background: #f7fafc; border-color: #cbd5e0; }
    .btn-submit {
        padding: 10px 30px;
        border-radius: 10px;
        font-weight: 600;
        border: none;
        background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        color: white;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(237, 137, 54, 0.35);
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

        .form-container { padding: 16px; }
        .form-actions { flex-direction: column; }
        .form-actions .btn-cancel,
        .form-actions .btn-submit { width: 100%; justify-content: center; }
        .info-card { flex-direction: column; text-align: center; }
        .info-card .info-text { flex-direction: column; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1rem; }
        .form-container { padding: 12px; }
        .form-container .form-group .form-control,
        .form-container .form-group .form-select { padding: 8px 12px; font-size: 0.85rem; border-radius: 8px; }
        .sidebar-toggle-mobile {
            width: 42px;
            height: 42px;
            font-size: 0.9rem;
            bottom: 15px;
            left: 15px;
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
            <li><a href="index.php" class="active"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="../inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="../paiements/index.php"><i class="fas fa-money-bill-wave"></i> Paiements</a></li>
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
                <i class="fas fa-edit"></i>
                Modifier un élève
            </h2>
            <div class="header-right">
                <a href="voir.php?id=<?php echo $idEleve; ?>" class="btn-back">
                    <i class="fas fa-eye"></i> Voir
                </a>
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>

        <!-- ===== ERREURS ===== -->
        <?php if (!empty($error)): ?>
            <div class="alert-box danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- ===== INFO ÉLÈVE ===== -->
        <div class="info-card">
            <div class="info-text">
                <span class="matricule"><i class="fas fa-id-card me-2"></i><?php echo htmlspecialchars($eleve['matricule']); ?></span>
                <span class="nom"><?php echo htmlspecialchars($eleve['nom'] . ' ' . ($eleve['postNom'] ?? '') . ' ' . $eleve['prenom']); ?></span>
            </div>
            <div>
                <?php if ($eleve['est_inscrit'] > 0): ?>
                    <span class="badge-statut actif"><i class="fas fa-check-circle me-1"></i> Inscrit</span>
                <?php else: ?>
                    <span class="badge-statut inactif"><i class="fas fa-times-circle me-1"></i> Non inscrit</span>
                <?php endif; ?>
                <a href="../inscriptions/ajouter.php?eleve=<?php echo $idEleve; ?>" class="btn btn-sm btn-primary ms-2" style="border-radius:6px;">
                    <i class="fas fa-plus me-1"></i> Inscrire
                </a>
            </div>
        </div>

        <!-- ===== FORMULAIRE ===== -->
        <div class="form-container">
            <form method="POST" action="">
                <input type="hidden" name="action" value="modifier_eleve">

                <!-- Identité -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nom <span class="required">*</span></label>
                            <input type="text" class="form-control" name="nom" 
                                   value="<?php echo htmlspecialchars($eleve['nom']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Post-Nom</label>
                            <input type="text" class="form-control" name="postNom" 
                                   value="<?php echo htmlspecialchars($eleve['postNom'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Prénom <span class="required">*</span></label>
                            <input type="text" class="form-control" name="prenom" 
                                   value="<?php echo htmlspecialchars($eleve['prenom']); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Naissance -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Sexe <span class="required">*</span></label>
                            <select class="form-select" name="sexe" required>
                                <option value="M" <?php echo $eleve['sexe'] === 'M' ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?php echo $eleve['sexe'] === 'F' ? 'selected' : ''; ?>>Féminin</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date de naissance</label>
                            <input type="date" class="form-control" name="dateNaissance" 
                                   value="<?php echo $eleve['dateNaissance']; ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Lieu de naissance</label>
                            <input type="text" class="form-control" name="lieuNaissance" 
                                   value="<?php echo htmlspecialchars($eleve['lieuNaissance'] ?? ''); ?>" 
                                   placeholder="Lieu de naissance">
                        </div>
                    </div>
                </div>

                <!-- Origine -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Province</label>
                            <input type="text" class="form-control" name="province" 
                                   value="<?php echo htmlspecialchars($eleve['province'] ?? ''); ?>" 
                                   placeholder="Province">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nationalité</label>
                            <input type="text" class="form-control" name="nationalite" 
                                   value="<?php echo htmlspecialchars($eleve['nationalite'] ?? ''); ?>" 
                                   placeholder="Nationalité">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" class="form-control" name="telephone" 
                                   value="<?php echo htmlspecialchars($eleve['telephone'] ?? ''); ?>" 
                                   placeholder="+243 812 345 678">
                        </div>
                    </div>
                </div>

                <!-- Parents -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nom du père</label>
                            <input type="text" class="form-control" name="nomPere" 
                                   value="<?php echo htmlspecialchars($eleve['nomPere'] ?? ''); ?>" 
                                   placeholder="Nom du père">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nom de la mère</label>
                            <input type="text" class="form-control" name="nomMere" 
                                   value="<?php echo htmlspecialchars($eleve['nomMere'] ?? ''); ?>" 
                                   placeholder="Nom de la mère">
                        </div>
                    </div>
                </div>

                <!-- Adresse -->
                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" class="form-control" name="adresse" 
                           value="<?php echo htmlspecialchars($eleve['adresse'] ?? ''); ?>" 
                           placeholder="Adresse complète">
                </div>

                <!-- Matricule et Mot de passe -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Matricule</label>
                            <input type="text" class="form-control" name="matricule" 
                                   value="<?php echo htmlspecialchars($eleve['matricule']); ?>" readonly>
                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Non modifiable</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nouveau mot de passe (optionnel)</label>
                            <input type="text" class="form-control" name="motDePasse" 
                                   placeholder="Laissez vide pour conserver l'actuel">
                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Remplissez pour changer le mot de passe</small>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="index.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
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