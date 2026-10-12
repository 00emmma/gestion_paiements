<?php
// caissier/paiements/rechercher_eleves.php - Recherche AJAX
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'eleves' => []]);
    exit();
}

try {
    // Rechercher les élèves
    $stmt = $db->prepare("
        SELECT e.idEleve, e.matricule, e.nom, e.prenom, 
               c.nomClasse
        FROM eleves e
        LEFT JOIN inscriptions i ON i.idEleve = e.idEleve AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
        LEFT JOIN classes c ON i.idClasse = c.idClasse
        WHERE e.matricule LIKE :search 
           OR e.nom LIKE :search 
           OR e.prenom LIKE :search
        ORDER BY e.nom ASC
        LIMIT 10
    ");
    $stmt->execute(['search' => "%$query%"]);
    $eleves = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'eleves' => $eleves]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>