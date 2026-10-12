<?php
// models/Utilisateur.php
require_once __DIR__ . '/../config/database.php';

class Utilisateur {
    private $conn;
    public $idUtilisateur;
    public $username;
    public $nom;
    public $postNom;
    public $prenom;
    public $idRole;
    public $roleName;
    public $statut;

    public function __construct() {
        $this->conn = db();
    }

    /**
     * Méthode de connexion
     */
    public function login($username, $password) {
        $query = "SELECT u.*, r.nomRole 
                  FROM utilisateurs u
                  JOIN roles r ON u.idRole = r.idRole
                  WHERE u.username = :username AND u.statut = 'Actif'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['motDePasse'])) {
            $this->idUtilisateur = $user['idUtilisateur'];
            $this->username = $user['username'];
            $this->nom = $user['nom'];
            $this->postNom = $user['postNom'] ?? '';
            $this->prenom = $user['prenom'];
            $this->idRole = $user['idRole'];
            $this->roleName = $user['nomRole'];
            $this->statut = $user['statut'];
            return true;
        }
        return false;
    }

    /**
     * Récupérer tous les utilisateurs
     */
    public function getAll() {
        $query = "SELECT u.*, r.nomRole 
                  FROM utilisateurs u
                  JOIN roles r ON u.idRole = r.idRole
                  ORDER BY u.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupérer un utilisateur par ID
     */
    public function getById($id) {
        $query = "SELECT u.*, r.nomRole 
                  FROM utilisateurs u
                  JOIN roles r ON u.idRole = r.idRole
                  WHERE u.idUtilisateur = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Récupérer un utilisateur par username
     */
    public function getByUsername($username) {
        $query = "SELECT u.*, r.nomRole 
                  FROM utilisateurs u
                  JOIN roles r ON u.idRole = r.idRole
                  WHERE u.username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['username' => $username]);
        return $stmt->fetch();
    }

    /**
     * Vérifier si un username existe déjà
     */
    public function usernameExists($username, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM utilisateurs WHERE username = :username";
        $params = ['username' => $username];
        
        if ($excludeId) {
            $query .= " AND idUtilisateur != :id";
            $params['id'] = $excludeId;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch()['count'] > 0;
    }

    /**
     * Créer un nouvel utilisateur
     */
    public function create($data) {
        $hashedPassword = password_hash($data['motDePasse'], PASSWORD_DEFAULT);
        
        $query = "INSERT INTO utilisateurs 
                  (nom, postNom, prenom, sexe, telephone, email, username, motDePasse, idRole, statut) 
                  VALUES 
                  (:nom, :postNom, :prenom, :sexe, :telephone, :email, :username, :motDePasse, :idRole, :statut)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'nom' => $data['nom'],
            'postNom' => $data['postNom'] ?? null,
            'prenom' => $data['prenom'],
            'sexe' => $data['sexe'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'email' => $data['email'] ?? null,
            'username' => $data['username'],
            'motDePasse' => $hashedPassword,
            'idRole' => $data['idRole'],
            'statut' => $data['statut'] ?? 'Actif'
        ]);
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function update($id, $data) {
        $query = "UPDATE utilisateurs 
                  SET nom = :nom, postNom = :postNom, prenom = :prenom, 
                      sexe = :sexe, telephone = :telephone, email = :email,
                      idRole = :idRole, statut = :statut
                  WHERE idUtilisateur = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'id' => $id,
            'nom' => $data['nom'],
            'postNom' => $data['postNom'] ?? null,
            'prenom' => $data['prenom'],
            'sexe' => $data['sexe'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'email' => $data['email'] ?? null,
            'idRole' => $data['idRole'],
            'statut' => $data['statut'] ?? 'Actif'
        ]);
    }

    /**
     * Supprimer un utilisateur
     */
    public function delete($id) {
        $query = "DELETE FROM utilisateurs WHERE idUtilisateur = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Changer le mot de passe d'un utilisateur
     */
    public function changePassword($id, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $query = "UPDATE utilisateurs SET motDePasse = :motDePasse WHERE idUtilisateur = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'id' => $id,
            'motDePasse' => $hashedPassword
        ]);
    }

    /**
     * Vérifier si l'utilisateur a une permission
     */
    public function hasPermission($permission) {
        $query = "SELECT COUNT(*) as count 
                  FROM role_permission rp
                  JOIN permissions p ON rp.idPermission = p.idPermission
                  WHERE rp.idRole = :idRole 
                  AND p.nomPermission = :permission";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            'idRole' => $this->idRole,
            'permission' => $permission
        ]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    /**
     * Journaliser une action
     */
    public function journaliser($action) {
        if (isset($_SESSION['user_id'])) {
            $query = "INSERT INTO journal_activite (idUtilisateur, action) 
                      VALUES (:idUtilisateur, :action)";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                'idUtilisateur' => $_SESSION['user_id'],
                'action' => $action
            ]);
        }
        return false;
    }

    /**
     * Compter le nombre d'utilisateurs
     */
    public function count() {
        $query = "SELECT COUNT(*) as total FROM utilisateurs";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch()['total'];
    }

    /**
     * Compter les utilisateurs par rôle
     */
    public function countByRole($idRole) {
        $query = "SELECT COUNT(*) as total FROM utilisateurs WHERE idRole = :idRole";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['idRole' => $idRole]);
        return $stmt->fetch()['total'];
    }

    /**
     * Obtenir le nom complet
     */
    public function getFullName() {
        return trim($this->nom . ' ' . $this->postNom . ' ' . $this->prenom);
    }

    /**
     * Activer un utilisateur
     */
    public function activate($id) {
        $query = "UPDATE utilisateurs SET statut = 'Actif' WHERE idUtilisateur = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Désactiver un utilisateur
     */
    public function deactivate($id) {
        $query = "UPDATE utilisateurs SET statut = 'Inactif' WHERE idUtilisateur = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }
}
?>