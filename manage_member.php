<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$board_id = $_GET['board_id'];
$action = $_GET['action'];
$user_id = $_SESSION['user_id'];
$member_id = $_GET['member_id'] ?? $user_id; // For owner actions, member_id is the target

// Check if user is owner for owner actions
$is_owner = false;
if(in_array($action, ['remove', 'cancel'])){
    $stmtOwner = $con->prepare("SELECT id FROM boards WHERE id=? AND user_id=?");
    $stmtOwner->bind_param("ii", $board_id, $user_id);
    $stmtOwner->execute();
    $is_owner = $stmtOwner->get_result()->num_rows > 0;
    if(!$is_owner){
        die("Access denied");
    }
}

if($action === "accept"){
    $stmt = $con->prepare("UPDATE board_members SET status='accepted' WHERE board_id=? AND user_id=? AND status='pending'");
    $stmt->bind_param("ii", $board_id, $user_id);
    $stmt->execute();
    header("Location: dashboard.php");
} elseif($action === "refuse"){
    $stmt = $con->prepare("DELETE FROM board_members WHERE board_id=? AND user_id=? AND status='pending'");
    $stmt->bind_param("ii", $board_id, $user_id);
    $stmt->execute();
    header("Location: dashboard.php");
} elseif($action === "remove" && $is_owner){
    $stmt = $con->prepare("DELETE FROM board_members WHERE board_id=? AND user_id=?");
    $stmt->bind_param("ii", $board_id, $member_id);
    $stmt->execute();
    header("Location: board.php?id=$board_id");
} elseif($action === "cancel" && $is_owner){
    $stmt = $con->prepare("DELETE FROM board_members WHERE board_id=? AND user_id=? AND status='pending'");
    $stmt->bind_param("ii", $board_id, $member_id);
    $stmt->execute();
    header("Location: board.php?id=$board_id");
} else {
    die("Invalid action");
}

exit();
?>