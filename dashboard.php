<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Vérifier que la session est correcte - lis l'utilisateur depuis la BD
$stmt_verify = $con->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt_verify->bind_param("i", $user_id);
$stmt_verify->execute();
$user_verify = $stmt_verify->get_result()->fetch_assoc();

if (!$user_verify) {
    // Session invalide
    session_destroy();
    header("Location: login.php");
    exit();
}

// Mettre à jour le nom de session s'il a changé
if ($_SESSION['user_name'] !== $user_verify['name']) {
    $_SESSION['user_name'] = $user_verify['name'];
}

// 1️⃣ Récupérer les invitations en attente (JUSTE LES PENDING)
$stmtPending = $con->prepare("
    SELECT bm.board_id, b.name, b.color, u.name as owner_name, u.email as owner_email
    FROM board_members bm
    JOIN boards b ON bm.board_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE bm.user_id=? AND bm.status='pending'
");
$stmtPending->bind_param("i", $user_id);
$stmtPending->execute();
$pendingInvites = $stmtPending->get_result();

// 2️⃣ Récupérer les boards accessibles (SEULEMENT propriétaires OU membres acceptés - PAS pending)
$stmt = $con->prepare("
    SELECT b.*, u.name as owner_name, u.email as owner_email,
    (SELECT GROUP_CONCAT(users.name) 
     FROM board_members 
     JOIN users ON board_members.user_id = users.id 
     WHERE board_members.board_id = b.id AND board_members.status = 'accepted') as members_list
    FROM boards b
    JOIN users u ON b.user_id = u.id
    WHERE b.user_id=? 
    OR b.id IN (SELECT board_id FROM board_members WHERE user_id=? AND status='accepted')
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

// 🔹 Récupérer les notifications (cartes proches de l'échéance ou expirées)
// On ne notifie que si la carte n'est pas "done" et n'a pas été ignorée
$stmtNotif = $con->prepare("
    SELECT c.id as card_id, c.title, c.due_date, l.name as list_name, b.name as board_name, b.id as board_id
    FROM cards c
    JOIN lists l ON c.list_id = l.id
    JOIN boards b ON l.board_id = b.id
    WHERE c.due_date IS NOT NULL 
    AND c.status != 'done'
    AND (b.user_id=? OR b.id IN (SELECT board_id FROM board_members WHERE user_id=? AND status='accepted'))
    AND (c.due_date < NOW() OR c.due_date < DATE_ADD(NOW(), INTERVAL 3 DAY))
    AND c.id NOT IN (SELECT card_id FROM notifications WHERE user_id=? AND type='due_date_dismissed' AND card_id IS NOT NULL)
    ORDER BY c.due_date ASC
");
$stmtNotif->bind_param("iii", $user_id, $user_id, $user_id);
$stmtNotif->execute();
$dueNotifications = $stmtNotif->get_result();

// Récupérer les notifications système
$stmtSysNotif = $con->prepare("
    SELECT n.*, b.name as board_name, c.title as card_title
    FROM notifications n
    LEFT JOIN boards b ON n.board_id = b.id
    LEFT JOIN cards c ON n.card_id = c.id
    WHERE n.user_id=? AND n.is_read = FALSE
    ORDER BY n.created_at DESC
    LIMIT 10
");
$stmtSysNotif->bind_param("i", $user_id);
$stmtSysNotif->execute();
$systemNotifications = $stmtSysNotif->get_result();
$pageTitle = "Dashboard - Trello Pro";
include "header.php";
?>

<h1>Bienvenue <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>

<!-- 1️⃣ Pending invitations section -->
<?php if($pendingInvites->num_rows > 0): ?>
    <div class="pending-invitations">
        <h2>Invitations en attente</h2>
        <?php while($invite = $pendingInvites->fetch_assoc()): ?>
            <div class="pending-invitation">
                <p>Tableau : <?php echo htmlspecialchars($invite['name']); ?> <br>
                   Propriétaire : <?php echo htmlspecialchars($invite['owner_name']); ?> (<?php echo htmlspecialchars($invite['owner_email']); ?>)</p>
                <a href="manage_member.php?board_id=<?php echo $invite['board_id']; ?>&action=accept">Accepter</a>
                <a href="manage_member.php?board_id=<?php echo $invite['board_id']; ?>&action=refuse">Refuser</a>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; ?>

<!-- 3️⃣ Section Notifications -->
<?php if($dueNotifications->num_rows > 0 || $systemNotifications->num_rows > 0): ?>
    <section class="dashboard-section">
        <div class="section-header">
            <h2>Notifications</h2>
            <span class="section-chip">Mises à jour importantes</span>
        </div>
        <div class="section-divider"></div>
        <div class="notifications">
            <?php while($notif = $dueNotifications->fetch_assoc()): ?>
                <div class="notification <?php echo (strtotime($notif['due_date']) < time()) ? 'expired' : 'upcoming'; ?>">
                    <p>Carte « <strong><?php echo htmlspecialchars($notif['title']); ?></strong> » dans la liste « <?php echo htmlspecialchars($notif['list_name']); ?> » du tableau « <strong><?php echo htmlspecialchars($notif['board_name']); ?></strong> » 
                    <?php echo (strtotime($notif['due_date']) < time()) ? 'est en retard' : 'sera bientôt à échéance'; ?> le <?php echo date('d/m/Y H:i', strtotime($notif['due_date'])); ?>.</p>
                    <div class="notification-actions">
                            <a class="btn btn-primary" href="board.php?id=<?php echo $notif['board_id']; ?>">Voir le tableau</a>
                        <button type="button" class="btn btn-ghost notification-dismiss" data-card-id="<?php echo $notif['card_id']; ?>">Ignorer</button>
                    </div>
                </div>
            <?php endwhile; ?>
            <?php while($sysNotif = $systemNotifications->fetch_assoc()): ?>
                <div class="notification system">
                    <p><?php echo htmlspecialchars($sysNotif['message']); ?> 
                    <?php if($sysNotif['board_name']): ?>
                        sur le tableau « <strong><?php echo htmlspecialchars($sysNotif['board_name']); ?></strong> »
                    <?php endif; ?>
                    <?php if($sysNotif['card_title']): ?>
                        (Carte : <?php echo htmlspecialchars($sysNotif['card_title']); ?>)
                    <?php endif; ?>
                    </p>
                    <div class="notification-actions">
                        <?php if($sysNotif['board_id']): ?>
                            <a class="btn btn-primary" href="board.php?id=<?php echo $sysNotif['board_id']; ?>">Voir le tableau</a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-ghost" onclick="markRead(event, <?php echo $sysNotif['id']; ?>)">Marquer comme lu</button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </section>
<?php endif; ?>

<section class="dashboard-section">
    <div class="section-header">
        <h2>Créer un tableau</h2>
        <span class="section-chip">Créer un projet</span>
    </div>
    <div class="section-divider"></div>
    <form action="create_board.php" method="POST" class="create-board-card">
        <input type="text" name="name" placeholder="Nom du tableau" required>
        <select name="color">
    <option value="#4F8EF7">🔵 Bleu doux</option>
    <option value="#B94BA1">🟣 Violet rosé</option>
    <option value="#F4A261">🟠 Orange doux</option>
    <option value="#E76F51">🔴 Rouge doux</option>
    <option value="#2A9D8F">🟢 Vert moderne</option>
    <option value="#6C757D">⚫ Gris élégant</option>
</select>
        <input type="email" name="member_email" placeholder="Ajouter un membre (facultatif)">
        <button>Créer</button>
    </form>
</section>

<section class="dashboard-section">
    <div class="section-header">
        <h2>Tableaux</h2>
        <span class="section-chip">Vos projets</span>
    </div>
    <div class="section-divider"></div>
    <div class="boards-grid">
    <?php while($row = $result->fetch_assoc()): ?>
    <div class="board-card <?php echo ($row['user_id'] != $user_id) ? 'shared' : ''; ?>" style="background: <?php echo htmlspecialchars($row['color']); ?>;">
        <div class="board-card-header">
            <h3><?php echo htmlspecialchars($row['name']); ?></h3>
            <div class="board-card-members">
                <!-- Owner Avatar -->
                <div class="member-avatar" style="background: #e5a30f; width: 24px; height: 24px; font-size: 0.7rem;" title="Propriétaire : <?php echo htmlspecialchars($row['owner_name']); ?>">
                    <?php echo strtoupper(substr($row['owner_name'], 0, 1)); ?>
                </div>
                <!-- Members Avatars -->
                <?php 
                if($row['members_list']) {
                    $m_names = explode(',', $row['members_list']);
                    foreach($m_names as $m_name) {
                        echo '<div class="member-avatar" style="width: 24px; height: 24px; font-size: 0.7rem; margin-left: -8px; border: 2px solid '.htmlspecialchars($row['color']).';" title="'.htmlspecialchars($m_name).'">'.strtoupper(substr($m_name, 0, 1)).'</div>';
                    }
                }
                ?>
            </div>
        </div>
        <?php if($row['user_id'] != $user_id): ?>
            <p style="font-size:12px; color:#fff; margin-bottom: 10px;">Partagé par : <?php echo htmlspecialchars($row['owner_name']); ?></p>
        <?php endif; ?>
        <div class="board-actions">
            <a href="board.php?id=<?php echo $row['id']; ?>"><i class="fa-solid fa-folder-open"></i> Open</a>
            <?php if($row['user_id'] == $user_id): ?>
                <a href="edit_board.php?id=<?php echo $row['id']; ?>"><i class="fa-solid fa-pen"></i></a>
                <a class="delete" href="delete_board.php?id=<?php echo $row['id']; ?>"><i class="fa-solid fa-trash"></i></a>
            <?php endif; ?>
        </div>
    </div>
<?php endwhile; ?>
    </div>
</section>

<?php include "footer.php"; ?>
