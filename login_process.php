<?php
session_start();
include "db.php";

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header("Location: login.php?error=Required fields missing");
    exit();
}

$stmt = $con->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // EMAIL INCORRECT
    header("Location: login.php?error= ❌ Incorrect email");
    exit();
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    // PASSWORD INCORRECT
    header("Location: login.php?error=⚠️ Incorrect password");
    exit();
}

// LOGIN OK
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];

header("Location: dashboard.php");
exit();
?>