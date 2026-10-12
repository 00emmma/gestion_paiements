<?php
// receptionniste/eleves/ajax_ajouter.php - Ajouter un élève via AJAX
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
$motDePasse = trim($_POST['motDePasse'] ?? 'password123');

if (empty($nom) || empty($prenom) || empty($sexe)) {
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit();
}

// Générer le matricule
$matricule = 'ELV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

// Vérifier que le matricule n'existe pas déjà
$stmt = $db->prepare("SELECT idEleve FROM eleves WHERE matricule = :matricule");
$stmt->execute(['matricule' => $matricule]);
if ($stmt->fetch()) {
    $matricule = 'ELV-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

$hashedPassword = password_hash($motDePasse, PASSWORD_DEFAULT);

try {
    $sql = "INSERT INTO eleves (matricule, nom, postNom, prenom, sexe, dateNaissance, 
            lieuNaissance, telephone, nomPere, nomMere, adresse, motDePasse) 
            VALUES (:matricule, :nom, :postNom, :prenom, :sexe, :dateNaissance, 
            :lieuNaissance, :telephone, :nomPere, :nomMere, :adresse, :motDePasse)";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        'matricule' => $matricule,
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
        'motDePasse' => $hashedPassword
    ]);
    
    if ($result) {
        $idEleve = $db->lastInsertId();
        
        // Journaliser
        try {
            $sqlLog = "INSERT INTO journal_activite (idUtilisateur, action, type, dateAction) 
                       VALUES (:idUser, :action, :type, NOW())";
            $stmtLog = $db->prepare($sqlLog);
            $stmtLog->execute([
                'idUser' => $_SESSION['user_id'],
                'action' => "Ajout de l'élève " . $matricule . " - " . $nom . ' ' . $prenom,
                'type' => 'creation'
            ]);
        } catch (PDOException $e) {}
        
        echo json_encode([
            'success' => true, 
            'message' => 'Élève ajouté avec succès ! Matricule : ' . $matricule,
            'matricule' => $matricule
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>