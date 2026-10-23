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
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$card_id = $_GET['card_id'] ?? 0;

if ($card_id > 0) {
    // Insérer un enregistrement dans notifications pour marquer l'alerte comme ignorée
    $stmt = $con->prepare("INSERT INTO notifications (user_id, card_id, message, type, is_read) VALUES (?, ?, 'Alerte échéance ignorée', 'due_date_dismissed', TRUE)");
    $stmt->bind_param("ii", $user_id, $card_id);
    $success = $stmt->execute();

    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit();
    }
} else {
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de carte invalide.']);
        exit();
    }
}

header("Location: dashboard.php");
exit();
?>