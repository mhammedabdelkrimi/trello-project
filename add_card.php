<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$list_id = $_POST['list_id'];
$title = $_POST['title'];
$description = $_POST['description'];
$due_date = $_POST['due_date'] ?? null;
$status = $_POST['status'] ?? 'todo';
$priority = $_POST['priority'] ?? 'medium';
$user_id = $_SESSION['user_id'];

// Vérifier accès via board : 
// ✔ la liste appartient à un board
// ✔ ET l’utilisateur a accès au board :
$stmt = $con->prepare("
    SELECT b.id, l.phase
    FROM boards b 
    JOIN lists l ON l.board_id = b.id 
    WHERE l.id=? AND (b.user_id=? OR b.id IN (SELECT board_id FROM board_members WHERE user_id=? AND status='accepted'))
");
$stmt->bind_param("iii", $list_id, $user_id, $user_id);
$stmt->execute();
$stmt->bind_result($board_id, $list_phase);
$stmt->fetch();
$stmt->close();

if (!$board_id) {
    die("Access denied");
}

if (!in_array($status, ['todo', 'in_progress', 'done'], true)) {
    $status = $list_phase ?: 'todo';
}

if ($due_date === '') {
    $due_date = null;
}

// Ajouter carte : placer la nouvelle carte à la fin de la liste
$stmt3 = $con->prepare("SELECT MAX(position) as max_pos FROM cards WHERE list_id=?");
$stmt3->bind_param("i", $list_id);
$stmt3->execute();
$stmt3->bind_result($max_pos);
$stmt3->fetch();
$stmt3->close();
$pos = $max_pos ? $max_pos : 0;
$pos++;

$stmt2 = $con->prepare("INSERT INTO cards (list_id, title, description, position, due_date, status, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt2->bind_param("ississs", $list_id, $title, $description, $pos, $due_date, $status, $priority);
$stmt2->execute();
$card_id = $stmt2->insert_id;
$stmt2->close();

// Ajouter à l'activité dons le journal d'activité du board
$stmtActivity = $con->prepare("INSERT INTO activity_log (board_id, user_id, action, details) VALUES (?, ?, 'created_card', ?)");
$details = "Carte créée : $title";
$stmtActivity->bind_param("iis", $board_id, $user_id, $details);
$stmtActivity->execute();
$stmtActivity->close();

// Créer notification pour les membres
$stmt4 = $con->prepare("SELECT user_id FROM board_members WHERE board_id=? AND status='accepted' AND user_id != ? UNION SELECT user_id FROM boards WHERE id=? AND user_id != ?");
$stmt4->bind_param("iiii", $board_id, $user_id, $board_id, $user_id);
$stmt4->execute();
$members = $stmt4->get_result();
while($member = $members->fetch_assoc()){
    $stmt5 = $con->prepare("INSERT INTO notifications (user_id, board_id, card_id, message, type) VALUES (?, ?, ?, ?, 'card_created')");
    $notif_msg = "Nouvelle carte créée : $title";
    $stmt5->bind_param("iiis", $member['user_id'], $board_id, $card_id, $notif_msg);
    $stmt5->execute();
    $stmt5->close();
}
$members->free();
$stmt4->close();

header("Location: board.php?id=" . $board_id . "#card-" . $card_id);
exit();
?>