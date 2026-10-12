<?php
// caissier/paiements/index.php - Liste des paiements avec modal et recherche
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

$page_title = 'Liste des paiements - Caissier';
$db = db();

// ===== TRAITEMENT DU FORMULAIRE =====
$modal_error = '';
$modal_success = '';

// Générer un numéro de reçu automatique
function genererNumeroRecu($db) {
    $stmt = $db->query("SELECT numeroRecu FROM paiements WHERE numeroRecu IS NOT NULL ORDER BY idPaiement DESC LIMIT 1");
    $last = $stmt->fetch();
    if ($last && !empty($last['numeroRecu'])) {
        $num = intval(substr($last['numeroRecu'], -4)) + 1;
        return 'REC-' . date('Y') . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
    return 'REC-' . date('Y') . '-0001';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_paiement') {
    $idEleve = intval($_POST['idEleve'] ?? 0);
    $idFrais = intval($_POST['idFrais'] ?? 0);
    $mois = $_POST['mois'] ?? '';
    $datePaiement = $_POST['datePaiement'] ?? date('Y-m-d H:i:s');
    
    if ($idEleve <= 0 || $idFrais <= 0 || empty($mois)) {
        $modal_error = 'Veuillez sélectionner un élève, un frais et un mois.';
        $_SESSION['show_modal'] = true;
    } else {
        // Récupérer le montant du frais sélectionné
        $stmt = $db->prepare("SELECT montant FROM frais WHERE idFrais = :id");
        $stmt->execute(['id' => $idFrais]);
        $frais = $stmt->fetch();
        $montantPaye = $frais ? floatval($frais['montant']) : 0;
        
        if ($montantPaye <= 0) {
            $modal_error = 'Le montant du frais est invalide.';
            $_SESSION['show_modal'] = true;
        } else {
            // Vérifier si l'élève a une inscription active
            $stmt = $db->prepare("SELECT idInscription FROM inscriptions WHERE idEleve = :eleve AND idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)");
            $stmt->execute(['eleve' => $idEleve]);
            $inscription = $stmt->fetch();
            
            if (!$inscription) {
                $modal_error = 'Cet élève n\'est pas inscrit pour l\'année en cours.';
                $_SESSION['show_modal'] = true;
            } else {
                // Générer le numéro de reçu
                $numeroRecu = genererNumeroRecu($db);
                
                $stmt = $db->prepare("
                    INSERT INTO paiements (idInscription, idFrais, mois, montantPaye, datePaiement, idCaissier, numeroRecu)
                    VALUES (:inscription, :frais, :mois, :montant, :date, :caissier, :recu)
                ");
                
                $result = $stmt->execute([
                    'inscription' => $inscription['idInscription'],
                    'frais' => $idFrais,
                    'mois' => $mois,
                    'montant' => $montantPaye,
                    'date' => $datePaiement,
                    'caissier' => $_SESSION['user_id'],
                    'recu' => $numeroRecu
                ]);
                
                if ($result) {
                    $idPaiement = $db->lastInsertId();
                    $_SESSION['flash'] = [
                        'type' => 'success',
                        'text' => '✅ Paiement enregistré avec succès !<br>Reçu n°: <strong>' . $numeroRecu . '</strong>'
                    ];
                    header('Location: index.php?success=1');
                    exit();
                } else {
                    $modal_error = 'Erreur lors de l\'enregistrement du paiement.';
                    $_SESSION['show_modal'] = true;
                }
            }
        }
    }
}

// Récupérer les filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtre_date = isset($_GET['date']) ? $_GET['date'] : '';
$filtre_frais = isset($_GET['frais']) ? intval($_GET['frais']) : 0;
$filtre_mois = isset($_GET['mois']) ? $_GET['mois'] : '';

// Construction de la requête
$sql = "SELECT p.*, e.matricule, e.nom, e.prenom, e.postNom, f.libelle as frais_libelle, 
               c.nomClasse, a.libelle as annee,
               u.nom as caissier_nom, u.prenom as caissier_prenom
        FROM paiements p
        JOIN inscriptions i ON p.idInscription = i.idInscription
        JOIN eleves e ON i.idEleve = e.idEleve
        JOIN frais f ON p.idFrais = f.idFrais
        JOIN classes c ON i.idClasse = c.idClasse
        JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
        JOIN utilisateurs u ON p.idCaissier = u.idUtilisateur
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (e.matricule LIKE :search OR e.nom LIKE :search OR e.prenom LIKE :search OR p.numeroRecu LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($filtre_date)) {
    $sql .= " AND DATE(p.datePaiement) = :date";
    $params['date'] = $filtre_date;
}

if ($filtre_frais > 0) {
    $sql .= " AND p.idFrais = :frais";
    $params['frais'] = $filtre_frais;
}

if (!empty($filtre_mois)) {
    $sql .= " AND p.mois = :mois";
    $params['mois'] = $filtre_mois;
}

$sql .= " ORDER BY p.datePaiement DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$paiements = $stmt->fetchAll();

// Récupérer les frais pour le filtre
$stmt = $db->query("SELECT idFrais, libelle FROM frais ORDER BY libelle");
$frais_list = $stmt->fetchAll();

// Récupérer les frais pour le modal avec le montant
$stmt = $db->query("SELECT idFrais, libelle, montant FROM frais WHERE statut = 'Actif' ORDER BY libelle");
$frais_modal = $stmt->fetchAll();

// Récupérer les élèves pour le modal
$stmt = $db->query("SELECT idEleve, matricule, nom, prenom FROM eleves ORDER BY nom");
$eleves_modal = $stmt->fetchAll();

// Liste des mois
$mois_liste = ['Septembre', 'Octobre', 'Novembre', 'Décembre', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin'];

// Statistiques
$total_paiements = count($paiements);
$montant_total = array_sum(array_column($paiements, 'montantPaye'));
$montant_moyen = $total_paiements > 0 ? $montant_total / $total_paiements : 0;

// Statistiques supplémentaires
$stmt = $db->query("SELECT COUNT(*) as total, SUM(montantPaye) as total_montant FROM paiements WHERE DATE(datePaiement) = CURDATE()");
$stats_jour = $stmt->fetch();

$stmt = $db->query("SELECT COUNT(*) as total, SUM(montantPaye) as total_montant FROM paiements WHERE MONTH(datePaiement) = MONTH(CURDATE()) AND YEAR(datePaiement) = YEAR(CURDATE())");
$stats_mois = $stmt->fetch();

$stmt = $db->query("SELECT COUNT(*) as total FROM paiements WHERE numeroRecu IS NOT NULL AND numeroRecu != ''");
$stats_recus = $stmt->fetch();

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

    /* ===== SIDEBAR ===== */
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
    .sidebar-menu li a:hover {
        background: rgba(255,255,255,0.08);
        color: white;
        text-decoration: none;
    }
    .sidebar-menu li a.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .sidebar-menu li a i {
        width: 18px;
        text-align: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

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
        font-size: 0.6rem;
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
        font-size: 0.75rem;
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
    .sidebar-toggle-mobile:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
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
        background: #f0f4f8;
        min-height: 100vh;
        width: 100%;
        margin-left: 0;
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
        font-size: 1.3rem;
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
    .page-header .btn-add {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
        cursor: pointer;
    }
    .page-header .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
        color: white;
    }
    .page-header .btn-pdf {
        background: #e53e3e;
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
        cursor: pointer;
    }
    .page-header .btn-pdf:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(229, 62, 62, 0.35);
        color: white;
    }
    .page-header .btn-print {
        background: #38a169;
        color: white;
        border: none;
        padding: 8px 18px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
        cursor: pointer;
    }
    .page-header .btn-print:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(56, 161, 105, 0.35);
        color: white;
    }

    /* ===== STATS ===== */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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

    /* ===== FILTRES ===== */
    .filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        align-items: center;
        background: white;
        padding: 12px 16px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .filters input,
    .filters select {
        padding: 7px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.8rem;
        background: white;
        flex: 1;
        min-width: 120px;
        transition: 0.3s;
    }
    .filters input:focus,
    .filters select:focus {
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    .filters .btn-filter {
        background: #667eea;
        color: white;
        border: none;
        padding: 7px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: 0.3s;
    }
    .filters .btn-filter:hover { background: #5a67d8; }
    .filters .btn-reset {
        background: #e2e8f0;
        color: #4a5568;
        border: none;
        padding: 7px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
    }
    .filters .btn-reset:hover { background: #cbd5e0; }

    /* ===== TABLE ===== */
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
        min-width: 800px;
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
        position: sticky;
        top: 0;
        background: #f7fafc;
        z-index: 10;
    }
    .table tbody tr { border-bottom: 1px solid #f0f2f5; transition: 0.3s; }
    .table tbody tr:hover { background: #f7fafc; }
    .table tbody td { padding: 8px 12px; vertical-align: middle; }

    .badge-recu {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        background: #ebf8ff;
        color: #2b6cb0;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .badge-recu .fa-check-circle { color: #48bb78; }

    .badge-mois {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #f7fafc;
        color: #4a5568;
    }

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
    .btn-actions .btn-view { background: #ebf8ff; color: #2b6cb0; }
    .btn-actions .btn-view:hover { background: #bee3f8; }
    .btn-actions .btn-edit { background: #fefcbf; color: #975a16; }
    .btn-actions .btn-edit:hover { background: #f6c23e; }
    .btn-actions .btn-recu { background: #fefcbf; color: #975a16; }
    .btn-actions .btn-recu:hover { background: #f6c23e; }
    .btn-actions .btn-print-recu { background: #38a169; color: white; }
    .btn-actions .btn-print-recu:hover { background: #2f855a; transform: translateY(-1px); }
    .btn-actions .btn-delete { background: #fff5f5; color: #9b2c2c; }
    .btn-actions .btn-delete:hover { background: #fed7d7; }

    /* ===== MODAL ===== */
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
        max-width: 700px;
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
    .modal-body .form-group { margin-bottom: 16px; }
    .modal-body .form-group label {
        display: block;
        font-weight: 600;
        font-size: 0.85rem;
        color: #2d3748;
        margin-bottom: 5px;
    }
    .modal-body .form-group .required { color: #e53e3e; margin-left: 2px; }
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
    .modal-body .btn-cancel:hover { background: #f7fafc; }
    .modal-body .btn-submit {
        padding: 10px 30px;
        border-radius: 10px;
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
    .modal-body .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
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
    .modal-body .alert-box.success {
        background: #f0fff4;
        color: #276749;
        border: 1px solid #c6f6d5;
    }
    .modal-body .alert-box i { font-size: 1.1rem; }

    /* ===== RECHERCHE ÉLÈVE ===== */
    .search-box-wrapper {
        position: relative;
    }
    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 2px solid #e2e8f0;
        border-top: none;
        border-radius: 0 0 10px 10px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .search-results .result-item {
        padding: 10px 14px;
        border-bottom: 1px solid #f0f2f5;
        cursor: pointer;
        transition: 0.3s;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .search-results .result-item:hover {
        background: #f7fafc;
    }
    .search-results .result-item .matricule {
        font-weight: 600;
        color: #667eea;
    }
    .search-results .result-item .nom {
        color: #1a202c;
    }
    .search-results .result-item .btn-select {
        background: #667eea;
        color: white;
        border: none;
        padding: 4px 14px;
        border-radius: 6px;
        font-size: 0.75rem;
        cursor: pointer;
        transition: 0.3s;
    }
    .search-results .result-item .btn-select:hover {
        background: #5a67d8;
    }
    .search-results .result-item .info {
        color: #718096;
        font-size: 0.85rem;
    }
    .search-results .no-result {
        padding: 20px;
        text-align: center;
        color: #a0aec0;
    }
    .eleve-selectionne {
        border: 2px solid #48bb78;
        border-radius: 10px;
        background: #f0fff4;
        padding: 8px 12px;
    }
    .input-group .btn-primary {
        background: #667eea;
        border-color: #667eea;
    }
    .input-group .btn-primary:hover {
        background: #5a67d8;
        border-color: #5a67d8;
    }

    /* ===== MODAL EDIT ===== */
    #modalEdit .modal-content {
        max-width: 650px;
    }
    #modalEdit .modal-header h4 i {
        color: #ed8936;
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
        
        .main-content {
            padding: 15px !important;
            width: 100% !important;
            max-height: none;
        }

        .page-header {
            flex-direction: column;
            text-align: center;
        }
        .page-header .header-right {
            flex-wrap: wrap;
            justify-content: center;
        }
        .page-header .header-right .btn-add,
        .page-header .header-right .btn-pdf,
        .page-header .header-right .btn-print {
            width: 100%;
            justify-content: center;
        }

        .stats-row { grid-template-columns: 1fr 1fr; }
        .filters { flex-direction: column; }
        .filters input, .filters select { width: 100%; min-width: auto; }
        .filters .btn-filter, .filters .btn-reset { width: 100%; justify-content: center; }
        .btn-actions { flex-direction: column; }
        .btn-actions .btn-sm { width: 100%; justify-content: center; }
        .modal-content { padding: 20px; margin: 10px; }
        .modal-body .form-actions { flex-direction: column; }
        .modal-body .form-actions .btn-cancel,
        .modal-body .form-actions .btn-submit { width: 100%; justify-content: center; }
        .search-results { max-height: 200px; }
    }

    @media (max-width: 576px) {
        .main-content { padding: 10px !important; }
        .page-header h2 { font-size: 1.1rem; }
        .stats-row { grid-template-columns: 1fr; }
        .stat-item { padding: 10px 14px; }
        .stat-item .stat-info .number { font-size: 1rem; }
        .table { font-size: 0.7rem; min-width: 600px; }
        .table thead th, .table tbody td { padding: 4px 6px; }
        .sidebar-toggle-mobile {
            width: 40px;
            height: 40px;
            font-size: 0.9rem;
            bottom: 15px;
            left: 15px;
        }
        .modal-content { padding: 15px; }
        .modal-header h4 { font-size: 1rem; }
        .modal-body .form-group .form-control,
        .modal-body .form-group .form-select { padding: 8px 12px; font-size: 0.85rem; }
        .search-results .result-item {
            flex-direction: column;
            text-align: center;
            gap: 5px;
        }
        .search-results .result-item .btn-select {
            width: 100%;
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
    <div class="sidebar-caissier" id="sidebarCaissier">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class="fas fa-coins"></i></div>
            <span>GestionScolaire</span>
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>

        <ul class="sidebar-menu">
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li class="menu-section"><i class="fas fa-money-bill-wave"></i> Paiements</li>
            <li><a href="index.php" class="active"><i class="fas fa-list"></i> Liste des paiements</a></li>
            <li class="menu-section"><i class="fas fa-users"></i> Élèves</li>
            <li><a href="../eleves/index.php"><i class="fas fa-user-graduate"></i> Liste des élèves</a></li>
            <li><a href="../eleves/situation_financiere.php"><i class="fas fa-chart-pie"></i> Situation financière</a></li>
            <li class="menu-section"><i class="fas fa-file-invoice"></i> Reçus</li>
            <li><a href="../recus/generer.php"><i class="fas fa-file-invoice"></i> Générer un reçu</a></li>
            <li><a href="../recus/historique.php"><i class="fas fa-history"></i> Historique des reçus</a></li>
            <li class="menu-section"><i class="fas fa-user-circle"></i> Compte</li>
            <li><a href="../profil/index.php"><i class="fas fa-user-circle"></i> Mon profil</a></li>
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
                <i class="fas fa-list"></i>
                Liste des paiements
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2">
                    <?php echo $total_paiements; ?> paiement(s)
                </span>
            </h2>
            <div class="header-right">
                <button class="btn-add" onclick="openModal()">
                    <i class="fas fa-plus"></i> Nouveau paiement
                </button>
                <button class="btn-pdf" onclick="genererPDF()">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button class="btn-print" onclick="imprimer()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>
        </div>

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(102, 126, 234, 0.15); color: #667eea;">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_paiements; ?></div>
                    <div class="label">Total paiements</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <div class="number">$<?php echo number_format($montant_total, 2); ?></div>
                    <div class="label">Montant total</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $stats_jour['total'] ?? 0; ?></div>
                    <div class="label">Aujourd'hui ($<?php echo number_format($stats_jour['total_montant'] ?? 0, 2); ?>)</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $stats_mois['total'] ?? 0; ?></div>
                    <div class="label">Ce mois ($<?php echo number_format($stats_mois['total_montant'] ?? 0, 2); ?>)</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $stats_recus['total'] ?? 0; ?></div>
                    <div class="label">Reçus générés</div>
                </div>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <form method="GET" action="" class="filters">
            <input type="text" name="search" placeholder="🔍 Rechercher..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <input type="date" name="date" value="<?php echo htmlspecialchars($filtre_date); ?>" title="Date">
            <select name="mois">
                <option value="">Tous les mois</option>
                <?php foreach ($mois_liste as $m): ?>
                    <option value="<?php echo $m; ?>" <?php echo $filtre_mois == $m ? 'selected' : ''; ?>>
                        <?php echo $m; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="frais">
                <option value="">Tous les frais</option>
                <?php foreach ($frais_list as $f): ?>
                    <option value="<?php echo $f['idFrais']; ?>" <?php echo $filtre_frais == $f['idFrais'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($f['libelle']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="index.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>

        <!-- ===== TABLE ===== -->
        <div class="table-container" id="tableContainer">
            <div class="table-responsive">
                <table class="table" id="paiementTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i></th>
                            <th><i class="fas fa-id-card"></i> Matricule</th>
                            <th><i class="fas fa-user"></i> Élève</th>
                            <th><i class="fas fa-tag"></i> Frais</th>
                            <th><i class="fas fa-calendar-alt"></i> Mois</th>
                            <th><i class="fas fa-dollar-sign"></i> Montant</th>
                            <th><i class="fas fa-chalkboard"></i> Classe</th>
                            <th><i class="fas fa-user-cog"></i> Caissier</th>
                            <th><i class="fas fa-clock"></i> Date</th>
                            <th><i class="fas fa-file-invoice"></i> Reçu</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paiements)): ?>
                            <tr>
                                <td colspan="11">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun paiement trouvé</p>
                                        <button class="btn btn-primary btn-sm mt-2" onclick="openModal()">
                                            <i class="fas fa-plus"></i> Enregistrer un paiement
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paiements as $index => $p): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($p['matricule']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?>
                                        <?php if (!empty($p['postNom'])): ?>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($p['postNom']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['frais_libelle']); ?></td>
                                    <td>
                                        <?php if (!empty($p['mois'])): ?>
                                            <span class="badge-mois">📅 <?php echo htmlspecialchars($p['mois']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>$<?php echo number_format($p['montantPaye'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['nomClasse']); ?></td>
                                    <td>
                                        <small><?php echo htmlspecialchars($p['caissier_nom'] . ' ' . $p['caissier_prenom']); ?></small>
                                    </td>
                                    <td><small><?php echo date('d/m/Y H:i', strtotime($p['datePaiement'])); ?></small></td>
                                    <td>
                                        <?php if (!empty($p['numeroRecu'])): ?>
                                            <span class="badge-recu">
                                                <i class="fas fa-check-circle"></i>
                                                <?php echo htmlspecialchars($p['numeroRecu']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:0.7rem;">
                                                <i class="fas fa-clock me-1"></i> Non généré
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-actions">
                                            <a href="voir.php?id=<?php echo $p['idPaiement']; ?>" class="btn-sm btn-view" title="Voir le paiement">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn-sm btn-edit" title="Modifier le paiement" onclick="openEditModal(<?php echo $p['idPaiement']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if (!empty($p['numeroRecu'])): ?>
                                                <a href="../recus/imprimer.php?id=<?php echo $p['idPaiement']; ?>" class="btn-sm btn-print-recu" title="Imprimer le reçu" target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="../recus/imprimer.php?id=<?php echo $p['idPaiement']; ?>" class="btn-sm btn-recu" title="Générer le reçu">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button class="btn-sm btn-delete" title="Supprimer" onclick="supprimer(<?php echo $p['idPaiement']; ?>, '<?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?>')">
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

        <!-- ===== PAGINATION INFO ===== -->
        <?php if (!empty($paiements)): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted" style="font-size:0.8rem;">
                Affichage de <strong><?php echo count($paiements); ?></strong> paiement(s)
            </span>
            <span class="text-muted" style="font-size:0.8rem;">
                Total: <strong>$<?php echo number_format($montant_total, 2); ?></strong>
            </span>
        </div>
        <?php endif; ?>

        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL AJOUT PAIEMENT AVEC RECHERCHE -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalAjout">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h4><i class="fas fa-plus-circle"></i> Enregistrer un paiement</h4>
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
            
            <form method="POST" action="" id="formPaiement">
                <input type="hidden" name="action" value="ajouter_paiement">

                <!-- ===== RECHERCHE D'ÉLÈVE ===== -->
                <div class="form-group">
                    <label><i class="fas fa-search me-1"></i> Rechercher un élève <span class="required">*</span></label>
                    <div class="search-box-wrapper">
                        <div class="input-group">
                            <input type="text" class="form-control" id="searchEleveInput" 
                                   placeholder="Entrez le nom, prénom ou matricule..." 
                                   autocomplete="off">
                            <button class="btn btn-primary" type="button" onclick="rechercherEleves()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <div id="resultatsRecherche" class="search-results" style="display:none;">
                            <!-- Les résultats s'affichent ici -->
                        </div>
                    </div>
                    <small class="form-text text-muted">
                        <i class="fas fa-info-circle me-1"></i> Recherchez un élève par nom, prénom ou matricule.
                    </small>
                </div>

                <!-- ===== ÉLÈVE SÉLECTIONNÉ ===== -->
                <div class="form-group" id="eleveSelectionneGroup" style="display:none;">
                    <label><i class="fas fa-user-check me-1"></i> Élève sélectionné</label>
                    <div class="eleve-selectionne">
                        <input type="hidden" name="idEleve" id="idEleve" value="">
                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                            <div>
                                <span id="eleveNom" class="fw-bold"></span>
                                <span id="eleveMatricule" class="text-muted ms-2"></span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deselectionnerEleve()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Frais (avec montant intégré) -->
                <div class="form-group">
                    <label><i class="fas fa-tag me-1"></i> Type de frais <span class="required">*</span></label>
                    <select class="form-select" name="idFrais" id="idFrais" required onchange="mettreAJourMontant()">
                        <option value="">Sélectionner un frais</option>
                        <?php foreach ($frais_modal as $f): ?>
                            <option value="<?php echo $f['idFrais']; ?>" data-montant="<?php echo $f['montant']; ?>">
                                <?php echo htmlspecialchars($f['libelle']); ?> - 
                                $<?php echo number_format($f['montant'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Montant (automatique, en lecture seule) -->
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign me-1"></i> Montant à payer</label>
                    <div class="form-control" style="background: #f7fafc; font-weight: 700; color: #1a202c; font-size: 1.1rem; cursor: default;">
                        <span id="montantAffiche">$0.00</span>
                    </div>
                    <small class="form-text text-muted">
                        <i class="fas fa-info-circle me-1"></i> Le montant est automatiquement récupéré du frais sélectionné.
                    </small>
                </div>

                <!-- Mois -->
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt me-1"></i> Mois <span class="required">*</span></label>
                    <select class="form-select" name="mois" required>
                        <option value="">Sélectionner un mois</option>
                        <?php foreach ($mois_liste as $m): ?>
                            <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt me-1"></i> Date de paiement</label>
                    <input type="datetime-local" class="form-control" name="datePaiement" 
                           value="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn-submit" id="btnEnregistrer" disabled>
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL MODIFIER PAIEMENT -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h4><i class="fas fa-edit" style="color:#ed8936;"></i> Modifier le paiement</h4>
            <button class="modal-close" onclick="closeEditModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="editModalBody">
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2 text-muted">Chargement du formulaire...</p>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SCRIPTS -->
<!-- ============================================================ -->
<script>
    // ===== RECHERCHE D'ÉLÈVES =====
    let searchTimeout = null;
    
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchEleveInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (e.key === 'Enter') {
                    rechercherEleves();
                    return;
                }
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(function() {
                        rechercherEleves();
                    }, 300);
                } else {
                    document.getElementById('resultatsRecherche').style.display = 'none';
                }
            });
        }
        
        // Initialiser le montant
        mettreAJourMontant();
    });

    function rechercherEleves() {
        const query = document.getElementById('searchEleveInput').value.trim();
        const resultsDiv = document.getElementById('resultatsRecherche');
        
        if (query.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }
        
        resultsDiv.innerHTML = '<div class="no-result"><i class="fas fa-spinner fa-spin me-2"></i> Recherche en cours...</div>';
        resultsDiv.style.display = 'block';
        
        fetch('rechercher_eleves.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.eleves.length > 0) {
                    let html = '';
                    data.eleves.forEach(function(eleve) {
                        html += `
                            <div class="result-item" onclick="selectionnerEleve(${eleve.idEleve}, '${eleve.nom} ${eleve.prenom}', '${eleve.matricule}')">
                                <div>
                                    <span class="matricule">${eleve.matricule}</span>
                                    <span class="nom">${eleve.nom} ${eleve.prenom}</span>
                                    <span class="info ms-2">${eleve.nomClasse || 'Non inscrit'}</span>
                                </div>
                                <button class="btn-select">Sélectionner</button>
                            </div>
                        `;
                    });
                    resultsDiv.innerHTML = html;
                } else {
                    resultsDiv.innerHTML = '<div class="no-result"><i class="fas fa-user-slash me-2"></i> Aucun élève trouvé</div>';
                }
            })
            .catch(error => {
                resultsDiv.innerHTML = '<div class="no-result"><i class="fas fa-exclamation-triangle me-2"></i> Erreur de recherche</div>';
                console.error('Erreur:', error);
            });
    }

    // ===== SÉLECTIONNER UN ÉLÈVE =====
    function selectionnerEleve(id, nom, matricule) {
        document.getElementById('idEleve').value = id;
        document.getElementById('eleveNom').textContent = nom;
        document.getElementById('eleveMatricule').textContent = '(' + matricule + ')';
        document.getElementById('eleveSelectionneGroup').style.display = 'block';
        document.getElementById('resultatsRecherche').style.display = 'none';
        document.getElementById('searchEleveInput').value = nom + ' (' + matricule + ')';
        document.getElementById('btnEnregistrer').disabled = false;
        document.getElementById('searchEleveInput').classList.add('is-valid');
    }

    // ===== DÉSÉLECTIONNER UN ÉLÈVE =====
    function deselectionnerEleve() {
        document.getElementById('idEleve').value = '';
        document.getElementById('eleveSelectionneGroup').style.display = 'none';
        document.getElementById('searchEleveInput').value = '';
        document.getElementById('searchEleveInput').classList.remove('is-valid');
        document.getElementById('btnEnregistrer').disabled = true;
        document.getElementById('resultatsRecherche').style.display = 'none';
    }

    // ===== FERMER LES RÉSULTATS EN CLIQUANT AILLEURS =====
    document.addEventListener('click', function(e) {
        const wrapper = document.querySelector('.search-box-wrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            document.getElementById('resultatsRecherche').style.display = 'none';
        }
    });

    // ===== Fonction pour mettre à jour le montant affiché =====
    function mettreAJourMontant() {
        const select = document.getElementById('idFrais');
        const montantAffiche = document.getElementById('montantAffiche');
        const selectedOption = select.options[select.selectedIndex];
        
        if (selectedOption && selectedOption.value) {
            const montant = selectedOption.getAttribute('data-montant') || 0;
            montantAffiche.textContent = '$' + parseFloat(montant).toFixed(2);
        } else {
            montantAffiche.textContent = '$0.00';
        }
    }

    // ===== MODAL AJOUT =====
    function openModal() {
        document.getElementById('modalAjout').classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Réinitialiser le formulaire
        document.getElementById('formPaiement').reset();
        document.getElementById('idEleve').value = '';
        document.getElementById('eleveSelectionneGroup').style.display = 'none';
        document.getElementById('searchEleveInput').value = '';
        document.getElementById('searchEleveInput').classList.remove('is-valid');
        document.getElementById('btnEnregistrer').disabled = true;
        document.getElementById('resultatsRecherche').style.display = 'none';
        mettreAJourMontant();
    }

    function closeModal() {
        document.getElementById('modalAjout').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('modalAjout').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    // ===== MODAL MODIFICATION =====
    function openEditModal(id) {
        const modal = document.getElementById('modalEdit');
        const body = document.getElementById('editModalBody');
        
        body.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2 text-muted">Chargement du formulaire...</p>
            </div>
        `;
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        fetch('modifier.php?id=' + id)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const bodyContent = doc.querySelector('body')?.innerHTML || html;
                
                let cleanContent = bodyContent
                    .replace(/window\.parent\.closeEditModal/g, 'closeEditModal')
                    .replace(/window\.parent\.location\.href/g, 'window.location.href');
                
                body.innerHTML = cleanContent || '<p class="text-center text-danger">Erreur de chargement.</p>';
                
                const form = body.querySelector('#editForm');
                if (form) {
                    form.onsubmit = function(e) {
                        e.preventDefault();
                        const formData = new FormData(form);
                        
                        fetch('modifier.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.text())
                        .then(result => {
                            body.innerHTML = result;
                            if (result.includes('alert-box success')) {
                                setTimeout(function() {
                                    closeEditModal();
                                    window.location.reload();
                                }, 1500);
                            }
                        })
                        .catch(error => {
                            body.innerHTML = '<p class="text-center text-danger">Erreur lors de l\'envoi.</p>';
                        });
                    };
                }
                
                const cancelBtn = body.querySelector('.btn-cancel-modal');
                if (cancelBtn) {
                    cancelBtn.onclick = function() {
                        closeEditModal();
                    };
                }
            })
            .catch(error => {
                body.innerHTML = '<p class="text-center text-danger">Erreur de chargement.</p>';
            });
    }

    function closeEditModal() {
        document.getElementById('modalEdit').classList.remove('active');
        document.body.style.overflow = '';
    }

    // ===== Fermer avec Echap =====
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const editModal = document.getElementById('modalEdit');
            if (editModal.classList.contains('active')) {
                closeEditModal();
            } else {
                closeModal();
            }
        }
    });

    // ===== Fermer en cliquant en dehors =====
    document.getElementById('modalEdit').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

    // ===== PDF =====
    function genererPDF() {
        window.open('export_pdf.php' + window.location.search, '_blank');
    }

    // ===== IMPRIMER =====
    function imprimer() {
        const contenu = document.getElementById('tableContainer').innerHTML;
        const titre = '<h2 style="text-align:center;margin-bottom:20px;"><i class="fas fa-list"></i> Liste des paiements</h2>';
        const styles = `
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                th { background: #f0f0f0; padding: 8px; text-align: left; border: 1px solid #ddd; }
                td { padding: 8px; border: 1px solid #ddd; }
                .badge-recu { padding: 2px 8px; border-radius: 4px; font-size: 10px; background: #d6edff; color: #004085; }
                .badge-mois { padding: 2px 8px; border-radius: 4px; font-size: 10px; background: #f0f0f0; color: #333; }
                .text-center { text-align: center; }
                .text-muted { color: #6c757d; }
                .btn-actions { display: none; }
            </style>
        `;
        const imprimerWindow = window.open('', '_blank');
        imprimerWindow.document.write(styles + titre + contenu);
        imprimerWindow.document.close();
        imprimerWindow.print();
    }

    // ===== SUPPRIMER =====
    function supprimer(id, nom) {
        if (confirm('Voulez-vous vraiment supprimer le paiement de ' + nom + ' ?\nCette action est irréversible.')) {
            window.location.href = 'supprimer.php?id=' + id;
        }
    }

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

        <?php if (isset($_SESSION['show_modal']) && $_SESSION['show_modal']): ?>
            openModal();
            <?php unset($_SESSION['show_modal']); ?>
        <?php endif; ?>
    });
</script>
</body>
</html>