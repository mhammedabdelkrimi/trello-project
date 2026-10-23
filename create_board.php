<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name = $_POST['name'];
$color = $_POST['color'];
$member_email = $_POST['member_email'] ?? "";

// Créer le board
$stmt = $con->prepare("INSERT INTO boards (name, color, user_id) VALUES (?, ?, ?)");
$stmt->bind_param("ssi", $name, $color, $user_id);
$stmt->execute();
$board_id = $stmt->insert_id;

// Ajouter membre si email fourni
if(!empty($member_email)){
    $stmt2 = $con->prepare("SELECT id FROM users WHERE email=?");
    $stmt2->bind_param("s", $member_email);
    $stmt2->execute();
    $user_result = $stmt2->get_result()->fetch_assoc();

    if($user_result){
        $member_id = $user_result['id'];
        $stmt3 = $con->prepare("INSERT INTO board_members (board_id, user_id, status) VALUES (?, ?, 'pending')");
        $stmt3->bind_param("ii", $board_id, $member_id);
        $stmt3->execute();
    }
}

header("Location: dashboard.php");
exit();
?>