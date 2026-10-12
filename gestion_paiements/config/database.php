<?php
// config/database.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_paiement_ecole');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/gestion_paiements/');

// Activer les erreurs en développement
error_reporting(E_ALL);
ini_set('display_errors', 1);

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die("Erreur de connexion: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}

function db() {
    return Database::getInstance()->getConnection();
}

// Fonction de session sécurisée
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Vérifier si l'utilisateur est connecté
function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']);
}

// Rediriger si non connecté
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'pages/login.php');
        exit();
    }
}

// Afficher les messages flash
function showFlashMessage() {
    startSecureSession();
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] ?? 'info';
        $message = $_SESSION['flash']['message'] ?? '';
        unset($_SESSION['flash']);
        return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                    {$message}
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
    return '';
}

// Définir un message flash
function setFlashMessage($message, $type = 'success') {
    startSecureSession();
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}
?>