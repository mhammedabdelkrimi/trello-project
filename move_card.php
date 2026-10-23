<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$card_id = $_GET['card_id'] ?? 0;
$new_phase = $_GET['to'] ?? '';
$target_list_id = $_GET['list_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Vérifier les phases autorisées
$allowed_phases = ['todo', 'in_progress', 'done'];
if (!in_array($new_phase, $allowed_phases)) {
    die("Phase invalide");
}

// Vérifier l'accès à la carte et récupérer le board_id, le titre de la carte et le nom de la liste
$stmt = $con->prepare("
    SELECT c.title as card_title, l.board_id, l.name as list_name, c.status as old_status
    FROM cards c
    JOIN lists l ON c.list_id = l.id
    JOIN boards b ON l.board_id = b.id
    WHERE c.id=? AND (b.user_id=? OR b.id IN (
        SELECT board_id FROM board_members WHERE user_id=? AND status='accepted'
    ))
");
$stmt->bind_param("iii", $card_id, $user_id, $user_id);
$stmt->execute();
$card = $stmt->get_result()->fetch_assoc();

if (!$card) {
    die("Accès refusé");
}

$list_name = $card['list_name']; // On garde le nom de la liste d'origine (ex: "DAYS")

// Trouver une liste de destination dans le même tableau avec la nouvelle phase
$stmt_list = $con->prepare("SELECT id FROM lists WHERE board_id=? AND phase=? LIMIT 1");
$stmt_list->bind_param("is", $card['board_id'], $new_phase);
$stmt_list->execute();
$list_dest = $stmt_list->get_result()->fetch_assoc();

if (!$list_dest) {
    // Si aucune liste n'existe pour cette phase, on en crée une par défaut
    $phase_labels = ['todo' => 'À faire', 'in_progress' => 'En cours', 'done' => 'Terminé'];
    $new_list_name = $phase_labels[$new_phase] ?? ucfirst($new_phase);
    
    $stmt_create = $con->prepare("INSERT INTO lists (board_id, name, phase, position) VALUES (?, ?, ?, 0)");
    $stmt_create->bind_param("iss", $card['board_id'], $new_list_name, $new_phase);
    $stmt_create->execute();
    $list_id = $stmt_create->insert_id;
} else {
    $list_id = $list_dest['id'];
}

// Mettre à jour la carte
$stmt_update = $con->prepare("UPDATE cards SET list_id=?, status=?, updated_at=NOW() WHERE id=?");
$stmt_update->bind_param("isi", $list_id, $new_phase, $card_id);

if ($stmt_update->execute()) {
    // Journal d'activité
    $stmt_act = $con->prepare("INSERT INTO activity_log (board_id, user_id, action, details) VALUES (?, ?, 'updated_card', ?)");
    $details = "Carte « " . $card['card_title'] . " » déplacée vers " . $new_phase . " dans la liste " . $list_name;
    $stmt_act->bind_param("iis", $card['board_id'], $user_id, $details);
    $stmt_act->execute();

    // Créer notification pour les membres
    $stmtNotifM = $con->prepare("SELECT user_id FROM board_members WHERE board_id=? AND status='accepted' AND user_id != ? UNION SELECT user_id FROM boards WHERE id=? AND user_id != ?");
    $stmtNotifM->bind_param("iiii", $card['board_id'], $user_id, $card['board_id'], $user_id);
    $stmtNotifM->execute();
    $membersNotif = $stmtNotifM->get_result();
    while($mNotif = $membersNotif->fetch_assoc()){
        $stmtS = $con->prepare("INSERT INTO notifications (user_id, board_id, card_id, message, type) VALUES (?, ?, ?, ?, 'card_moved')");
        $notif_msg = "Carte déplacée : " . $card['card_title'] . " vers " . $new_phase;
        $stmtS->bind_param("iiis", $mNotif['user_id'], $card['board_id'], $card_id, $notif_msg);
        $stmtS->execute();
    }

    // Si c'est une requête AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['success' => true]);
        exit();
    }
}

header("Location: board.php?id=" . $card['board_id'] . "#card-" . $card_id);
exit();
?>
