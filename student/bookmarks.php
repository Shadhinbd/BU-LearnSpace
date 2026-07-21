<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
if (!isset($_SESSION['student'])) {
    header('Location: login.php');
    exit;
}

require '../db.php';

$student_id = (int)($_SESSION['student_id'] ?? 0);
if ($student_id <= 0) {
    header('Location: login.php');
    exit;
}

function material_label($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if ($ext === 'pdf') return 'PDF';
    if (in_array($ext, ['doc', 'docx'], true)) return 'DOC';
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) return 'IMAGE';

    return strtoupper($ext ?: 'FILE');
}

$delete_id = intval($_GET['delete'] ?? 0);
if ($delete_id > 0) {
    $stmt = $mysqli->prepare('DELETE FROM material_bookmarks WHERE material_id = ? AND student_id = ?');
    $stmt->bind_param('ii', $delete_id, $student_id);
    $stmt->execute();
    header('Location: bookmarks.php?removed=1');
    exit;
}

$materials = $mysqli->prepare('SELECT m.id, m.title, m.filename, m.cover_image, m.course_name, m.semester, m.department_id, m.uploaded_by, m.view_count, m.download_count, m.created_at, u.name AS teacher, IFNULL(ROUND(AVG(r.rating), 1), 0) AS avg_rating, COUNT(r.id) AS ratings_count, MAX(b.created_at) AS bookmarked_at FROM materials m INNER JOIN material_bookmarks b ON b.material_id = m.id LEFT JOIN users u ON m.uploaded_by = u.id LEFT JOIN material_ratings r ON r.material_id = m.id WHERE b.student_id = ? GROUP BY m.id ORDER BY bookmarked_at DESC');
$materials->bind_param('i', $student_id);
$materials->execute();
$res = $materials->get_result();

$status = '';
if (isset($_GET['removed'])) {
    $status = 'Bookmark removed.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Bookmarks</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_student.php'; ?>
  <main class="main-content">
    <div class="page-title">
      <h1>Bookmarks</h1>
      <p>Quick access to materials you saved for later study.</p>
    </div>

    <?php if ($status): ?>
      <div class="alert-message success"><?= htmlspecialchars($status) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-header">
        <h3>Bookmarked Materials</h3>
        <span class="badge badge-blue">Saved for later</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Material</th>
              <th>Course</th>
              <th>Semester</th>
              <th>Teacher</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($res->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center;padding:28px;color:#6b7280">You haven't bookmarked any materials yet.</td></tr>
            <?php else: while ($m = $res->fetch_assoc()):
              $cover = !empty($m['cover_image']) && $m['cover_image'] !== 'no-cover.png' ? $m['cover_image'] : '';
              $show_image = $cover !== '' && file_exists('../uploads/' . $cover);
              $label = material_label($m['filename']);
            ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($m['title']) ?></strong><br>
                <span class="badge badge-gray"><?= htmlspecialchars($m['filename']) ?></span>
              </td>
              <td><?= htmlspecialchars($m['course_name'] ?: 'General') ?></td>
              <td><?= htmlspecialchars($m['semester']) ?></td>
              <td><?= htmlspecialchars($m['teacher'] ?: 'Unknown') ?></td>
              <td>
                <span class="badge badge-green">Saved</span><br>
                <span class="badge badge-gray">Rating <?= htmlspecialchars($m['avg_rating']) ?>/5</span>
              </td>
              <td>
                <div class="action-group" style="justify-content:flex-start;gap:8px;flex-wrap:wrap;">
                  <a class="btn btn-sm btn-primary" href="preview.php?id=<?= (int)$m['id'] ?>">Preview</a>
                  <a class="btn btn-sm btn-ghost" href="download.php?id=<?= (int)$m['id'] ?>">Download</a>
                  <a class="btn btn-sm btn-danger" href="bookmarks.php?delete=<?= (int)$m['id'] ?>" onclick="return confirm('Remove bookmark?');">Remove</a>
                </div>
              </td>
            </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
