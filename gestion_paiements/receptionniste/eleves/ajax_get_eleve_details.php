<?php
// receptionniste/eleves/ajax_get_eleve_details.php - Récupérer les détails d'un élève via AJAX
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Réceptionniste') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

$db = db();

try {
    $sql = "
        SELECT 
            e.*,
            i.idInscription,
            i.dateInscription,
            i.statut,
            c.nomClasse,
            n.nomNiveau,
            a.annee as annee_scolaire,
            a.libelle as libelle_annee
        FROM eleves e
        LEFT JOIN inscriptions i ON e.idEleve = i.idEleve 
            AND i.idAnnee = (SELECT idAnnee FROM annees_scolaires WHERE active = 1)
        LEFT JOIN classes c ON i.idClasse = c.idClasse
        LEFT JOIN niveaux n ON c.idNiveau = n.idNiveau
        LEFT JOIN annees_scolaires a ON i.idAnnee = a.idAnnee
        WHERE e.idEleve = :id
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $id]);
    $eleve = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($eleve) {
        echo json_encode([
            'success' => true,
            'idEleve' => $eleve['idEleve'],
            'matricule' => $eleve['matricule'],
            'nom' => $eleve['nom'],
            'postNom' => $eleve['postNom'],
            'prenom' => $eleve['prenom'],
            'sexe' => $eleve['sexe'],
            'dateNaissance' => $eleve['dateNaissance'] ? date('d/m/Y', strtotime($eleve['dateNaissance'])) : null,
            'lieuNaissance' => $eleve['lieuNaissance'],
            'telephone' => $eleve['telephone'],
            'nomPere' => $eleve['nomPere'],
            'nomMere' => $eleve['nomMere'],
            'adresse' => $eleve['adresse'],
            'est_inscrit' => !empty($eleve['idInscription']),
            'nomClasse' => $eleve['nomClasse'],
            'nomNiveau' => $eleve['nomNiveau'],
            'dateInscription' => $eleve['dateInscription'] ? date('d/m/Y', strtotime($eleve['dateInscription'])) : null,
            'statut' => $eleve['statut'],
            'annee_scolaire' => $eleve['libelle_annee'] ?? $eleve['annee_scolaire']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Élève non trouvé']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>