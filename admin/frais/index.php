<?php
// admin/frais/index.php - Gestion des frais (sans mois)
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Administrateur') {
    header('Location: ../index.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$page_title = 'Gestion des frais - Administrateur';
$db = db();

// ===== VÉRIFIER SI LA COLONNE idNiveau EXISTE =====
$colonne_niveau_existe = false;
try {
    $stmt = $db->query("SHOW COLUMNS FROM frais LIKE 'idNiveau'");
    $colonne_niveau_existe = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $colonne_niveau_existe = false;
}

// ===== SI LA COLONNE N'EXISTE PAS, L'AJOUTER =====
if (!$colonne_niveau_existe) {
    try {
        $db->exec("ALTER TABLE frais ADD COLUMN idNiveau INT(11) NULL AFTER idAnnee");
        $colonne_niveau_existe = true;
    } catch (PDOException $e) {
        // Ignorer l'erreur si la colonne existe déjà
    }
}

// ===== RÉCUPÉRER LES NIVEAUX =====
$niveaux = [];
try {
    $stmt = $db->query("SELECT * FROM niveaux ORDER BY nomNiveau");
    $niveaux = $stmt->fetchAll();
} catch (PDOException $e) {
    $niveaux = [];
}

// ===== TRAITEMENT DES FORMULAIRES =====
$error = '';
$success = '';

// Ajouter un frais
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'ajouter') {
        $libelle = trim($_POST['libelle'] ?? '');
        $montant = floatval($_POST['montant'] ?? 0);
        $typeFrais = $_POST['typeFrais'] ?? 'Mensualité';
        $idAnnee = intval($_POST['idAnnee'] ?? 0);
        $idNiveau = !empty($_POST['idNiveau']) ? intval($_POST['idNiveau']) : null;
        $statut = isset($_POST['statut']) ? 'Actif' : 'Inactif';
        
        if (empty($libelle)) {
            $error = 'Le libellé est obligatoire.';
        } elseif ($montant <= 0) {
            $error = 'Le montant doit être supérieur à 0.';
        } elseif ($idAnnee <= 0) {
            $error = 'Veuillez sélectionner une année scolaire.';
        } else {
            // Vérifier si un frais existe déjà pour ce niveau et cette année
            $sql_check = "SELECT idFrais FROM frais WHERE libelle = :libelle AND idAnnee = :idAnnee";
            $params_check = ['libelle' => $libelle, 'idAnnee' => $idAnnee];
            if ($colonne_niveau_existe && $idNiveau) {
                $sql_check .= " AND idNiveau = :idNiveau";
                $params_check['idNiveau'] = $idNiveau;
            }
            $stmt = $db->prepare($sql_check);
            $stmt->execute($params_check);
            
            if ($stmt->fetch()) {
                $error = 'Un frais avec ce libellé existe déjà pour cette année et ce niveau.';
            } else {
                if ($colonne_niveau_existe) {
                    $sql = "INSERT INTO frais (libelle, montant, typeFrais, idAnnee, idNiveau, statut)
                            VALUES (:libelle, :montant, :typeFrais, :idAnnee, :idNiveau, :statut)";
                    $params = [
                        'libelle' => $libelle,
                        'montant' => $montant,
                        'typeFrais' => $typeFrais,
                        'idAnnee' => $idAnnee,
                        'idNiveau' => $idNiveau,
                        'statut' => $statut
                    ];
                } else {
                    $sql = "INSERT INTO frais (libelle, montant, typeFrais, idAnnee, statut)
                            VALUES (:libelle, :montant, :typeFrais, :idAnnee, :statut)";
                    $params = [
                        'libelle' => $libelle,
                        'montant' => $montant,
                        'typeFrais' => $typeFrais,
                        'idAnnee' => $idAnnee,
                        'statut' => $statut
                    ];
                }
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute($params);
                
                if ($result) {
                    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Frais ajouté avec succès !'];
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Erreur lors de l\'ajout du frais.';
                }
            }
        }
    }
    
    // Modifier un frais
    if ($action === 'modifier') {
        $idFrais = intval($_POST['idFrais'] ?? 0);
        $libelle = trim($_POST['libelle'] ?? '');
        $montant = floatval($_POST['montant'] ?? 0);
        $typeFrais = $_POST['typeFrais'] ?? 'Mensualité';
        $idAnnee = intval($_POST['idAnnee'] ?? 0);
        $idNiveau = !empty($_POST['idNiveau']) ? intval($_POST['idNiveau']) : null;
        $statut = isset($_POST['statut']) ? 'Actif' : 'Inactif';
        
        if ($idFrais <= 0) {
            $error = 'Frais invalide.';
        } elseif (empty($libelle)) {
            $error = 'Le libellé est obligatoire.';
        } elseif ($montant <= 0) {
            $error = 'Le montant doit être supérieur à 0.';
        } elseif ($idAnnee <= 0) {
            $error = 'Veuillez sélectionner une année scolaire.';
        } else {
            if ($colonne_niveau_existe) {
                $sql = "UPDATE frais 
                        SET libelle = :libelle, montant = :montant, typeFrais = :typeFrais, 
                            idAnnee = :idAnnee, idNiveau = :idNiveau, statut = :statut
                        WHERE idFrais = :id";
                $params = [
                    'libelle' => $libelle,
                    'montant' => $montant,
                    'typeFrais' => $typeFrais,
                    'idAnnee' => $idAnnee,
                    'idNiveau' => $idNiveau,
                    'statut' => $statut,
                    'id' => $idFrais
                ];
            } else {
                $sql = "UPDATE frais 
                        SET libelle = :libelle, montant = :montant, typeFrais = :typeFrais, 
                            idAnnee = :idAnnee, statut = :statut
                        WHERE idFrais = :id";
                $params = [
                    'libelle' => $libelle,
                    'montant' => $montant,
                    'typeFrais' => $typeFrais,
                    'idAnnee' => $idAnnee,
                    'statut' => $statut,
                    'id' => $idFrais
                ];
            }
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $_SESSION['flash'] = ['type' => 'success', 'text' => 'Frais modifié avec succès !'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'Erreur lors de la modification du frais.';
            }
        }
    }
    
    // Supprimer un frais
    if ($action === 'supprimer') {
        $idFrais = intval($_POST['idFrais'] ?? 0);
        
        if ($idFrais <= 0) {
            $error = 'Frais invalide.';
        } else {
            // Vérifier si des paiements utilisent ce frais
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM paiements WHERE idFrais = :id");
            $stmt->execute(['id' => $idFrais]);
            $count = $stmt->fetch()['total'];
            
            if ($count > 0) {
                $error = 'Ce frais est utilisé par des paiements. Vous ne pouvez pas le supprimer.';
            } else {
                $stmt = $db->prepare("DELETE FROM frais WHERE idFrais = :id");
                $result = $stmt->execute(['id' => $idFrais]);
                
                if ($result) {
                    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Frais supprimé avec succès !'];
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Erreur lors de la suppression du frais.';
                }
            }
        }
    }
}

