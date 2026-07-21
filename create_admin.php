<?php
require 'db.php';

$name = "Admin";
$email = "admin@bu.com";
$password = password_hash("admin123", PASSWORD_DEFAULT);

$stmt = $mysqli->prepare("SELECT id FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    die("Admin already exists. Delete create_admin.php");
}

$stmt = $mysqli->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'admin')");
$stmt->bind_param("sss", $name, $email, $password);
$stmt->execute();

echo "Admin Created: admin@bu.com / admin123. Delete this file!";
?>
