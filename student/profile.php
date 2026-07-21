<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require '../db.php';

if (!isset($_SESSION['student'])) { header('Location: login.php'); exit; }

$student = $_SESSION['student'];
$studentId = (int)$student['id'];
$uploadDir = __DIR__ . '/../uploads/profile_photos';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
    @chmod($uploadDir, 0777);
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['current_password'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($name === '' || $email === '') {
        $message = 'Name and email are required.';
    } else {
        $photoName = $student['profile_photo'] ?? null;
        $uploadError = '';
        
        if (!empty($_FILES['photo']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            
            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                $uploadError = 'Upload error: ' . $_FILES['photo']['error'];
            } elseif (!in_array($ext, $allowed, true)) {
                $uploadError = 'Only JPG, PNG, and WEBP images are allowed.';
            } elseif ($_FILES['photo']['size'] > 5000000) {
                $uploadError = 'File size must be less than 5MB.';
            } else {
                $newPhotoName = 'student_' . $studentId . '_' . time() . '.' . $ext;
                $target = $uploadDir . '/' . $newPhotoName;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                    @chmod($target, 0644);
                    $photoName = $newPhotoName;
                } else {
                    $uploadError = 'Failed to save image file. Check folder permissions.';
                }
            }
        }
        
        if ($uploadError !== '') {
            $message = $uploadError;
        } else {
            if (!empty($student['profile_photo']) && $photoName !== $student['profile_photo']) {
                $oldPhoto = $uploadDir . '/' . $student['profile_photo'];
                if (file_exists($oldPhoto)) { unlink($oldPhoto); }
            }

            $stmt = $mysqli->prepare('UPDATE users SET name=?, email=?, profile_photo=? WHERE id=?');
            $stmt->bind_param('sssi', $name, $email, $photoName, $studentId);
            $stmt->execute();

            $stmt = $mysqli->prepare('SELECT * FROM users WHERE id=?');
            $stmt->bind_param('i', $studentId);
            $stmt->execute();
            $updated = $stmt->get_result()->fetch_assoc();
            $_SESSION['student'] = $updated;
            $student = $updated;
            $message = 'Profile updated successfully.';
            $success = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password'], $_POST['new_password'], $_POST['confirm_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {
        $message = 'Please fill all password fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New passwords do not match.';
    } elseif (!password_verify($currentPassword, $student['password'])) {
        $message = 'Current password is incorrect.';
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare('UPDATE users SET password=? WHERE id=?');
        $stmt->bind_param('si', $hashed, $studentId);
        $stmt->execute();
        $message = 'Password updated successfully.';
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Profile - BU LearnSpace</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_student.php'; ?>
  <main class="main-content">
    <div class="page-title">
      <h1>Profile</h1>
      <p>Update your profile information and change your password.</p>
    </div>

    <?php if ($message !== ''): ?>
      <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-body">
        <div class="profile-grid">
          <div class="profile-card">
            <div class="profile-photo-preview">
              <?php if (!empty($student['profile_photo']) && file_exists($uploadDir . '/' . $student['profile_photo'])): ?>
                <img src="../uploads/profile_photos/<?= htmlspecialchars($student['profile_photo']) ?>?t=<?= time() ?>" alt="Profile photo">
              <?php else: ?>
                <span><?= htmlspecialchars(strtoupper(substr(trim($student['name'] ?? 'S'), 0, 1))) ?></span>
              <?php endif; ?>
            </div>
            <h3><?= htmlspecialchars($student['name'] ?? 'Student') ?></h3>
            <p><?= htmlspecialchars($student['email'] ?? '') ?></p>
          </div>

          <div class="profile-form-card">
            <form method="post" enctype="multipart/form-data" class="profile-form">
              <div class="form-group">
                <label>Profile Photo</label>
                <input type="file" name="photo" accept="image/*">
              </div>
              <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($student['name'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required>
              </div>
              <button type="submit" class="btn btn-primary">Save Profile</button>
            </form>

            <hr class="profile-divider">

            <form method="post" class="profile-form">
              <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
              </div>
              <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required>
              </div>
              <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
              </div>
              <button type="submit" class="btn btn-secondary">Change Password</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