// Récupérer les années scolaires
$stmt = $db->query("SELECT * FROM annees_scolaires ORDER BY idAnnee DESC");
$annees = $stmt->fetchAll();

// Récupérer les filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtre_niveau = isset($_GET['idNiveau']) ? intval($_GET['idNiveau']) : 0;
$filtre_annee = isset($_GET['idAnnee']) ? intval($_GET['idAnnee']) : 0;
$filtre_statut = isset($_GET['statut']) ? $_GET['statut'] : '';

// Construction de la requête
if ($colonne_niveau_existe) {
    $sql = "SELECT f.*, a.libelle as annee_libelle, n.nomNiveau as niveau_libelle
            FROM frais f
            JOIN annees_scolaires a ON f.idAnnee = a.idAnnee
            LEFT JOIN niveaux n ON f.idNiveau = n.idNiveau
            WHERE 1=1";
} else {
    $sql = "SELECT f.*, a.libelle as annee_libelle, NULL as niveau_libelle
            FROM frais f
            JOIN annees_scolaires a ON f.idAnnee = a.idAnnee
            WHERE 1=1";
}
$params = [];

if (!empty($search)) {
    $sql .= " AND f.libelle LIKE :search";
    $params['search'] = "%$search%";
}

if ($colonne_niveau_existe && $filtre_niveau > 0) {
    $sql .= " AND f.idNiveau = :idNiveau";
    $params['idNiveau'] = $filtre_niveau;
}

if ($filtre_annee > 0) {
    $sql .= " AND f.idAnnee = :idAnnee";
    $params['idAnnee'] = $filtre_annee;
}

