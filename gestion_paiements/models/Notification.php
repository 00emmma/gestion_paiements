<?php
// models/Notification.php
require_once __DIR__ . '/../config/database.php';

class NotificationModel {
    private $conn;
    
    public function __construct() {
        $this->conn = db();
    }

    /**
     * Créer une notification
     */
    public function create($idUtilisateur, $type, $message, $lien = null) {
        $query = "INSERT INTO notifications (idUtilisateur, type, message, lien, est_lu) 
                  VALUES (:idUtilisateur, :type, :message, :lien, 0)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            'idUtilisateur' => $idUtilisateur,
            'type' => $type,
            'message' => $message,
            'lien' => $lien
        ]);
    }

    /**
     * Récupérer les notifications non lues d'un utilisateur
     */
    public function getNonLues($idUtilisateur) {
        $query = "SELECT * FROM notifications 
                  WHERE idUtilisateur = :idUtilisateur AND est_lu = 0 
                  ORDER BY dateCreation DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['idUtilisateur' => $idUtilisateur]);
        return $stmt->fetchAll();
    }

    /**
     * Récupérer toutes les notifications d'un utilisateur (limitées)
     */
    public function getAll($idUtilisateur, $limit = 10) {
        $query = "SELECT * FROM notifications 
                  WHERE idUtilisateur = :idUtilisateur 
                  ORDER BY dateCreation DESC 
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':idUtilisateur', $idUtilisateur, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Compter les notifications non lues
     */
    public function countNonLues($idUtilisateur) {
        $query = "SELECT COUNT(*) as total FROM notifications 
                  WHERE idUtilisateur = :idUtilisateur AND est_lu = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['idUtilisateur' => $idUtilisateur]);
        return $stmt->fetch()['total'];
    }

    /**
     * Marquer une notification comme lue
     */
    public function marquerCommeLu($idNotification) {
        $query = "UPDATE notifications SET est_lu = 1 WHERE idNotification = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $idNotification]);
    }

    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public function marquerToutCommeLu($idUtilisateur) {
        $query = "UPDATE notifications SET est_lu = 1 WHERE idUtilisateur = :idUtilisateur";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['idUtilisateur' => $idUtilisateur]);
    }

    /**
     * Supprimer une notification
     */
    public function delete($idNotification) {
        $query = "DELETE FROM notifications WHERE idNotification = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(['id' => $idNotification]);
    }

    /**
     * Créer une notification pour un événement automatique
     */
    public function notify($idUtilisateur, $type, $message, $lien = null) {
        return $this->create($idUtilisateur, $type, $message, $lien);
    }
}
?>