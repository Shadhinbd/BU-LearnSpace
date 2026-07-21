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

$assignment_id = (int)($_GET['id'] ?? 0);

// Make sure this assignment belongs to the logged-in teacher.
$stmt = $mysqli->prepare("SELECT a.*, d.name as dept FROM assignments a LEFT JOIN departments d ON a.department_id=d.id WHERE a.id=? AND a.teacher_id=?");
$stmt->bind_param('ii', $assignment_id, $tid);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();

if (!$assignment) {
    header('Location: assignments.php');
    exit;
}

// Save mark / feedback for a submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grade'])) {
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $mark = $_POST['mark'] !== '' ? (float)$_POST['mark'] : null;
    $feedback = trim($_POST['feedback'] ?? '');

    // Ownership check: the submission must belong to an assignment owned by this teacher.
    $check = $mysqli->prepare("SELECT s.id FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE s.id=? AND a.teacher_id=?");
    $check->bind_param('ii', $submission_id, $tid);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $update = $mysqli->prepare("UPDATE submissions SET mark=?, feedback=? WHERE id=?");
        $update->bind_param('dsi', $mark, $feedback, $submission_id);
        $update->execute();
        $msg = 'Grade saved successfully!';
    } else {
        $msg = 'Unable to update that submission.'; $msg_type = 'error';
    }
}

// All students eligible for this assignment (by department + batch), left-joined with their submission (if any).
$batch = $assignment['batch'] ?? '';
$studentsQuery = "SELECT u.id as student_id, u.name, u.email,
                          s.id as submission_id, s.filename, s.original_name, s.mark, s.feedback, s.submitted_at
                   FROM users u
                   LEFT JOIN submissions s ON s.student_id = u.id AND s.assignment_id = ?
                   WHERE u.role='student' AND u.department_id = ?";
$params = [$assignment_id, (int)$assignment['department_id']];
$types = 'ii';
if ($batch !== '') {
    $studentsQuery .= " AND u.batch = ?";
    $params[] = $batch;
    $types .= 's';
}
$studentsQuery .= " ORDER BY (s.id IS NULL), u.name";

$stmt = $mysqli->prepare($studentsQuery);
$refs = [$types];
foreach ($params as $key => $value) {
    $refs[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $refs);
$stmt->execute();
$students = $stmt->get_result();

$deadline_passed = strtotime($assignment['deadline']) < time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submissions - <?= htmlspecialchars($assignment['title']) ?></title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_teacher.php'; ?>
  <main class="main-content">
    <div class="page-title" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
      <div>
        <h1><?= htmlspecialchars($assignment['title']) ?></h1>
        <p><?= htmlspecialchars($assignment['course_name']) ?> &middot; <?= htmlspecialchars($assignment['dept'] ?? '') ?> &middot; Batch <?= htmlspecialchars($assignment['batch'] ?? '—') ?></p>
      </div>
      <a href="assignments.php" class="btn btn-ghost">&larr; Back to Assignments</a>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-header"><h3>Assignment Details</h3></div>
      <div class="panel-body">
        <p style="margin-bottom:12px;color:#94a3b8"><?= htmlspecialchars($assignment['description'] ?? 'No description provided.') ?></p>
        <span class="badge <?= $deadline_passed ? 'badge-red' : 'badge-green' ?>">
          Deadline: <?= date('d M Y, h:i A', strtotime($assignment['deadline'])) ?>
        </span>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header"><h3>Student Submissions</h3></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Status</th>
              <th>File</th>
              <th>Submitted At</th>
              <th>Grade & Feedback</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($students->num_rows === 0): ?>
            <tr><td colspan="5" style="text-align:center;padding:32px;color:#6b7280">No students found for this department/batch.</td></tr>
            <?php else: while ($s = $students->fetch_assoc()):
              $has_submission = !empty($s['submission_id']);
            ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($s['name']) ?></strong><br>
                <span style="color:#94a3b8;font-size:13px"><?= htmlspecialchars($s['email']) ?></span>
              </td>
              <td>
                <?php if ($has_submission): ?>
                  <span class="badge badge-green">Submitted</span>
                <?php elseif ($deadline_passed): ?>
                  <span class="badge badge-red">Missed</span>
                <?php else: ?>
                  <span class="badge badge-yellow">Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($has_submission): ?>
                  <a class="link-button" href="download_submission.php?submission_id=<?= (int)$s['submission_id'] ?>"><?= htmlspecialchars($s['original_name']) ?></a>
                <?php else: ?>
                  <span style="color:#6b7280">—</span>
                <?php endif; ?>
              </td>
              <td><?= $has_submission ? date('d M Y, h:i A', strtotime($s['submitted_at'])) : '—' ?></td>
              <td>
                <?php if ($has_submission): ?>
                <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                  <input type="hidden" name="save_grade" value="1">
                  <input type="hidden" name="submission_id" value="<?= (int)$s['submission_id'] ?>">
                  <input type="number" step="0.01" min="0" name="mark" value="<?= $s['mark'] !== null ? htmlspecialchars($s['mark']) : '' ?>" placeholder="Mark" style="width:90px;">
                  <input type="text" name="feedback" value="<?= htmlspecialchars($s['feedback'] ?? '') ?>" placeholder="Feedback" style="min-width:160px;flex:1;">
                  <button type="submit" class="btn btn-sm btn-primary">Save</button>
                </form>
                <?php else: ?>
                  <span style="color:#6b7280">Not graded</span>
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
