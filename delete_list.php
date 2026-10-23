<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$list_id = $_GET['list_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Vérifier accès via board
$stmt = $con->prepare("
    SELECT b.id, l.name FROM boards b 
    JOIN lists l ON l.board_id = b.id 
    WHERE l.id=? AND (b.user_id=? OR b.id IN (SELECT board_id FROM board_members WHERE user_id=? AND status='accepted'))
");
$stmt->bind_param("iii", $list_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$list_info = $result->fetch_assoc();

if(!$list_info){
    die("Accès refusé ou liste inexistante.");
}

$board_id = $list_info['id'];
$list_name = $list_info['name'];

// Supprimer les cartes de la liste
$stmt_del_cards = $con->prepare("DELETE FROM cards WHERE list_id=?");
$stmt_del_cards->bind_param("i", $list_id);
$stmt_del_cards->execute();

// Supprimer la liste
$stmt_del = $con->prepare("DELETE FROM lists WHERE id=?");
$stmt_del->bind_param("i", $list_id);

if($stmt_del->execute()){
    // Ajouter au journal d'activité
    $stmt_act = $con->prepare("INSERT INTO activity_log (board_id, user_id, action, details) VALUES (?, ?, 'deleted_list', ?)");
    $details = "Liste supprimée : $list_name";
    $stmt_act->bind_param("iis", $board_id, $user_id, $details);
    $stmt_act->execute();

    header("Location: board.php?id=$board_id");
} else {
    echo "Erreur lors de la suppression.";
}
exit();
?>
