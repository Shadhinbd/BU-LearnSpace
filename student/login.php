<?php
session_start();
require '../db.php';

// Prevent the browser from showing a stale, cached copy of this page
// (fixes back/forward navigation issues between login and dashboard)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// If already logged in, skip the form and go straight to the dashboard
if (isset($_SESSION['student'])) {
    header("Location: dashboard.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = $_POST['email'];
    $pass = $_POST['password'];
    $role = "student"; // or student/admin depending on page

    $stmt = $mysqli->prepare("SELECT * FROM users WHERE email=? AND role=?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($u = $res->fetch_assoc()) {

        // check hashed password
        if (password_verify($pass, $u['password'])) {
            $_SESSION['student'] = $u;
            $_SESSION['student_id'] = (int)$u['id'];
            $_SESSION['student_department_id'] = (int)($u['department_id'] ?? 0);
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
* { box-sizing: border-box; }
body {
    margin: 0;
    min-height: 100vh;
    font-family: Inter, "Segoe UI", Roboto, Arial, sans-serif;
    background-color: #141d2f;
    background-image:
        radial-gradient(at 15% 15%, rgba(59, 130, 246, 0.28) 0, transparent 45%),
        radial-gradient(at 85% 0%, rgba(139, 92, 246, 0.24) 0, transparent 50%),
        radial-gradient(at 0% 90%, rgba(16, 185, 129, 0.18) 0, transparent 45%),
        radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.20) 0, transparent 50%);
    background-attachment: fixed;
    background-repeat: no-repeat;
    background-size: cover;
    color: #f1f5f9;
}
.auth-shell {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 24px;
}
.auth-card {
    width: min(100%, 440px);
    background: #1b2740;
    border: 1px solid #1e293b;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);
}
.auth-form {
    padding: 32px 28px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.auth-form h2 {
    margin: 0 0 6px;
    font-size: 1.8rem;
    color: #f1f5f9;
    text-align: center;
}
.auth-form .subtitle {
    margin: 0 0 20px;
    color: #94a3b8;
    font-size: 15px;
    text-align: center;
}
form { display: grid; gap: 14px; }
input {
    width: 100%;
    padding: 14px 15px;
    border: 1px solid #334155;
    border-radius: 14px;
    font-size: 15px;
    background: #0d1320;
    color: #f1f5f9;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
input:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12);
}
button {
    width: 100%;
    padding: 14px 16px;
    border: none;
    border-radius: 14px;
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, #0f766e, #10b981);
    cursor: pointer;
    box-shadow: 0 14px 30px rgba(16, 185, 129, 0.2);
}
.error {
    min-height: 20px;
    color: #dc2626;
    font-weight: 600;
    margin-bottom: 4px;
}
.forgot-link {
    text-align: center;
    margin-top: 8px;
    font-size: 14px;
}
.forgot-link a {
    color: #34d399;
    text-decoration: none;
    font-weight: 600;
}
@media (max-width: 820px) {
    .auth-card { width: min(100%, 100%); }
    .auth-form { padding: 28px 24px 32px; }
}
</style>
</head>

<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-form">
            <h2>Student Login</h2>
            <p class="subtitle">Sign in to continue your learning journey.</p>
            <p class="error"><?= $msg ?></p>

            <form method="post" autocomplete="off">
                <input name="email" placeholder="Email address" required autocomplete="off">
                <input name="password" type="password" placeholder="Password" required autocomplete="new-password">
                <button type="submit">Login</button>
                <p class="forgot-link">
                    <a href="forgot_password.php">Forgot Password?</a>
                </p>
            </form>
        </div>
    </div>
</div>
</body>
</html>
