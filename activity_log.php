<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$board_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Vérifier accès au board
$stmt = $con->prepare("
    SELECT b.*
    FROM boards b
    WHERE b.id=? AND (b.user_id=? OR b.id IN (
        SELECT board_id 
        FROM board_members 
        WHERE user_id=? AND status='accepted'
    ))
");
$stmt->bind_param("iii", $board_id, $user_id, $user_id);
$stmt->execute();
$board = $stmt->get_result()->fetch_assoc();

if (!$board) {
    die("Access denied");
}

// Récupérer le journal d'activité
$stmtLog = $con->prepare("
    SELECT al.*, u.name as user_name, u.email as user_email
    FROM activity_log al
    JOIN users u ON al.user_id = u.id
    WHERE al.board_id=?
    ORDER BY al.created_at DESC
    LIMIT 100
");
$stmtLog->bind_param("i", $board_id);
$stmtLog->execute();
$activities = $stmtLog->get_result();

// Mapper les actions vers des textes plus lisibles en français
$actionLabels = [
    'created_card' => 'a créé la carte',
    'updated_card' => 'a mis à jour la carte',
    'deleted_card' => 'a supprimé une carte',
    'added_comment' => 'a ajouté un commentaire',
    'created_list' => 'a créé la liste',
    'deleted_list' => 'a supprimé une liste',
    'added_member' => 'a invité un nouveau membre',
    'accepted_invite' => 'a rejoint le tableau',
];
?>
<?php
$pageTitle = "Journal d'activité - " . htmlspecialchars($board['name']);
include "header.php";
?>
<style>
    .activity-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        margin-top: 2rem;
        overflow: hidden;
    }
    .activity-header {
        padding: 1.5rem 2rem;
        background: #172b4d;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .activity-header h1 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 700;
        text-shadow: none;
    }
    .activity-list {
        padding: 0;
    }
    .activity-row {
        display: flex;
        gap: 1.5rem;
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #eee;
        transition: background 0.2s;
    }
    .activity-row:hover {
        background: #f8f9fa;
    }
    .activity-row:last-child {
        border-bottom: none;
    }
    .activity-content {
        flex: 1;
    }
    .activity-user {
        font-weight: 700;
        color: #172b4d;
    }
    .activity-action {
        color: #5e6c84;
    }
    .activity-details {
        background: #f4f5f7;
        padding: 0.75rem 1rem;
        border-radius: 6px;
        font-size: 0.9rem;
        color: #172b4d;
        border-left: 4px solid #0079bf;
        margin-top: 0.75rem;
    }
    .activity-time {
        font-size: 0.8rem;
        color: #8993a4;
        margin-top: 0.5rem;
    }
</style>

<div class="activity-container">
    <div class="activity-header">
        <h1><i class="fa-solid fa-clock-rotate-left"></i> Journal d'activité : <?php echo htmlspecialchars($board['name']); ?></h1>
        <a href="board.php?id=<?php echo $board_id; ?>" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    </div>

    <div class="activity-list">
        <?php if($activities->num_rows == 0): ?>
            <div style="text-align: center; padding: 4rem; color: #8993a4;">
                <i class="fa-solid fa-inbox fa-3x" style="margin-bottom: 1rem; opacity: 0.3;"></i>
                <p>Aucune activité enregistrée pour le moment.</p>
            </div>
        <?php endif; ?>

        <?php while($activity = $activities->fetch_assoc()): ?>
            <div class="activity-row">
                <div class="member-avatar">
                    <?php echo strtoupper(substr($activity['user_name'], 0, 1)); ?>
                </div>
                <div class="activity-content">
                    <div>
                        <span class="activity-user"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                        <span class="activity-action"><?php echo $actionLabels[$activity['action']] ?? htmlspecialchars($activity['action']); ?></span>
                    </div>
                    <?php if($activity['details']): ?>
                        <div class="activity-details">
                            <?php echo htmlspecialchars($activity['details']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="activity-time">
                        <i class="fa-regular fa-calendar-days"></i> <?php echo date('d/m/Y à H:i', strtotime($activity['created_at'])); ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<?php include "footer.php"; ?>
