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

$att_base_query = "SELECT COUNT(*) as c FROM attendance_records ar JOIN attendance a ON ar.attendance_id=a.id WHERE ar.student_id=$sid AND a.department_id=$dept_id";
if ($batch !== '') { $att_base_query .= " AND a.batch='$batch'"; }
$total = $mysqli->query($att_base_query)->fetch_assoc()['c'];
$present = $mysqli->query($att_base_query . " AND ar.status='present'")->fetch_assoc()['c'];
$late = $mysqli->query($att_base_query . " AND ar.status='late'")->fetch_assoc()['c'];
$absent = $total - $present - $late;
$pct = $total > 0 ? round(($present/$total)*100) : 0;

// Pagination for attendance records
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;
$pages = $total > 0 ? (int)ceil($total / $per_page) : 1;

$records_query = "SELECT a.class_date, a.course_name, a.semester, ar.status FROM attendance_records ar JOIN attendance a ON ar.attendance_id=a.id WHERE ar.student_id=$sid AND a.department_id=$dept_id";
if ($batch !== '') { $records_query .= " AND a.batch='$batch'"; }
$records_query .= " ORDER BY a.class_date DESC";
$records_query .= " LIMIT $offset, $per_page";
$records = $mysqli->query($records_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Attendance - BU LearnSpace</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_student.php'; ?>
  <main class="main-content">
    <div class="page-title" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
      <div>
        <h1>My Attendance</h1>
        <p>Track your class attendance record</p>
      </div>
      <!-- pagination moved below table to match view.php style -->
    </div>

    <?php if ($pct < 75 && $total > 0): ?>
    <div class="alert alert-warning">&#9888; Your attendance is <?= $pct ?>%. You need at least 75% to avoid issues.</div>
    <?php endif; ?>

    <div class="stats-row">
      <div class="stat-box green">
        <strong><?= $present ?></strong>
        <span>Present</span>
      </div>
      <div class="stat-box orange">
        <strong><?= $late ?></strong>
        <span>Late</span>
      </div>
      <div class="stat-box red">
        <strong><?= $absent ?></strong>
        <span>Absent</span>
      </div>
      <div class="stat-box <?= $pct < 75 ? 'red' : '' ?>">
        <strong><?= $pct ?>%</strong>
        <span>Overall</span>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header"><h3>Attendance History</h3></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Date</th><th>Course</th><th>Semester</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if ($records->num_rows === 0): ?>
            <tr><td colspan="4" style="text-align:center;padding:32px;color:#6b7280">No attendance records yet.</td></tr>
            <?php else: while ($r = $records->fetch_assoc()): ?>
            <tr>
              <td><?= date('d M Y', strtotime($r['class_date'])) ?></td>
              <td><?= htmlspecialchars($r['course_name']) ?></td>
              <td><?= htmlspecialchars($r['semester']) ?></td>
              <td>
                <span class="badge <?= $r['status']==='present'?'badge-green':($r['status']==='late'?'badge-yellow':'badge-red') ?>">
                  <?= ucfirst($r['status']) ?>
                </span>
              </td>
            </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
        <?php if ($total > 0):
          $prev_page = max(1, $page - 1);
          $next_page = min($pages, $page + 1);
          $prev_params = $_GET;
          $next_params = $_GET;
          $prev_params['page'] = $prev_page;
          $next_params['page'] = $next_page;
          $prev_query = http_build_query($prev_params);
          $next_query = http_build_query($next_params);
          $is_first_page = $page === 1;
          $is_last_page = $page === $pages;
        ?>
        <div class="pagination-wrap">
          <?php if ($is_first_page): ?>
            <span class="ghost-button disabled" aria-disabled="true">Previous</span>
          <?php else: ?>
            <a class="ghost-button" href="attendance.php?<?= $prev_query ?>">Previous</a>
          <?php endif; ?>

          <span class="page-chip">Page <?= (int)$page ?> of <?= (int)$pages ?></span>

          <?php if ($is_last_page): ?>
            <span class="ghost-button disabled" aria-disabled="true">Next</span>
          <?php else: ?>
            <a class="ghost-button" href="attendance.php?<?= $next_query ?>">Next</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
  </main>
</div>
</body>
</html>
