<?php
// receptionniste/marquer_tout_lu.php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

require_once __DIR__ . '/../models/Notification.php';

$notificationModel = new NotificationModel();
$success = $notificationModel->marquerToutCommeLu($_SESSION['user_id']);

echo json_encode(['success' => $success]);
?>