if (!empty($filtre_statut)) {
    $sql .= " AND f.statut = :statut";
    $params['statut'] = $filtre_statut;
}

$sql .= " ORDER BY f.idFrais DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$frais = $stmt->fetchAll();

// Statistiques
$total_frais = count($frais);
$total_actif = 0;
$total_inactif = 0;
$montant_total = 0;

foreach ($frais as $f) {
    if ($f['statut'] === 'Actif') $total_actif++;
    else $total_inactif++;
    $montant_total += $f['montant'];
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
        background: #f0f4f8;
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

    /* ===== STATS ===== */
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
        min-width: 700px;
    }
    .table thead {
        background: #f7fafc;
        border-bottom: 2px solid #e2e8f0;
    }
    .table thead th {
        padding: 10px 12px;
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
    .table tbody td { padding: 10px 12px; vertical-align: middle; }

    .badge-statut {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .badge-statut.actif { background: #f0fff4; color: #276749; }
    .badge-statut.inactif { background: #fff5f5; color: #9b2c2c; }

    .badge-type {
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .badge-type.inscription { background: #ebf8ff; color: #2b6cb0; }
    .badge-type.mensualite { background: #fefcbf; color: #975a16; }
    .badge-type.autre { background: #faf5ff; color: #6b46c1; }

    .badge-niveau {
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .badge-niveau.maternelle { background: #fce4ec; color: #c62828; }
    .badge-niveau.primaire { background: #e3f2fd; color: #0d47a1; }
    .badge-niveau.secondaire { background: #fff3e0; color: #e65100; }
    .badge-niveau.default { background: #f5f5f5; color: #616161; }

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
    .btn-actions .btn-edit {
        background: #ebf8ff;
        color: #2b6cb0;
    }
    .btn-actions .btn-edit:hover {
        background: #bee3f8;
    }
    .btn-actions .btn-delete {
        background: #fff5f5;
        color: #9b2c2c;
    }
    .btn-actions .btn-delete:hover {
        background: #fed7d7;
    }

    /* ===== INFO NOTE ===== */
    .info-note {
        background: #ebf8ff;
        padding: 12px 16px;
        border-radius: 8px;
        border-left: 4px solid #667eea;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.85rem;
        color: #2b6cb0;
    }
    .info-note i {
        font-size: 1.2rem;
    }

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
        max-width: 550px;
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
    .modal-body .form-group .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .modal-body .form-group .form-check input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .modal-body .form-group .form-check label {
        margin: 0;
        cursor: pointer;
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

    .alert-box {
        padding: 12px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-box.danger {
        background: #fff5f5;
        color: #c53030;
        border: 1px solid #fed7d7;
    }
    .alert-box.success {
        background: #f0fff4;
        color: #276749;
        border: 1px solid #c6f6d5;
    }
    .alert-box i { font-size: 1.1rem; }

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
        .page-header .header-right .btn-add {
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
            <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
        </div>

        <ul class="sidebar-menu">
            <li class="menu-section"><i class="fas fa-cog"></i> Administration</li>
            <li><a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            <li><a href="../eleves/index.php"><i class="fas fa-users"></i> Élèves</a></li>
            <li><a href="../inscriptions/index.php"><i class="fas fa-book-open"></i> Inscriptions</a></li>
            <li><a href="../paiements/index.php"><i class="fas fa-money-bill-wave"></i> Paiements</a></li>
            <li><a href="index.php" class="active"><i class="fas fa-tag"></i> Frais</a></li>
            <li><a href="../classes/index.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
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
                <i class="fas fa-tag"></i>
                Gestion des frais scolaires
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2">
                    <?php echo $total_frais; ?> frais
                </span>
            </h2>
            <div class="header-right">
                <button class="btn-add" onclick="openModal('ajouter')">
                    <i class="fas fa-plus"></i> Nouveau frais
                </button>
            </div>
        </div>

  

        <!-- ===== STATISTIQUES ===== -->
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(102, 126, 234, 0.15); color: #667eea;">
                    <i class="fas fa-tag"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_frais; ?></div>
                    <div class="label">Total frais</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_actif; ?></div>
                    <div class="label">Actifs</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(237, 137, 54, 0.15); color: #ed8936;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo $total_inactif; ?></div>
                    <div class="label">Inactifs</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon" style="background: rgba(72, 187, 120, 0.15); color: #48bb78;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <div class="number"><?php echo number_format($montant_total, 2); ?> $</div>
                    <div class="label">Montant total</div>
                </div>
            </div>
        </div>

        <!-- ===== FILTRES ===== -->
        <form method="GET" action="" class="filters">
            <input type="text" name="search" placeholder="🔍 Rechercher..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <?php if ($colonne_niveau_existe): ?>
            <select name="idNiveau">
                <option value="">Tous les niveaux</option>
                <?php foreach ($niveaux as $n): ?>
                    <option value="<?php echo $n['idNiveau']; ?>" <?php echo $filtre_niveau == $n['idNiveau'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($n['nomNiveau']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select name="idAnnee">
                <option value="">Toutes les années</option>
                <?php foreach ($annees as $a): ?>
                    <option value="<?php echo $a['idAnnee']; ?>" <?php echo $filtre_annee == $a['idAnnee'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a['libelle']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="statut">
                <option value="">Tous les statuts</option>
                <option value="Actif" <?php echo $filtre_statut == 'Actif' ? 'selected' : ''; ?>>Actif</option>
                <option value="Inactif" <?php echo $filtre_statut == 'Inactif' ? 'selected' : ''; ?>>Inactif</option>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="index.php" class="btn-reset"><i class="fas fa-undo"></i> Réinitialiser</a>
        </form>

        <!-- ===== TABLE ===== -->
        <div class="table-container" id="tableContainer">
            <div class="table-responsive">
                <table class="table" id="fraisTable">
                    <thead>
                        <tr>
                            <th style="width:40px;"><i class="fas fa-hashtag"></i></th>
                            <th><i class="fas fa-tag"></i> Libellé</th>
                            <th><i class="fas fa-dollar-sign"></i> Montant</th>
                            <?php if ($colonne_niveau_existe): ?>
                            <th><i class="fas fa-layer-group"></i> Niveau</th>
                            <?php endif; ?>
                            <th><i class="fas fa-calendar-alt"></i> Année</th>
                            <th><i class="fas fa-toggle-on"></i> Statut</th>
                            <th style="text-align:center; width:100px;"><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($frais)): ?>
                            <tr>
                                <td colspan="<?php echo $colonne_niveau_existe ? 7 : 6; ?>">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>Aucun frais trouvé</p>
                                        <button class="btn btn-primary btn-sm mt-2" onclick="openModal('ajouter')">
                                            <i class="fas fa-plus"></i> Ajouter un frais
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($frais as $index => $f): 
                                $niveauClass = 'default';
                                $niveau = strtolower($f['niveau_libelle'] ?? '');
                                if (strpos($niveau, 'maternelle') !== false) $niveauClass = 'maternelle';
                                elseif (strpos($niveau, 'primaire') !== false) $niveauClass = 'primaire';
                                elseif (strpos($niveau, 'secondaire') !== false) $niveauClass = 'secondaire';
                            ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($f['libelle']); ?></strong></td>
                                    <td><strong><?php echo number_format($f['montant'], 2); ?> $</strong></td>
                                    <?php if ($colonne_niveau_existe): ?>
                                    <td>
                                        <?php if ($f['niveau_libelle']): ?>
                                            <span class="badge-niveau <?php echo $niveauClass; ?>">
                                                <?php echo htmlspecialchars($f['niveau_libelle']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Tous</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($f['annee_libelle']); ?></td>
                                    <td>
                                        <span class="badge-statut <?php echo $f['statut'] === 'Actif' ? 'actif' : 'inactif'; ?>">
                                            <?php echo $f['statut']; ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <div class="btn-actions">
                                            <button class="btn-sm btn-edit" onclick="openModal('modifier', <?php echo $f['idFrais']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-sm btn-delete" onclick="supprimer(<?php echo $f['idFrais']; ?>, '<?php echo htmlspecialchars($f['libelle']); ?>')">
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
<!-- MODAL AJOUT/MODIFIER FRAIS -->
<!-- ============================================================ -->
<div class="modal-overlay" id="modalFrais">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="modalTitle"><i class="fas fa-plus-circle"></i> Ajouter un frais</h4>
            <button class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <?php if (!empty($error)): ?>
                <div class="alert-box danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="" id="formFrais">
                <input type="hidden" name="action" id="formAction" value="ajouter">
                <input type="hidden" name="idFrais" id="idFrais" value="0">
                
                <!-- Libellé -->
                <div class="form-group">
                    <label><i class="fas fa-tag me-1"></i> Libellé <span class="required">*</span></label>
                    <input type="text" class="form-control" name="libelle" id="libelle" 
                           placeholder="Ex: Frais de scolarité" required>
                </div>

                <!-- Montant -->
                <div class="form-group">
                    <label><i class="fas fa-dollar-sign me-1"></i> Montant <span class="required">*</span></label>
                    <input type="number" step="0.01" class="form-control" name="montant" id="montant" 
                           placeholder="0.00" required>
                </div>

                <!-- Type de frais -->
                <div class="form-group">
                    <label><i class="fas fa-info-circle me-1"></i> Type de frais <span class="required">*</span></label>
                    <select class="form-select" name="typeFrais" id="typeFrais" required>
                        <option value="Inscription">Inscription</option>
                        <option value="Mensualité" selected>Mensualité</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>

                <?php if ($colonne_niveau_existe): ?>
                <!-- Niveau -->
                <div class="form-group">
                    <label><i class="fas fa-layer-group me-1"></i> Niveau <span class="required">*</span></label>
                    <select class="form-select" name="idNiveau" id="idNiveau" required>
                        <option value="">Sélectionner un niveau</option>
                        <?php foreach ($niveaux as $n): ?>
                            <option value="<?php echo $n['idNiveau']; ?>">
                                <?php echo htmlspecialchars($n['nomNiveau']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Année scolaire -->
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt me-1"></i> Année scolaire <span class="required">*</span></label>
                    <select class="form-select" name="idAnnee" id="idAnnee" required>
                        <option value="">Sélectionner une année</option>
                        <?php foreach ($annees as $a): ?>
                            <option value="<?php echo $a['idAnnee']; ?>" <?php echo ($a['active'] == 1) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a['libelle']); ?>
                                <?php if ($a['active'] == 1): ?>(Active)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Statut -->
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" name="statut" id="statut" checked>
                        <label for="statut">Actif</label>
                    </div>
                </div>

                <!-- Note sur les mois -->
                <div class="alert-box" style="background: #ebf8ff; color: #2b6cb0; border-color: #bee3f8; margin-bottom: 0;">
                    <i class="fas fa-info-circle"></i>
                    <small>Le caissier sélectionnera le mois lors du paiement.</small>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <span id="btnText">Enregistrer</span>
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
    function openModal(action, id = 0) {
        const modal = document.getElementById('modalFrais');
        const title = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const btnText = document.getElementById('btnText');
        const idFrais = document.getElementById('idFrais');
        
        // Réinitialiser le formulaire
        document.getElementById('formFrais').reset();
        document.getElementById('statut').checked = true;
        document.getElementById('typeFrais').value = 'Mensualité';
        
        if (action === 'ajouter') {
            title.innerHTML = '<i class="fas fa-plus-circle"></i> Ajouter un frais';
            formAction.value = 'ajouter';
            btnText.textContent = 'Enregistrer';
            idFrais.value = 0;
        } else if (action === 'modifier') {
            title.innerHTML = '<i class="fas fa-edit"></i> Modifier le frais';
            formAction.value = 'modifier';
            btnText.textContent = 'Modifier';
            idFrais.value = id;
            
            // Charger les données via AJAX
            fetch('get_frais.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('libelle').value = data.libelle;
                        document.getElementById('montant').value = data.montant;
                        document.getElementById('typeFrais').value = data.typeFrais;
                        <?php if ($colonne_niveau_existe): ?>
                        document.getElementById('idNiveau').value = data.idNiveau || '';
                        <?php endif; ?>
                        document.getElementById('idAnnee').value = data.idAnnee;
                        document.getElementById('statut').checked = data.statut === 'Actif';
                    }
                })
                .catch(error => console.error('Erreur:', error));
        }
        
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('modalFrais').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('modalFrais').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // ===== SUPPRIMER =====
    function supprimer(id, libelle) {
        if (confirm('Voulez-vous vraiment supprimer le frais "' + libelle + '" ?\nCette action est irréversible.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.innerHTML = `
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="idFrais" value="${id}">
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