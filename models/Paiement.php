<?php
// models/Paiement.php
require_once __DIR__ . '/../config/database.php';

class Paiement {
    private $conn;
    
    public function __construct() {
        $this->conn = db();
    }

    /**
     * Générer un numéro de reçu
     */
    public function generateRecuNumber() {
        $annee = date('Y');
        $query = "SELECT COUNT(*) as total FROM paiements WHERE YEAR(datePaiement) = :annee";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['annee' => $annee]);
        $row = $stmt->fetch();
        $numero = str_pad($row['total'] + 1, 4, '0', STR_PAD_LEFT);
        return "REC-{$annee}-{$numero}";
    }

    /**
     * Créer un paiement
     */
    public function create($data) {
        $numeroRecu = $this->generateRecuNumber();
        
        $query = "INSERT INTO paiements 
                  (idInscription, idFrais, mois, montantPaye, datePaiement, idCaissier, numeroRecu) 
                  VALUES 
                  (:idInscription, :idFrais, :mois, :montantPaye, NOW(), :idCaissier, :numeroRecu)";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'idInscription' => $data['idInscription'],
            'idFrais' => $data['idFrais'],
            'mois' => $data['mois'] ?? null,
            'montantPaye' => $data['montantPaye'],
            'idCaissier' => $data['idCaissier'],
            'numeroRecu' => $numeroRecu
        ]);
    }

    /**
     * Récupérer les paiements d'une inscription
     */
    public function getByInscription($idInscription) {
        $query = "SELECT p.*, f.libelle as frais_libelle, f.typeFrais,
                         u.nom as caissier_nom, u.postNom as caissier_postNom
                  FROM paiements p
                  JOIN frais f ON p.idFrais = f.idFrais
                  JOIN utilisateurs u ON p.idCaissier = u.idUtilisateur
                  WHERE p.idInscription = :idInscription
                  ORDER BY p.datePaiement DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['idInscription' => $idInscription]);
        return $stmt->fetchAll();
    }

    /**
     * Récupérer les paiements d'un élève
     */
    public function getByEleve($idEleve) {
        $query = "SELECT p.*, e.matricule, e.nom, e.prenom, 
                         f.libelle as frais_libelle,
                         c.nomClasse
                  FROM paiements p
                  JOIN inscriptions i ON p.idInscription = i.idInscription
                  JOIN eleves e ON i.idEleve = e.idEleve
                  JOIN frais f ON p.idFrais = f.idFrais
                  JOIN classes c ON i.idClasse = c.idClasse
                  WHERE e.idEleve = :idEleve
                  ORDER BY p.datePaiement DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['idEleve' => $idEleve]);
        return $stmt->fetchAll();
    }

    /**
     * Récupérer tous les paiements avec filtres
     */
    public function getAll($filters = []) {
        $query = "SELECT p.*, e.matricule, e.nom, e.postNom, e.prenom, 
                         f.libelle as frais_libelle, f.typeFrais,
                         c.nomClasse,
                         u.nom as caissier_nom
                  FROM paiements p
                  JOIN inscriptions i ON p.idInscription = i.idInscription
                  JOIN eleves e ON i.idEleve = e.idEleve
                  JOIN frais f ON p.idFrais = f.idFrais
                  JOIN classes c ON i.idClasse = c.idClasse
                  JOIN utilisateurs u ON p.idCaissier = u.idUtilisateur
                  WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['date_debut']) && $filters['date_debut']) {
            $query .= " AND DATE(p.datePaiement) >= :date_debut";
            $params['date_debut'] = $filters['date_debut'];
        }
        
        if (isset($filters['date_fin']) && $filters['date_fin']) {
            $query .= " AND DATE(p.datePaiement) <= :date_fin";
            $params['date_fin'] = $filters['date_fin'];
        }
        
        if (isset($filters['classe']) && $filters['classe']) {
            $query .= " AND i.idClasse = :classe";
            $params['classe'] = $filters['classe'];
        }
        
        $query .= " ORDER BY p.datePaiement DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Compter les paiements du jour
     */
    public function countToday() {
        $query = "SELECT COUNT(*) as total FROM paiements WHERE DATE(datePaiement) = CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch()['total'];
    }
}
?>