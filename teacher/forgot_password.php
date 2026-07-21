<?php
session_start();
require '../db.php';

if (file_exists('../vendor/autoload.php')) {
    require '../vendor/autoload.php';
}

$step     = $_GET['step'] ?? 'request';
$msg      = '';
$msg_type = 'success';
$token    = trim($_GET['token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email'] ?? '');
    $role  = 'teacher';

    $stmt = $mysqli->prepare("SELECT id FROM users WHERE email=? AND role=?");
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $token  = bin2hex(random_bytes(32));
        date_default_timezone_set('Asia/Dhaka');
        date_default_timezone_set('UTC');
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $upd = $mysqli->prepare("UPDATE users SET reset_token=?, token_expiry=? WHERE id=?");
        $upd->bind_param("ssi", $token, $expiry, $user['id']);
        $upd->execute();

        $reset_link = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST']
                    . dirname($_SERVER['REQUEST_URI'])
                    . '/forgot_password.php?step=reset&token=' . $token;

        $mail_sent = false;

        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            try {
                $mail             = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'together.shadhin@gmail.com';
                $mail->Password   = 'aopqntmjdrqwzptq';
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->setFrom('together.shadhin@gmail.com', 'BU LearnSpace');
                $mail->addAddress($email);
                $mail->Subject = 'Password Reset - BU LearnSpace';
                $mail->Body    = "Hello,\n\nReset your password here:\n\n$reset_link\n\nExpires in 1 hour.";
                $mail->send();
                $mail_sent = true;
            } catch (Exception $e) {
                $mail_sent = false;
            }
        }

        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_link']  = $reset_link;
        $_SESSION['mail_sent']   = $mail_sent;
        header('Location: forgot_password.php?step=sent');
        exit;
    } else {
        $msg      = 'No teacher account found with that email.';
        $msg_type = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_reset'])) {
    $token    = trim($_POST['token'] ?? '');
    $new_pass = $_POST['new_password']     ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 6) {
        $msg = 'Password must be at least 6 characters.'; $msg_type = 'error';
    } elseif ($new_pass !== $confirm) {
        $msg = 'Passwords do not match.'; $msg_type = 'error';
    } else {
       $stmt = $mysqli->prepare("SELECT id FROM users WHERE reset_token=? AND token_expiry > UTC_TIMESTAMP() AND role='teacher'");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $upd    = $mysqli->prepare("UPDATE users SET password=?, reset_token=NULL, token_expiry=NULL WHERE id=?");
            $upd->bind_param("si", $hashed, $user['id']);
            $upd->execute();
            header('Location: forgot_password.php?step=done');
            exit;
        } else {
            $msg      = 'This link has expired or is invalid. Please request a new one.';
            $msg_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - BU LearnSpace</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background-color: #141d2f; background-image: radial-gradient(at 15% 15%, rgba(59, 130, 246, 0.28) 0, transparent 45%), radial-gradient(at 85% 0%, rgba(139, 92, 246, 0.24) 0, transparent 50%), radial-gradient(at 0% 90%, rgba(16, 185, 129, 0.18) 0, transparent 45%), radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.20) 0, transparent 50%); background-attachment: fixed; background-repeat: no-repeat; background-size: cover; font-family: Arial, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; color: #e2e8f0; }
    .box { width: 420px; max-width: 94vw; background: #1b2740; border: 1px solid #1e293b; border-radius: 16px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5); padding: 36px 32px; }
    h2 { text-align: center; color: #3b82f6; margin-bottom: 6px; font-size: 22px; }
    .subtitle { text-align: center; color: #94a3b8; font-size: 14px; margin-bottom: 24px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 6px; }
    input[type=email], input[type=password] { width: 100%; padding: 11px 14px; border: 1px solid #334155; border-radius: 10px; font-size: 15px; margin-bottom: 16px; background: #0d1320; color: #f1f5f9; }
    input:focus { outline: none; border-color: #3b82f6; }
    button[type=submit] { width: 100%; padding: 12px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: bold; cursor: pointer; }
    .alert { padding: 12px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
    .alert-error   { background: rgba(239, 68, 68, 0.15); color: #f87171; border-left: 4px solid #ef4444; }
    .alert-success { background: rgba(34, 197, 94, 0.15); color: #4ade80; border-left: 4px solid #22c55e; }
    .alert-info    { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border-left: 4px solid #3b82f6; }
    .back { display: block; text-align: center; margin-top: 18px; color: #60a5fa; font-size: 14px; text-decoration: none; font-weight: 600; }
    .icon { font-size: 48px; text-align: center; margin-bottom: 16px; }
    .dev-box { background: rgba(245, 158, 11, 0.12); border: 1px dashed #d97706; border-radius: 8px; padding: 12px; margin-bottom: 16px; font-size: 13px; color: #fbbf24; word-break: break-all; }
    .dev-box strong { display: block; margin-bottom: 6px; }
    .steps { display: flex; justify-content: center; gap: 8px; margin-bottom: 28px; }
    .step-dot { width: 10px; height: 10px; border-radius: 50%; background: #334155; }
    .step-dot.active { background: #3b82f6; }
    .step-dot.done   { background: #22c55e; }
    .go-btn { display: block; text-align: center; padding: 12px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; border-radius: 10px; text-decoration: none; font-weight: bold; margin-top: 8px; }
  </style>
</head>
<body>
<div class="box">

<?php if ($step === 'request'): ?>
  <div class="icon">🔑</div>
  <h2>Forgot Password</h2>
  <p class="subtitle">Enter your teacher email to receive a reset link</p>
  <div class="steps">
    <div class="step-dot active"></div>
    <div class="step-dot"></div>
    <div class="step-dot"></div>
  </div>
  <?php if ($msg): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="request_reset" value="1">
    <label>Email Address</label>
    <input type="email" name="email" placeholder="teacher@bu.com" required autofocus>
    <button type="submit">Send Reset Link</button>
  </form>
  <a href="login.php" class="back">← Back to Login</a>

<?php elseif ($step === 'sent'): ?>
  <div class="icon">📧</div>
  <h2>Check Your Email</h2>
  <div class="steps">
    <div class="step-dot done"></div>
    <div class="step-dot active"></div>
    <div class="step-dot"></div>
  </div>
  <?php if (!empty($_SESSION['mail_sent'])): ?>
    <div class="alert alert-success">Reset link sent to <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong>.</div>
  <?php else: ?>
    <div class="alert alert-info">Email not configured. Use the link below (development only):</div>
    <?php if (!empty($_SESSION['reset_link'])): ?>
    <div class="dev-box">
      <strong>🛠 Development Reset Link:</strong>
      <a href="<?= htmlspecialchars($_SESSION['reset_link']) ?>">Click here to reset password</a>
    </div>
    <?php endif; ?>
  <?php endif; ?>
  <a href="login.php" class="back">← Back to Login</a>

<?php elseif ($step === 'reset'): ?>
  <div class="icon">🔒</div>
  <h2>Set New Password</h2>
  <div class="steps">
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot active"></div>
  </div>
  <?php
    if (!$token) {
        echo '<div class="alert alert-error">Invalid token.</div>';
    } else {
        $chk = $mysqli->prepare("SELECT id FROM users WHERE reset_token=? AND token_expiry > UTC_TIMESTAMP() AND role='teacher'");
        $chk->bind_param("s", $token);
        $chk->execute();
        $valid_user = $chk->get_result()->fetch_assoc();
        if (!$valid_user) {
            echo '<div class="alert alert-error">Link expired. <a href="forgot_password.php">Request a new one</a>.</div>';
        } else {
  ?>
  <?php if ($msg): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="do_reset" value="1">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <label>New Password (min 6 characters)</label>
    <input type="password" name="new_password" placeholder="New password" required minlength="6" autofocus>
    <label>Confirm New Password</label>
    <input type="password" name="confirm_password" placeholder="Confirm password" required minlength="6">
    <button type="submit">Reset Password</button>
  </form>
  <?php } } ?>
  <a href="login.php" class="back">← Back to Login</a>

<?php elseif ($step === 'done'): ?>
  <div class="icon">✅</div>
  <h2>Password Reset!</h2>
  <div class="steps">
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
  </div>
  <div class="alert alert-success">Password updated! You can now login.</div>
  <a href="login.php" class="go-btn">Go to Login →</a>

<?php endif; ?>
</div>
</body>
</html>