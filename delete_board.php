<?php
ob_start(); // Empêcher toute sortie accidentelle avant le JSON
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

$id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Vérifier si l'utilisateur est propriétaire
$stmtOwner = $con->prepare("SELECT id FROM boards WHERE id=? AND user_id=?");
$stmtOwner->bind_param("ii", $id, $user_id);
$stmtOwner->execute();
if ($stmtOwner->get_result()->num_rows === 0) {
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
        exit();
    }
    die("Access denied");
}

// Supprimer le tableau (la cascade SQL s'occupera du reste)
$stmt = $con->prepare("DELETE FROM boards WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $id, $user_id);
$success = $stmt->execute();

if ($isAjax) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
    exit();
}

header("Location: dashboard.php");
exit();
?>