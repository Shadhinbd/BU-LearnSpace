<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require '../db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') { header('Location: login.php'); exit; }

$tid = (int)$_SESSION['user']['id'];
$msg = ''; $msg_type = 'success';

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $mysqli->query("DELETE FROM assignments WHERE id=$id AND teacher_id=$tid");
    header('Location: assignments.php?done=deleted'); exit;
}

// Create assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $course = trim($_POST['course_name'] ?? '');
    $sem = trim($_POST['semester'] ?? '');
    $dept = (int)($_POST['department_id'] ?? 0);
    $batch = trim($_POST['batch'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');

    if (!$title || !$course || !$sem || !$dept || !$batch || !$deadline) {
        $msg = 'Please fill in all required fields.'; $msg_type = 'error';
    } else {
        $stmt = $mysqli->prepare("INSERT INTO assignments (title, description, course_name, semester, department_id, batch, teacher_id, deadline) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sssssiis', $title, $desc, $course, $sem, $dept, $batch, $tid, $deadline);
        $stmt->execute();
        $msg = 'Assignment created successfully!';
    }
}

if (isset($_GET['done'])) { $msg = $_GET['done'] === 'deleted' ? 'Assignment deleted.' : 'Done!'; }

$depts = $mysqli->query("SELECT * FROM departments");
$assignments = $mysqli->query("SELECT a.*, d.name as dept, (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id=a.id) as sub_count FROM assignments a LEFT JOIN departments d ON a.department_id=d.id WHERE a.teacher_id=$tid ORDER BY a.created_at DESC");
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
  <?php include '../includes/sidebar_teacher.php'; ?>
  <main class="main-content">
    <div class="page-title">
      <h1>Assignments</h1>
      <p>Create assignments and review student submissions</p>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-header"><h3>Create New Assignment</h3></div>
      <div class="panel-body">
        <form method="post">
          <input type="hidden" name="create" value="1">
          <div class="form-row">
            <div class="form-group">
              <label>Title *</label>
              <input name="title" placeholder="Assignment title" required>
            </div>
            <div class="form-group">
              <label>Course Name *</label>
              <input name="course_name" placeholder="e.g. CSE301" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Department *</label>
              <select name="department_id" required>
                <option value="">Select Department</option>
                <?php while ($d = $depts->fetch_assoc()): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Semester *</label>
              <input name="semester" placeholder="e.g. 3rd Semester" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Batch *</label>
              <input name="batch" placeholder="e.g. 2024" required>
            </div>
            <div class="form-group">
              <label>Description</label>
              <input name="description" placeholder="Optional description">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Deadline *</label>
              <input type="datetime-local" name="deadline" required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Create Assignment</button>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header"><h3>All Assignments</h3></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Title</th><th>Course</th><th>Dept</th><th>Batch</th><th>Semester</th><th>Deadline</th><th>Submissions</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if ($assignments->num_rows === 0): ?>
            <tr><td colspan="8" style="text-align:center;padding:32px;color:#6b7280">No assignments yet.</td></tr>
            <?php else: while ($a = $assignments->fetch_assoc()): 
              $deadline_passed = strtotime($a['deadline']) < time();
            ?>
            <tr>
              <td><?= htmlspecialchars($a['title']) ?></td>
              <td><?= htmlspecialchars($a['course_name']) ?></td>
              <td><span class="badge badge-blue"><?= htmlspecialchars($a['dept']) ?></span></td>
              <td><span class="badge badge-gray"><?= htmlspecialchars($a['batch'] ?? '—') ?></span></td>
              <td><?= htmlspecialchars($a['semester']) ?></td>
              <td>
                <span class="badge <?= $deadline_passed ? 'badge-red' : 'badge-green' ?>">
                  <?= date('d M Y, h:i A', strtotime($a['deadline'])) ?>
                </span>
              </td>
              <td><span class="badge badge-gray"><?= (int)$a['sub_count'] ?> submitted</span></td>
              <td>
                <a href="view_submissions.php?id=<?= $a['id'] ?>" class="btn btn-ghost btn-sm">View Submissions</a>
                <a href="?delete=<?= $a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this assignment?')">Delete</a>
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
