<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$board_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Vérifier que la session est correcte
$stmt_verify = $con->prepare("SELECT id FROM users WHERE id = ?");
$stmt_verify->bind_param("i", $user_id);
$stmt_verify->execute();
if ($stmt_verify->get_result()->num_rows == 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// 🔹 Vérifier si l'utilisateur est propriétaire OU membre accepté
$stmt = $con->prepare("
    SELECT b.*, u.name as owner_name, u.email as owner_email
    FROM boards b
    JOIN users u ON b.user_id = u.id
    WHERE b.id=? 
    AND (b.user_id=? OR b.id IN (
        SELECT board_id 
        FROM board_members 
        WHERE user_id=? AND status='accepted'
    ))
");
$stmt->bind_param("iii", $board_id, $user_id, $user_id);
$stmt->execute();
$board = $stmt->get_result()->fetch_assoc();

if (!$board) {
    die("Access denied"); // 🔹 Blocks any unauthorized user
}

// 🔹 Récupérer les listes du board
$stmtLists = $con->prepare("SELECT * FROM lists WHERE board_id=? ORDER BY position");
$stmtLists->bind_param("i", $board_id);
$stmtLists->execute();
$listsResult = $stmtLists->get_result();

$columns = [
    'todo' => [],
    'in_progress' => [],
    'done' => []
];
while ($list = $listsResult->fetch_assoc()) {
    $phase = $list['phase'] ?? 'todo';
    if (!isset($columns[$phase])) {
        $phase = 'todo';
    }
    $columns[$phase][] = $list;
}

// 🔹 Récupérer les membres acceptés
$stmtMembers = $con->prepare("SELECT u.id, u.name, u.email FROM board_members bm JOIN users u ON bm.user_id = u.id WHERE bm.board_id=? AND bm.status='accepted'");
$stmtMembers->bind_param("i", $board_id);
$stmtMembers->execute();
$members = $stmtMembers->get_result();

// 🔹 Récupérer les invitations en attente
$stmtPending = $con->prepare("SELECT u.id, u.name, u.email FROM board_members bm JOIN users u ON bm.user_id = u.id WHERE bm.board_id=? AND bm.status='pending'");
$stmtPending->bind_param("i", $board_id);
$stmtPending->execute();
$pendings = $stmtPending->get_result();

// 🔹 Vérifier si l'utilisateur actuel a une invitation en attente
$stmtUserPending = $con->prepare("SELECT * FROM board_members WHERE board_id=? AND user_id=? AND status='pending'");
$stmtUserPending->bind_param("ii", $board_id, $user_id);
$stmtUserPending->execute();
$pending = $stmtUserPending->get_result()->fetch_assoc();

// 🔹 Déterminer le statut de l'utilisateur pour restreindre les actions
if ($board['user_id'] == $user_id) {
    $user_status = 'owner';
} elseif ($pending) {
    $user_status = 'pending';
} else {
    $user_status = 'accepted';
}
?>
<?php
$pageTitle = htmlspecialchars($board['name']) . " - Trello Pro";
include "header.php";
?>

<style>
    /* Add board-specific styles */
    body {
        background: <?php echo htmlspecialchars($board['color']); ?>;
    }
    .board-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        background: rgba(0,0,0,0.15);
        padding: 1rem 1.5rem;
        border-radius: 12px;
        backdrop-filter: blur(8px);
    }
    .board-title {
        color: white;
        margin: 0;
        font-size: 1.8rem;
        font-weight: 800;
    }
    .board-phases {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        align-items: flex-start;
    }
    .phase-column {
        background: #ebecf0;
        border-radius: 10px;
        padding: 1rem;
        min-height: 200px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
    }
    .phase-column h2 {
        color: #172b4d;
        font-size: 1.1rem;
        margin-bottom: 1rem;
        border-bottom: none;
        padding: 0;
    }
    .cards-container {
        flex-grow: 1;
        min-height: 50px;
    }
    .card {
        position: relative;
        background: white;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        cursor: grab;
        border-left: 4px solid #0079bf;
        transition: transform 0.1s;
    }
    .card:active {
        cursor: grabbing;
        transform: scale(1.02);
    }
    .card h4 {
        margin: 0 0 0.5rem 0;
        color: #172b4d;
    }
    .card p {
        font-size: 0.9rem;
        color: #5e6c84;
        margin-bottom: 0.5rem;
    }
    .priority-badge {
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .priority-low { background: #61bd4f22; color: #61bd4f; }
    .priority-medium { background: #f2d60022; color: #d9b51c; }
    .priority-high { background: #eb5a4622; color: #eb5a46; }
    
    .add-card-btn {
        background: transparent;
        border: none;
        color: #5e6c84;
        text-align: left;
        padding: 8px;
        width: 100%;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .add-card-btn:hover {
        background: #dadbe2;
        color: #172b4d;
    }
    .add-card-form {
        display: none;
        background: white;
        padding: 1rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-top: 0.5rem;
    }
    .add-card-form input, .add-card-form textarea {
        width: 100%;
        margin-bottom: 0.5rem;
        padding: 8px;
        border: 2px solid #dfe1e6;
        border-radius: 4px;
        font-family: inherit;
    }
    .add-card-form button {
        background: #0079bf;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        font-weight: 700;
        cursor: pointer;
    }
</style>

<div class="board-header">
    <div class="board-info-left">
        <h1 class="board-title"><?php echo htmlspecialchars($board['name']); ?></h1>
        <div class="members-list">
            <div class="member-avatar" style="background: #e5a30f;" title="Propriétaire : <?php echo htmlspecialchars($board['owner_name']); ?>">
                <?php echo strtoupper(substr($board['owner_name'], 0, 1)); ?>
            </div>
            <?php while($member = $members->fetch_assoc()): ?>
                <div class="member-avatar" title="<?php echo htmlspecialchars($member['name']); ?>">
                    <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <div class="nav-buttons-right">
        <?php if($board['user_id'] == $user_id): ?>
            <a href="edit_board.php?id=<?php echo $board_id; ?>" class="nav-btn"><i class="fa-solid fa-pen-to-square"></i> Modifier</a>
        <?php endif; ?>
        <a href="activity_log.php?id=<?php echo $board_id; ?>" class="nav-btn"><i class="fa-solid fa-list-ul"></i> Journal</a>
    </div>
</div>

<?php if($board['user_id'] == $user_id): ?>
    <div class="invite-center-row">
        <form action="add_member.php" method="POST" class="invite-form-pro">
            <input type="hidden" name="board_id" value="<?php echo $board_id; ?>">
            <input type="email" name="member_email" class="invite-input-pro" placeholder="Inviter par email..." required>
            <button type="submit" class="invite-btn-pro">Inviter</button>
        </form>
    </div>
<?php endif; ?>

<div class="board-phases">
    <?php foreach (['todo' => 'À faire', 'in_progress' => 'En cours', 'done' => 'Terminé'] as $phaseKey => $phaseLabel): ?>
        <div class="phase-column" id="column-<?php echo $phaseKey; ?>">
            <h2><?php echo $phaseLabel; ?></h2>
            
            <div class="cards-container" id="cards-<?php echo $phaseKey; ?>" data-phase="<?php echo $phaseKey; ?>">
            <?php 
            $first_list_id = null;
            if (!empty($columns[$phaseKey])):
                foreach ($columns[$phaseKey] as $list):
                    if ($first_list_id === null) $first_list_id = $list['id'];
                    
                    $stmtCards = $con->prepare("SELECT * FROM cards WHERE list_id=? ORDER BY position");
                    $stmtCards->bind_param("i", $list['id']);
                    $stmtCards->execute();
                    $cards = $stmtCards->get_result();
                    while($card = $cards->fetch_assoc()): 
                        $is_done = ($card['status'] == 'done');
                        $is_expired = ($card['due_date'] && strtotime($card['due_date']) < time());
                        $show_red = ($is_expired && !$is_done);
                        $finished_late = ($is_done && $is_expired);
                    ?>
                        <div class="card <?php echo $show_red ? 'expired' : ''; ?>" id="card-<?php echo $card['id']; ?>" data-id="<?php echo $card['id']; ?>">
                            <div class="card-quick-actions">
                                <button onclick="changeStatus(<?php echo $card['id']; ?>, 'todo')" class="btn-status <?php echo ($card['status'] == 'todo') ? 'active' : ''; ?>">À faire</button>
                                <button onclick="changeStatus(<?php echo $card['id']; ?>, 'in_progress')" class="btn-status <?php echo ($card['status'] == 'in_progress') ? 'active' : ''; ?>">En cours</button>
                                <button onclick="changeStatus(<?php echo $card['id']; ?>, 'done')" class="btn-status <?php echo ($card['status'] == 'done') ? 'active' : ''; ?>">Terminé</button>
                            </div>
                            
                            <h4><a href="card_details.php?card_id=<?php echo $card['id']; ?>" style="text-decoration: none; color: inherit;"><?php echo htmlspecialchars($card['title']); ?></a></h4>
                            <p><?php echo htmlspecialchars(substr($card['description'], 0, 100)) . (strlen($card['description']) > 100 ? '...' : ''); ?></p>
                            
                            <div style="margin-bottom: 10px;">
                                <span class="priority-badge priority-<?php echo $card['priority']; ?>">
                                    <?php echo ['low'=>'Faible','medium'=>'Moyenne','high'=>'Élevée'][$card['priority']] ?? $card['priority']; ?>
                                </span>
                            </div>

                            <?php if($card['due_date']): ?>
                                <div style="margin-bottom: 10px;">
                                    <span class="due-date <?php echo $show_red ? 'expired' : ''; ?>">
                                        <i class="fa-regular fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($card['due_date'])); ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if($finished_late): ?>
                                <div style="margin-bottom: 10px;">
                                    <span class="badge-late"><i class="fa-solid fa-clock-rotate-left"></i> Terminé en retard</span>
                                </div>
                            <?php endif; ?>

                            <div class="card-actions-bottom">
                                <a href="edit_card.php?card_id=<?php echo $card['id']; ?>" class="btn-card-action">Modifier</a>
                                <a href="delete_card.php?card_id=<?php echo $card['id']; ?>" class="btn-card-action delete">Supprimer</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            <?php if($user_status == 'owner' || $user_status == 'accepted'): ?>
                <?php if ($first_list_id): ?>
                    <button class="add-card-btn" onclick="toggleForm('form-<?php echo $phaseKey; ?>')">
                        <i class="fa-solid fa-plus"></i> Ajouter une carte
                    </button>
                    <form id="form-<?php echo $phaseKey; ?>" action="add_card.php" method="POST" class="add-card-form">
                        <input type="hidden" name="list_id" value="<?php echo $first_list_id; ?>">
                        <input type="hidden" name="status" value="<?php echo $phaseKey; ?>">
                        <input type="text" name="title" placeholder="Titre..." required>
                        <textarea name="description" placeholder="Description..."></textarea>
                        <div style="margin-bottom: 0.5rem;">
                            <label style="font-size: 0.8rem; color: #5e6c84;">Deadline :</label>
                            <input type="datetime-local" name="due_date">
                        </div>
                        <select name="priority">
                            <option value="low">Faible</option>
                            <option value="medium" selected>Moyenne</option>
                            <option value="high">Élevée</option>
                        </select>
                        <button type="submit" name="add_card">Ajouter</button>
                        <button type="button" onclick="toggleForm('form-<?php echo $phaseKey; ?>')" style="background: transparent; color: #5e6c84;">Annuler</button>
                    </form>
                <?php else: ?>
                    <form action="add_list.php" method="POST">
                        <input type="hidden" name="board_id" value="<?php echo $board_id; ?>">
                        <input type="hidden" name="phase" value="<?php echo $phaseKey; ?>">
                        <input type="hidden" name="name" value="<?php echo $phaseLabel; ?>">
                        <button type="submit" class="add-card-btn"><i class="fa-solid fa-plus"></i> Initialiser la liste</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
    function toggleForm(id) {
        const form = document.getElementById(id);
        form.style.display = form.style.display === 'block' ? 'none' : 'block';
    }

    function changeStatus(cardId, newPhase) {
        const cardElement = document.getElementById('card-' + cardId);
        const originalParent = cardElement.parentElement;
        const targetContainer = document.getElementById('cards-' + newPhase);

        if (originalParent === targetContainer) return;

        // AJAX request
        fetch(`move_card.php?card_id=${cardId}&to=${newPhase}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Animation logic
                cardElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                cardElement.style.opacity = '0';
                cardElement.style.transform = 'translateY(10px)';

                setTimeout(() => {
                    targetContainer.appendChild(cardElement);
                    cardElement.style.opacity = '1';
                    cardElement.style.transform = 'translateY(0)';
                    
                    // Update buttons active state
                    cardElement.querySelectorAll('.btn-status').forEach(btn => {
                        btn.classList.remove('active');
                        if (btn.getAttribute('onclick').includes(`'${newPhase}'`)) {
                            btn.classList.add('active');
                        }
                    });

                    // Update expired/late styles
                    location.reload(); // Simplest way to recalculate all PHP logic for deadlines
                }, 300);
            } else {
                alert('Erreur lors du déplacement');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Une erreur est survenue');
        });
    }

    // Initialize SortableJS
    document.querySelectorAll('.cards-container').forEach(container => {
        new Sortable(container, {
            group: 'cards',
            animation: 150,
            ghostClass: 'sortable-ghost',
            filter: '.btn-card-action, .btn-status', // Ignore clicks on these elements
            preventOnFilter: false, // Allow default action (like following a link) on filtered elements
            onEnd: function (evt) {
                const cardId = evt.item.dataset.id;
                const newPhase = evt.to.dataset.phase;
                
                // Send AJAX request to move card
                fetch(`move_card.php?card_id=${cardId}&to=${newPhase}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Erreur lors du déplacement');
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error(err);
                    location.reload();
                });
            }
        });
    });
</script>

<?php include "footer.php"; ?>
