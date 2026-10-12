<?php
// receptionniste/eleves/ajax_get_eleve.php - Récupérer les données d'un élève pour modification
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Réceptionniste') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit();
}

$stmt = $db->prepare("SELECT * FROM eleves WHERE idEleve = :id");
$stmt->execute(['id' => $id]);
$eleve = $stmt->fetch();

if ($eleve) {
    echo json_encode([
        'success' => true,
        'idEleve' => $eleve['idEleve'],
        'matricule' => $eleve['matricule'],
        'nom' => $eleve['nom'],
        'postNom' => $eleve['postNom'],
        'prenom' => $eleve['prenom'],
        'sexe' => $eleve['sexe'],
        'dateNaissance' => $eleve['dateNaissance'],
        'lieuNaissance' => $eleve['lieuNaissance'],
        'telephone' => $eleve['telephone'],
        'nomPere' => $eleve['nomPere'],
        'nomMere' => $eleve['nomMere'],
        'adresse' => $eleve['adresse']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Élève non trouvé']);
}
?>