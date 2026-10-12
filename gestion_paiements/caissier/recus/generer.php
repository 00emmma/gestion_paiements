<?php
// caissier/recus/generer.php - Générer un reçu
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Caissier') {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Générer un reçu - Caissier';
$db = db();

// Récupérer les paramètres
$idPaiement = isset($_GET['id']) ? intval($_GET['id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Si un ID est envoyé via POST, on le prend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idPaiement'])) {
    $idPaiement = intval($_POST['idPaiement']);
}

$message = '';
$message_type = '';

// ================================================================
// REQUÊTE POUR RÉCUPÉRER LES PAIEMENTS AVEC RECHERCHE ET PAGINATION
// ================================================================
$sql_count = "
    SELECT COUNT(*) as total
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    JOIN eleves e ON i.idEleve = e.idEleve
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE 1=1
";

$sql = "
    SELECT p.idPaiement, p.montantPaye, p.datePaiement, p.numeroRecu, p.mois,
           e.matricule, e.nom, e.prenom, e.postNom,
           c.nomClasse,
           a.libelle as annee
    FROM paiements p
    JOIN inscriptions i ON p.idInscription = i.idInscription
    JOIN eleves e ON i.idEleve = e.idEleve
    JOIN classes c ON i.idClasse = c.idClasse
    JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
    WHERE 1=1
";

$params = [];

