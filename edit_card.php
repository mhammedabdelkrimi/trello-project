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

// Traiter la mise à jour
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_card'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $status = $_POST['status'];
    $priority = $_POST['priority'];

    if (!empty($title)) {
        // Si le statut change, on doit aussi changer la liste
        $list_id = $card['list_id'];
        if ($status != $card['status']) {
            // Trouver une liste de destination dans la nouvelle phase
            $stmt_list = $con->prepare("SELECT id FROM lists WHERE board_id=? AND phase=? LIMIT 1");
            $stmt_list->bind_param("is", $card['board_id'], $status);
            $stmt_list->execute();
            $list_dest = $stmt_list->get_result()->fetch_assoc();
            
            if (!$list_dest) {
                // Créer une liste par défaut si nécessaire
                $phase_labels = ['todo' => 'À faire', 'in_progress' => 'En cours', 'done' => 'Terminé'];
                $new_list_name = $phase_labels[$status] ?? ucfirst($status);
                $stmt_create = $con->prepare("INSERT INTO lists (board_id, name, phase, position) VALUES (?, ?, ?, 0)");
                $stmt_create->bind_param("iss", $card['board_id'], $new_list_name, $status);
                $stmt_create->execute();
                $list_id = $stmt_create->insert_id;
            } else {
                $list_id = $list_dest['id'];
            }
        }

        $stmtUpdate = $con->prepare("UPDATE cards SET title=?, description=?, due_date=?, status=?, priority=?, list_id=?, updated_at=NOW() WHERE id=?");
        $stmtUpdate->bind_param("sssssii", $title, $description, $due_date, $status, $priority, $list_id, $card_id);
        
        if ($stmtUpdate->execute()) {
            // Ajouter à l'activité
            $stmtAct = $con->prepare("INSERT INTO activity_log (board_id, user_id, action, details) VALUES (?, ?, 'updated_card', ?)");
            $details = "Carte modifiée : $title";
            $stmtAct->bind_param("iis", $card['board_id'], $user_id, $details);
            $stmtAct->execute();

            // Créer notification pour les membres
            $stmtNotifE = $con->prepare("SELECT user_id FROM board_members WHERE board_id=? AND status='accepted' AND user_id != ? UNION SELECT user_id FROM boards WHERE id=? AND user_id != ?");
            $stmtNotifE->bind_param("iiii", $card['board_id'], $user_id, $card['board_id'], $user_id);
            $stmtNotifE->execute();
            $membersNotifE = $stmtNotifE->get_result();
            while($mNotifE = $membersNotifE->fetch_assoc()){
                $stmtSE = $con->prepare("INSERT INTO notifications (user_id, board_id, card_id, message, type) VALUES (?, ?, ?, ?, 'card_updated')");
                $notif_msg_e = "Carte modifiée : $title";
                $stmtSE->bind_param("iiis", $mNotifE['user_id'], $card['board_id'], $card_id, $notif_msg_e);
                $stmtSE->execute();
            }

            header("Location: board.php?id=" . $card['board_id']);
            exit();
        } else {
            $error = "Erreur lors de la mise à jour.";
        }
    } else {
        $error = "Le titre ne peut pas être vide.";
    }
}
?>
<?php
$pageTitle = "Modifier la carte - " . htmlspecialchars($card['title']);
include "header.php";
?>

<div class="form-professional" style="max-width: 800px; margin: 2rem auto;">
    <h1><i class="fa-solid fa-pen-to-square"></i> Modifier la carte</h1>
    <?php if(isset($error)): ?>
        <div style="background: #fff5f5; color: #c53030; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; border: 1px solid #feb2b2;">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <form action="" method="POST">
        <div class="form-group">
            <label class="form-label">Titre de la carte</label>
            <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($card['title']); ?>" required placeholder="Ex: Finaliser le design">
        </div>
        
        <div class="form-group">
            <label class="form-label">Description détaillée</label>
            <textarea name="description" class="form-textarea" placeholder="Décrivez les tâches à accomplir..."><?php echo htmlspecialchars($card['description']); ?></textarea>
        </div>
        
        <div class="grid grid-cols-2" style="gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">Date d'échéance</label>
                <input type="datetime-local" name="due_date" class="form-input" value="<?php echo $card['due_date'] ? date('Y-m-d\TH:i', strtotime($card['due_date'])) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Statut actuel</label>
                <select name="status" class="form-select">
                    <option value="todo" <?php echo $card['status'] == 'todo' ? 'selected' : ''; ?>>À faire</option>
                    <option value="in_progress" <?php echo $card['status'] == 'in_progress' ? 'selected' : ''; ?>>En cours</option>
                    <option value="done" <?php echo $card['status'] == 'done' ? 'selected' : ''; ?>>Terminé</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Importance / Priorité</label>
            <select name="priority" class="form-select">
                <option value="low" <?php echo $card['priority'] == 'low' ? 'selected' : ''; ?>>Faible</option>
                <option value="medium" <?php echo $card['priority'] == 'medium' ? 'selected' : ''; ?>>Moyenne</option>
                <option value="high" <?php echo $card['priority'] == 'high' ? 'selected' : ''; ?>>Élevée</option>
            </select>
        </div>
        
        <div style="display: flex; gap: 1rem; margin-top: 2.5rem; border-top: 1px solid #eee; padding-top: 2rem;">
            <button type="submit" name="update_card" class="btn btn-primary" style="flex: 2;">
                <i class="fa-solid fa-check"></i> Enregistrer les modifications
            </button>
            <a href="board.php?id=<?php echo $card['board_id']; ?>" class="btn btn-secondary" style="flex: 1;">
                Annuler
            </a>
            <a href="delete_card.php?card_id=<?php echo $card_id; ?>" class="btn btn-danger" style="flex: 1;" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette carte ?');">
                <i class="fa-solid fa-trash"></i>
            </a>
        </div>
    </form>
</div>

<?php include "footer.php"; ?>
