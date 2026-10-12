<?php
// receptionniste/eleves/ajax_modifier.php - Modifier un élève via AJAX
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Réceptionniste') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();

if (!isset($_POST['ajax']) || $_POST['ajax'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit();
}

$idEleve = intval($_POST['idEleve'] ?? 0);
$nom = trim($_POST['nom'] ?? '');
$postNom = trim($_POST['postNom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$sexe = $_POST['sexe'] ?? '';
$dateNaissance = $_POST['dateNaissance'] ?? null;
$lieuNaissance = trim($_POST['lieuNaissance'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$nomPere = trim($_POST['nomPere'] ?? '');
$nomMere = trim($_POST['nomMere'] ?? '');
$adresse = trim($_POST['adresse'] ?? '');

if ($idEleve <= 0 || empty($nom) || empty($prenom) || empty($sexe)) {
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit();
}

try {
    $sql = "UPDATE eleves SET 
            nom = :nom,
            postNom = :postNom,
            prenom = :prenom,
            sexe = :sexe,
            dateNaissance = :dateNaissance,
            lieuNaissance = :lieuNaissance,
            telephone = :telephone,
            nomPere = :nomPere,
            nomMere = :nomMere,
            adresse = :adresse
            WHERE idEleve = :id";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        'nom' => $nom,
        'postNom' => $postNom,
        'prenom' => $prenom,
        'sexe' => $sexe,
        'dateNaissance' => $dateNaissance,
        'lieuNaissance' => $lieuNaissance,
        'telephone' => $telephone,
        'nomPere' => $nomPere,
        'nomMere' => $nomMere,
        'adresse' => $adresse,
        'id' => $idEleve
    ]);
    
    if ($result) {
        // Journaliser
        try {
            $sqlLog = "INSERT INTO journal_activite (idUtilisateur, action, type, dateAction) 
                       VALUES (:idUser, :action, :type, NOW())";
            $stmtLog = $db->prepare($sqlLog);
            $stmtLog->execute([
                'idUser' => $_SESSION['user_id'],
                'action' => "Modification de l'élève ID: " . $idEleve,
                'type' => 'modification'
            ]);
        } catch (PDOException $e) {}
        
        echo json_encode(['success' => true, 'message' => 'Élève modifié avec succès !']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>