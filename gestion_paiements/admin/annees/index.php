<?php
// admin/annees/index.php - Gestion des années scolaires (Génération automatique)
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Gestion des années scolaires - Administrateur';
$db = db();

// ===== DÉTECTER LES COLONNES DISPONIBLES =====
$colonnes = [];
try {
    $stmt = $db->query("DESCRIBE annees_scolaires");
    $colonnes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignorer
}

$has_dateDebut = in_array('dateDebut', $colonnes);
$has_dateFin = in_array('dateFin', $colonnes);
$has_dateCreation = in_array('dateCreation', $colonnes) || in_array('created_at', $colonnes);

// ===== FONCTION POUR GÉNÉRER LES ANNÉES AUTOMATIQUEMENT =====
function genererAnneeScolaire($annee) {
    $debut = $annee . '-09-01';
    $fin = ($annee + 1) . '-06-30';
    $libelle = $annee . '-' . ($annee + 1);
    return [
        'libelle' => $libelle,
        'dateDebut' => $debut,
        'dateFin' => $fin
    ];
}

// ===== TRAITEMENT =====
$error = '';
$success = '';

// Générer une année automatiquement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generer_annee') {
    $annee = intval($_POST['annee'] ?? date('Y'));
    
    $libelle = $annee . '-' . ($annee + 1);
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM annees_scolaires WHERE libelle = :libelle");
    $stmt->execute(['libelle' => $libelle]);
    $existe = $stmt->fetch()['total'];
    
    if ($existe > 0) {
        $error = 'L\'année ' . $libelle . ' existe déjà.';
    } else {
        $data = genererAnneeScolaire($annee);
        
        $fields = ['libelle'];
        $placeholders = [':libelle'];
        $params = ['libelle' => $data['libelle']];
        
        if ($has_dateDebut) {
            $fields[] = 'dateDebut';
            $placeholders[] = ':dateDebut';
            $params['dateDebut'] = $data['dateDebut'];
        }
        
        if ($has_dateFin) {
            $fields[] = 'dateFin';
            $placeholders[] = ':dateFin';
            $params['dateFin'] = $data['dateFin'];
        }
        
        $fields[] = 'active';
        $placeholders[] = ':active';
        $params['active'] = 0;
        
        if ($has_dateCreation) {
            $fields[] = 'dateCreation';
            $placeholders[] = ':dateCreation';
            $params['dateCreation'] = date('Y-m-d H:i:s');
        }
        
        $sql = "INSERT INTO annees_scolaires (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $_SESSION['flash'] = [
                'type' => 'success', 
                'text' => 'Année ' . $data['libelle'] . ' générée avec succès !'
            ];
            header('Location: index.php');
            exit();
        } else {
            $error = 'Erreur lors de la génération de l\'année.';
        }
    }
}

// Activer une année
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activer') {
    $idAnnee = intval($_POST['idAnnee'] ?? 0);
    
    if ($idAnnee <= 0) {
        $error = 'Année invalide.';
    } else {
        $db->query("UPDATE annees_scolaires SET active = 0");
        $stmt = $db->prepare("UPDATE annees_scolaires SET active = 1 WHERE idAnnee = :id");
        $result = $stmt->execute(['id' => $idAnnee]);
        
        if ($result) {
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Année activée avec succès !'];
            header('Location: index.php');
            exit();
        } else {
            $error = 'Erreur lors de l\'activation.';
        }
    }
}

// Supprimer une année
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
    $idAnnee = intval($_POST['idAnnee'] ?? 0);
    
    if ($idAnnee <= 0) {
        $error = 'Année invalide.';
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM inscriptions WHERE idAnnee = :id");
        $stmt->execute(['id' => $idAnnee]);
        $count = $stmt->fetch()['total'];
        
        if ($count > 0) {
            $error = 'Cette année est utilisée par des inscriptions. Vous ne pouvez pas la supprimer.';
        } else {
            $stmt = $db->prepare("DELETE FROM annees_scolaires WHERE idAnnee = :id");
            $result = $stmt->execute(['id' => $idAnnee]);
            
            if ($result) {
                $_SESSION['flash'] = ['type' => 'success', 'text' => 'Année scolaire supprimée avec succès !'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'Erreur lors de la suppression.';
            }
        }
    }
}

// Récupérer les années scolaires
$stmt = $db->query("SELECT * FROM annees_scolaires ORDER BY idAnnee DESC");
$annees = $stmt->fetchAll();

$annee_courante = date('Y');
$mois_actuel = date('m');
if ($mois_actuel >= 9) {
    $annee_scolaire_debut = $annee_courante;
} else {
    $annee_scolaire_debut = $annee_courante - 1;
}