// Ajouter la recherche
if (!empty($search)) {
    $sql_count .= " AND (e.nom LIKE :search OR e.prenom LIKE :search OR e.matricule LIKE :search OR p.numeroRecu LIKE :search)";
    $sql .= " AND (e.nom LIKE :search OR e.prenom LIKE :search OR e.matricule LIKE :search OR p.numeroRecu LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY p.datePaiement DESC LIMIT :limit OFFSET :offset";

// Compter le nombre total de paiements
$stmt_count = $db->prepare($sql_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_paiements = $stmt_count->fetch()['total'];
$total_pages = ceil($total_paiements / $limit);

// Récupérer les paiements
$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue('limit', $limit, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$paiements = $stmt->fetchAll();

// Récupérer les informations du paiement sélectionné
$paiement_selected = null;
if ($idPaiement > 0) {
    $stmt = $db->prepare("
        SELECT p.*, 
               e.matricule, e.nom, e.prenom, e.postNom, e.adresse, e.telephone,
               c.nomClasse, 
               a.libelle as annee,
               u.nom as caissier_nom, u.prenom as caissier_prenom
        FROM paiements p
        JOIN inscriptions i ON p.idInscription = i.idInscription
        JOIN eleves e ON i.idEleve = e.idEleve
        JOIN classes c ON i.idClasse = c.idClasse
        JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
        JOIN utilisateurs u ON p.idCaissier = u.idUtilisateur
        WHERE p.idPaiement = :id
    ");
    $stmt->execute(['id' => $idPaiement]);
    $paiement_selected = $stmt->fetch();
    
    if (!$paiement_selected) {
        $message = 'Paiement non trouvé.';
        $message_type = 'danger';
        $idPaiement = 0;
    }
}

// Générer un nouveau numéro de reçu
function genererNumeroRecu($db) {
    try {
        $stmt = $db->query("SELECT numeroRecu FROM paiements WHERE numeroRecu IS NOT NULL ORDER BY idPaiement DESC LIMIT 1");
        $last = $stmt->fetch();
        if ($last && !empty($last['numeroRecu'])) {
            $num = intval(substr($last['numeroRecu'], -4)) + 1;
            return 'REC-' . date('Y') . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
        }
    } catch (PDOException $e) {}
    return 'REC-' . date('Y') . '-0001';
}

$nouveau_numero = genererNumeroRecu($db);

// Traitement du formulaire de génération
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generer'])) {
    $idPaiement = intval($_POST['idPaiement'] ?? 0);
    $numRecu = trim($_POST['numeroRecu'] ?? '');
    
    if ($idPaiement <= 0) {
        $message = 'Veuillez sélectionner un paiement.';
        $message_type = 'danger';
    } elseif (empty($numRecu)) {
        $message = 'Veuillez entrer un numéro de reçu.';
        $message_type = 'danger';
    } else {
        // Vérifier si le numéro de reçu existe déjà
        $stmt = $db->prepare("SELECT COUNT(*) FROM paiements WHERE numeroRecu = :num AND idPaiement != :id");
        $stmt->execute(['num' => $numRecu, 'id' => $idPaiement]);
        if ($stmt->fetchColumn() > 0) {
            $message = 'Ce numéro de reçu existe déjà. Veuillez en choisir un autre.';
            $message_type = 'danger';
        } else {
            // Mettre à jour le paiement avec le numéro de reçu
            $stmt = $db->prepare("UPDATE paiements SET numeroRecu = :num WHERE idPaiement = :id");
            if ($stmt->execute(['num' => $numRecu, 'id' => $idPaiement])) {
                $message = 'Reçu généré avec succès ! Numéro: ' . $numRecu;
                $message_type = 'success';
                
                // Recharger les données
                header('Location: generer.php?id=' . $idPaiement . '&success=1');
                exit();
            } else {
                $message = 'Erreur lors de la génération du reçu.';
                $message_type = 'danger';
            }
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    /* ===== STYLES COMPLETS ===== */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: #f0f4f8;
        font-size: 14px;
    }

    .app-layout { display: flex; min-height: 100vh; }

    .sidebar-caissier {
        width: 230px;
        flex-shrink: 0;
        background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
        min-height: 100vh;
        padding: 16px 12px;
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
        padding-bottom: 14px;
        margin-bottom: 14px;
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
    .sidebar-menu li { margin-bottom: 1px; }
    .sidebar-menu li a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 12px;
        border-radius: 8px;
        color: #a0aec0;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.8rem;
        transition: all 0.3s ease;
    }
    .sidebar-menu li a:hover { background: rgba(255,255,255,0.08); color: white; text-decoration: none; }
    .sidebar-menu li a.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .sidebar-menu li a i { width: 18px; text-align: center; font-size: 0.85rem; flex-shrink: 0; }

    .sidebar-footer {
        padding-top: 12px;
        margin-top: 12px;
        border-top: 1px solid rgba(255,255,255,0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
    }
    .sidebar-footer .user-info {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        min-width: 0;
    }
    .sidebar-footer .user-info .avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .sidebar-footer .user-info div { display: flex; flex-direction: column; overflow: hidden; }
    .sidebar-footer .user-info div strong { color: white; font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sidebar-footer .user-info div span { color: #a0aec0; font-size: 0.6rem; }
    .sidebar-footer .btn-logout {
        background: rgba(255,255,255,0.08);
        color: #fc8181;
        border: none;
        border-radius: 6px;
        padding: 6px 10px;
        transition: all 0.3s ease;
        text-decoration: none;
        flex-shrink: 0;
        font-size: 0.75rem;
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
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.5);
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: none;
        align-items: center;
        justify-content: center;
    }
    .sidebar-toggle-mobile:hover { transform: scale(1.05); box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6); }
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
        background: #f0f4f8;
        min-height: 100vh;
        width: 100%;
        margin-left: 0;
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
    .page-header h2 { font-size: 1.3rem; font-weight: 700; color: #1a202c; margin: 0; }
    .page-header h2 i { color: #667eea; margin-right: 10px; }
    .page-header .header-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
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
    .page-header .btn-back:hover { background: #cbd5e0; color: #1a202c; }
    
    /* ===== NOUVEAU BOUTON LISTE DES REÇUS ===== */
    .page-header .btn-list {
        background: linear-gradient(135deg, #48bb78, #38a169);
        color: white;
        border: none;
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.3s;
    }
    .page-header .btn-list:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(72, 187, 120, 0.35);
        color: white;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.5s ease;
    }
    .alert-success { background: #f0fff4; color: #276749; border: 1px solid #c6f6d5; }
    .alert-danger { background: #fff5f5; color: #9b2c2c; border: 1px solid #fed7d7; }
    .alert i { font-size: 1.1rem; }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ===== BARRE DE RECHERCHE ===== */
    .search-bar {
        background: white;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .search-bar .search-info {
        font-size: 0.85rem;
        color: #4a5568;
    }
    .search-bar .search-info strong {
        color: #1a202c;
    }
    .search-bar .search-input-group {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .search-bar .search-input-group input {
        padding: 8px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.85rem;
        width: 300px;
        transition: 0.3s;
    }
    .search-bar .search-input-group input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }
    .search-bar .search-input-group .btn-search {
        padding: 8px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .search-bar .search-input-group .btn-search:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
    }
    .search-bar .search-input-group .btn-clear {
        padding: 8px 16px;
        background: #e2e8f0;
        color: #4a5568;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .search-bar .search-input-group .btn-clear:hover {
        background: #cbd5e0;
    }

    /* ===== PAGINATION ===== */
    .pagination-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        padding: 12px 0;
        flex-wrap: wrap;
    }
    .pagination-container .page-link {
        padding: 6px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        color: #4a5568;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.8rem;
        transition: 0.3s;
    }
    .pagination-container .page-link:hover {
        background: #f7fafc;
        border-color: #667eea;
    }
    .pagination-container .page-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }
    .pagination-container .page-link.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }
    .pagination-container .page-info {
        font-size: 0.8rem;
        color: #718096;
        padding: 0 8px;
    }

    /* ===== FORMULAIRE ===== */
    .form-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 20px 24px;
        margin-bottom: 20px;
    }
    .form-container .row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .form-container .form-group { margin-bottom: 0; }
    .form-container .form-group label {
        display: block;
        font-weight: 600;
        font-size: 0.85rem;
        color: #2d3748;
        margin-bottom: 4px;
    }
    .form-container .form-group label .required { color: #e53e3e; margin-left: 2px; }
    .form-container .form-group .form-control,
    .form-container .form-group .form-select {
        width: 100%;
        padding: 8px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.85rem;
        transition: 0.3s;
        background: white;
    }
    .form-container .form-group .form-control:focus,
    .form-container .form-group .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }
    .form-container .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 2px solid #f0f2f5;
        flex-wrap: wrap;
    }
    .form-container .btn-cancel {
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        border: 2px solid #e2e8f0;
        background: white;
        color: #4a5568;
        cursor: pointer;
        transition: 0.3s;
    }
    .form-container .btn-cancel:hover { background: #f7fafc; }
    .form-container .btn-submit {
        padding: 8px 24px;
        border-radius: 8px;
        font-weight: 600;
        border: none;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .form-container .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
    }
    .form-container .btn-print-recu {
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 600;
        border: none;
        background: #38a169;
        color: white;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .form-container .btn-print-recu:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(56, 161, 105, 0.35);
        color: white;
    }

    /* ===== APERÇU REÇU ===== */
    .recu-preview {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        padding: 0;
        overflow: hidden;
    }
    .recu-preview .recu-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .recu-preview .recu-header h5 { margin: 0; font-weight: 700; font-size: 1rem; }
    .recu-preview .recu-header h5 i { margin-right: 8px; }
    .recu-preview .recu-body { padding: 20px 24px; }
    .recu-preview .recu-body .recu-infos {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 20px;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #edf2f7;
    }
    .recu-preview .recu-body .recu-infos .info-item .label {
        font-size: 0.65rem;
        color: #a0aec0;
        text-transform: uppercase;
        font-weight: 600;
    }
    .recu-preview .recu-body .recu-infos .info-item .value {
        font-size: 0.9rem;
        font-weight: 500;
        color: #1a202c;
    }
    .recu-preview .recu-body .recu-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 15px;
        border-top: 2px solid #edf2f7;
    }
    .recu-preview .recu-body .recu-total .label {
        font-size: 0.9rem;
        font-weight: 600;
        color: #4a5568;
    }
    .recu-preview .recu-body .recu-total .amount {
        font-size: 1.4rem;
        font-weight: 800;
        color: #1a202c;
    }
    .recu-preview .recu-footer {
        background: #f7fafc;
        padding: 12px 24px;
        border-top: 1px solid #edf2f7;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 0.75rem;
        color: #718096;
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
            width: 280px;
            z-index: 1000;
            height: 100vh;
            border-radius: 0;
        }
        .sidebar-caissier.open { transform: translateX(0); }
        .sidebar-close { display: block !important; }
        .sidebar-toggle-mobile { display: flex !important; }
        .sidebar-overlay.active { display: block !important; opacity: 1; }
        .main-content { padding: 15px !important; width: 100% !important; max-height: none; }
        .page-header { flex-direction: column; text-align: center; }
        .page-header .header-right { flex-wrap: wrap; justify-content: center; }
        .search-bar { flex-direction: column; text-align: center; }
        .search-bar .search-input-group { width: 100%; flex-direction: column; }
        .search-bar .search-input-group input { width: 100%; }
        .search-bar .search-input-group .btn-search,
        .search-bar .search-input-group .btn-clear { width: 100%; justify-content: center; }
        .form-container .row { grid-template-columns: 1fr; }
        .recu-preview .recu-body .recu-infos { grid-template-columns: 1fr; }
        .recu-preview .recu-header { flex-direction: column; text-align: center; }
        .form-container .form-actions { flex-direction: column; }
        .form-container .form-actions .btn-cancel,
        .form-container .form-actions .btn-submit,
        .form-container .form-actions .btn-print-recu { width: 100%; justify-content: center; }
        .pagination-container { gap: 5px; }
        .pagination-container .page-link { padding: 4px 10px; font-size: 0.7rem; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1.1rem; }
        .form-container { padding: 14px 16px; }
        .recu-preview .recu-body { padding: 14px 16px; }
        .recu-preview .recu-body .recu-total .amount { font-size: 1.1rem; }
        .sidebar-toggle-mobile { width: 40px; height: 40px; font-size: 0.9rem; bottom: 15px; left: 15px; }
        .search-bar .search-info { font-size: 0.75rem; }
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
    <div class="sidebar-caissier" id="sidebarCaissier">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class="fas fa-coins"></i></div>
            <span>GestionScolaire</span>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>

        <ul class="sidebar-menu">
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li class="menu-section"><i class="fas fa-money-bill-wave"></i> Paiements</li>
            <li><a href="../paiements/index.php"><i class="fas fa-list"></i> Liste des paiements</a></li>
            <li class="menu-section"><i class="fas fa-users"></i> Élèves</li>
            <li><a href="../eleves/index.php"><i class="fas fa-user-graduate"></i> Liste des élèves</a></li>
            <li><a href="../eleves/situation_financiere.php"><i class="fas fa-chart-pie"></i> Situation financière</a></li>
            <li class="menu-section"><i class="fas fa-file-invoice"></i> Reçus</li>
            <li><a href="generer.php" class="active"><i class="fas fa-file-invoice"></i> Générer un reçu</a></li>
            <li><a href="historique.php"><i class="fas fa-history"></i> Historique des reçus</a></li>
            <li class="menu-section"><i class="fas fa-user-circle"></i> Compte</li>
            <li><a href="../profil.php"><i class="fas fa-user-circle"></i> Mon profil</a></li>
        </ul>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['prenom'] ?? 'C', 0, 1) . substr($_SESSION['nom'] ?? 'A', 0, 1)); ?></div>
                <div>
                    <strong><?php echo $_SESSION['prenom'] ?? 'Caissier'; ?> <?php echo $_SESSION['nom'] ?? ''; ?></strong>
                    <span><?php echo $_SESSION['role'] ?? 'Caissier'; ?></span>
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
                <i class="fas fa-file-invoice"></i>
                Générer un reçu
            </h2>
            <div class="header-right">
                <!-- ===== NOUVEAU BOUTON LISTE DES REÇUS ===== -->
                <a href="listerecus.php" class="btn-list">
                    <i class="fas fa-list"></i> Liste des reçus
                </a>
                <a href="historique.php" class="btn-back">
                    <i class="fas fa-history"></i> Historique
                </a>
                <a href="../paiements/index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Paiements
                </a>
            </div>
        </div>

        <!-- ===== MESSAGES ===== -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Reçu généré avec succès !
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- ===== BARRE DE RECHERCHE ===== -->
        <div class="search-bar">
            <div class="search-info">
                <i class="fas fa-list me-1"></i>
                <strong><?php echo $total_paiements; ?></strong> paiement(s) trouvé(s)
                <?php if (!empty($search)): ?>
                    <span class="text-muted" style="font-size:0.8rem;">
                        - Résultats pour "<strong><?php echo htmlspecialchars($search); ?></strong>"
                    </span>
                <?php endif; ?>
            </div>
            <form method="GET" action="" class="search-input-group">
                <input type="text" name="search" placeholder="Rechercher par nom, matricule ou n° reçu..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Rechercher
                </button>
                <?php if (!empty($search)): ?>
                    <a href="generer.php" class="btn-clear">
                        <i class="fas fa-times"></i> Effacer
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ===== FORMULAIRE ===== -->
        <div class="form-container">
            <form method="POST" action="">
                <div class="row">
                    <!-- Sélection du paiement -->
                    <div class="form-group">
                        <label>Paiement <span class="required">*</span></label>
                        <select class="form-select" name="idPaiement" id="idPaiement" required>
                            <option value="">-- Sélectionner un paiement --</option>
                            <?php foreach ($paiements as $p): ?>
                                <option value="<?php echo $p['idPaiement']; ?>" 
                                    <?php echo ($idPaiement == $p['idPaiement']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['matricule'] . ' - ' . $p['nom'] . ' ' . $p['prenom']); ?>
                                    ($<?php echo number_format($p['montantPaye'], 2); ?> - 
                                    <?php echo date('d/m/Y', strtotime($p['datePaiement'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Numéro de reçu -->
                    <div class="form-group">
                        <label>Numéro de reçu <span class="required">*</span></label>
                        <input type="text" class="form-control" name="numeroRecu" 
                               value="<?php echo $paiement_selected && !empty($paiement_selected['numeroRecu']) ? htmlspecialchars($paiement_selected['numeroRecu']) : $nouveau_numero; ?>"
                               placeholder="REC-2024-0001" required>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="../paiements/index.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <?php if ($paiement_selected && !empty($paiement_selected['numeroRecu'])): ?>
                        <a href="imprimer.php?id=<?php echo $idPaiement; ?>" target="_blank" class="btn-print-recu">
                            <i class="fas fa-print"></i> Imprimer le reçu
                        </a>
                    <?php endif; ?>
                    <button type="submit" name="generer" class="btn-submit">
                        <i class="fas fa-save"></i> Générer le reçu
                    </button>
                </div>
            </form>
        </div>

        <!-- ===== APERÇU DU REÇU ===== -->
        <?php if ($paiement_selected): ?>
        <div class="recu-preview" id="recuPreview">
            <div class="recu-header">
                <h5><i class="fas fa-file-invoice"></i> Aperçu du reçu</h5>
                <?php if (!empty($paiement_selected['numeroRecu'])): ?>
                    <span class="badge bg-white text-dark">
                        <i class="fas fa-check-circle text-success me-1"></i>
                        Reçu n° <?php echo htmlspecialchars($paiement_selected['numeroRecu']); ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark">
                        <i class="fas fa-clock me-1"></i> En attente de génération
                    </span>
                <?php endif; ?>
            </div>
            <div class="recu-body">
                <div class="recu-infos">
                    <div class="info-item">
                        <div class="label"><i class="fas fa-user me-1"></i> Élève</div>
                        <div class="value">
                            <?php echo htmlspecialchars($paiement_selected['nom'] . ' ' . ($paiement_selected['postNom'] ?? '') . ' ' . $paiement_selected['prenom']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="label"><i class="fas fa-id-card me-1"></i> Matricule</div>
                        <div class="value"><?php echo htmlspecialchars($paiement_selected['matricule']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label"><i class="fas fa-chalkboard me-1"></i> Classe</div>
                        <div class="value"><?php echo htmlspecialchars($paiement_selected['nomClasse']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label"><i class="fas fa-calendar-alt me-1"></i> Année</div>
                        <div class="value"><?php echo htmlspecialchars($paiement_selected['annee']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label"><i class="fas fa-calendar-alt me-1"></i> Mois</div>
                        <div class="value"><?php echo htmlspecialchars($paiement_selected['mois'] ?? '-'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label"><i class="fas fa-calendar-alt me-1"></i> Date de paiement</div>
                        <div class="value"><?php echo date('d/m/Y H:i', strtotime($paiement_selected['datePaiement'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label"><i class="fas fa-user-cog me-1"></i> Caissier</div>
                        <div class="value"><?php echo htmlspecialchars(($paiement_selected['caissier_nom'] ?? '') . ' ' . ($paiement_selected['caissier_prenom'] ?? '')); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="label"><i class="fas fa-hashtag me-1"></i> N° Reçu</div>
                        <div class="value">
                            <?php if (!empty($paiement_selected['numeroRecu'])): ?>
                                <?php echo htmlspecialchars($paiement_selected['numeroRecu']); ?>
                            <?php else: ?>
                                <span class="text-muted">Non généré</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="recu-total">
                    <span class="label">Montant payé</span>
                    <span class="amount">$<?php echo number_format($paiement_selected['montantPaye'], 2); ?></span>
                </div>
            </div>
            <div class="recu-footer">
                <span>
                    <i class="fas fa-print me-1"></i>
                    <?php if (!empty($paiement_selected['numeroRecu'])): ?>
                        Reçu prêt à être imprimé
                    <?php else: ?>
                        Cliquez sur "Générer le reçu" pour valider
                    <?php endif; ?>
                </span>
                <span>
                    <i class="fas fa-clock me-1"></i>
                    <?php echo date('d/m/Y H:i'); ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== PAGINATION ===== -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1): ?>
                <a href="?page=1&search=<?php echo urlencode($search); ?>" class="page-link">1</a>
                <?php if ($start_page > 2): ?>
                    <span class="page-link disabled">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span class="page-link disabled">...</span>
                <?php endif; ?>
                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                    <?php echo $total_pages; ?>
                </a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>

            <span class="page-info">
                Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                (<?php echo $total_paiements; ?> paiements)
            </span>
        </div>
        <?php endif; ?>

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

    // ===== GESTION DE LA SÉLECTION =====
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('idPaiement');
        
        select.addEventListener('change', function() {
            const id = this.value;
            const search = '<?php echo htmlspecialchars($search); ?>';
            if (id > 0) {
                window.location.href = 'generer.php?id=' + id + '&search=' + encodeURIComponent(search);
            } else {
                window.location.href = 'generer.php?search=' + encodeURIComponent(search);
            }
        });
    });
</script>
</body>
</html>