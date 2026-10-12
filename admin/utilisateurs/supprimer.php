<?php
// admin/utilisateurs/supprimer.php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrateur') {
    header('Location: ../../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Utilisateur.php';

$userModel = new Utilisateur();

$id = $_GET['id'] ?? 0;

if ($id && $id != $_SESSION['user_id']) {
    $user = $userModel->getById($id);
    if ($user) {
        if ($userModel->delete($id)) {
            $userModel->journaliser("Suppression de l'utilisateur " . $user['username']);
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Utilisateur supprimé avec succès !'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Erreur lors de la suppression.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Utilisateur non trouvé.'];
    }
} else {
    $_SESSION['flash'] = ['type' => 'danger', 'text' => 'Vous ne pouvez pas supprimer votre propre compte.'];
}

header('Location: index.php');
exit();
?>