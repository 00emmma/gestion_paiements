<?php
// admin/classes/ajax_ajouter.php - Traitement AJAX pour l'ajout d'une classe
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();

if (!isset($_POST['ajax']) || $_POST['ajax'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit();
}

$nomClasse = trim($_POST['nomClasse'] ?? '');
$idNiveau = intval($_POST['idNiveau'] ?? 0);

if (empty($nomClasse)) {
    echo json_encode(['success' => false, 'message' => 'Le nom de la classe est requis']);
    exit();
}

if (empty($idNiveau)) {
    echo json_encode(['success' => false, 'message' => 'Le niveau est requis']);
    exit();
}

// Vérifier si la classe existe déjà
try {
    $stmt = $db->prepare("SELECT idClasse FROM classes WHERE nomClasse = :nomClasse AND idNiveau = :idNiveau");
    $stmt->execute(['nomClasse' => $nomClasse, 'idNiveau' => $idNiveau]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cette classe existe déjà pour ce niveau.']);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de vérification']);
    exit();
}

try {
    $sql = "INSERT INTO classes (nomClasse, idNiveau) VALUES (:nomClasse, :idNiveau)";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        'nomClasse' => $nomClasse,
        'idNiveau' => $idNiveau
    ]);
    
    if ($result) {
        // Journalisation
        try {
            $sqlLog = "INSERT INTO journal_activite (idUtilisateur, action, type, dateAction) 
                       VALUES (:idUser, :action, :type, NOW())";
            $stmtLog = $db->prepare($sqlLog);
            $stmtLog->execute([
                'idUser' => $_SESSION['user_id'],
                'action' => "Ajout de la classe " . $nomClasse,
                'type' => 'creation'
            ]);
        } catch (PDOException $e) {}
        
        echo json_encode(['success' => true, 'message' => 'Classe ajoutée avec succès !']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
}
?>