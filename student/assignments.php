<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require '../db.php';
if (!isset($_SESSION['student'])) { header('Location: login.php'); exit; }

$stu = $_SESSION['student'];
$sid = (int)$stu['id'];
$dept_id = (int)($stu['department_id'] ?? 0);
$batch = trim($stu['batch'] ?? '');
$msg = '';
$msg_type = 'success';

$uploadsAbsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsAbsDir)) {
    @mkdir($uploadsAbsDir, 0775, true);
}
if (!is_writable($uploadsAbsDir)) {
    // Try to self-heal permissions before bothering anyone about it.
    @chmod($uploadsAbsDir, 0775);
}
$uploads_writable = is_writable($uploadsAbsDir);

// Submit assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $aid = (int)($_POST['assignment_id'] ?? 0);
    if (!$uploads_writable) {
        $msg = uploads_permission_message($uploadsAbsDir);
        $msg_type = 'error';
    } elseif ($aid && !empty($_FILES['file']['name'])) {
        $orig = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = ['pdf','doc','docx','ppt','pptx','zip'];
        if (in_array($ext, $allowed)) {
            $new_name = time() . '_sub_' . $sid . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $orig);
            if (@move_uploaded_file($_FILES['file']['tmp_name'], $uploadsAbsDir . '/' . $new_name)) {
                $stmt = $mysqli->prepare("INSERT INTO submissions (assignment_id, student_id, filename, original_name) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename), original_name=VALUES(original_name), submitted_at=NOW()");
                $stmt->bind_param('iiss', $aid, $sid, $new_name, $orig);
                $stmt->execute();
                $msg = 'Assignment submitted successfully!';
            } else {
                $msg = 'Upload failed. The server could not save your file — please try again or contact your administrator.';
                $msg_type = 'error';
            }
        } else { $msg = 'File type not allowed. Use PDF, DOC, DOCX, PPT, ZIP.'; $msg_type = 'error'; }
    }
}

$submit_id = (int)($_GET['submit'] ?? 0);
$assignments_query = "SELECT a.*, (SELECT id FROM submissions WHERE assignment_id=a.id AND student_id=$sid) as my_sub_id, (SELECT mark FROM submissions WHERE assignment_id=a.id AND student_id=$sid) as my_mark FROM assignments a WHERE a.department_id=$dept_id";
if ($batch !== '') {
    $assignments_query .= " AND a.batch='$batch'";
}
$assignments_query .= " ORDER BY a.deadline ASC";
$assignments = $mysqli->query($assignments_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Assignments - BU LearnSpace</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_student.php'; ?>
  <main class="main-content">
    <div class="page-title" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
      <div>
        <h1>Assignments</h1>
        <p>View and submit your assignments</p>
      </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($submit_id > 0): 
      $aInfoQuery = "SELECT * FROM assignments WHERE id=$submit_id AND department_id=$dept_id";
      if ($batch !== '') { $aInfoQuery .= " AND batch='$batch'"; }
      $aInfo = $mysqli->query($aInfoQuery)->fetch_assoc();
      if ($aInfo):
    ?>
    <div class="panel">
      <div class="panel-header"><h3>Submit: <?= htmlspecialchars($aInfo['title']) ?></h3></div>
      <div class="panel-body">
        <p style="margin-bottom:12px;color:#374151"><?= htmlspecialchars($aInfo['description'] ?? '') ?></p>
        <p style="margin-bottom:16px"><span class="badge badge-red">Deadline: <?= date('d M Y, h:i A', strtotime($aInfo['deadline'])) ?></span></p>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="submit_assignment" value="1">
          <input type="hidden" name="assignment_id" value="<?= $submit_id ?>">
          <div class="form-group" style="max-width:400px">
            <label>Upload File (PDF, DOC, DOCX, PPT, ZIP)</label>
            <input type="file" name="file" required>
          </div>
          <button type="submit" class="btn btn-primary">Submit Assignment</button>
          <a href="assignments.php" class="btn btn-ghost" style="margin-left:8px">Cancel</a>
        </form>
      </div>
    </div>
    <?php endif; endif; ?>

    <div class="panel">
      <div class="panel-header"><h3>All Assignments</h3></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Title</th><th>Course</th><th>Deadline</th><th>Status</th><th>Mark</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if ($assignments->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center;padding:32px;color:#6b7280">No assignments yet.</td></tr>
            <?php else: while ($a = $assignments->fetch_assoc()):
              $deadline_passed = strtotime($a['deadline']) < time();
              $submitted = !empty($a['my_sub_id']);
            ?>
            <tr>
              <td><?= htmlspecialchars($a['title']) ?></td>
              <td><?= htmlspecialchars($a['course_name']) ?></td>
              <td>
                <span class="badge <?= $deadline_passed ? 'badge-gray' : 'badge-green' ?>">
                  <?= date('d M Y', strtotime($a['deadline'])) ?>
                </span>
              </td>
              <td>
                <?php if ($submitted): ?>
                <span class="badge badge-green">Submitted</span>
                <?php elseif ($deadline_passed): ?>
                <span class="badge badge-red">Missed</span>
                <?php else: ?>
                <span class="badge badge-yellow">Pending</span>
                <?php endif; ?>
              </td>
              <td><?= $a['my_mark'] !== null ? '<span class="badge badge-blue">' . $a['my_mark'] . '</span>' : '—' ?></td>
              <td>
                <?php if (!$submitted && !$deadline_passed): ?>
                <a href="?submit=<?= $a['id'] ?>" class="btn btn-primary btn-sm">Submit</a>
                <?php elseif ($submitted): ?>
                <span style="color:#6b7280;font-size:13px">Submitted ✓</span>
                <?php endif; ?>
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
