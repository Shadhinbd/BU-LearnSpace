<?php
session_start();
require '../db.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = $_POST['email'];
    $pass = $_POST['password'];
    $role = "teacher";

    // ✅ get user by email + role only
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE email=? AND role=?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($u = $res->fetch_assoc()) {

        // ✅ verify hashed password
        if (password_verify($pass, $u['password'])) {

            // ✅ store full user (important for dashboard)
            $_SESSION['user'] = $u;

            header("Location: dashboard.php");
            exit;
        }
    }

    $msg = "Invalid credentials";
}
?>
<!DOCTYPE html>
<html>
<head>
<style>
body {background:#fff; font-family:Arial;}
.box {width:350px; margin:80px auto; padding:30px; background:#fff;
border-radius:14px; box-shadow:0 5px 18px rgba(0,0,0,0.1);}
h2 {text-align:center; color:#0b66c3;}
input {width:100%; padding:12px; margin:10px 0; border:1px solid #ccc; border-radius:8px;}
button {width:100%; padding:12px; background:#0b66c3; color:#fff; border:none;
border-radius:8px; font-size:16px; font-weight:bold;}
.error {color:red; text-align:center;}
</style>
</head>

<body>
<div class="box">
<h2>Teacher Login</h2>
<p class="error"><?= $msg ?></p>

<form method="post" autocomplete="off">
    <input name="email" placeholder="Email" required autocomplete="off">
    <input name="password" type="password" placeholder="Password" required>
    <button>Login</button>
</form>

</div>
</body>
</html>