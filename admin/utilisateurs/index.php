<?php
// admin/utilisateurs/index.php - Gestion des utilisateurs
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Gestion des utilisateurs';
$db = db();

// ===== RÉCUPÉRER TOUS LES UTILISATEURS =====
$sql = "
    SELECT u.idUtilisateur, u.nom, u.postNom, u.prenom, u.username, u.email, 
           u.telephone, u.sexe, u.statut,
           r.nomRole
    FROM utilisateurs u
    LEFT JOIN roles r ON u.idRole = r.idRole
    ORDER BY u.nom, u.prenom
";
$stmt = $db->query($sql);
$utilisateurs = $stmt->fetchAll();

// ===== RÉCUPÉRER LES RÔLES POUR LE MODAL =====
$roles = [];
try {
    $stmt = $db->query("SELECT * FROM roles ORDER BY nomRole");
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    $roles = [];
}

$message = '';
if (isset($_SESSION['flash'])) {
    $message = $_SESSION['flash'];
    unset($_SESSION['flash']);
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

/* Logo */
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
    flex: 1;
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

/* Menu */
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

/* Footer sidebar */
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

/* ===== CONTENU PRINCIPAL ===== */
.main-content {
    flex: 1;
    padding: 20px 25px;
    background: #f8fafc;
    min-height: 100vh;
    margin-left: 0;
    transition: margin-left 0.3s ease;
}

/* ===== EN-TETE ===== */
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
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
.stat-card.green { border-color: #48bb78; }
.stat-card.orange { border-color: #ed8936; }
.stat-card.blue { border-color: #4299e1; }

/* ===== TABLEAU ===== */
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

.table-scroll {
    overflow-x: auto;
}
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
.badge-role {
    padding: 2px 12px;
    border-radius: 16px;
    font-size: 0.65rem;
    font-weight: 600;
    display: inline-block;
}
.badge-role.admin { background: #ebf8ff; color: #2b6cb0; }
.badge-role.caissier { background: #f0fff4; color: #276749; }
.badge-role.receptionniste { background: #fefcbf; color: #975a16; }
.badge-role.eleve { background: #faf5ff; color: #6b46c1; }
.badge-role.default { background: #edf2f7; color: #4a5568; }

.badge-status {
    padding: 2px 12px;
    border-radius: 16px;
    font-size: 0.6rem;
    font-weight: 600;
    display: inline-block;
}
.badge-status.Actif { background: #f0fff4; color: #276749; }
.badge-status.Inactif { background: #fff5f5; color: #c53030; }

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
.actions .btn-edit { background: #fefcbf; color: #975a16; }
.actions .btn-edit:hover { background: #f6e05e; color: #975a16; }
.actions .btn-delete { background: #fff5f5; color: #c53030; }
.actions .btn-delete:hover { background: #fed7d7; color: #c53030; }

.username {
    font-weight: 600;
    color: #2b6cb0;
    font-size: 0.8rem;
}
.username i {
    color: #667eea;
    margin-right: 4px;
    font-size: 0.75rem;
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
    max-width: 650px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 35px 40px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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

/* En-tête du modal */
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20px;
    margin-bottom: 25px;
    border-bottom: 2px solid #f0f2f5;
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
.modal-header .btn-close-modal {
    background: #f7fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.85rem;
    color: #4a5568;
    text-decoration: none;
    transition: all 0.3s;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.modal-header .btn-close-modal:hover {
    background: #edf2f7;
    border-color: #cbd5e0;
}

/* Champs du formulaire dans le modal */
.modal-box .form-group {
    margin-bottom: 18px;
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
    padding: 10px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
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
.modal-box .form-group .form-text {
    font-size: 0.75rem;
    color: #a0aec0;
    margin-top: 4px;
}
.modal-box .form-group .form-text i {
    margin-right: 4px;
}

.modal-box .section-title {
    font-weight: 700;
    color: #667eea;
    font-size: 0.9rem;
    padding-bottom: 8px;
    margin-bottom: 15px;
    border-bottom: 2px solid #f0f2f5;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-box .section-title i {
    font-size: 0.95rem;
}

.modal-box .row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
.modal-box .row .col-md-6 {
    width: 100%;
}

.modal-box .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 20px;
    margin-top: 10px;
    border-top: 2px solid #f0f2f5;
}
.modal-box .btn-cancel {
    padding: 10px 25px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    border: 2px solid #e2e8f0;
    background: white;
    color: #4a5568;
    transition: all 0.3s;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.modal-box .btn-cancel:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}
.modal-box .btn-submit {
    padding: 10px 30px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    border: none;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    transition: all 0.3s;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.modal-box .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
}

.modal-box .alert-box {
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    background: #fff5f5;
    color: #c53030;
    border: 1px solid #fed7d7;
    display: flex;
    align-items: center;
    gap: 10px;
}
.modal-box .alert-box i {
    font-size: 1.1rem;
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
        margin-left: 0 !important;
    }
    .header-bar {
        flex-direction: column;
        text-align: center;
        padding: 12px 16px;
    }
    .header-bar h3 {
        font-size: 0.9rem;
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
    .table th, .table td {
        padding: 5px 8px;
    }
    .btn-add {
        width: 100%;
        justify-content: center;
    }
    .table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table-scroll table {
        min-width: 550px;
    }
    .badge-role, .badge-status {
        font-size: 0.55rem;
        padding: 2px 8px;
    }
    .actions .btn {
        padding: 2px 6px;
        font-size: 0.6rem;
    }
    .username {
        font-size: 0.7rem;
    }
    .modal-overlay .modal-box {
        margin: 20px;
        padding: 25px 20px;
        max-height: 95vh;
    }
    .modal-box .row {
        grid-template-columns: 1fr;
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
        gap: 15px;
        text-align: center;
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
    .header-bar h3 {
        font-size: 0.8rem;
    }
    .header-bar small {
        font-size: 0.65rem;
    }
    .sidebar-admin {
        width: 240px;
    }
    .table th, .table td {
        padding: 4px 6px;
        font-size: 0.65rem;
    }
    .modal-overlay .modal-box {
        padding: 20px 15px;
        margin: 10px;
    }
    .modal-header h4 {
        font-size: 1rem;
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
    .main-content {
        margin-left: 0;
    }
}

/* Scroll pour le modal */
.modal-box::-webkit-scrollbar {
    width: 4px;
}
.modal-box::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 4px;
}
</style>

<!-- ============================================================ -->
<!-- ===== OVERLAY ET TOGGLE ===== -->
<!-- ============================================================ -->

<button class="sidebar-toggle-mobile" id="sidebarToggle" title="Menu">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ============================================================ -->
<!-- ===== MODAL AJOUT UTILISATEUR ===== -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalAjout">
    <div class="modal-box">
        <!-- En-tête -->
        <div class="modal-header">
            <h4><i class="fas fa-user-plus"></i>Ajouter un utilisateur</h4>
            <button class="btn-close-modal" onclick="closeModal()">
                <i class="fas fa-times"></i> Fermer
            </button>
        </div>

        <!-- Message d'erreur/succès -->
        <div id="modalMessage"></div>

        <!-- Formulaire -->
        <form id="formAjout" method="POST" action="ajax_ajouter.php">
            <input type="hidden" name="ajax" value="1">

            <!-- Identifiants -->
            <div class="section-title"><i class="fas fa-key"></i>Identifiants de connexion</div>

            <div class="form-group">
                <label>Nom d'utilisateur <span class="required">*</span></label>
                <input type="text" class="form-control" name="username" placeholder="ex: admin" required>
            </div>

            <div class="form-group">
                <label>Mot de passe <span class="required">*</span></label>
                <input type="password" class="form-control" name="motDePasse" placeholder="Minimum 6 caractères" required>
                <div class="form-text"><i class="fas fa-info-circle"></i>Minimum 6 caractères</div>
            </div>

            <!-- Informations personnelles -->
            <div class="section-title" style="margin-top:25px;"><i class="fas fa-user"></i>Informations personnelles</div>

            <div class="form-group">
                <label>Nom <span class="required">*</span></label>
                <input type="text" class="form-control" name="nom" placeholder="Nom de famille" required>
            </div>

            <div class="form-group">
                <label>Post-Nom</label>
                <input type="text" class="form-control" name="postNom" placeholder="Post-Nom">
            </div>

            <div class="form-group">
                <label>Prénom <span class="required">*</span></label>
                <input type="text" class="form-control" name="prenom" placeholder="Prénom" required>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Sexe</label>
                        <select class="form-select" name="sexe">
                            <option value="">Sélectionner</option>
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" class="form-control" name="telephone" placeholder="+243 812 345 678">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" class="form-control" name="email" placeholder="exemple@email.com">
            </div>

            <!-- Rôle et statut -->
            <div class="section-title" style="margin-top:25px;"><i class="fas fa-user-tag"></i>Rôle et statut</div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Rôle <span class="required">*</span></label>
                        <select class="form-select" name="idRole" required>
                            <option value="">Sélectionner</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['idRole']; ?>"><?php echo htmlspecialchars($role['nomRole']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Statut</label>
                        <select class="form-select" name="statut">
                            <option value="Actif">Actif</option>
                            <option value="Inactif">Inactif</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" class="btn-submit" id="btnSubmit">
                    <i class="fas fa-save"></i> Enregistrer
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
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <ul class="sidebar-menu">
            <li class="menu-section"><i class="fas fa-cog"></i> Administration</li>
            <li>
                <a href="../dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Tableau de bord
                </a>
            </li>
            <li>
                <a href="../eleves/index.php">
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
                <a href="index.php" class="active">
                    <i class="fas fa-user-cog"></i> Utilisateurs
                </a>
            </li>
            <li class="menu-section"><i class="fas fa-file-alt"></i> Rapports</li>
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
            <li>
                <a href="../statut.php">
                    <i class="fas fa-user-check"></i> Statut
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

        <div class="header-bar">
            <div>
                <h3><i class="fas fa-user-cog"></i>Gestion des Utilisateurs</h3>
                <small>Gérez les comptes et les accès au système</small>
            </div>
            <button class="btn-add" onclick="openModal()">
                <i class="fas fa-plus"></i> Nouvel utilisateur
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert-box <?php echo $message['type'] == 'success' ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $message['type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message['text']; ?>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <?php 
        // Calcul des statistiques
        $total = count($utilisateurs);
        $actifs = 0;
        $admins = 0;
        $caissiers = 0;
        $enseignants = 0;
        $eleves = 0;
        
        foreach ($utilisateurs as $u) {
            if ($u['statut'] === 'Actif') $actifs++;
            $role = strtolower($u['nomRole'] ?? '');
            if (strpos($role, 'administrateur') !== false) $admins++;
            elseif (strpos($role, 'caissier') !== false) $caissiers++;
            elseif (strpos($role, 'enseignant') !== false) $enseignants++;
            elseif (strpos($role, 'élève') !== false || strpos($role, 'eleve') !== false) $eleves++;
        }
        ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $total; ?></div>
                <div class="label">Total utilisateurs</div>
            </div>
            <div class="stat-card green">
                <div class="number"><?php echo $actifs; ?></div>
                <div class="label">Actifs</div>
            </div>
            <div class="stat-card orange">
                <div class="number"><?php echo $admins; ?></div>
                <div class="label">Administrateurs</div>
            </div>
            <div class="stat-card blue">
                <div class="number"><?php echo $caissiers + $enseignants + $eleves; ?></div>
                <div class="label">Autres rôles</div>
            </div>
        </div>

        <div class="table-box">
            <div class="table-header">
                <span class="title"><i class="fas fa-list"></i>Liste des utilisateurs</span>
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
                            <th>Utilisateur</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th style="text-align:center; width:85px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($utilisateurs)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h5>Aucun utilisateur</h5>
                                        <p>Ajoutez votre premier utilisateur</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($utilisateurs as $i => $u): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td>
                                        <span class="username">
                                            <i class="fas fa-user-circle"></i>
                                            <?php echo htmlspecialchars($u['username']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(trim($u['nom'] . ' ' . ($u['postNom'] ?? '') . ' ' . $u['prenom'])); ?></td>
                                    <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($u['telephone'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                            $roleClass = 'default';
                                            $rl = strtolower($u['nomRole'] ?? '');
                                            if (strpos($rl, 'administrateur') !== false) $roleClass = 'admin';
                                            elseif (strpos($rl, 'caissier') !== false) $roleClass = 'caissier';
                                            elseif (strpos($rl, 'enseignant') !== false) $roleClass = 'enseignant';
                                            elseif (strpos($rl, 'réceptionniste') !== false) $roleClass = 'receptionniste';
                                            elseif (strpos($rl, 'élève') !== false || strpos($rl, 'eleve') !== false) $roleClass = 'eleve';
                                        ?>
                                        <span class="badge-role <?php echo $roleClass; ?>"><?php echo htmlspecialchars($u['nomRole'] ?? 'Utilisateur'); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-status <?php echo $u['statut']; ?>">
                                            <?php echo $u['statut']; ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <div class="actions">
                                            <a href="modifier.php?id=<?php echo $u['idUtilisateur']; ?>" class="btn btn-edit" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($u['idUtilisateur'] != $_SESSION['user_id']): ?>
                                                <a href="supprimer.php?id=<?php echo $u['idUtilisateur']; ?>" 
                                                   class="btn btn-delete" 
                                                   title="Supprimer"
                                                   onclick="return confirm('Supprimer cet utilisateur ?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
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

<script>
// ===== FONCTIONS MODAL =====
function openModal() {
    document.getElementById('modalAjout').classList.add('active');
    document.body.style.overflow = 'hidden';
    // Réinitialiser le formulaire
    document.getElementById('formAjout').reset();
    document.getElementById('modalMessage').innerHTML = '';
}

function closeModal() {
    document.getElementById('modalAjout').classList.remove('active');
    document.body.style.overflow = '';
}

// Fermer le modal en cliquant sur l'overlay
document.getElementById('modalAjout').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// ===== SOUMISSION AJAX DU FORMULAIRE =====
document.getElementById('formAjout').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const messageDiv = document.getElementById('modalMessage');
    const submitBtn = document.getElementById('btnSubmit');
    
    // Désactiver le bouton
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
            // Recharger la page après 1.5 secondes
            setTimeout(function() {
                location.reload();
            }, 1500);
        } else {
            messageDiv.innerHTML = `
                <div class="alert-box">
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
            <div class="alert-box">
                <i class="fas fa-exclamation-circle"></i>
                Une erreur est survenue : ${error}
            </div>
        `;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';
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

    if (toggleBtn) {
        toggleBtn.addEventListener('click', openSidebar);
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    if (sidebar) {
        sidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991) {
                    closeSidebar();
                }
            });
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    // Recherche en temps réel
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