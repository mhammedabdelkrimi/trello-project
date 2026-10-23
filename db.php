<?php

$host = "localhost";
$user = "root";
$password = "";
$database ="trello_db";

$con = new mysqli($host, $user, $password, $database);

if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Ensure character set is utf8mb4 for better international support
$con->set_charset("utf8mb4");
