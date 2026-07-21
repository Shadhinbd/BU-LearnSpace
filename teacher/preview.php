<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken
// back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

require '../db.php';

$teacher_id = (int)$_SESSION['user']['id'];
$id = intval($_GET['id'] ?? 0);

$stmt = $mysqli->prepare('SELECT * FROM materials WHERE id = ? AND uploaded_by = ?');
$stmt->bind_param('ii', $id, $teacher_id);
$stmt->execute();
$mat = $stmt->get_result()->fetch_assoc();

if (!$mat) {
    header('HTTP/1.0 404 Not Found');
    echo 'Material not found.';
    exit;
}

$ext = strtolower(pathinfo($mat['filename'], PATHINFO_EXTENSION));
$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$isPdf = $ext === 'pdf';
$isImage = in_array($ext, $imageExts, true);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="container">
  <h2><?= htmlspecialchars($mat['title']) ?></h2>
  <?php if ($isPdf): ?>
    <iframe src="../uploads/<?= rawurlencode($mat['filename']) ?>" style="width:100%;height:600px;border:1px solid var(--border);border-radius:8px;"></iframe>
  <?php elseif ($isImage): ?>
    <img src="../uploads/<?= rawurlencode($mat['filename']) ?>" alt="<?= htmlspecialchars($mat['title']) ?>" style="max-width:100%;border-radius:8px;border:1px solid var(--border);">
  <?php else: ?>
    <div class="panel" style="padding:32px;text-align:center;">
      <p style="color:var(--gray-500);margin-bottom:16px;">
        Inline preview isn't available for <?= htmlspecialchars(strtoupper($ext ?: 'this')) ?> files. Please download to view the full content.
      </p>
      <a class="btn btn-primary" href="download.php?id=<?= (int)$id ?>">Download <?= htmlspecialchars(strtoupper($ext ?: 'File')) ?></a>
    </div>
  <?php endif; ?>
  <p style="margin-top:14px;">
    <a class="link" href="download.php?id=<?= (int)$id ?>">Download</a>
  </p>
</div>
</body>
</html>
