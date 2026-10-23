<?php
ob_start();
session_start();
include "db.php";

$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');

if ($isAjax) {
    error_reporting(0);
}

if(!isset($_SESSION['user_id'])){
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expirée.']);
        exit();
    }
    header("Location: login.php");
    exit();
}

$card_id = $_GET['card_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Vérifier accès et récupérer board_id
$stmt = $con->prepare("
    SELECT b.id, c.title FROM boards b 
    JOIN lists l ON l.board_id = b.id 
    JOIN cards c ON c.list_id = l.id
    WHERE c.id=? AND (b.user_id=? OR b.id IN (SELECT board_id FROM board_members WHERE user_id=? AND status='accepted'))
");
$stmt->bind_param("iii", $card_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$card_info = $result->fetch_assoc();

if(!$card_info){
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès refusé ou carte inexistante.']);
        exit();
    }
    die("Accès refusé ou carte inexistante.");
}

$board_id = $card_info['id'];
$card_title = $card_info['title'];

// Supprimer la carte
$stmt_del = $con->prepare("DELETE FROM cards WHERE id=?");
$stmt_del->bind_param("i", $card_id);

if($stmt_del->execute()){
    // Ajouter au journal d'activité
    $stmt_act = $con->prepare("INSERT INTO activity_log (board_id, user_id, action, details) VALUES (?, ?, 'deleted_card', ?)");
    $details = "Carte supprimée : $card_title";
    $stmt_act->bind_param("iis", $board_id, $user_id, $details);
    $stmt_act->execute();

    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }

    header("Location: board.php?id=$board_id");
} else {
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression.']);
        exit();
    }
    echo "Erreur lors de la suppression.";
}
exit();
?>
