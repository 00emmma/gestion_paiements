<?php
// logout.php - Déconnexion globale
session_start();

// Vérifier si l'utilisateur est connecté
if (isset($_SESSION['user_id'])) {
    // Inclure les fichiers nécessaires pour journaliser
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/models/Utilisateur.php';
    
    try {
        $user = new Utilisateur();
        $username = $_SESSION['username'] ?? 'Inconnu';
        $user->journaliser("Déconnexion de l'utilisateur " . $username);
    } catch (Exception $e) {
        // Ignorer les erreurs de journalisation
    }
}

// Détruire complètement la session
$_SESSION = array();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header('Location: login.php');
exit();
?>