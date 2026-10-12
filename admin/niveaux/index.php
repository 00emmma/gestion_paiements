<?php
// admin/niveaux/index.php - Gestion des niveaux
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Gestion des niveaux';
$db = db();

// ===== VÉRIFIER SI LA COLONNE DESCRIPTION EXISTE =====
$colonne_description_existe = false;
try {
    $stmt = $db->query("SHOW COLUMNS FROM niveaux LIKE 'description'");
    $colonne_description_existe = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $colonne_description_existe = false;
}

// ===== SI LA COLONNE N'EXISTE PAS, L'AJOUTER =====
if (!$colonne_description_existe) {
    try {
        $db->exec("ALTER TABLE niveaux ADD COLUMN description TEXT NULL AFTER nomNiveau");
        $colonne_description_existe = true;
    } catch (PDOException $e) {
        // Ignorer l'erreur si la colonne existe déjà
    }
}

// ===== RÉCUPÉRER TOUS LES NIVEAUX =====
$sql = "SELECT * FROM niveaux ORDER BY nomNiveau";
$stmt = $db->query($sql);
$niveaux = $stmt->fetchAll();

// ===== TRAITEMENT DES ACTIONS =====
$error = '';
$success = '';

// Ajouter un niveau
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'ajouter') {
        $nomNiveau = trim($_POST['nomNiveau'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($nomNiveau)) {
            $error = 'Le nom du niveau est obligatoire.';
        } else {
            // Vérifier si le niveau existe déjà
            $stmt = $db->prepare("SELECT idNiveau FROM niveaux WHERE nomNiveau = :nomNiveau");
            $stmt->execute(['nomNiveau' => $nomNiveau]);
            if ($stmt->fetch()) {
                $error = 'Ce niveau existe déjà.';
            } else {
                // Insérer avec ou sans description selon si la colonne existe
                if ($colonne_description_existe) {
                    $stmt = $db->prepare("INSERT INTO niveaux (nomNiveau, description) VALUES (:nomNiveau, :description)");
                    $result = $stmt->execute([
                        'nomNiveau' => $nomNiveau,
                        'description' => $description
                    ]);
                } else {
                    $stmt = $db->prepare("INSERT INTO niveaux (nomNiveau) VALUES (:nomNiveau)");
                    $result = $stmt->execute(['nomNiveau' => $nomNiveau]);
                }
                
                if ($result) {
                    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Niveau ajouté avec succès !'];
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Erreur lors de l\'ajout.';
                }
            }
        }
    }
    
    // Modifier un niveau
    if ($action === 'modifier') {
        $idNiveau = intval($_POST['idNiveau'] ?? 0);
        $nomNiveau = trim($_POST['nomNiveau'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($idNiveau <= 0) {
            $error = 'Niveau invalide.';
        } elseif (empty($nomNiveau)) {
            $error = 'Le nom du niveau est obligatoire.';
        } else {
            // Vérifier si un autre niveau existe avec ce nom
            $stmt = $db->prepare("SELECT idNiveau FROM niveaux WHERE nomNiveau = :nomNiveau AND idNiveau != :idNiveau");
            $stmt->execute(['nomNiveau' => $nomNiveau, 'idNiveau' => $idNiveau]);
            if ($stmt->fetch()) {
                $error = 'Un autre niveau existe déjà avec ce nom.';
            } else {
                // Mettre à jour avec ou sans description selon si la colonne existe
                if ($colonne_description_existe) {
                    $stmt = $db->prepare("UPDATE niveaux SET nomNiveau = :nomNiveau, description = :description WHERE idNiveau = :idNiveau");
                    $result = $stmt->execute([
                        'nomNiveau' => $nomNiveau,
                        'description' => $description,
                        'idNiveau' => $idNiveau
                    ]);
                } else {
                    $stmt = $db->prepare("UPDATE niveaux SET nomNiveau = :nomNiveau WHERE idNiveau = :idNiveau");
                    $result = $stmt->execute([
                        'nomNiveau' => $nomNiveau,
                        'idNiveau' => $idNiveau
                    ]);
                }
                
                if ($result) {
                    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Niveau modifié avec succès !'];
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Erreur lors de la modification.';
                }
            }
        }
    }
    
    // Supprimer un niveau
    if ($action === 'supprimer') {
        $idNiveau = intval($_POST['idNiveau'] ?? 0);
        
        if ($idNiveau <= 0) {
            $error = 'Niveau invalide.';
        } else {
            // Vérifier si des classes utilisent ce niveau
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM classes WHERE idNiveau = :idNiveau");
            $stmt->execute(['idNiveau' => $idNiveau]);
            $count = $stmt->fetch()['total'];
            
            if ($count > 0) {
                $error = 'Ce niveau est utilisé par des classes. Vous ne pouvez pas le supprimer.';
            } else {
                $stmt = $db->prepare("DELETE FROM niveaux WHERE idNiveau = :idNiveau");
                $result = $stmt->execute(['idNiveau' => $idNiveau]);
                
                if ($result) {
                    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Niveau supprimé avec succès !'];
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Erreur lors de la suppression.';
                }
            }
        }
    }
}

// Message flash
$message = '';
if (isset($_SESSION['flash'])) {
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ===== RESET ===== */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: #f8fafc;
        font-size: 14px;
    }

    /* ===== LAYOUT ===== */
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

    /* ===== MAIN CONTENT ===== */
    .main-content {
        flex: 1;
        padding: 20px 25px;
        background: #f8fafc;
        min-height: 100vh;
        margin-left: 0;
    }

    /* ===== HEADER ===== */
    .header-bar {
        background: white;
        padding: 14px 20px;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .header-bar h3 {
        font-size: 1rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .header-bar h3 i {
        color: #667eea;
        margin-right: 8px;
        font-size: 0.9rem;
    }
    .header-bar small {
        color: #718096;
        font-size: 0.75rem;
        display: block;
        font-weight: 400;
    }

    .btn-add {
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
    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102,126,234,0.35);
        color: white !important;
        text-decoration: none;
    }
    .btn-add i {
        font-size: 0.75rem;
    }

    /* ===== ALERTE ===== */
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
    .alert-box i {
        font-size: 0.9rem;
    }
    .alert-box .close-btn {
        margin-left: auto;
        background: none;
        border: none;
        font-size: 1rem;
        cursor: pointer;
        color: inherit;
        opacity: 0.6;
    }

    /* ===== TABLE ===== */
    .table-box {
        background: white;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .table-header {
        padding: 10px 16px;
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
    .table-header .title i {
        color: #667eea;
        margin-right: 6px;
    }
    .search-box {
        display: flex;
        align-items: center;
        background: #f7fafc;
        border-radius: 6px;
        padding: 3px 12px;
        border: 1px solid #e2e8f0;
    }
    .search-box input {
        border: none;
        background: transparent;
        padding: 5px 8px;
        outline: none;
        font-size: 0.8rem;
        width: 160px;
    }
    .search-box i {
        color: #a0aec0;
        font-size: 0.8rem;
    }

    .table-scroll { overflow-x: auto; }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
    }
    .table th {
        background: #f7fafc;
        color: #4a5568;
        font-weight: 600;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding: 8px 12px;
        border-bottom: 2px solid #edf2f7;
        text-align: left;
    }
    .table td {
        padding: 8px 12px;
        border-bottom: 1px solid #f0f2f5;
        vertical-align: middle;
    }
    .table tbody tr:hover td {
        background: #f7fafc;
    }

    /* ===== BADGES ===== */
    .badge-niveau {
        padding: 2px 12px;
        border-radius: 16px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-niveau.primary { background: #ebf8ff; color: #2b6cb0; }
    .badge-niveau.success { background: #f0fff4; color: #276749; }
    .badge-niveau.warning { background: #fffbeb; color: #975a16; }
    .badge-niveau.danger { background: #fff5f5; color: #9b2c2c; }
    .badge-niveau.default { background: #edf2f7; color: #4a5568; }

    /* ===== ACTIONS ===== */
    .actions {
        display: flex;
        gap: 4px;
        justify-content: center;
    }
    .actions .btn {
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        border: none;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        cursor: pointer;
    }
    .actions .btn-edit { background: #fefcbf; color: #975a16; }
    .actions .btn-edit:hover { background: #f6e05e; color: #975a16; }
    .actions .btn-delete { background: #fff5f5; color: #c53030; }
    .actions .btn-delete:hover { background: #fed7d7; color: #c53030; }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }
    .empty-state i {
        font-size: 2rem;
        opacity: 0.3;
        margin-bottom: 8px;
        display: block;
    }
    .empty-state h5 {
        color: #4a5568;
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 4px;
    }
    .empty-state p {
        font-size: 0.8rem;
    }

    /* ============================================================ */
    /* ===== MODAL ===== */
    /* ============================================================ */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.3s ease;
    }
    .modal-overlay.active {
        display: flex;
    }
    .modal-overlay .modal-box {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px 35px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 15px;
        margin-bottom: 20px;
        border-bottom: 2px solid #f0f2f5;
    }
    .modal-header h4 {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .modal-header h4 i {
        color: #667eea;
        margin-right: 10px;
    }
    .modal-header .btn-close-modal {
        background: #f7fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 6px 14px;
        font-size: 0.8rem;
        color: #4a5568;
        cursor: pointer;
        transition: all 0.3s;
    }
    .modal-header .btn-close-modal:hover {
        background: #edf2f7;
    }

    .modal-box .form-group {
        margin-bottom: 16px;
    }
    .modal-box .form-group label {
        display: block;
        font-weight: 600;
        font-size: 0.85rem;
        color: #2d3748;
        margin-bottom: 5px;
    }
    .modal-box .form-group label .required {
        color: #e53e3e;
        margin-left: 2px;
    }
    .modal-box .form-group .form-control,
    .modal-box .form-group .form-select {
        width: 100%;
        padding: 9px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: all 0.3s;
        background: white;
    }
    .modal-box .form-group .form-control:focus,
    .modal-box .form-group .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .modal-box .niveau-info {
        background: #f7fafc;
        padding: 10px 14px;
        border-radius: 8px;
        margin-bottom: 18px;
        border-left: 3px solid #667eea;
    }
    .modal-box .niveau-info .info-label {
        font-size: 0.65rem;
        color: #718096;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .modal-box .niveau-info .info-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: #1a202c;
    }

    .modal-box .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 18px;
        margin-top: 10px;
        border-top: 2px solid #f0f2f5;
    }
    .modal-box .btn-cancel {
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        border: 2px solid #e2e8f0;
        background: white;
        color: #4a5568;
        cursor: pointer;
        transition: all 0.3s;
    }
    .modal-box .btn-cancel:hover {
        background: #f7fafc;
    }
    .modal-box .btn-submit {
        padding: 8px 25px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        background: linear-gradient(135deg, #48bb78, #38a169);
        color: white;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .modal-box .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(72, 187, 120, 0.35);
    }

    .modal-box .alert-box {
        padding: 10px 16px;
        border-radius: 8px;
        margin-bottom: 15px;
        background: #fff5f5;
        color: #c53030;
        border: 1px solid #fed7d7;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .modal-box .alert-box.success {
        background: #f0fff4;
        color: #276749;
        border-color: #c6f6d5;
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
        
        .main-content { padding: 12px !important; margin-left: 0 !important; }
        .header-bar {
            flex-direction: column;
            text-align: center;
            padding: 12px 16px;
        }
        .header-bar h3 { font-size: 0.9rem; }
        .header-bar small { font-size: 0.7rem; }
        .btn-add { width: 100%; justify-content: center; }
        .table-header {
            flex-direction: column;
            align-items: stretch;
        }
        .search-box { width: 100%; }
        .search-box input { width: 100%; }
        .table { font-size: 0.7rem; }
        .table th, .table td { padding: 5px 8px; }
        .modal-overlay .modal-box {
            margin: 20px;
            padding: 20px;
            max-height: 95vh;
        }
        .modal-box .form-actions {
            flex-direction: column;
        }
        .modal-box .form-actions .btn-cancel,
        .modal-box .form-actions .btn-submit {
            width: 100%;
            justify-content: center;
        }
        .modal-header {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }
    }

    @media (max-width: 480px) {
        .main-content { padding: 10px !important; }
        .header-bar h3 { font-size: 0.8rem; }
        .header-bar small { font-size: 0.65rem; }
        .table th, .table td { padding: 4px 6px; font-size: 0.65rem; }
        .modal-overlay .modal-box { padding: 15px; margin: 10px; }
        .modal-header h4 { font-size: 0.95rem; }
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
<!-- MODAL AJOUT NIVEAU -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalAjout">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-plus-circle"></i>Ajouter un niveau</h4>
            <button class="btn-close-modal" onclick="closeModalAjout()">
                <i class="fas fa-times"></i> Fermer
            </button>
        </div>
        <div id="modalMessageAjout"></div>
        <form id="formAjout" method="POST" action="">
            <input type="hidden" name="action" value="ajouter">
            <div class="form-group">
                <label>Nom du niveau <span class="required">*</span></label>
                <input type="text" class="form-control" name="nomNiveau" placeholder="Ex: Primaire, Secondaire, Maternelle..." required>
            </div>
            <?php if ($colonne_description_existe): ?>
            <div class="form-group">
                <label>Description</label>
                <textarea class="form-control" name="description" rows="3" placeholder="Description du niveau..."></textarea>
            </div>
            <?php endif; ?>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModalAjout()">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" class="btn-submit" id="btnSubmitAjout">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL MODIFICATION NIVEAU -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-edit"></i>Modifier le niveau</h4>
            <button class="btn-close-modal" onclick="closeModalEdit()">
                <i class="fas fa-times"></i> Fermer
            </button>
        </div>
        <div id="modalMessageEdit"></div>
        <form id="formEdit" method="POST" action="">
            <input type="hidden" name="action" value="modifier">
            <input type="hidden" name="idNiveau" id="edit_idNiveau" value="">
            
            <div class="niveau-info">
                <div class="info-label">Niveau actuel</div>
                <div class="info-value" id="edit_niveau_actuel">-</div>
            </div>

            <div class="form-group">
                <label>Nom du niveau <span class="required">*</span></label>
                <input type="text" class="form-control" name="nomNiveau" id="edit_nomNiveau" placeholder="Ex: Primaire, Secondaire, Maternelle..." required>
            </div>
            <?php if ($colonne_description_existe): ?>
            <div class="form-group">
                <label>Description</label>
                <textarea class="form-control" name="description" id="edit_description" rows="3" placeholder="Description du niveau..."></textarea>
            </div>
            <?php endif; ?>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModalEdit()">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" class="btn-submit" id="btnSubmitEdit">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- LAYOUT PRINCIPAL -->
<!-- ============================================================ -->
<div class="app-layout">

    <!-- SIDEBAR -->
    <div class="sidebar-admin" id="sidebarAdmin">
        <div class="sidebar-logo">
            <i class="fas fa-school"></i>
            <span>GestionScolaire</span>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>

        <ul class="sidebar-menu">
            <li class="menu-section"><i class="fas fa-cog"></i> Administration</li>
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="../eleves/index.php"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="../inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="../paiements/index.php"><i class="fas fa-money-bill-wave"></i> Paiements</a></li>
            <li><a href="../frais/index.php"><i class="fas fa-tag"></i> Frais</a></li>
            <li><a href="../classes/index.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="index.php" class="active"><i class="fas fa-layer-group"></i> Niveaux</a></li>
            <li><a href="../annees/index.php"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
            <li><a href="../utilisateurs/index.php"><i class="fas fa-user-cog"></i> Utilisateurs</a></li>
            <li class="menu-section"><i class="fas fa-file-alt"></i> Rapports</li>
            <li><a href="../rapports/index.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
            <li><a href="../journal/index.php"><i class="fas fa-history"></i> Journal</a></li>
            <li><a href="../statut.php"><i class="fas fa-user-check"></i> Statut</a></li>
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
        <div class="header-bar">
            <div>
                <h3><i class="fas fa-layer-group"></i>Gestion des niveaux</h3>
                <small>Gérez les niveaux scolaires (Maternelle, Primaire, Secondaire...)</small>
            </div>
            <button class="btn-add" onclick="openModalAjout()">
                <i class="fas fa-plus"></i> Nouveau niveau
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert-box <?php echo $message['type'] == 'success' ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $message['type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message['text']; ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-box danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <!-- ===== TABLEAU ===== -->
        <div class="table-box">
            <div class="table-header">
                <span class="title"><i class="fas fa-list"></i>Liste des niveaux</span>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Rechercher...">
                </div>
            </div>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:38px;">#</th>
                            <th>Nom du niveau</th>
                            <?php if ($colonne_description_existe): ?>
                            <th>Description</th>
                            <?php endif; ?>
                            <th style="text-align:center; width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($niveaux)): ?>
                            <tr>
                                <td colspan="<?php echo $colonne_description_existe ? 4 : 3; ?>">
                                    <div class="empty-state">
                                        <i class="fas fa-layer-group"></i>
                                        <h5>Aucun niveau</h5>
                                        <p>Ajoutez votre premier niveau</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($niveaux as $i => $n): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($n['nomNiveau']); ?></strong>
                                    </td>
                                    <?php if ($colonne_description_existe): ?>
                                    <td><?php echo htmlspecialchars($n['description'] ?? '-'); ?></td>
                                    <?php endif; ?>
                                    <td style="text-align:center;">
                                        <div class="actions">
                                            <button class="btn btn-edit" title="Modifier" onclick="openModalEdit(<?php echo $n['idNiveau']; ?>, '<?php echo htmlspecialchars($n['nomNiveau']); ?>', '<?php echo htmlspecialchars($n['description'] ?? ''); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-delete" title="Supprimer" onclick="supprimer(<?php echo $n['idNiveau']; ?>, '<?php echo htmlspecialchars($n['nomNiveau']); ?>')">
                                                <i class="fas fa-trash"></i>
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
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    // ===== MODAL AJOUT =====
    function openModalAjout() {
        document.getElementById('modalAjout').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.getElementById('formAjout').reset();
        document.getElementById('modalMessageAjout').innerHTML = '';
    }

    function closeModalAjout() {
        document.getElementById('modalAjout').classList.remove('active');
        document.body.style.overflow = '';
    }

    // ===== MODAL MODIFICATION =====
    function openModalEdit(id, nomNiveau, description) {
        document.getElementById('modalEdit').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.getElementById('modalMessageEdit').innerHTML = '';

        document.getElementById('edit_idNiveau').value = id;
        document.getElementById('edit_nomNiveau').value = nomNiveau;
        <?php if ($colonne_description_existe): ?>
        document.getElementById('edit_description').value = description || '';
        <?php endif; ?>
        document.getElementById('edit_niveau_actuel').textContent = nomNiveau;
    }

    function closeModalEdit() {
        document.getElementById('modalEdit').classList.remove('active');
        document.body.style.overflow = '';
    }

    // ===== SUPPRIMER =====
    function supprimer(id, nom) {
        if (confirm('Voulez-vous vraiment supprimer le niveau "' + nom + '" ?\nCette action est irréversible.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.innerHTML = `
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="idNiveau" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // ===== FERMER LES MODALS AVEC ÉCHAP =====
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('modalAjout').classList.contains('active')) {
                closeModalAjout();
            }
            if (document.getElementById('modalEdit').classList.contains('active')) {
                closeModalEdit();
            }
        }
    });

    // ===== FERMER EN CLIQUANT SUR L'OVERLAY =====
    document.getElementById('modalAjout').addEventListener('click', function(e) {
        if (e.target === this) closeModalAjout();
    });
    document.getElementById('modalEdit').addEventListener('click', function(e) {
        if (e.target === this) closeModalEdit();
    });

    // ===== SIDEBAR MOBILE =====
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

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });

        // Recherche
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    });
</script>
</body>
</html>