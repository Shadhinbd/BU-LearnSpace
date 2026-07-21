<?php
session_start();
require '../db.php';

// Prevent the browser from caching this protected page (fixes broken
// back/forward navigation between login and dashboard)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['student'])) { header('Location: login.php'); exit; }

$stu = $_SESSION['student'];
$sid = (int)$stu['id'];
$dept_id = (int)($stu['department_id'] ?? 0);
$batch = trim($stu['batch'] ?? '');

// Stats
$total_materials = $mysqli->query("SELECT COUNT(*) as c FROM materials WHERE department_id=$dept_id")->fetch_assoc()['c'];
$pending_assignments_query = "SELECT COUNT(*) as c FROM assignments a WHERE a.department_id=$dept_id AND a.deadline > NOW()";
if ($batch !== '') { $pending_assignments_query .= " AND a.batch='$batch'"; }
$pending_assignments_query .= " AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id=a.id AND s.student_id=$sid)";
$pending_assignments = $mysqli->query($pending_assignments_query)->fetch_assoc()['c'];
$submitted = $mysqli->query("SELECT COUNT(*) as c FROM submissions WHERE student_id=$sid")->fetch_assoc()['c'];

// Attendance %
$att_total_query = "SELECT COUNT(*) as c FROM attendance_records ar JOIN attendance a ON ar.attendance_id=a.id WHERE ar.student_id=$sid AND a.department_id=$dept_id";
if ($batch !== '') { $att_total_query .= " AND a.batch='$batch'"; }
$att_present_query = $att_total_query . " AND ar.status='present'";
$att_total = $mysqli->query($att_total_query)->fetch_assoc()['c'];
$att_present = $mysqli->query($att_present_query)->fetch_assoc()['c'];
$att_pct = $att_total > 0 ? round(($att_present/$att_total)*100) : 0;

// Upcoming assignments
$upcoming_query = "SELECT a.* FROM assignments a WHERE a.department_id=$dept_id AND a.deadline > NOW()";
if ($batch !== '') { $upcoming_query .= " AND a.batch='$batch'"; }
$upcoming_query .= " AND NOT EXISTS (SELECT 1 FROM submissions s WHERE s.assignment_id=a.id AND s.student_id=$sid) ORDER BY a.deadline ASC LIMIT 5";
$upcoming = $mysqli->query($upcoming_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Dashboard - BU LearnSpace</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_student.php'; ?>
  <main class="main-content">
    <div class="page-title">
      <h1>Welcome, <?= htmlspecialchars($stu['name']) ?></h1>
      <p>Here is your academic overview</p>
    </div>

    <div class="stats-row">
      <div class="stat-box">
        <strong><?= $total_materials ?></strong>
        <span>Study Materials</span>
      </div>
      <div class="stat-box orange">
        <strong><?= $pending_assignments ?></strong>
        <span>Pending Assignments</span>
      </div>
      <div class="stat-box green">
        <strong><?= $submitted ?></strong>
        <span>Submitted</span>
      </div>
      <div class="stat-box <?= $att_pct < 75 ? 'red' : '' ?>">
        <strong><?= $att_pct ?>%</strong>
        <span>Attendance</span>
      </div>
    </div>

    <?php if ($att_pct < 75 && $att_total > 0): ?>
    <div class="alert alert-warning">
      &#9888; Your attendance is <?= $att_pct ?>%. Minimum 75% is required. Please attend classes regularly.
    </div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-header">
        <h3>Pending Assignments</h3>
        <a href="assignments.php" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Title</th><th>Course</th><th>Deadline</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php if ($upcoming->num_rows === 0): ?>
            <tr><td colspan="4" style="text-align:center;padding:24px;color:#6b7280">No pending assignments!</td></tr>
            <?php else: while ($a = $upcoming->fetch_assoc()):
              $days_left = ceil((strtotime($a['deadline']) - time()) / 86400);
            ?>
            <tr>
              <td><?= htmlspecialchars($a['title']) ?></td>
              <td><?= htmlspecialchars($a['course_name']) ?></td>
              <td>
                <span class="badge <?= $days_left <= 2 ? 'badge-red' : 'badge-green' ?>">
                  <?= date('d M Y', strtotime($a['deadline'])) ?> (<?= $days_left ?> days left)
                </span>
              </td>
              <td><a href="assignments.php?submit=<?= $a['id'] ?>" class="btn btn-primary btn-sm">Submit</a></td>
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
