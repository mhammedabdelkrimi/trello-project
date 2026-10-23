<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$board_id = $_POST['board_id'];
$name = $_POST['name'];
$phase = $_POST['phase'] ?? 'todo';
$user_id = $_SESSION['user_id'];

$allowed = ['todo', 'in_progress', 'done'];
if (!in_array($phase, $allowed, true)) {
    $phase = 'todo';
}

// Vérifier si propriétaire ou membre
$stmt = $con->prepare("SELECT id FROM boards WHERE id=? AND (user_id=? OR id IN (SELECT board_id FROM board_members WHERE user_id=? AND status='accepted'))");
$stmt->bind_param("iii", $board_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if(!$result || $result->num_rows == 0){
    die("Access denied");
}
$result->free();
$stmt->close();

// Ajouter liste
$stmt3 = $con->prepare("SELECT MAX(position) as max_pos FROM lists WHERE board_id=?");
$stmt3->bind_param("i", $board_id);
$stmt3->execute();
$stmt3->bind_result($max_pos);
$stmt3->fetch();
$stmt3->close();
$pos = $max_pos ? $max_pos : 0;
$pos++;

$stmt2 = $con->prepare("INSERT INTO lists (board_id, name, position, phase) VALUES (?, ?, ?, ?)");
$stmt2->bind_param("isis", $board_id, $name, $pos, $phase);
$stmt2->execute();
$stmt2->close();

// Ajouter à l'activité
$stmtActivity = $con->prepare("INSERT INTO activity_log (board_id, user_id, action, details) VALUES (?, ?, 'created_list', ?)");
$details = "Liste créée : $name";
$stmtActivity->bind_param("iis", $board_id, $user_id, $details);
$stmtActivity->execute();
$stmtActivity->close();

// Créer notification pour les membres
$stmt4 = $con->prepare("SELECT user_id FROM board_members WHERE board_id=? AND status='accepted' AND user_id != ? UNION SELECT user_id FROM boards WHERE id=? AND user_id != ?");
$stmt4->bind_param("iiii", $board_id, $user_id, $board_id, $user_id);
$stmt4->execute();
$members = $stmt4->get_result();
while($member = $members->fetch_assoc()){
    $stmt5 = $con->prepare("INSERT INTO notifications (user_id, board_id, message, type) VALUES (?, ?, ?, 'list_created')");
    $notification_message = "Nouvelle liste créée : $name";
    $stmt5->bind_param("iis", $member['user_id'], $board_id, $notification_message);
    $stmt5->execute();
    $stmt5->close();
}
$members->free();
$stmt4->close();

header("Location: board.php?id=$board_id");
exit();
?>