$message = '';
if (isset($_SESSION['flash'])) {
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

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

    .main-content {
        flex: 1;
        padding: 20px 25px;
        background: #f8fafc;
        min-height: 100vh;
        max-height: 100vh;
        overflow-y: auto;
    }

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
    .btn-generate {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white !important;
        padding: 7px 18px;
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
    .btn-generate:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102,126,234,0.35);
        color: white !important;
        text-decoration: none;
    }

    .alert-box {
        padding: 10px 16px;
        border-radius: 8px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
    }
    .alert-box.success {
        background: #f0fff4;
        color: #276749;
        border: 1px solid #c6f6d5;
    }
    .alert-box.danger {
        background: #fff5f5;
        color: #c53030;
        border: 1px solid #fed7d7;
    }
    .alert-box i { font-size: 0.9rem; }
    .alert-box .close-btn {
        margin-left: auto;
        background: none;
        border: none;
        font-size: 1rem;
        cursor: pointer;
        color: inherit;
        opacity: 0.6;
    }
    .alert-box .close-btn:hover { opacity: 1; }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        padding: 12px 18px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .stat-item .stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .stat-item .stat-info .number {
        font-size: 1.2rem;
        font-weight: 800;
        color: #1a202c;
        line-height: 1.2;
    }
    .stat-item .stat-info .label {
        font-size: 0.7rem;
        color: #718096;
    }

    .table-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .table-responsive { overflow-x: auto; }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 600px;
    }
    .table thead {
        background: #f7fafc;
        border-bottom: 2px solid #e2e8f0;
    }
    .table thead th {
        padding: 8px 12px;
        text-align: left;
        font-weight: 700;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #4a5568;
        white-space: nowrap;
    }
    .table tbody tr { border-bottom: 1px solid #f0f2f5; transition: 0.3s; }
    .table tbody tr:hover { background: #f7fafc; }
    .table tbody td { padding: 8px 12px; vertical-align: middle; }

    .badge-active {
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .badge-active.oui { background: #f0fff4; color: #276749; }
    .badge-active.non { background: #f7fafc; color: #4a5568; }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }
    .empty-state i { font-size: 2.5rem; margin-bottom: 10px; color: #e2e8f0; }

    .btn-actions {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    .btn-actions .btn-sm {
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        border: none;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-actions .btn-activate {
        background: #48bb78;
        color: white;
    }
    .btn-actions .btn-activate:hover {
        background: #38a169;
    }
    .btn-actions .btn-delete {
        background: #fff5f5;
        color: #9b2c2c;
    }
    .btn-actions .btn-delete:hover {
        background: #fed7d7;
    }

    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active {
        display: flex;
        animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    .modal-content {
        background: white;
        border-radius: 16px;
        max-width: 500px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px;
        animation: slideUp 0.4s ease;
        box-shadow: 0 30px 80px rgba(0,0,0,0.2);
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(40px) scale(0.95); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f2f5;
        margin-bottom: 20px;
    }
    .modal-header h4 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .modal-header h4 i {
        color: #667eea;
        margin-right: 10px;
    }
    .modal-close {
        background: #f7fafc;
        border: 1px solid #e2e8f0;
        border-radius: 50%;
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.3s;
        font-size: 1rem;
        color: #4a5568;
    }
    .modal-close:hover { background: #edf2f7; }

    .modal-body .form-group {
        margin-bottom: 16px;
    }
    .modal-body .form-group label {
        display: block;
        font-weight: 600;
        font-size: 0.85rem;
        color: #2d3748;
        margin-bottom: 5px;
    }
    .modal-body .form-group .required {
        color: #e53e3e;
        margin-left: 2px;
    }
    .modal-body .form-group .form-control,
    .modal-body .form-group .form-select {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.9rem;
        transition: 0.3s;
        background: white;
    }
    .modal-body .form-group .form-control:focus,
    .modal-body .form-group .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .modal-body .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 20px;
        border-top: 2px solid #f0f2f5;
        margin-top: 10px;
        flex-wrap: wrap;
    }
    .modal-body .btn-cancel {
        padding: 10px 25px;
        border-radius: 10px;
        font-weight: 600;
        border: 2px solid #e2e8f0;
        background: white;
        color: #4a5568;
        cursor: pointer;
        transition: 0.3s;
    }
    .modal-body .btn-cancel:hover { background: #f7fafc; border-color: #cbd5e0; }
    .modal-body .btn-submit {
        padding: 10px 30px;
        border-radius: 10px;
        font-weight: 600;
        border: none;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .modal-body .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
    }

    .modal-body .info-box {
        background: #f0fff4;
        border: 1px solid #c6f6d5;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 16px;
        color: #276749;
    }
    .modal-body .info-box i {
        margin-right: 8px;
        color: #48bb78;
    }
    .modal-body .info-box ul {
        padding-left: 18px;
        margin-top: 6px;
        margin-bottom: 0;
        list-style: none;
    }
    .modal-body .info-box ul li {
        padding: 2px 0;
    }

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
        .btn-generate { width: 100%; justify-content: center; }

        .stats-row { grid-template-columns: 1fr 1fr; }
        .btn-actions { flex-direction: column; }
        .btn-actions .btn-sm { width: 100%; justify-content: center; }
        .modal-content { padding: 20px; margin: 10px; }
        .modal-body .form-actions { flex-direction: column; }
        .modal-body .form-actions .btn-cancel,
        .modal-body .form-actions .btn-submit { width: 100%; justify-content: center; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1rem; }
        .stats-row { grid-template-columns: 1fr; }
        .stat-item { padding: 10px 14px; }
        .stat-item .stat-info .number { font-size: 1rem; }
        .table { font-size: 0.7rem; min-width: 500px; }
        .table thead th, .table tbody td { padding: 4px 6px; }
        .sidebar-toggle-mobile {
            width: 42px;
            height: 42px;
            font-size: 0.9rem;
            bottom: 15px;
            left: 15px;
        }
        .modal-content { padding: 15px; }
        .modal-header h4 { font-size: 1rem; }
        .modal-body .form-group .form-control,
        .modal-body .form-group .form-select { padding: 8px 12px; font-size: 0.85rem; border-radius: 8px; }
        .modal-body .info-box ul li { font-size: 0.85rem; }
    }

    @media (min-width: 992px) {
        .sidebar-toggle-mobile { display: none !important; }
        .sidebar-overlay { display: none !important; }
        .sidebar-close { display: none !important; }
    }
</style>

<!-- ===== OVERLAY ET TOGGLE ===== -->
<button class="sidebar-toggle-mobile" id="sidebarToggle" title="Menu">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

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
            <li class="menu-section"><i class="fas fa-cog"></i> Administration</li>
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="../eleves/index.php"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="../inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="../paiements/index.php"><i class="fas fa-money-bill-wave"></i> Paiements</a></li>
            <li><a href="../frais/index.php"><i class="fas fa-tag"></i> Frais</a></li>
            <li><a href="../classes/index.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="index.php" class="active"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
            <li><a href="../utilisateurs/index.php"><i class="fas fa-user-cog"></i> Utilisateurs</a></li>
            <li class="menu-section"><i class="fas fa-file-alt"></i> Rapports</li>
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
                <i class="fas fa-calendar-alt"></i>
                Gestion des années scolaires
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2">
                    <i class="fas fa-hashtag me-1"></i><?php echo count($annees); ?>
                </span>
            </h2>
            <div class="header-right">
                <button class="btn-generate" onclick="openModal()">
                    <i class="fas fa-plus-circle"></i> Générer une année
                </button>
            </div>
        </div>

        <!-- ===== MESSAGE FLASH ===== -->
        <?php if (!empty($message)): ?>
            <div class="alert-box <?php echo $message['type'] == 'success' ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $message['type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message['text']; ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert-box danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <!-- ===== STATISTIQUES ===== -->
        <?php
        $active_count = 0;
        $inactive_count = 0;
        foreach ($annees as $a) {
            if ($a['active'] == 1) $active_count++;
            else $inactive_count++;
        }
        ?>

        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(102, 126, 234, 0.15); color: #667eea;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo count($annees); ?></div>
                    <div class="label">Total années</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $active_count; ?></div>
                    <div class="label">Année active</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $inactive_count; ?></div>
                    <div class="label">Inactives</div>
                </div>
            </div>
        </div>

        <!-- ===== TABLEAU ===== -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i></th>
                            <th><i class="fas fa-tag"></i> Libellé</th>
                            <?php if ($has_dateDebut): ?>
                                <th><i class="fas fa-calendar-alt"></i> Période</th>
                            <?php endif; ?>
                            <th><i class="fas fa-toggle-on"></i> Statut</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($annees)): ?>
                            <tr>
                                <td colspan="<?php echo 4 + ($has_dateDebut ? 1 : 0); ?>">
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-alt fa-3x"></i>
                                        <p>Aucune année scolaire</p>
                                        <button class="btn btn-primary btn-sm mt-2" onclick="openModal()">
                                            <i class="fas fa-plus-circle"></i> Générer une année
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($annees as $index => $a): ?>
                                <tr <?php echo $a['active'] == 1 ? 'style="background:#f0fff4;"' : ''; ?>>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($a['libelle']); ?></strong>
                                        <?php if ($a['active'] == 1): ?>
                                            <span class="badge bg-success ms-2" style="font-size:0.6rem;">
                                                <i class="fas fa-check-circle me-1"></i> Active
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($has_dateDebut): ?>
                                        <td>
                                            <i class="fas fa-calendar-alt me-1" style="color:#667eea;"></i>
                                            <?php 
                                                $debut = isset($a['dateDebut']) && $a['dateDebut'] ? date('d/m/Y', strtotime($a['dateDebut'])) : '-';
                                                $fin = isset($a['dateFin']) && $a['dateFin'] ? date('d/m/Y', strtotime($a['dateFin'])) : '-';
                                                echo $debut . ' <i class="fas fa-arrow-right mx-1" style="font-size:0.6rem;color:#a0aec0;"></i> ' . $fin;
                                            ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge-active <?php echo $a['active'] == 1 ? 'oui' : 'non'; ?>">
                                            <i class="fas fa-<?php echo $a['active'] == 1 ? 'check-circle' : 'circle'; ?> me-1"></i>
                                            <?php echo $a['active'] == 1 ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-actions">
                                            <?php if ($a['active'] == 0): ?>
                                                <form method="POST" action="" style="display:inline;">
                                                    <input type="hidden" name="action" value="activer">
                                                    <input type="hidden" name="idAnnee" value="<?php echo $a['idAnnee']; ?>">
                                                    <button type="submit" class="btn-sm btn-activate" 
                                                            onclick="return confirm('Activer l\'année <?php echo htmlspecialchars($a['libelle']); ?> ?')">
                                                        <i class="fas fa-check"></i> Activer
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <button class="btn-sm btn-delete" onclick="supprimer(<?php echo $a['idAnnee']; ?>, '<?php echo htmlspecialchars($a['libelle']); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
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
<!-- MODAL GÉNÉRER ANNÉE -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalAnnee">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fas fa-calendar-plus"></i> Générer une année scolaire</h4>
            <button class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>Informations :</strong>
                <ul>
                    <li><i class="fas fa-tag me-1"></i> Libellé: <strong id="apercuLibelle"><?php echo $annee_scolaire_debut . '-' . ($annee_scolaire_debut + 1); ?></strong></li>
                    <li><i class="fas fa-calendar-alt me-1"></i> Début: <strong>1er Septembre <?php echo $annee_scolaire_debut; ?></strong></li>
                    <li><i class="fas fa-calendar-alt me-1"></i> Fin: <strong>30 Juin <?php echo $annee_scolaire_debut + 1; ?></strong></li>
                </ul>
            </div>
            <form method="POST" action="" id="formAnnee">
                <input type="hidden" name="action" value="generer_annee">
                <input type="hidden" name="annee" id="annee" value="<?php echo $annee_scolaire_debut; ?>">

                <div class="form-group">
                    <label><i class="fas fa-calendar-alt me-1"></i> Année à générer</label>
                    <select class="form-select" name="annee" id="anneeSelect" onchange="updateLibelle()">
                        <option value="<?php echo $annee_scolaire_debut; ?>">
                            <?php echo $annee_scolaire_debut . '-' . ($annee_scolaire_debut + 1); ?>
                        </option>
                        <option value="<?php echo $annee_scolaire_debut + 1; ?>">
                            <?php echo ($annee_scolaire_debut + 1) . '-' . ($annee_scolaire_debut + 2); ?>
                        </option>
                        <option value="<?php echo $annee_scolaire_debut + 2; ?>">
                            <?php echo ($annee_scolaire_debut + 2) . '-' . ($annee_scolaire_debut + 3); ?>
                        </option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Générer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    // ===== MODAL =====
    function openModal() {
        document.getElementById('modalAnnee').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('modalAnnee').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('modalAnnee').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // ===== MISE À JOUR DE L'APERÇU =====
    function updateLibelle() {
        const select = document.getElementById('anneeSelect');
        const annee = parseInt(select.value);
        const libelle = annee + '-' + (annee + 1);
        document.getElementById('apercuLibelle').textContent = libelle;
        document.getElementById('annee').value = annee;
    }

    // ===== SUPPRIMER =====
    function supprimer(id, libelle) {
        if (confirm('Voulez-vous vraiment supprimer l\'année "' + libelle + '" ? Cette action est irréversible.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.innerHTML = `
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="idAnnee" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

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