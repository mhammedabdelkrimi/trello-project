<?php
session_start();
include "db.php";

$id = $_GET['id'];

// Si formulaire soumis
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $name = $_POST['name'];
    $color = $_POST['color'];

    // Modifier uniquement si propriétaire
    $stmt = $con->prepare("UPDATE boards SET name=?, color=? WHERE id=? AND user_id=?");
    $stmt->bind_param("ssii", $name, $color, $id, $_SESSION['user_id']);
    $stmt->execute();

    header("Location: dashboard.php");
    exit();
}

// Récupérer board existant
$stmt = $con->prepare("SELECT * FROM boards WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $id, $_SESSION['user_id']);
$stmt->execute();
$board = $stmt->get_result()->fetch_assoc();
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Board</title>
<link rel="stylesheet" href="dashboard.css">
</head>
<body>
<div class="container">

<h1 class="page-title">Edit Board <small><?php echo htmlspecialchars($board['name']); ?></small></h1>

<!-- FORMULAIRE MODIFICATION BOARD -->
<form method="POST" class="edit-form">
    <input type="text" name="name" value="<?php echo htmlspecialchars($board['name']); ?>" placeholder="Board name" required>
    <select name="color">
        <option value="#0079bf" <?php if($board['color'] == '#0079bf') echo 'selected'; ?>>Blue</option>
        <option value="#519839" <?php if($board['color'] == '#519839') echo 'selected'; ?>>Green</option>
        <option value="#d29034" <?php if($board['color'] == '#d29034') echo 'selected'; ?>>Orange</option>
        <option value="#b04632" <?php if($board['color'] == '#b04632') echo 'selected'; ?>>Red</option>
    </select>
    <button>Save</button>
</form>

<a href="dashboard.php" class="back-button">Retour au tableau de bord</a>
</div>
</body>
</html>
