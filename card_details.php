<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$card_id = $_GET['card_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Vérifier accès à la carte
$stmt = $con->prepare("
    SELECT c.*, l.name as list_name, b.name as board_name, b.id as board_id
    FROM cards c
    JOIN lists l ON c.list_id = l.id
    JOIN boards b ON l.board_id = b.id
    WHERE c.id=? AND (b.user_id=? OR b.id IN (
        SELECT board_id 
        FROM board_members 
        WHERE user_id=? AND status='accepted'
    ))
");
$stmt->bind_param("iii", $card_id, $user_id, $user_id);
$stmt->execute();
$card = $stmt->get_result()->fetch_assoc();

if (!$card) {
    die("Access denied");
}

// Récupérer les commentaires
$stmtComments = $con->prepare("
    SELECT cc.*, u.name as user_name
    FROM card_comments cc
    JOIN users u ON cc.user_id = u.id
    WHERE cc.card_id=?
    ORDER BY cc.created_at DESC
");
$stmtComments->bind_param("i", $card_id);
$stmtComments->execute();
$comments = $stmtComments->get_result();

// Traiter l'ajout de commentaire
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);
    if (!empty($comment)) {
        $stmtAdd = $con->prepare("INSERT INTO card_comments (card_id, user_id, comment) VALUES (?, ?, ?)");
        $stmtAdd->bind_param("iis", $card_id, $user_id, $comment);
        $stmtAdd->execute();

        // Ajouter à l'activité
        $stmtAct = $con->prepare("INSERT INTO activity_log (board_id, user_id, action, details) VALUES (?, ?, 'added_comment', ?)");
        $details = "Commentaire ajouté à la carte : " . substr($comment, 0, 100) . "...";
        $stmtAct->bind_param("iis", $card['board_id'], $user_id, $details);
        $stmtAct->execute();

        // Notifications
        $stmtMembers = $con->prepare("SELECT user_id FROM board_members WHERE board_id=? AND status='accepted' AND user_id != ? UNION SELECT user_id FROM boards WHERE id=? AND user_id != ?");
        $stmtMembers->bind_param("iiii", $card['board_id'], $user_id, $card['board_id'], $user_id);
        $members = $stmtMembers->get_result();
        while($member = $members->fetch_assoc()){
            $stmtNotif = $con->prepare("INSERT INTO notifications (user_id, board_id, card_id, message, type) VALUES (?, ?, ?, ?, 'comment')");
            $stmtNotif->bind_param("iiis", $member['user_id'], $card['board_id'], $card_id, "Nouveau commentaire sur la carte : " . substr($comment, 0, 50) . "...");
            $stmtNotif->execute();
        }

        header("Location: card_details.php?card_id=$card_id");
        exit();
    }
}
?>
<?php
$pageTitle = htmlspecialchars($card['title']) . " - Trello Pro";
include "header.php";

$statusLabels = [
    'todo' => 'À faire',
    'in_progress' => 'En cours',
    'done' => 'Terminé'
];
$priorityLabels = [
    'low' => 'Faible',
    'medium' => 'Moyenne',
    'high' => 'Élevée'
];
?>
<style>
    .card-detail-view {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        margin-top: 2rem;
    }
    .card-meta {
        display: flex;
        gap: 2rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #eee;
        color: #5e6c84;
    }
    .card-description {
        font-size: 1.1rem;
        line-height: 1.6;
        margin-bottom: 2rem;
    }
    .comments-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .comment-item {
        background: #f4f5f7;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    .comment-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }
    .comment-user {
        font-weight: 700;
        color: #172b4d;
    }
    .comment-date {
        font-size: 0.8rem;
        color: #5e6c84;
    }
    .add-comment-form textarea {
        width: 100%;
        min-height: 100px;
        padding: 1rem;
        border: 2px solid #dfe1e6;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-family: inherit;
    }
    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: #5e6c84;
        font-weight: 600;
        margin-top: 1rem;
    }
    .btn-back:hover {
        color: #172b4d;
    }
</style>

<div class="card-detail-view">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
        <h1><?php echo htmlspecialchars($card['title']); ?></h1>
        <a href="edit_card.php?card_id=<?php echo $card_id; ?>" class="btn btn-secondary"><i class="fa-solid fa-pen"></i> Modifier</a>
    </div>
    
    <div class="card-meta">
        <div><i class="fa-solid fa-layer-group"></i> <strong>Liste :</strong> <?php echo htmlspecialchars($card['list_name']); ?></div>
        <div><i class="fa-solid fa-chalkboard"></i> <strong>Tableau :</strong> <?php echo htmlspecialchars($card['board_name']); ?></div>
        <div><i class="fa-solid fa-signal"></i> <strong>Priorité :</strong> 
            <span class="priority-badge priority-<?php echo $card['priority']; ?>">
                <?php echo $priorityLabels[$card['priority']] ?? $card['priority']; ?>
            </span>
        </div>
        <?php if($card['due_date']): ?>
            <div class="<?php echo (strtotime($card['due_date']) < time()) ? 'text-danger' : ''; ?>">
                <i class="fa-regular fa-clock"></i> <strong>Échéance :</strong> <?php echo date('d/m/Y H:i', strtotime($card['due_date'])); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-description">
        <h3>Description</h3>
        <p><?php echo nl2br(htmlspecialchars($card['description'] ?: 'Aucune description fournie.')); ?></p>
    </div>

    <h2 class="comments-title"><i class="fa-regular fa-comments"></i> Commentaires</h2>
    
    <div class="comments-section">
        <?php while($comment = $comments->fetch_assoc()): ?>
            <div class="comment-item">
                <div class="comment-header">
                    <span class="comment-user"><?php echo htmlspecialchars($comment['user_name']); ?></span>
                    <span class="comment-date"><?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?></span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
            </div>
        <?php endwhile; ?>
    </div>

    <form action="" method="POST" class="add-comment-form">
        <textarea name="comment" placeholder="Écrire un commentaire..." required></textarea>
        <button type="submit" class="btn btn-primary">Publier le commentaire</button>
    </form>

    <a href="board.php?id=<?php echo $card['board_id']; ?>" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Retour au tableau</a>
</div>

<?php include "footer.php"; ?>