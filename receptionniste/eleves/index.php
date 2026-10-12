<?php
// receptionniste/eleves/index.php - Liste des élèves
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Réceptionniste') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Liste des élèves';
$db = db();

// ===== RECHERCHE =====
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// ===== RÉCUPÉRER LES ÉLÈVES =====
if (!empty($search)) {
    $sql = "
        SELECT e.*, i.idClasse, c.nomClasse, n.nomNiveau
        FROM eleves e
        LEFT JOIN inscriptions i ON e.idEleve = i.idEleve 
            AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
        LEFT JOIN classes c ON i.idClasse = c.idClasse
        LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau
        WHERE e.nom LIKE :search OR e.prenom LIKE :search OR e.matricule LIKE :search
        ORDER BY e.nom, e.prenom
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(['search' => "%$search%"]);
} else {
    $sql = "
        SELECT e.*, i.idClasse, c.nomClasse, n.nomNiveau
        FROM eleves e
        LEFT JOIN inscriptions i ON e.idEleve = i.idEleve 
            AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
        LEFT JOIN classes c ON i.idClasse = c.idClasse
        LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau
        ORDER BY e.nom, e.prenom
    ";
    $stmt = $db->query($sql);
}
$eleves = $stmt->fetchAll();

// ===== STATISTIQUES =====
$total_eleves = count($eleves);
$total_garcons = 0;
$total_filles = 0;
foreach ($eleves as $e) {
    if ($e['sexe'] === 'M') $total_garcons++;
    else $total_filles++;
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
    /* ===== STYLES GENERAUX ===== */
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

    /* ===== MAIN CONTENT ===== */
    .main-content {
        flex: 1;
        padding: 25px 30px;
        background: #f8fafc;
        min-height: 100vh;
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

    .btn-add {
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
        cursor: pointer;
    }
    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(66,153,225,0.35);
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
        transition: all 0.3s ease;
    }
    .stat-card .number { font-size: 1.4rem; font-weight: 700; color: #1a202c; }
    .stat-card .label { font-size: 0.7rem; color: #718096; margin-top: 3px; }
    .stat-card.green { border-color: #48bb78; }
    .stat-card.orange { border-color: #ed8936; }
    .stat-card.purple { border-color: #9f7aea; }

    /* ===== FILTRE ===== */
    .filter-bar {
        background: white;
        padding: 10px 18px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }
    .filter-bar form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        flex: 1;
    }
    .filter-bar input {
        flex: 1;
        min-width: 200px;
        padding: 8px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.85rem;
        transition: all 0.3s;
    }
    .filter-bar input:focus {
        border-color: #4299e1;
        outline: none;
        box-shadow: 0 0 0 3px rgba(66,153,225,0.1);
    }
    .filter-bar .btn-search {
        background: #4299e1;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.3s;
    }
    .filter-bar .btn-search:hover {
        background: #3182ce;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(66,153,225,0.35);
    }
    .filter-bar .btn-reset {
        background: #a0aec0;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.75rem;
        text-decoration: none;
        transition: all 0.3s;
    }
    .filter-bar .btn-reset:hover {
        background: #718096;
        transform: translateY(-2px);
    }

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
    .search-box i { color: #a0aec0; font-size: 0.8rem; }

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
    .table tbody tr {
        transition: all 0.3s ease;
    }
    .table tbody tr:hover td { background: #f7fafc; }

    .badge-classe {
        padding: 2px 12px;
        border-radius: 16px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-classe.inscrit { background: #f0fff4; color: #276749; }
    .badge-classe.non-inscrit { background: #fff5f5; color: #9b2c2c; }

    .badge-sexe {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-sexe.M { background: #dbeafe; color: #1e40af; }
    .badge-sexe.F { background: #fce4ec; color: #c62828; }

    /* ===== BOUTONS D'ACTION ===== */
    .btn-actions {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: center;
    }
    .btn-actions .btn {
        padding: 5px 10px;
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
    .btn-actions .btn-view { 
        background: #dbeafe; 
        color: #1e40af; 
    }
    .btn-actions .btn-view:hover { 
        background: #bfdbfe; 
        transform: scale(1.05);
    }
    .btn-actions .btn-edit { 
        background: #fefcbf; 
        color: #975a16; 
    }
    .btn-actions .btn-edit:hover { 
        background: #f6e05e; 
        transform: scale(1.05);
    }
    .btn-actions .btn-recu { 
        background: #d1fae5; 
        color: #065f46; 
    }
    .btn-actions .btn-recu:hover { 
        background: #a7f3d0; 
        transform: scale(1.05);
    }
    .btn-actions .btn-delete { 
        background: #fff5f5; 
        color: #c53030; 
    }
    .btn-actions .btn-delete:hover { 
        background: #fed7d7; 
        transform: scale(1.05);
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }
    .empty-state i { font-size: 2.5rem; opacity: 0.3; margin-bottom: 10px; display: block; }
    .empty-state h5 { color: #4a5568; font-weight: 600; font-size: 0.9rem; margin-bottom: 4px; }
    .empty-state p { font-size: 0.85rem; margin: 0; }

    /* ============================================================ */
    /* ===== STYLES DES MODALS PROFESSIONNELS ===== */
    /* ============================================================ */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.3s ease;
    }
    .modal-overlay.active {
        display: flex;
    }
    .modal-overlay .modal-box {
        background: #ffffff;
        border-radius: 20px;
        width: 100%;
        max-width: 700px;
        max-height: 92vh;
        overflow-y: auto;
        box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35);
        animation: slideUp 0.3s ease;
    }
    .modal-overlay .modal-box::-webkit-scrollbar {
        width: 6px;
    }
    .modal-overlay .modal-box::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .modal-overlay .modal-box::-webkit-scrollbar-thumb {
        background: #c1c7cd;
        border-radius: 10px;
    }
    .modal-overlay .modal-box::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideUp {
        from { transform: translateY(40px) scale(0.96); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }

    /* ===== EN-TÊTE DU MODAL ===== */
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 22px 30px;
        background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        border-radius: 20px 20px 0 0;
        border-bottom: 2px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 10;
        background: #ffffff;
        border-bottom: 2px solid #edf2f7;
    }

    .modal-header-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .modal-icon {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, #4299e1, #3182ce);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        box-shadow: 0 4px 12px rgba(66, 153, 225, 0.35);
        flex-shrink: 0;
    }

    .modal-header h4 {
        font-size: 1.15rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
        line-height: 1.3;
    }

    .modal-subtitle {
        font-size: 0.78rem;
        color: #718096;
        display: block;
        margin-top: 1px;
        font-weight: 400;
    }

    .btn-close-modal {
        background: #f7fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 7px 14px;
        font-size: 0.85rem;
        color: #4a5568;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
    }
    .btn-close-modal:hover {
        background: #fff5f5;
        border-color: #feb2b2;
        color: #c53030;
        transform: rotate(90deg) scale(1.02);
    }

    /* ===== CORPS DU MODAL ===== */
    .modal-body {
        padding: 24px 30px 30px;
    }

    /* ===== MESSAGE ===== */
    .modal-message {
        margin-bottom: 18px;
    }

    .alert-box {
        padding: 12px 16px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        font-size: 0.9rem;
        border: 1px solid transparent;
    }
    .alert-box.danger {
        background: #fff5f5;
        color: #c53030;
        border-color: #fed7d7;
    }
    .alert-box.success {
        background: #f0fff4;
        color: #276749;
        border-color: #c6f6d5;
    }
    .alert-box i {
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    /* ===== INFO ÉLÈVE ===== */
    .info-eleve {
        background: linear-gradient(135deg, #ebf8ff, #bee3f8);
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        border-left: 4px solid #4299e1;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .info-eleve-avatar {
        width: 48px;
        height: 48px;
        background: #4299e1;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .info-eleve-content {
        flex: 1;
    }
    .info-eleve .info-label {
        font-size: 0.7rem;
        color: #2b6cb0;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .info-eleve .info-value {
        font-size: 1rem;
        font-weight: 700;
        color: #1a202c;
        margin: 2px 0 4px 0;
    }
    .info-badge {
        display: flex;
        gap: 8px;
        margin-top: 4px;
    }
    .badge-info {
        background: #4299e1;
        color: white;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    /* ===== SECTIONS DU FORMULAIRE ===== */
    .form-section {
        margin-bottom: 22px;
    }
    .form-section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: #2d3748;
        font-size: 0.9rem;
        margin-bottom: 4px;
    }
    .form-section-header i {
        color: #4299e1;
        font-size: 1rem;
    }
    .section-divider {
        margin: 4px 0 16px 0;
        border: 0;
        border-top: 1px solid #e2e8f0;
    }

    /* ===== CHAMPS ===== */
    .form-group {
        margin-bottom: 16px;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        font-size: 0.82rem;
        color: #2d3748;
        margin-bottom: 5px;
    }
    .form-group label .required {
        color: #e53e3e;
        margin-left: 2px;
    }

    .input-group {
        position: relative;
        display: flex;
        align-items: center;
    }
    .input-icon {
        position: absolute;
        left: 13px;
        color: #a0aec0;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        pointer-events: none;
    }
    .input-group .form-control {
        width: 100%;
        padding: 10px 14px 10px 40px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: #ffffff;
        color: #2d3748;
        font-family: inherit;
    }
    .input-group .form-control:focus {
        border-color: #4299e1;
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
        outline: none;
    }
    .input-group .form-control:focus ~ .input-icon,
    .input-group .form-control:focus + .input-icon {
        color: #4299e1;
    }
    .input-group .form-control::placeholder {
        color: #a0aec0;
    }
    .input-group select.form-control {
        appearance: none;
        padding-right: 35px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234a5568' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        cursor: pointer;
    }

    /* ===== GRILLE ===== */
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .form-row-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 16px;
    }

    /* ===== ACTIONS ===== */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 20px;
        margin-top: 10px;
        border-top: 2px solid #f0f2f5;
    }
    .btn-cancel {
        padding: 10px 28px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: 2px solid #e2e8f0;
        background: #ffffff;
        color: #4a5568;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-cancel:hover {
        background: #f7fafc;
        border-color: #cbd5e0;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
    .btn-submit {
        padding: 10px 32px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        color: #ffffff;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 15px rgba(66, 153, 225, 0.3);
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(66, 153, 225, 0.4);
        background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%);
    }
    .btn-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    /* ===== MATRICULE DISPLAY ===== */
    .matricule-display {
        background: #f7fafc;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 14px;
        font-weight: 700;
        color: #2d3748;
        font-size: 0.95rem;
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
    }

    /* ===== TOAST ===== */
    .toast-notification {
        position: fixed;
        top: 24px;
        right: 24px;
        padding: 14px 24px;
        border-radius: 12px;
        font-weight: 600;
        z-index: 10001;
        animation: slideInRight 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        max-width: 420px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
    }
    .toast-notification.success {
        background: #48bb78;
        color: white;
    }
    .toast-notification.error {
        background: #e53e3e;
        color: white;
    }
    .toast-notification.info {
        background: #4299e1;
        color: white;
    }
    .toast-notification i {
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    @keyframes slideInRight {
        from { transform: translateX(120%) scale(0.9); opacity: 0; }
        to { transform: translateX(0) scale(1); opacity: 1; }
    }

    /* ============================================================ */
    /* ===== MODAL VOIR DÉTAILS ===== */
    /* ============================================================ */
    .voir-card {
        background: #f7fafc;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    .voir-header {
        background: linear-gradient(135deg, #ebf8ff, #bee3f8);
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .voir-avatar {
        width: 70px;
        height: 70px;
        background: #4299e1;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2.5rem;
        flex-shrink: 0;
    }
    .voir-header-info h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0 0 4px 0;
    }
    .voir-matricule {
        display: inline-block;
        background: white;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        color: #2d3748;
        margin-right: 8px;
    }
    .voir-statut {
        display: inline-block;
        padding: 2px 12px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .voir-statut.inscrit {
        background: #f0fff4;
        color: #276749;
    }
    .voir-statut.non-inscrit {
        background: #fff5f5;
        color: #9b2c2c;
    }
    .voir-body {
        padding: 20px;
    }
    .voir-section {
        margin-bottom: 16px;
    }
    .voir-section h5 {
        font-size: 0.85rem;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 10px 0;
        padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    .voir-section h5 i {
        color: #4299e1;
        margin-right: 6px;
    }
    .voir-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .voir-item {
        display: flex;
        flex-direction: column;
    }
    .voir-item .voir-label {
        font-size: 0.7rem;
        color: #718096;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .voir-item .voir-value {
        font-size: 0.95rem;
        font-weight: 500;
        color: #1a202c;
        padding: 4px 0;
    }
    .voir-item .voir-value.text-muted {
        color: #a0aec0;
        font-style: italic;
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
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .stat-card { padding: 10px 12px; }
        .stat-card .number { font-size: 1.1rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar form { flex-direction: column; }
        .filter-bar input { min-width: auto; }
        .table-header { flex-direction: column; align-items: stretch; }
        .search-box { width: 100%; }
        .search-box input { width: 100%; }
        .table { font-size: 0.75rem; }
        .table th, .table td { padding: 6px 10px; }
        .modal-overlay .modal-box {
            margin: 16px;
            max-height: 96vh;
        }
        .modal-header {
            padding: 18px 20px;
            flex-direction: column;
            gap: 12px;
            text-align: center;
        }
        .modal-header-left {
            flex-direction: column;
            text-align: center;
        }
        .modal-body {
            padding: 18px 20px 24px;
        }
        .form-row,
        .form-row-3 {
            grid-template-columns: 1fr;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn-cancel,
        .form-actions .btn-submit {
            width: 100%;
            justify-content: center;
        }
        .info-eleve {
            flex-direction: column;
            text-align: center;
        }
        .info-eleve-avatar {
            margin: 0 auto;
        }
        .info-badge {
            justify-content: center;
        }
        .btn-close-modal {
            align-self: center;
        }
        .voir-header {
            flex-direction: column;
            text-align: center;
        }
        .voir-grid {
            grid-template-columns: 1fr;
        }
        .btn-actions .btn {
            padding: 4px 8px;
            font-size: 0.65rem;
        }
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
        .modal-overlay .modal-box {
            margin: 10px;
            padding: 0;
        }
        .modal-header {
            padding: 14px 16px;
        }
        .modal-header h4 {
            font-size: 1rem;
        }
        .modal-body {
            padding: 14px 16px 20px;
        }
        .modal-icon {
            width: 36px;
            height: 36px;
            font-size: 1rem;
        }
        .toast-notification {
            top: 12px;
            right: 12px;
            left: 12px;
            max-width: none;
            font-size: 0.85rem;
            padding: 12px 18px;
        }
        .btn-cancel, .btn-submit {
            padding: 10px 20px;
            font-size: 0.8rem;
        }
        .voir-avatar {
            width: 50px;
            height: 50px;
            font-size: 1.8rem;
        }
        .btn-actions .btn {
            padding: 3px 6px;
            font-size: 0.6rem;
        }
        .btn-actions .btn i {
            font-size: 0.6rem;
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
<!-- ===== MODAL AJOUT ÉLÈVE ===== -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalAjout">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <h4>Ajouter un élève</h4>
                    <span class="modal-subtitle">Enregistrez un nouvel élève dans le système</span>
                </div>
            </div>
            <button class="btn-close-modal" onclick="closeModalAjout()" title="Fermer">
                <i class="fas fa-times"></i> Fermer
            </button>
        </div>

        <div class="modal-body">
            <div id="modalMessageAjout" class="modal-message"></div>

            <form id="formAjout" method="POST" action="ajax_ajouter.php">
                <input type="hidden" name="ajax" value="1">

                <!-- Identité -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-user-circle"></i>
                        <span>Identité</span>
                    </div>
                    <hr class="section-divider">

                    <div class="form-row-3">
                        <div class="form-group">
                            <label>Nom <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="nom" placeholder="Nom" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Post-Nom</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <input type="text" class="form-control" name="postNom" placeholder="Post-Nom">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Prénom <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="prenom" placeholder="Prénom" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informations personnelles -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-info-circle"></i>
                        <span>Informations personnelles</span>
                    </div>
                    <hr class="section-divider">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Sexe <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-venus-mars"></i></span>
                                <select class="form-control" name="sexe" required>
                                    <option value="">Sélectionner</option>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Date de naissance</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                                <input type="date" class="form-control" name="dateNaissance">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Lieu de naissance</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                                <input type="text" class="form-control" name="lieuNaissance" placeholder="Lieu de naissance">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" name="telephone" placeholder="+243 812 345 678">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Parents -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-users"></i>
                        <span>Informations des parents</span>
                    </div>
                    <hr class="section-divider">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom du père</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-male"></i></span>
                                <input type="text" class="form-control" name="nomPere" placeholder="Nom du père">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nom de la mère</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-female"></i></span>
                                <input type="text" class="form-control" name="nomMere" placeholder="Nom de la mère">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Adresse -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-home"></i>
                        <span>Adresse</span>
                    </div>
                    <hr class="section-divider">

                    <div class="form-group">
                        <label>Adresse complète</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-address-book"></i></span>
                            <input type="text" class="form-control" name="adresse" placeholder="Adresse complète">
                        </div>
                    </div>
                </div>

                <!-- Connexion -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-key"></i>
                        <span>Connexion</span>
                    </div>
                    <hr class="section-divider">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Matricule</label>
                            <div class="matricule-display">
                                <?php 
                                    $matricule = 'ELV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                                    echo $matricule;
                                ?>
                            </div>
                            <div class="form-text" style="font-size:0.75rem; color:#a0aec0; margin-top:4px;">
                                <i class="fas fa-info-circle"></i> Généré automatiquement
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-lock"></i></span>
                                <input type="text" class="form-control" name="motDePasse" value="password123">
                            </div>
                            <div class="form-text" style="font-size:0.75rem; color:#a0aec0; margin-top:4px;">
                                <i class="fas fa-info-circle"></i> Mot de passe par défaut
                            </div>
                        </div>
                    </div>
                </div>

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
</div>

<!-- ============================================================ -->
<!-- ===== MODAL MODIFICATION ÉLÈVE ===== -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div>
                    <h4>Modifier l'élève</h4>
                    <span class="modal-subtitle">Mettez à jour les informations de l'élève</span>
                </div>
            </div>
            <button class="btn-close-modal" onclick="closeModalEdit()" title="Fermer">
                <i class="fas fa-times"></i> Fermer
            </button>
        </div>

        <div class="modal-body">
            <div id="modalMessageEdit" class="modal-message"></div>

            <form id="formEdit" method="POST" action="ajax_modifier.php">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="idEleve" id="edit_idEleve" value="">

                <!-- Informations de l'élève -->
                <div class="info-eleve">
                    <div class="info-eleve-avatar">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="info-eleve-content">
                        <div class="info-label">Élève</div>
                        <div class="info-value" id="edit_eleve_info">-</div>
                        <div class="info-badge">
                            <span class="badge-info">
                                <i class="fas fa-id-card"></i> Matricule
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Identité -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-user-circle"></i>
                        <span>Identité</span>
                    </div>
                    <hr class="section-divider">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="nom" id="edit_nom" placeholder="Nom" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Post-Nom</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                                <input type="text" class="form-control" name="postNom" id="edit_postNom" placeholder="Post-Nom">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Prénom <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" name="prenom" id="edit_prenom" placeholder="Prénom" required>
                        </div>
                    </div>
                </div>

                <!-- Informations personnelles -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-info-circle"></i>
                        <span>Informations personnelles</span>
                    </div>
                    <hr class="section-divider">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Sexe <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-venus-mars"></i></span>
                                <select class="form-control" name="sexe" id="edit_sexe" required>
                                    <option value="">Sélectionner</option>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Date de naissance</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                                <input type="date" class="form-control" name="dateNaissance" id="edit_dateNaissance">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Lieu de naissance</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                                <input type="text" class="form-control" name="lieuNaissance" id="edit_lieuNaissance" placeholder="Lieu de naissance">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" name="telephone" id="edit_telephone" placeholder="+243 812 345 678">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Parents -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-users"></i>
                        <span>Informations des parents</span>
                    </div>
                    <hr class="section-divider">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom du père</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-male"></i></span>
                                <input type="text" class="form-control" name="nomPere" id="edit_nomPere" placeholder="Nom du père">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nom de la mère</label>
                            <div class="input-group">
                                <span class="input-icon"><i class="fas fa-female"></i></span>
                                <input type="text" class="form-control" name="nomMere" id="edit_nomMere" placeholder="Nom de la mère">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Adresse -->
                <div class="form-section">
                    <div class="form-section-header">
                        <i class="fas fa-home"></i>
                        <span>Adresse</span>
                    </div>
                    <hr class="section-divider">

                    <div class="form-group">
                        <label>Adresse complète</label>
                        <div class="input-group">
                            <span class="input-icon"><i class="fas fa-address-book"></i></span>
                            <input type="text" class="form-control" name="adresse" id="edit_adresse" placeholder="Adresse complète">
                        </div>
                    </div>
                </div>

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
</div>

<!-- ============================================================ -->
<!-- ===== MODAL VOIR DÉTAILS ÉLÈVE ===== -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalVoir">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                    <h4>Détails de l'élève</h4>
                    <span class="modal-subtitle">Informations complètes de l'élève</span>
                </div>
            </div>
            <button class="btn-close-modal" onclick="closeModalVoir()" title="Fermer">
                <i class="fas fa-times"></i> Fermer
            </button>
        </div>

        <div class="modal-body">
            <div id="modalMessageVoir" class="modal-message"></div>

            <!-- Contenu des détails -->
            <div id="voirContenu">
                <div class="voir-loading" style="text-align:center; padding:40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem; color:#4299e1;"></i>
                    <p style="margin-top:10px; color:#718096;">Chargement des informations...</p>
                </div>
            </div>

            <div class="form-actions" style="margin-top:20px;">
                <button type="button" class="btn-cancel" onclick="closeModalVoir()">
                    <i class="fas fa-times"></i> Fermer
                </button>
                <button type="button" class="btn-submit" id="btnImprimerVoir" onclick="imprimerDetails()" style="display:none;">
                    <i class="fas fa-print"></i> Imprimer
                </button>
                <button type="button" class="btn-submit" id="btnRecuVoir" onclick="genererRecuFromModal()" style="display:none; background: linear-gradient(135deg, #48bb78, #38a169);">
                    <i class="fas fa-file-invoice"></i> Générer reçu
                </button>
            </div>
        </div>
    </div>
</div>

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
            <li><a href="index.php" class="active"><i class="fas fa-user-graduate"></i> Liste des élèves</a></li>
            <li><a href="../inscriptions/index.php"><i class="fas fa-list"></i> Liste des inscriptions</a></li>            
            <li><a href="../classes/index.php"><i class="fas fa-list"></i> Liste des classes</a></li>
            <li><a href="../annees/index.php"><i class="fas fa-calendar-alt"></i> Années scolaires</a></li>
            <li><a href="../profil.php"><i class="fas fa-user-circle"></i> Mon profil</a></li>
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
                <h2><i class="fas fa-users"></i> Liste des élèves</h2>
                <small id="totalEleves"><?php echo $total_eleves; ?> élève(s) enregistré(s)</small>
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
                <button class="btn-add" onclick="openModalAjout()">
                    <i class="fas fa-plus"></i> Nouvel élève
                </button>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="number" id="statTotal"><?php echo $total_eleves; ?></div>
                <div class="label">Total élèves</div>
            </div>
            <div class="stat-card green">
                <div class="number" id="statGarcons"><?php echo $total_garcons; ?></div>
                <div class="label">Garçons</div>
            </div>
            <div class="stat-card purple">
                <div class="number" id="statFilles"><?php echo $total_filles; ?></div>
                <div class="label">Filles</div>
            </div>
            <div class="stat-card orange">
                <div class="number" id="statInscrits">
                    <?php 
                        $inscrits = 0;
                        foreach ($eleves as $e) {
                            if ($e['nomClasse']) $inscrits++;
                        }
                        echo $inscrits;
                    ?>
                </div>
                <div class="label">Inscrits</div>
            </div>
        </div>

        <!-- ===== FILTRE ===== -->
        <div class="filter-bar">
            <form method="GET" action="">
                <input type="text" name="search" placeholder="🔍 Rechercher par nom, prénom ou matricule..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Rechercher
                </button>
                <?php if (!empty($search)): ?>
                    <a href="index.php" class="btn-reset">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ===== TABLEAU ===== -->
        <div class="table-box">
            <div class="table-header">
                <span class="title"><i class="fas fa-list"></i> Liste des élèves</span>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Rechercher dans le tableau...">
                </div>
            </div>
            <div class="table-scroll">
                <table class="table" id="elevesTable">
                    <thead>
                        <tr>
                            <th style="width:45px;">#</th>
                            <th>Matricule</th>
                            <th>Nom complet</th>
                            <th>Sexe</th>
                            <th>Téléphone</th>
                            <th>Classe</th>
                            <th style="text-align:center; width:180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eleves)): ?>
                            <tr id="emptyRow">
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h5>Aucun élève</h5>
                                        <p>Ajoutez votre premier élève</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eleves as $i => $e): 
                                $nomComplet = trim($e['nom'] . ' ' . ($e['postNom'] ?? '') . ' ' . $e['prenom']);
                                $classe = $e['nomClasse'] ?? 'Non inscrit';
                                $classeBadge = $e['nomClasse'] ? 'inscrit' : 'non-inscrit';
                                $estInscrit = $e['nomClasse'] ? true : false;
                            ?>
                                <tr data-id="<?php echo $e['idEleve']; ?>">
                                    <td><?php echo $i + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($e['matricule']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($nomComplet); ?></td>
                                    <td>
                                        <span class="badge-sexe <?php echo $e['sexe']; ?>">
                                            <?php echo $e['sexe'] === 'M' ? 'Masculin' : 'Féminin'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($e['telephone'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge-classe <?php echo $classeBadge; ?>">
                                            <?php echo htmlspecialchars($classe); ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <div class="btn-actions">
                                            <!-- Bouton Voir -->
                                            <button class="btn btn-view" title="Voir les détails" onclick="openModalVoir(<?php echo $e['idEleve']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <!-- Bouton Modifier -->
                                            <button class="btn btn-edit" title="Modifier" onclick="openModalEdit(<?php echo $e['idEleve']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Bouton Reçu d'inscription (si inscrit) -->
                                            <?php if ($estInscrit): ?>
                                                <button class="btn btn-recu" title="Générer le reçu d'inscription" onclick="genererRecu(<?php echo $e['idEleve']; ?>)">
                                                    <i class="fas fa-file-invoice"></i>
                                                </button>
                                            <?php endif; ?>
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
    // ============================================================
    // MODAL AJOUT
    // ============================================================
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

    // ============================================================
    // MODAL MODIFICATION
    // ============================================================
    function openModalEdit(id) {
        const modal = document.getElementById('modalEdit');
        const messageDiv = document.getElementById('modalMessageEdit');
        
        messageDiv.innerHTML = '';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        fetch('ajax_get_eleve.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_idEleve').value = data.idEleve;
                    document.getElementById('edit_eleve_info').textContent = data.matricule + ' - ' + data.nom + ' ' + data.prenom;
                    document.getElementById('edit_nom').value = data.nom;
                    document.getElementById('edit_postNom').value = data.postNom || '';
                    document.getElementById('edit_prenom').value = data.prenom;
                    document.getElementById('edit_sexe').value = data.sexe;
                    document.getElementById('edit_dateNaissance').value = data.dateNaissance || '';
                    document.getElementById('edit_lieuNaissance').value = data.lieuNaissance || '';
                    document.getElementById('edit_telephone').value = data.telephone || '';
                    document.getElementById('edit_nomPere').value = data.nomPere || '';
                    document.getElementById('edit_nomMere').value = data.nomMere || '';
                    document.getElementById('edit_adresse').value = data.adresse || '';
                } else {
                    messageDiv.innerHTML = `
                        <div class="alert-box danger">
                            <i class="fas fa-exclamation-circle"></i>
                            ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                messageDiv.innerHTML = `
                    <div class="alert-box danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Erreur lors du chargement des données.
                    </div>
                `;
            });
    }

    function closeModalEdit() {
        document.getElementById('modalEdit').classList.remove('active');
        document.body.style.overflow = '';
    }

    // ============================================================
    // MODAL VOIR DÉTAILS
    // ============================================================
    function openModalVoir(id) {
        const modal = document.getElementById('modalVoir');
        const messageDiv = document.getElementById('modalMessageVoir');
        const contenu = document.getElementById('voirContenu');
        
        messageDiv.innerHTML = '';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Afficher le chargement
        contenu.innerHTML = `
            <div class="voir-loading" style="text-align:center; padding:40px;">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem; color:#4299e1;"></i>
                <p style="margin-top:10px; color:#718096;">Chargement des informations...</p>
            </div>
        `;
        document.getElementById('btnImprimerVoir').style.display = 'none';
        document.getElementById('btnRecuVoir').style.display = 'none';
        
        fetch('ajax_get_eleve_details.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = `
                        <div class="voir-card">
                            <div class="voir-header">
                                <div class="voir-avatar">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <div class="voir-header-info">
                                    <h3>${data.nom} ${data.prenom}</h3>
                                    <span class="voir-matricule"><i class="fas fa-id-card"></i> ${data.matricule}</span>
                                    <span class="voir-statut ${data.est_inscrit ? 'inscrit' : 'non-inscrit'}">
                                        <i class="fas ${data.est_inscrit ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                                        ${data.est_inscrit ? 'Inscrit' : 'Non inscrit'}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="voir-body">
                                <div class="voir-section">
                                    <h5><i class="fas fa-info-circle"></i> Informations personnelles</h5>
                                    <div class="voir-grid">
                                        <div class="voir-item">
                                            <span class="voir-label">Sexe</span>
                                            <span class="voir-value">${data.sexe === 'M' ? 'Masculin' : 'Féminin'}</span>
                                        </div>
                                        <div class="voir-item">
                                            <span class="voir-label">Date de naissance</span>
                                            <span class="voir-value">${data.dateNaissance || 'Non renseignée'}</span>
                                        </div>
                                        <div class="voir-item">
                                            <span class="voir-label">Lieu de naissance</span>
                                            <span class="voir-value">${data.lieuNaissance || 'Non renseigné'}</span>
                                        </div>
                                        <div class="voir-item">
                                            <span class="voir-label">Téléphone</span>
                                            <span class="voir-value">${data.telephone || 'Non renseigné'}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="voir-section">
                                    <h5><i class="fas fa-users"></i> Parents</h5>
                                    <div class="voir-grid">
                                        <div class="voir-item">
                                            <span class="voir-label">Père</span>
                                            <span class="voir-value">${data.nomPere || 'Non renseigné'}</span>
                                        </div>
                                        <div class="voir-item">
                                            <span class="voir-label">Mère</span>
                                            <span class="voir-value">${data.nomMere || 'Non renseigné'}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="voir-section">
                                    <h5><i class="fas fa-home"></i> Adresse</h5>
                                    <div class="voir-item">
                                        <span class="voir-value">${data.adresse || 'Non renseignée'}</span>
                                    </div>
                                </div>
                                
                                ${data.est_inscrit ? `
                                <div class="voir-section">
                                    <h5><i class="fas fa-book-open"></i> Inscription</h5>
                                    <div class="voir-grid">
                                        <div class="voir-item">
                                            <span class="voir-label">Classe</span>
                                            <span class="voir-value">${data.nomClasse || 'Non définie'}</span>
                                        </div>
                                        <div class="voir-item">
                                            <span class="voir-label">Niveau</span>
                                            <span class="voir-value">${data.nomNiveau || 'Non défini'}</span>
                                        </div>
                                        <div class="voir-item">
                                            <span class="voir-label">Date d'inscription</span>
                                            <span class="voir-value">${data.dateInscription || 'Non définie'}</span>
                                        </div>
                                        <div class="voir-item">
                                            <span class="voir-label">Statut</span>
                                            <span class="voir-value"><span class="badge-statut actif">${data.statut || 'Actif'}</span></span>
                                        </div>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                    
                    contenu.innerHTML = html;
                    
                    // Afficher les boutons
                    document.getElementById('btnImprimerVoir').style.display = 'inline-flex';
                    if (data.est_inscrit) {
                        document.getElementById('btnRecuVoir').style.display = 'inline-flex';
                    }
                } else {
                    contenu.innerHTML = `
                        <div class="alert-box danger">
                            <i class="fas fa-exclamation-circle"></i>
                            ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                contenu.innerHTML = `
                    <div class="alert-box danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Erreur lors du chargement des données.
                    </div>
                `;
            });
    }

    function closeModalVoir() {
        document.getElementById('modalVoir').classList.remove('active');
        document.body.style.overflow = '';
    }

    // ============================================================
    // IMPRIMER LES DÉTAILS
    // ============================================================
    function imprimerDetails() {
        const contenu = document.getElementById('voirContenu');
        const contenuHtml = contenu.innerHTML;
        
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Détails de l'élève</title>
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                    <style>
                        body { font-family: Arial, sans-serif; padding: 30px; }
                        .voir-card { max-width: 800px; margin: 0 auto; }
                        .voir-header { background: #ebf8ff; padding: 20px; border-radius: 8px; }
                        .voir-avatar { display: inline-block; width: 60px; height: 60px; background: #4299e1; border-radius: 50%; text-align: center; line-height: 60px; color: white; font-size: 2rem; }
                        .voir-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
                        .voir-item { margin: 5px 0; }
                        .voir-label { font-weight: bold; color: #718096; font-size: 0.8rem; }
                        .voir-value { display: block; font-size: 1rem; }
                        @media print { .no-print { display: none; } }
                    </style>
                </head>
                <body>
                    ${contenuHtml}
                    <div style="text-align:center; margin-top:30px; color:#a0aec0; font-size:0.8rem;">
                        Document imprimé le ${new Date().toLocaleDateString()} à ${new Date().toLocaleTimeString()}
                    </div>
                </body>
            </html>
        `);
        printWindow.document.close();
        setTimeout(() => {
            printWindow.print();
        }, 500);
    }

    // ============================================================
    // GÉNÉRER REÇU D'INSCRIPTION
    // ============================================================
    function genererRecu(id) {
        showToast('info', 'Génération du reçu en cours...');
        window.open('generer_recu.php?id=' + id, '_blank');
    }

    function genererRecuFromModal() {
        const id = document.getElementById('edit_idEleve').value || 
                   document.querySelector('#voirContenu .voir-matricule')?.getAttribute('data-id');
        if (id) {
            genererRecu(id);
        } else {
            showToast('error', 'Impossible de générer le reçu.');
        }
    }

    // ============================================================
    // FERMER LES MODALS AVEC ÉCHAP
    // ============================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('modalAjout').classList.contains('active')) {
                closeModalAjout();
            }
            if (document.getElementById('modalEdit').classList.contains('active')) {
                closeModalEdit();
            }
            if (document.getElementById('modalVoir').classList.contains('active')) {
                closeModalVoir();
            }
        }
    });

    // ============================================================
    // FERMER EN CLIQUANT SUR L'OVERLAY
    // ============================================================
    document.getElementById('modalAjout').addEventListener('click', function(e) {
        if (e.target === this) closeModalAjout();
    });
    document.getElementById('modalEdit').addEventListener('click', function(e) {
        if (e.target === this) closeModalEdit();
    });
    document.getElementById('modalVoir').addEventListener('click', function(e) {
        if (e.target === this) closeModalVoir();
    });

    // ============================================================
    // SOUMISSION AJAX AJOUT
    // ============================================================
    document.getElementById('formAjout').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const formData = new FormData(form);
        const messageDiv = document.getElementById('modalMessageAjout');
        const submitBtn = document.getElementById('btnSubmitAjout');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
        
        fetch('ajax_ajouter.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageDiv.innerHTML = `
                    <div class="alert-box success">
                        <i class="fas fa-check-circle"></i>
                        ${data.message}
                    </div>
                `;
                showToast('success', 'Élève ajouté avec succès !');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                messageDiv.innerHTML = `
                    <div class="alert-box danger">
                        <i class="fas fa-exclamation-circle"></i>
                        ${data.message}
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';
            }
        })
        .catch(error => {
            messageDiv.innerHTML = `
                <div class="alert-box danger">
                    <i class="fas fa-exclamation-circle"></i>
                    Une erreur est survenue.
                </div>
            `;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';
        });
    });

    // ============================================================
    // SOUMISSION AJAX MODIFICATION
    // ============================================================
    document.getElementById('formEdit').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const formData = new FormData(form);
        const messageDiv = document.getElementById('modalMessageEdit');
        const submitBtn = document.getElementById('btnSubmitEdit');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mise à jour...';
        
        fetch('ajax_modifier.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                messageDiv.innerHTML = `
                    <div class="alert-box success">
                        <i class="fas fa-check-circle"></i>
                        ${data.message}
                    </div>
                `;
                showToast('success', 'Élève modifié avec succès !');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                messageDiv.innerHTML = `
                    <div class="alert-box danger">
                        <i class="fas fa-exclamation-circle"></i>
                        ${data.message}
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Mettre à jour';
            }
        })
        .catch(error => {
            messageDiv.innerHTML = `
                <div class="alert-box danger">
                    <i class="fas fa-exclamation-circle"></i>
                    Une erreur est survenue.
                </div>
            `;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Mettre à jour';
        });
    });

    // ============================================================
    // TOAST NOTIFICATION
    // ============================================================
    function showToast(type, message) {
        const existing = document.querySelector('.toast-notification');
        if (existing) existing.remove();
        
        const toast = document.createElement('div');
        toast.className = 'toast-notification ' + type;
        
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        else if (type === 'error') icon = 'fa-exclamation-circle';
        
        toast.innerHTML = `<i class="fas ${icon}"></i> ${message}`;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ============================================================
    // RECHERCHE DANS LE TABLEAU
    // ============================================================
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#elevesTable tbody tr:not(#emptyRow)').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });

    // ============================================================
    // SIDEBAR MOBILE
    // ============================================================
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