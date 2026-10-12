<?php
// admin/frais/get_frais.php - Récupérer les données d'un frais pour modification
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();
$idFrais = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idFrais <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit();
}

// Vérifier si la colonne idNiveau existe
try {
    $stmt = $db->query("SHOW COLUMNS FROM frais LIKE 'idNiveau'");
    $colonne_niveau_existe = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $colonne_niveau_existe = false;
}

if ($colonne_niveau_existe) {
    $stmt = $db->prepare("SELECT * FROM frais WHERE idFrais = :id");
} else {
    $stmt = $db->prepare("SELECT idFrais, libelle, montant, typeFrais, idAnnee, statut FROM frais WHERE idFrais = :id");
}
$stmt->execute(['id' => $idFrais]);
$frais = $stmt->fetch();

if ($frais) {
    $response = [
        'success' => true,
        'libelle' => $frais['libelle'],
        'montant' => $frais['montant'],
        'typeFrais' => $frais['typeFrais'],
        'idAnnee' => $frais['idAnnee'],
        'statut' => $frais['statut']
    ];
    if ($colonne_niveau_existe) {
        $response['idNiveau'] = $frais['idNiveau'];
    }
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Frais non trouvé']);
}
?>