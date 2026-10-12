<?php
// admin/eleves/index.php - Gestion des élèves
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Eleve.php';

$page_title = 'Gestion des Élèves - Administrateur';
$db = db();
$eleveModel = new Eleve();
$eleves = $eleveModel->getAll();

$message = '';
if (isset($_SESSION['flash'])) {
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ===== TRAITEMENT DU FORMULAIRE MODAL =====
$modal_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_eleve') {
    $matricule = trim($_POST['matricule'] ?? '');
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
    $motDePasse = trim($_POST['motDePasse'] ?? 'password123');
    
    if (empty($nom) || empty($prenom) || empty($sexe)) {
        $modal_error = 'Le nom, le prénom et le sexe sont obligatoires.';
        $_SESSION['show_modal'] = true;
    } else {
        $data = [
            'matricule' => $matricule,
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
            'telephone' => $telephone,
            'motDePasse' => password_hash($motDePasse, PASSWORD_DEFAULT)
        ];
        
        if ($eleveModel->create($data)) {
            $_SESSION['flash'] = [
                'type' => 'success',
                'text' => '✅ Élève ajouté avec succès ! Matricule: ' . $matricule
            ];
            header('Location: index.php');
            exit();
        } else {
            $modal_error = 'Erreur lors de l\'ajout de l\'élève.';
            $_SESSION['show_modal'] = true;
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ===== RESET ===== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

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

    .sidebar-admin::-webkit-scrollbar {
        width: 3px;
    }
    .sidebar-admin::-webkit-scrollbar-thumb {
        background: #4a5568;
        border-radius: 4px;
    }

    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 16px;
        margin-bottom: 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .sidebar-logo i {
        font-size: 1.3rem;
        color: #667eea;
    }
    .sidebar-logo span {
        font-size: 1rem;
        font-weight: 700;
        color: white;
        flex: 1;
    }

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

    .sidebar-footer {
        padding-top: 16px;
        margin-top: 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
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

    /* ===== CONTENU PRINCIPAL ===== */
    .main-content {
        flex: 1;
        padding: 20px 25px;
        background: #f8fafc;
        min-height: 100vh;
        max-height: 100vh;
        overflow-y: auto;
    }

    /* ===== EN-TETE ===== */
    .header-bar {
        background: white;
        padding: 14px 20px;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
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
    .header-bar h2 i {
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
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
        color: white !important;
        text-decoration: none;
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
    .alert-box .close-btn:hover {
        opacity: 1;
    }

    /* ===== STATISTIQUES ===== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: white;
        padding: 12px 16px;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        text-align: center;
        border-left: 3px solid #667eea;
    }
    .stat-card .number {
        font-size: 1.3rem;
        font-weight: 700;
        color: #1a202c;
    }
    .stat-card .label {
        font-size: 0.7rem;
        color: #718096;
        margin-top: 2px;
    }
    .stat-card.green {
        border-color: #48bb78;
    }
    .stat-card.orange {
        border-color: #ed8936;
    }
    .stat-card.blue {
        border-color: #4299e1;
    }

    /* ===== TABLEAU ===== */
    .table-box {
        background: white;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
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

    .table-scroll {
        overflow-x: auto;
    }
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
        min-width: 600px;
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
    .badge-role {
        padding: 2px 12px;
        border-radius: 16px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-role.actif {
        background: #f0fff4;
        color: #276749;
    }
    .badge-role.termine {
        background: #edf2f7;
        color: #4a5568;
    }
    .badge-role.non-inscrit {
        background: #fefcbf;
        color: #975a16;
    }

    .badge-status {
        padding: 2px 12px;
        border-radius: 16px;
        font-size: 0.6rem;
        font-weight: 600;
        display: inline-block;
    }
    .badge-status.masculin {
        background: #ebf8ff;
        color: #2b6cb0;
    }
    .badge-status.feminin {
        background: #faf5ff;
        color: #6b46c1;
    }
    .badge-status.classe {
        background: #ebf8ff;
        color: #2b6cb0;
    }

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
    }
    .actions .btn-view {
        background: #ebf8ff;
        color: #2b6cb0;
    }
    .actions .btn-view:hover {
        background: #bee3f8;
        color: #2b6cb0;
    }
    .actions .btn-edit {
        background: #fefcbf;
        color: #975a16;
    }
    .actions .btn-edit:hover {
        background: #f6e05e;
        color: #975a16;
    }
    .actions .btn-delete {
        background: #fff5f5;
        color: #c53030;
    }
    .actions .btn-delete:hover {
        background: #fed7d7;
        color: #c53030;
    }

    /* ===== EMPTY STATE ===== */
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

    /* ===== MODAL ===== */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
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
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    .modal-content {
        background: white;
        border-radius: 16px;
        max-width: 700px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px;
        animation: slideUp 0.4s ease;
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.2);
    }
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(40px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
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
    .modal-close:hover {
        background: #edf2f7;
    }
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
    .modal-body .alert-box {
        padding: 12px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .modal-body .alert-box.danger {
        background: #fff5f5;
        color: #c53030;
        border: 1px solid #fed7d7;
    }
    .modal-body .alert-box i {
        font-size: 1.1rem;
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
    .modal-body .btn-cancel:hover {
        background: #f7fafc;
        border-color: #cbd5e0;
    }
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
        .main-content {
            padding: 12px !important;
            max-height: none !important;
        }
        .header-bar {
            flex-direction: column;
            text-align: center;
            padding: 12px 16px;
        }
        .header-bar h2 {
            font-size: 1rem;
        }
        .header-bar small {
            font-size: 0.7rem;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        .stat-card {
            padding: 10px 12px;
        }
        .stat-card .number {
            font-size: 1.1rem;
        }
        .stat-card .label {
            font-size: 0.65rem;
        }
        .table-header {
            flex-direction: column;
            align-items: stretch;
        }
        .search-box {
            width: 100%;
        }
        .search-box input {
            width: 100%;
        }
        .table {
            font-size: 0.7rem;
        }
        .table th,
        .table td {
            padding: 5px 8px;
        }
        .btn-add {
            width: 100%;
            justify-content: center;
        }
        .actions .btn {
            padding: 2px 6px;
            font-size: 0.6rem;
        }
        .modal-content {
            padding: 20px;
            margin: 10px;
        }
        .modal-body .form-actions {
            flex-direction: column;
        }
        .modal-body .form-actions .btn-cancel,
        .modal-body .form-actions .btn-submit {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 6px;
        }
        .stat-card {
            padding: 8px 10px;
        }
        .stat-card .number {
            font-size: 0.95rem;
        }
        .stat-card .label {
            font-size: 0.6rem;
        }
        .header-bar h2 {
            font-size: 0.85rem;
        }
        .header-bar small {
            font-size: 0.65rem;
        }
        .table th,
        .table td {
            padding: 4px 6px;
            font-size: 0.65rem;
        }
        .table {
            min-width: 500px;
        }
        .modal-content {
            padding: 15px;
        }
        .modal-header h4 {
            font-size: 1rem;
        }
        .modal-body .form-group .form-control,
        .modal-body .form-group .form-select {
            padding: 8px 12px;
            font-size: 0.85rem;
            border-radius: 8px;
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

<!-- ===== OVERLAY ET TOGGLE ===== -->
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
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <ul class="sidebar-menu">
            <li class="menu-section">Administration</li>
            <li>
                <a href="../dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="index.php" class="active">
                    <i class="fas fa-users"></i> Élèves
                </a>
            </li>
            <li>
                <a href="../inscriptions/index.php">
                    <i class="fas fa-book-open"></i> Inscriptions
                </a>
            </li>
            <li>
                <a href="../paiements/index.php">
                    <i class="fas fa-money-bill-wave"></i> Paiements
                </a>
            </li>
            <li>
                <a href="../frais/index.php">
                    <i class="fas fa-tag"></i> Frais
                </a>
            </li>
            <li>
                <a href="../classes/index.php">
                    <i class="fas fa-chalkboard"></i> Classes
                </a>
            </li>
            <li>
                <a href="../annees/index.php">
                    <i class="fas fa-calendar-alt"></i> Années scolaires
                </a>
            </li>
            <li>
                <a href="../utilisateurs/index.php">
                    <i class="fas fa-user-cog"></i> Utilisateurs
                </a>
            </li>
            <li class="menu-section">Rapports</li>
            <li>
                <a href="../rapports/index.php">
                    <i class="fas fa-file-alt"></i> Rapports
                </a>
            </li>
            <li>
                <a href="../journal/index.php">
                    <i class="fas fa-history"></i> Journal
                </a>
            </li>
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

        <!-- EN-TETE -->
        <div class="header-bar">
            <div>
                <h2><i class="fas fa-users"></i> Gestion des Élèves</h2>
                <small>Gérez les informations des élèves</small>
            </div>
            <button class="btn-add" onclick="openModal()">
                <i class="fas fa-plus"></i> Nouvel élève
            </button>
        </div>

        <!-- MESSAGE FLASH -->
        <?php if (!empty($message)): ?>
            <div class="alert-box <?php echo $message['type'] == 'success' ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $message['type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message['text']; ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <!-- STATISTIQUES -->
        <?php
        $total_eleves = count($eleves);
        $inscrits = 0;
        $termines = 0;
        $non_inscrits = 0;

        foreach ($eleves as $e) {
            if ($e['statutInscription'] === 'Actif') {
                $inscrits++;
            } elseif ($e['statutInscription'] === 'Terminé') {
                $termines++;
            } else {
                $non_inscrits++;
            }
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $total_eleves; ?></div>
                <div class="label">Total élèves</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo $inscrits; ?></div>
                <div class="label">Inscrits</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo $termines; ?></div>
                <div class="label">Terminé</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $non_inscrits; ?></div>
                <div class="label">Non inscrits</div>
            </div>
        </div>

        <!-- TABLEAU -->
        <div class="table-box">
            <div class="table-header">
                <span class="title"><i class="fas fa-list"></i> Liste des élèves</span>
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
                            <th>Matricule</th>
                            <th>Nom complet</th>
                            <th>Sexe</th>
                            <th>Classe</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th style="text-align:center; width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eleves)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h5>Aucun élève</h5>
                                        <p>Ajoutez votre premier élève</p>
                                        <button class="btn btn-primary btn-sm mt-2" onclick="openModal()">
                                            <i class="fas fa-plus me-1"></i> Ajouter un élève
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($eleves as $i => $e): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($e['matricule']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($e['nom'] . ' ' . ($e['postNom'] ?? '') . ' ' . $e['prenom']); ?>
                                        <small class="text-muted d-block">Né le <?php echo $e['dateNaissance'] ? date('d/m/Y', strtotime($e['dateNaissance'])) : '-'; ?></small>
                                    </td>
                                    <td>
                                        <?php if ($e['sexe'] === 'M'): ?>
                                            <span class="badge-status masculin">Masculin</span>
                                        <?php elseif ($e['sexe'] === 'F'): ?>
                                            <span class="badge-status feminin">Féminin</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($e['nomClasse']): ?>
                                            <span class="badge-status classe">
                                                <i class="fas fa-chalkboard me-1" style="font-size:0.6rem;"></i>
                                                <?php echo htmlspecialchars($e['nomClasse']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Non inscrit</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($e['telephone'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($e['statutInscription'] === 'Actif'): ?>
                                            <span class="badge-role actif">Inscrit</span>
                                        <?php elseif ($e['statutInscription'] === 'Terminé'): ?>
                                            <span class="badge-role termine">Terminé</span>
                                        <?php else: ?>
                                            <span class="badge-role non-inscrit">Non inscrit</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <div class="actions">
                                            <a href="voir.php?id=<?php echo $e['idEleve']; ?>" class="btn btn-view" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="modifier.php?id=<?php echo $e['idEleve']; ?>" class="btn btn-edit" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="confirmDelete(<?php echo $e['idEleve']; ?>)" class="btn btn-delete" title="Supprimer">
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
<!-- MODAL AJOUT ÉLÈVE -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalAjout">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fas fa-user-plus"></i> Ajouter un élève</h4>
            <button class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <?php if (!empty($modal_error)): ?>
                <div class="alert-box danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $modal_error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="formEleve">
                <input type="hidden" name="action" value="ajouter_eleve">

                <!-- Identité -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nom <span class="required">*</span></label>
                            <input type="text" class="form-control" name="nom" placeholder="Nom" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Post-Nom</label>
                            <input type="text" class="form-control" name="postNom" placeholder="Post-Nom">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Prénom <span class="required">*</span></label>
                            <input type="text" class="form-control" name="prenom" placeholder="Prénom" required>
                        </div>
                    </div>
                </div>

                <!-- Naissance -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Sexe <span class="required">*</span></label>
                            <select class="form-select" name="sexe" required>
                                <option value="">Sélectionner</option>
                                <option value="M">Masculin</option>
                                <option value="F">Féminin</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Date de naissance</label>
                            <input type="date" class="form-control" name="dateNaissance">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Lieu de naissance</label>
                            <input type="text" class="form-control" name="lieuNaissance" placeholder="Lieu de naissance">
                        </div>
                    </div>
                </div>

                <!-- Origine -->
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Province</label>
                            <input type="text" class="form-control" name="province" placeholder="Province">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nationalité</label>
                            <input type="text" class="form-control" name="nationalite" placeholder="Nationalité">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" class="form-control" name="telephone" placeholder="+243 812 345 678">
                        </div>
                    </div>
                </div>

                <!-- Parents -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nom du père</label>
                            <input type="text" class="form-control" name="nomPere" placeholder="Nom du père">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nom de la mère</label>
                            <input type="text" class="form-control" name="nomMere" placeholder="Nom de la mère">
                        </div>
                    </div>
                </div>

                <!-- Adresse -->
                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" class="form-control" name="adresse" placeholder="Adresse complète">
                </div>

                <!-- Matricule et Mot de passe -->
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Matricule</label>
                            <input type="text" class="form-control" name="matricule" 
                                   placeholder="Généré automatiquement" readonly 
                                   value="<?php echo $eleveModel->generateMatricule(); ?>">
                            <small class="text-muted"><i class="fas fa-info-circle"></i> Généré automatiquement</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <input type="text" class="form-control" name="motDePasse" value="password123">
                            <small class="text-muted"><i class="fas fa-info-circle"></i> Mot de passe par défaut</small>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== MODAL SUPPRESSION ===== -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px;">
            <div class="modal-header" style="border-bottom:2px solid #f0f2f5;">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Confirmation de suppression
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer cet élève ?</p>
                <p class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Cette action est irréversible.
                </p>
            </div>
            <div class="modal-footer" style="border-top:2px solid #f0f2f5;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:8px;">Annuler</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger" style="border-radius:8px;">
                    <i class="fas fa-trash me-1"></i> Supprimer
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar toggle
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

        // Recherche
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const term = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    });

    // ===== MODAL AJOUT =====
    function openModal() {
        document.getElementById('modalAjout').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('modalAjout').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Fermer en cliquant en dehors
    document.getElementById('modalAjout').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Fermer avec Echap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // ===== CONFIRMATION SUPPRESSION =====
    function confirmDelete(id) {
        document.getElementById('confirmDeleteBtn').href = 'supprimer.php?id=' + id;
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }
</script>
</body>
</html>