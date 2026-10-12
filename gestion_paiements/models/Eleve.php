<?php
// models/Eleve.php
require_once __DIR__ . '/../config/database.php';

class Eleve {
    private $conn;
    
    public function __construct() {
        $this->conn = db();
    }

    /**
     * Générer un matricule automatique
     */
    public function generateMatricule() {
        $annee = date('Y');
        $query = "SELECT COUNT(*) as total FROM eleves WHERE matricule LIKE 'EL{$annee}%'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $row = $stmt->fetch();
        $numero = str_pad($row['total'] + 1, 4, '0', STR_PAD_LEFT);
        return "EL{$annee}{$numero}";
    }

    /**
     * Créer un nouvel élève avec mot de passe
     */
    public function create($data) {
        // Hacher le mot de passe si fourni
        $motDePasseHash = null;
        if (!empty($data['motDePasse'])) {
            $motDePasseHash = password_hash($data['motDePasse'], PASSWORD_DEFAULT);
        }

        $query = "INSERT INTO eleves 
                  (matricule, nom, postNom, prenom, sexe, dateNaissance, adresse, telephone, motDePasse) 
                  VALUES 
                  (:matricule, :nom, :postNom, :prenom, :sexe, :dateNaissance, :adresse, :telephone, :motDePasse)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'matricule' => $data['matricule'],
            'nom' => $data['nom'],
            'postNom' => $data['postNom'] ?? null,
            'prenom' => $data['prenom'],
            'sexe' => $data['sexe'],
            'dateNaissance' => $data['dateNaissance'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'motDePasse' => $motDePasseHash
        ]);
    }

    /**
     * Récupérer tous les élèves avec leur classe actuelle
     */
    public function getAll() {
        $query = "SELECT e.*, c.nomClasse, i.idInscription, i.statut as statutInscription
                  FROM eleves e
                  LEFT JOIN inscriptions i ON e.idEleve = i.idEleve 
                      AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
                  LEFT JOIN classes c ON i.idClasse = c.idClasse
                  ORDER BY e.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupérer un élève par ID
     */
    public function getById($id) {
        $query = "SELECT e.*, c.nomClasse, i.idInscription, i.statut as statutInscription
                  FROM eleves e
                  LEFT JOIN inscriptions i ON e.idEleve = i.idEleve 
                      AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
                  LEFT JOIN classes c ON i.idClasse = c.idClasse
                  WHERE e.idEleve = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Récupérer un élève par matricule
     */
    public function getByMatricule($matricule) {
        $query = "SELECT * FROM eleves WHERE matricule = :matricule";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['matricule' => $matricule]);
        return $stmt->fetch();
    }

    /**
     * Mettre à jour un élève
     */
    public function update($id, $data) {
        $query = "UPDATE eleves 
                  SET nom = :nom, postNom = :postNom, prenom = :prenom, 
                      sexe = :sexe, dateNaissance = :dateNaissance, 
                      adresse = :adresse, telephone = :telephone
                  WHERE idEleve = :id";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'id' => $id,
            'nom' => $data['nom'],
            'postNom' => $data['postNom'] ?? null,
            'prenom' => $data['prenom'],
            'sexe' => $data['sexe'],
            'dateNaissance' => $data['dateNaissance'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'telephone' => $data['telephone'] ?? null
        ]);
    }

    /**
     * Mettre à jour le mot de passe d'un élève
     */
    public function updatePassword($id, $motDePasse) {
        $motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);
        $query = "UPDATE eleves SET motDePasse = :motDePasse WHERE idEleve = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'id' => $id,
            'motDePasse' => $motDePasseHash
        ]);
    }

    /**
     * Supprimer un élève
     */
    public function delete($id) {
        $query = "SELECT COUNT(*) as count FROM inscriptions WHERE idEleve = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return false;
        }
        
        $query = "DELETE FROM eleves WHERE idEleve = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Rechercher des élèves
     */
    public function search($keyword) {
        $query = "SELECT e.*, c.nomClasse
                  FROM eleves e
                  LEFT JOIN inscriptions i ON e.idEleve = i.idEleve 
                      AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
                  LEFT JOIN classes c ON i.idClasse = c.idClasse
                  WHERE e.nom LIKE :keyword 
                     OR e.postNom LIKE :keyword 
                     OR e.prenom LIKE :keyword 
                     OR e.matricule LIKE :keyword
                  ORDER BY e.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        $keyword = "%$keyword%";
        $stmt->execute(['keyword' => $keyword]);
        return $stmt->fetchAll();
    }

    /**
     * Compter le nombre total d'élèves
     */
    public function count() {
        $query = "SELECT COUNT(*) as total FROM eleves";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch()['total'];
    }

    /**
     * Compter les élèves inscrits cette année
     */
    public function countInscritsThisYear() {
        $query = "SELECT COUNT(*) as total FROM inscriptions 
                  WHERE idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch()['total'];
    }

    /**
     * Vérifier si un matricule existe déjà
     */
    public function matriculeExists($matricule) {
        $query = "SELECT COUNT(*) as count FROM eleves WHERE matricule = :matricule";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['matricule' => $matricule]);
        return $stmt->fetch()['count'] > 0;
    }

    /**
     * Vérifier les identifiants de connexion pour un élève
     */
    public function login($matricule, $motDePasse) {
        $query = "SELECT * FROM eleves WHERE matricule = :matricule";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['matricule' => $matricule]);
        $eleve = $stmt->fetch();

        if ($eleve && password_verify($motDePasse, $eleve['motDePasse'])) {
            return $eleve;
        }
        return false;
    }
}
?>