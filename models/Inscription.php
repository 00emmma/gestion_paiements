<?php
// models/Inscription.php
require_once __DIR__ . '/../config/database.php';

class InscriptionModel {
    private $conn;
    
    public function __construct() {
        $this->conn = db();
    }

    /**
     * Récupérer l'ID de l'année en cours
     */
    public function getCurrentYearId() {
        $query = "SELECT idAnnee FROM annees_scolaires WHERE active = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['idAnnee'] ?? 1;
    }

    /**
     * Créer une inscription
     */
    public function create($data) {
        $query = "INSERT INTO inscriptions 
                  (idEleve, idClasse, idAnnee, dateInscription, statut) 
                  VALUES 
                  (:idEleve, :idClasse, :idAnnee, NOW(), 'Actif')";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'idEleve' => $data['idEleve'],
            'idClasse' => $data['idClasse'],
            'idAnnee' => $data['idAnnee'] ?? $this->getCurrentYearId()
        ]);
    }

    /**
     * Récupérer les inscriptions de l'année en cours
     */
    public function getCurrentYear() {
        $query = "SELECT i.*, e.matricule, e.nom, e.postNom, e.prenom, 
                         c.nomClasse, n.nomNiveau
                  FROM inscriptions i
                  JOIN eleves e ON i.idEleve = e.idEleve
                  JOIN classes c ON i.idClasse = c.idClasse
                  JOIN niveaux n ON c.idNiveau = n.idNiveau
                  WHERE i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
                  ORDER BY c.nomClasse, e.nom ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupérer les inscriptions d'un élève
     */
    public function getByEleve($idEleve) {
        $query = "SELECT i.*, c.nomClasse, n.nomNiveau, a.libelle as annee
                  FROM inscriptions i
                  JOIN classes c ON i.idClasse = c.idClasse
                  JOIN niveaux n ON c.idNiveau = n.idNiveau
                  JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
                  WHERE i.idEleve = :idEleve
                  ORDER BY i.idAnnee DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['idEleve' => $idEleve]);
        return $stmt->fetchAll();
    }

    /**
     * Vérifier si un élève est déjà inscrit cette année
     */
    public function isInscritThisYear($idEleve) {
        $query = "SELECT COUNT(*) as count FROM inscriptions 
                  WHERE idEleve = :idEleve 
                  AND idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['idEleve' => $idEleve]);
        return $stmt->fetch()['count'] > 0;
    }

    /**
     * Désinscrire un élève
     */
    public function desinscrire($idInscription) {
        $query = "UPDATE inscriptions SET statut = 'Terminé' WHERE idInscription = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $idInscription]);
    }

    /**
     * Compter les inscriptions de l'année
     */
    public function countCurrentYear() {
        $query = "SELECT COUNT(*) as total FROM inscriptions 
                  WHERE idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
                  AND statut = 'Actif'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch()['total'];
    }

    /**
     * Compter les inscriptions par classe
     */
    public function countByClasse($idClasse) {
        $query = "SELECT COUNT(*) as total FROM inscriptions 
                  WHERE idClasse = :idClasse 
                  AND idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['idClasse' => $idClasse]);
        return $stmt->fetch()['total'];
    }
}
?>