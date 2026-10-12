<?php
// models/Classe.php
require_once __DIR__ . '/../config/database.php';

class ClasseModel {
    private $conn;
    
    public function __construct() {
        $this->conn = db();
    }

    /**
     * Récupérer toutes les classes
     */
    public function getAll() {
        $query = "SELECT c.*, n.nomNiveau 
                  FROM classes c
                  JOIN niveaux n ON c.idNiveau = n.idNiveau
                  ORDER BY n.nomNiveau, c.nomClasse";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupérer une classe par ID
     */
    public function getById($id) {
        $query = "SELECT c.*, n.nomNiveau 
                  FROM classes c
                  JOIN niveaux n ON c.idNiveau = n.idNiveau
                  WHERE c.idClasse = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Récupérer les classes d'un niveau
     */
    public function getByNiveau($idNiveau) {
        $query = "SELECT * FROM classes WHERE idNiveau = :idNiveau ORDER BY nomClasse";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['idNiveau' => $idNiveau]);
        return $stmt->fetchAll();
    }

    /**
     * Créer une classe
     */
    public function create($data) {
        $query = "INSERT INTO classes (nomClasse, idNiveau) VALUES (:nomClasse, :idNiveau)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'nomClasse' => $data['nomClasse'],
            'idNiveau' => $data['idNiveau']
        ]);
    }

    /**
     * Mettre à jour une classe
     */
    public function update($id, $data) {
        $query = "UPDATE classes SET nomClasse = :nomClasse, idNiveau = :idNiveau WHERE idClasse = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'id' => $id,
            'nomClasse' => $data['nomClasse'],
            'idNiveau' => $data['idNiveau']
        ]);
    }

    /**
     * Supprimer une classe
     */
    public function delete($id) {
        $query = "DELETE FROM classes WHERE idClasse = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Compter le nombre de classes
     */
    public function count() {
        $query = "SELECT COUNT(*) as total FROM classes";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch()['total'];
    }
}
?>