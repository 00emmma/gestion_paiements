<?php
// admin/classes/ajax_modifier.php - Traitement AJAX pour la modification d'une classe
session_start();

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté et est administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();

// Vérifier si c'est une requête AJAX
if (!isset($_POST['ajax']) || $_POST['ajax'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit();
}

// Récupérer les données
$idClasse = intval($_POST['idClasse'] ?? 0);
$nomClasse = trim($_POST['nomClasse'] ?? '');
$idNiveau = intval($_POST['idNiveau'] ?? 0);

// Validation
if (empty($idClasse)) {
    echo json_encode(['success' => false, 'message' => 'ID de classe manquant']);
    exit();
}

if (empty($nomClasse)) {
    echo json_encode(['success' => false, 'message' => 'Le nom de la classe est requis']);
    exit();
}

if (empty($idNiveau)) {
    echo json_encode(['success' => false, 'message' => 'Le niveau est requis']);
    exit();
}

// Vérifier si une autre classe avec le même nom existe déjà
try {
    $stmt = $db->prepare("SELECT idClasse FROM classes WHERE nomClasse = :nomClasse AND idNiveau = :idNiveau AND idClasse != :idClasse");
    $stmt->execute([
        'nomClasse' => $nomClasse,
        'idNiveau' => $idNiveau,
        'idClasse' => $idClasse
    ]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cette classe existe déjà pour ce niveau.']);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de vérification : ' . $e->getMessage()]);
    exit();
}

// Mettre à jour la classe
try {
    $sql = "UPDATE classes SET nomClasse = :nomClasse, idNiveau = :idNiveau WHERE idClasse = :idClasse";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        'nomClasse' => $nomClasse,
        'idNiveau' => $idNiveau,
        'idClasse' => $idClasse
    ]);
    
    if ($result) {
        // Journalisation
        try {
            $sqlLog = "INSERT INTO journal_activite (idUtilisateur, action, type, dateAction) 
                       VALUES (:idUser, :action, :type, NOW())";
            $stmtLog = $db->prepare($sqlLog);
            $stmtLog->execute([
                'idUser' => $_SESSION['user_id'],
                'action' => "Modification de la classe " . $nomClasse,
                'type' => 'modification'
            ]);
        } catch (PDOException $e) {
            // Ignorer les erreurs de journalisation
        }
        
        echo json_encode(['success' => true, 'message' => 'Classe modifiée avec succès !']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
}
?>