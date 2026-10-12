<?php
// admin/utilisateurs/ajax_ajouter.php - Traitement AJAX pour l'ajout d'utilisateur
session_start();

header('Content-Type: application/json');

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
$nom = trim($_POST['nom'] ?? '');
$postNom = trim($_POST['postNom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$sexe = $_POST['sexe'] ?? null;
$telephone = trim($_POST['telephone'] ?? '');
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$motDePasse = $_POST['motDePasse'] ?? '';
$idRole = intval($_POST['idRole'] ?? 0);
$statut = $_POST['statut'] ?? 'Actif';

// ===== VALIDATION =====
if (empty($nom) || empty($prenom) || empty($username) || empty($motDePasse) || empty($idRole)) {
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit();
}

if (strlen($motDePasse) < 6) {
    echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères.']);
    exit();
}

// ===== VÉRIFIER SI LE USERNAME EXISTE =====
$stmt = $db->prepare("SELECT idUtilisateur FROM utilisateurs WHERE username = :username");
$stmt->execute(['username' => $username]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Ce nom d\'utilisateur est déjà utilisé.']);
    exit();
}

// ===== HASHER LE MOT DE PASSE =====
$hashedPassword = password_hash($motDePasse, PASSWORD_DEFAULT);

// ===== INSÉRER L'UTILISATEUR =====
try {
    $sql = "INSERT INTO utilisateurs (nom, postNom, prenom, sexe, telephone, email, username, motDePasse, idRole, statut) 
            VALUES (:nom, :postNom, :prenom, :sexe, :telephone, :email, :username, :motDePasse, :idRole, :statut)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'nom' => $nom,
        'postNom' => $postNom,
        'prenom' => $prenom,
        'sexe' => $sexe,
        'telephone' => $telephone,
        'email' => $email,
        'username' => $username,
        'motDePasse' => $hashedPassword,
        'idRole' => $idRole,
        'statut' => $statut
    ]);
    
    // ===== JOURNALISER L'ACTION =====
    try {
        $sqlLog = "INSERT INTO journal_activite (idUtilisateur, action, type, dateAction) 
                   VALUES (:idUser, :action, :type, NOW())";
        $stmtLog = $db->prepare($sqlLog);
        $stmtLog->execute([
            'idUser' => $_SESSION['user_id'],
            'action' => "Ajout de l'utilisateur " . $username,
            'type' => 'creation'
        ]);
    } catch (PDOException $e) {
        // Ignorer les erreurs de journal
    }
    
    echo json_encode(['success' => true, 'message' => 'Utilisateur ajouté avec succès !']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout : ' . $e->getMessage()]);
}
?>