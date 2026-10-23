<?php
session_start();
include "db.php";

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    die("All fields are required");
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);


// Vérifier email
$check = $con->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    header("Location: register.php?error=❌ Email already in use");
    exit();
}

// Insérer utilisateur
$stmt = $con->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $hashedPassword);

if ($stmt->execute()) {

    // récupérer l'id du nouvel utilisateur
    $user_id = $stmt->insert_id;

    // créer la session (auto login)
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $name;

    // redirection vers dashboard
    header("Location: dashboard.php");
    exit();

} else {
    header("Location:register.php?error=⚠️ Registration error");
    exit();
}
?>