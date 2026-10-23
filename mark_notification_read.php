<?php
ob_start();
session_start();
include "db.php";

$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');

if ($isAjax) {
    error_reporting(0);
}

if (!isset($_SESSION['user_id'])) {
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expirée.']);
        exit();
    }
    exit('error');
}

$notification_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

$stmt = $con->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $notification_id, $user_id);
$stmt->execute();

if ($isAjax) {
    ob_clean();
    header('Content-Type: application/json');
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucune notification trouvée ou déjà lue.']);
    }
    exit();
}

if ($stmt->affected_rows > 0) {
    echo 'success';
} else {
    echo 'error';
}
?>