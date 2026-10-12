<?php
// receptionniste/marquer_lu.php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

require_once __DIR__ . '/../models/Notification.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $notificationModel = new NotificationModel();
    $success = $notificationModel->marquerCommeLu($id);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false]);
}
?>