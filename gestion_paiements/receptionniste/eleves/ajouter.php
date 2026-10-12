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

    .btn-actions {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    .btn-actions .btn {
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
    .btn-actions .btn-view { background: #dbeafe; color: #1e40af; }
    .btn-actions .btn-view:hover { background: #bfdbfe; }
    .btn-actions .btn-edit { background: #fefcbf; color: #975a16; }
    .btn-actions .btn-edit:hover { background: #f6e05e; }
    .btn-actions .btn-delete { background: #fff5f5; color: #c53030; }
    .btn-actions .btn-delete:hover { background: #fed7d7; }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #a0aec0;
    }
    .empty-state i { font-size: 2.5rem; opacity: 0.3; margin-bottom: 10px; display: block; }
    .empty-state h5 { color: #4a5568; font-weight: 600; font-size: 0.9rem; margin-bottom: 4px; }
    .empty-state p { font-size: 0.85rem; margin: 0; }

    /* ============================================================ */
    /* ===== MODAL AJOUT ===== */
    /* ============================================================ */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
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
        max-width: 650px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 25px 30px;
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
        margin-bottom: 15px;
        border-bottom: 2px solid #f0f2f5;
    }
    .modal-header h4 {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a202c;
        margin: 0;
    }
    .modal-header h4 i {
        color: #4299e1;
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

    .modal-body .form-group {
        margin-bottom: 14px;
    }
    .modal-body .form-group label {
        display: block;
        font-weight: 600;
        font-size: 0.8rem;
        color: #2d3748;
        margin-bottom: 4px;
    }
    .modal-body .form-group label .required {
        color: #e53e3e;
        margin-left: 2px;
    }
    .modal-body .form-group .form-control,
    .modal-body .form-group .form-select {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.85rem;
        transition: all 0.3s;
        background: white;
    }
    .modal-body .form-group .form-control:focus,
    .modal-body .form-group .form-select:focus {
        border-color: #4299e1;
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        outline: none;
    }
    .modal-body .form-group .form-text {
        font-size: 0.7rem;
        color: #a0aec0;
        margin-top: 3px;
    }

    .modal-body .row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 12px;
    }
    .modal-body .row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .modal-body .matricule-display {
        background: #f7fafc;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        font-weight: 600;
        color: #4a5568;
        font-size: 0.85rem;
    }

    .modal-body .section-title {
        font-weight: 700;
        color: #4299e1;
        font-size: 0.8rem;
        margin: 15px 0 8px 0;
    }
    .modal-body .section-title i { margin-right: 6px; }
    .modal-body .section-divider {
        margin: 0 0 10px 0;
        border: 0;
        border-top: 1px solid #edf2f7;
    }

    .modal-body .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 15px;
        margin-top: 10px;
        border-top: 2px solid #f0f2f5;
    }
    .modal-body .btn-cancel {
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        border: 2px solid #e2e8f0;
        background: white;
        color: #4a5568;
        cursor: pointer;
        transition: all 0.3s;
    }
    .modal-body .btn-cancel:hover {
        background: #f7fafc;
    }
    .modal-body .btn-submit {
        padding: 8px 25px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        border: none;
        background: linear-gradient(135deg, #4299e1, #3182ce);
        color: white;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .modal-body .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(66, 153, 225, 0.35);
    }

    .modal-body .alert-box {
        padding: 8px 14px;
        border-radius: 8px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
    }
    .modal-body .alert-box.danger {
        background: #fff5f5;
        color: #c53030;
        border: 1px solid #fed7d7;
    }
    .modal-body .alert-box.success {
        background: #f0fff4;
        color: #276749;
        border: 1px solid #c6f6d5;
    }
    .modal-body .alert-box i { font-size: 0.9rem; }

    /* ===== MODAL MODIFICATION ===== */
    .modal-body .info-eleve {
        background: #f7fafc;
        padding: 10px 14px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 3px solid #4299e1;
    }
    .modal-body .info-eleve .info-label {
        font-size: 0.6rem;
        color: #718096;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .modal-body .info-eleve .info-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: #1a202c;
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
            margin: 20px;
            padding: 20px;
            max-height: 95vh;
        }
        .modal-body .row {
            grid-template-columns: 1fr;
        }
        .modal-body .row-2 {
            grid-template-columns: 1fr;
        }
        .modal-body .form-actions {
            flex-direction: column;
        }
        .modal-body .form-actions .btn-cancel,
        .modal-body .form-actions .btn-submit {
            width: 100%;
            justify-content: center;
        }
        .modal-header {
            flex-direction: column;
            gap: 10px;
            text-align: center;
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
        .modal-overlay .modal-box { padding: 15px; margin: 10px; }
        .modal-header h4 { font-size: 0.95rem; }
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
            <h4><i class="fas fa-user-plus"></i> Ajouter un élève</h4>
            <button class="btn-close-modal" onclick="closeModalAjout()">
                <i class="fas fa-times"></i> Fermer
            </button>
        </div>

        <div id="modalMessageAjout"></div>

        <form id="formAjout" method="POST" action="ajax_ajouter.php">
            <input type="hidden" name="ajax" value="1">

            <!-- Identité -->
            <div class="section-title"><i class="fas fa-user"></i> Identité</div>
            <hr class="section-divider">

            <div class="row">
                <div class="form-group">
                    <label>Nom <span class="required">*</span></label>
                    <input type="text" class="form-control" name="nom" placeholder="Nom" required>
                </div>
                <div class="form-group">
                    <label>Post-Nom</label>
                    <input type="text" class="form-control" name="postNom" placeholder="Post-Nom">
                </div>
                <div class="form-group">
                    <label>Prénom <span class="required">*</span></label>
                    <input type="text" class="form-control" name="prenom" placeholder="Prénom" required>
                </div>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label>Sexe <span class="required">*</span></label>
                    <select class="form-select" name="sexe" required>
                        <option value="">Sélectionner</option>
                        <option value="M">Masculin</option>
                        <option value="F">Féminin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date de naissance</label>
                    <input type="date" class="form-control" name="dateNaissance">
                </div>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label>Lieu de naissance</label>
                    <input type="text" class="form-control" name="lieuNaissance" placeholder="Lieu de naissance">
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" class="form-control" name="telephone" placeholder="+243 812 345 678">
                </div>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label>Nom du père</label>
                    <input type="text" class="form-control" name="nomPere" placeholder="Nom du père">
                </div>
                <div class="form-group">
                    <label>Nom de la mère</label>
                    <input type="text" class="form-control" name="nomMere" placeholder="Nom de la mère">
                </div>
            </div>

            <div class="form-group">
                <label>Adresse</label>
                <input type="text" class="form-control" name="adresse" placeholder="Adresse complète">
            </div>

            <!-- Connexion -->
            <div class="section-title"><i class="fas fa-key"></i> Connexion</div>
            <hr class="section-divider">

            <div class="row-2">
                <div class="form-group">
                    <label>Matricule</label>
                    <div class="matricule-display">
                        <?php 
                            $matricule = 'ELV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                            echo $matricule;
                        ?>
                    </div>
                    <div class="form-text"><i class="fas fa-info-circle"></i> Généré automatiquement</div>
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="text" class="form-control" name="motDePasse" value="password123">
                    <div class="form-text"><i class="fas fa-info-circle"></i> Mot de passe par défaut</div>
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

<!-- ============================================================ -->
<!-- ===== MODAL MODIFICATION ÉLÈVE ===== -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-edit"></i> Modifier l'élève</h4>
            <button class="btn-close-modal" onclick="closeModalEdit()">
                <i class="fas fa-times"></i> Fermer
            </button>
        </div>

        <div id="modalMessageEdit"></div>

        <form id="formEdit" method="POST" action="ajax_modifier.php">
            <input type="hidden" name="ajax" value="1">
            <input type="hidden" name="idEleve" id="edit_idEleve" value="">

            <div class="info-eleve">
                <div class="info-label">Élève</div>
                <div class="info-value" id="edit_eleve_info">-</div>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label>Nom <span class="required">*</span></label>
                    <input type="text" class="form-control" name="nom" id="edit_nom" placeholder="Nom" required>
                </div>
                <div class="form-group">
                    <label>Post-Nom</label>
                    <input type="text" class="form-control" name="postNom" id="edit_postNom" placeholder="Post-Nom">
                </div>
            </div>

            <div class="form-group">
                <label>Prénom <span class="required">*</span></label>
                <input type="text" class="form-control" name="prenom" id="edit_prenom" placeholder="Prénom" required>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label>Sexe <span class="required">*</span></label>
                    <select class="form-select" name="sexe" id="edit_sexe" required>
                        <option value="">Sélectionner</option>
                        <option value="M">Masculin</option>
                        <option value="F">Féminin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date de naissance</label>
                    <input type="date" class="form-control" name="dateNaissance" id="edit_dateNaissance">
                </div>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label>Lieu de naissance</label>
                    <input type="text" class="form-control" name="lieuNaissance" id="edit_lieuNaissance" placeholder="Lieu de naissance">
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" class="form-control" name="telephone" id="edit_telephone" placeholder="+243 812 345 678">
                </div>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label>Nom du père</label>
                    <input type="text" class="form-control" name="nomPere" id="edit_nomPere" placeholder="Nom du père">
                </div>
                <div class="form-group">
                    <label>Nom de la mère</label>
                    <input type="text" class="form-control" name="nomMere" id="edit_nomMere" placeholder="Nom de la mère">
                </div>
            </div>

            <div class="form-group">
                <label>Adresse</label>
                <input type="text" class="form-control" name="adresse" id="edit_adresse" placeholder="Adresse complète">
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
            <li><a href="../dashboard.php"><i class="fas fa-chart-pie"></i> Tableau de bord</a></li>
            
            <li class="menu-section"><i class="fas fa-users"></i> Élèves</li>
            <li><a href="index.php" class="active"><i class="fas fa-user-graduate"></i> Liste des élèves</a></li>
            
            <li class="menu-section"><i class="fas fa-book-open"></i> Inscriptions</li>
            <li><a href="../inscriptions/index.php"><i class="fas fa-list"></i> Liste des inscriptions</a></li>
            <li><a href="../inscriptions/ajouter.php"><i class="fas fa-plus-circle"></i> Nouvelle inscription</a></li>
            
            <li class="menu-section"><i class="fas fa-chalkboard"></i> Classes</li>
            <li><a href="../classes/index.php"><i class="fas fa-list"></i> Liste des classes</a></li>
            
            <li class="menu-section"><i class="fas fa-calendar-alt"></i> Années</li>
            <li><a href="../annees/index.php"><i class="fas fa-list"></i> Années scolaires</a></li>
            
            <li class="menu-section"><i class="fas fa-user-circle"></i> Compte</li>
            <li><a href="../profil.php"><i class="fas fa-user-circle"></i> Mon profil</a></li>
            <li><a href="../notifications.php"><i class="fas fa-bell"></i> Notifications
                <?php if ($nbNonLues > 0): ?>
                    <span style="background:#e53e3e; color:white; font-size:0.55rem; padding:2px 8px; border-radius:50%; margin-left:auto;"><?php echo $nbNonLues; ?></span>
                <?php endif; ?>
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

        <!-- ===== EN-TÊTE ===== -->
        <div class="header-bar">
            <div>
                <h2><i class="fas fa-users"></i> Liste des élèves</h2>
                <small><?php echo $total_eleves; ?> élève(s) enregistré(s)</small>
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
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $total_eleves; ?></div>
                <div class="label">Total élèves</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo $total_garcons; ?></div>
                <div class="label">Garçons</div>
            </div>
            <div class="stat-card purple">
                <div class="number"><?php echo $total_filles; ?></div>
                <div class="label">Filles</div>
            </div>
            <div class="stat-card orange">
                <div class="number">
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
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eleves)): ?>
                            <tr>
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
                            ?>
                                <tr>
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
                                            <a href="voir.php?id=<?php echo $e['idEleve']; ?>" class="btn btn-view" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-edit" title="Modifier" onclick="openModalEdit(<?php echo $e['idEleve']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="supprimer.php?id=<?php echo $e['idEleve']; ?>" 
                                               class="btn btn-delete" 
                                               title="Supprimer"
                                               onclick="return confirm('Supprimer cet élève ?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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

    // ===== SOUMISSION AJAX AJOUT =====
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

    // ===== SOUMISSION AJAX MODIFICATION =====
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

    // ===== RECHERCHE =====
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('#elevesTable tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
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