<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require '../db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') { header('Location: login.php'); exit; }

$tid = (int)$_SESSION['user']['id'];
$id = (int)($_GET['id'] ?? 0);
$msg = ''; $msg_type = 'success';

$sessionInfo = $mysqli->query("SELECT a.*, d.name as dept FROM attendance a LEFT JOIN departments d ON a.department_id=d.id WHERE a.id=$id AND a.teacher_id=$tid")->fetch_assoc();
if (!$sessionInfo) { header('Location: attendance.php'); exit; }

$edit_mode = isset($_GET['edit']) || ($_SERVER['REQUEST_METHOD'] === 'POST');

// Save updated statuses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $statuses = $_POST['status'] ?? [];
    foreach ($statuses as $stu_id => $status) {
        $stu_id = (int)$stu_id;
        $status = in_array($status, ['present', 'absent', 'late'], true) ? $status : 'absent';
        $upd = $mysqli->prepare("UPDATE attendance_records SET status=? WHERE attendance_id=? AND student_id=?");
        $upd->bind_param('sii', $status, $id, $stu_id);
        $upd->execute();
    }
    $msg = 'Attendance updated successfully.';
    $edit_mode = false;
}

$records = $mysqli->query("SELECT ar.*, u.name FROM attendance_records ar JOIN users u ON ar.student_id=u.id WHERE ar.attendance_id=$id ORDER BY u.name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Detail - BU LearnSpace</title>
  <link rel="stylesheet" href="../style.css">
  <style>
    @media print { .no-print { display:none !important; } .main-content { margin-left:0; padding:0; } body{background:#fff;} }
  </style>
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_teacher.php'; ?>
  <main class="main-content">
    <div class="page-title no-print">
      <h1>Attendance Detail</h1>
      <p>Review or print the selected class attendance sheet</p>
    </div>
    <div class="panel">
      <div class="panel-header">
        <h3><?= htmlspecialchars($sessionInfo['course_name']) ?> • <?= htmlspecialchars($sessionInfo['dept']) ?> • Batch <?= htmlspecialchars($sessionInfo['batch'] ?? '—') ?></h3>
        <div class="no-print">
          <?php if ($edit_mode): ?>
            <a href="attendance_detail.php?id=<?= $id ?>" class="btn btn-ghost">Cancel</a>
          <?php else: ?>
            <a href="attendance_detail.php?id=<?= $id ?>&edit=1" class="btn btn-primary">Edit</a>
            <button class="btn btn-primary" onclick="window.print()">Print</button>
          <?php endif; ?>
          <a href="attendance.php" class="btn btn-ghost">Back</a>
        </div>
      </div>
      <div class="panel-body">
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <p><strong>Date:</strong> <?= date('d M Y', strtotime($sessionInfo['class_date'])) ?> &nbsp;|&nbsp; <strong>Semester:</strong> <?= htmlspecialchars($sessionInfo['semester']) ?></p>
        <div class="table-wrap" style="margin-top:16px;">
          <?php if ($edit_mode): ?>
          <form method="post">
            <input type="hidden" name="update_attendance" value="1">
            <table>
              <thead>
                <tr><th>#</th><th>Student Name</th><th>Present</th><th>Late</th><th>Absent</th></tr>
              </thead>
              <tbody>
                <?php if ($records->num_rows === 0): ?>
                <tr><td colspan="5" class="empty-state">No records found.</td></tr>
                <?php else: $i = 1; while ($r = $records->fetch_assoc()): ?>
                <tr>
                  <td><?= $i++ ?></td>
                  <td><?= htmlspecialchars($r['name']) ?></td>
                  <td style="text-align:center"><input type="radio" name="status[<?= $r['student_id'] ?>]" value="present" <?= $r['status'] === 'present' ? 'checked' : '' ?>></td>
                  <td style="text-align:center"><input type="radio" name="status[<?= $r['student_id'] ?>]" value="late" <?= $r['status'] === 'late' ? 'checked' : '' ?>></td>
                  <td style="text-align:center"><input type="radio" name="status[<?= $r['student_id'] ?>]" value="absent" <?= $r['status'] === 'absent' ? 'checked' : '' ?>></td>
                </tr>
                <?php endwhile; endif; ?>
              </tbody>
            </table>
            <div style="margin-top:16px;">
              <button type="submit" class="btn btn-primary">Save Changes</button>
              <a href="attendance_detail.php?id=<?= $id ?>" class="btn btn-ghost">Cancel</a>
            </div>
          </form>
          <?php else: ?>
          <table>
            <thead>
              <tr><th>#</th><th>Student Name</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php if ($records->num_rows === 0): ?>
              <tr><td colspan="3" class="empty-state">No records found.</td></tr>
              <?php else: $i=1; while ($r=$records->fetch_assoc()): ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><span class="badge <?= $r['status']==='present'?'badge-green':($r['status']==='late'?'badge-yellow':'badge-red') ?>"><?= ucfirst($r['status']) ?></span></td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
