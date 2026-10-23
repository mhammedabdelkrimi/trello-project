<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$board_id = $_POST['board_id'];
$member_email = $_POST['member_email'];
$user_id = $_SESSION['user_id'];

// Vérifier si propriétaire
$stmt = $con->prepare("SELECT id FROM boards WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $board_id, $user_id);
$stmt->execute();
if($stmt->get_result()->num_rows == 0){
    die("Access denied");
}

// Trouver user par email
$stmt2 = $con->prepare("SELECT id FROM users WHERE email=?");
$stmt2->bind_param("s", $member_email);
$stmt2->execute();
$user_result = $stmt2->get_result()->fetch_assoc();

if($user_result){
    $member_id = $user_result['id'];
    // Vérifier si déjà membre
    $stmt3 = $con->prepare("SELECT id FROM board_members WHERE board_id=? AND user_id=?");
    $stmt3->bind_param("ii", $board_id, $member_id);
    $stmt3->execute();
    if($stmt3->get_result()->num_rows == 0){
        $stmt4 = $con->prepare("INSERT INTO board_members (board_id, user_id, status) VALUES (?, ?, 'pending')");
        $stmt4->bind_param("ii", $board_id, $member_id);
        $stmt4->execute();
    }
}

header("Location: board.php?id=$board_id");
exit();
?>