<?php
session_start();
require '../db.php';

// Prevent the browser from caching this protected page (fixes broken
// back/forward navigation between login and dashboard)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header('Location: login.php'); exit;
}

$tid = (int)$_SESSION['user']['id'];

// Stats
$total_materials = $mysqli->query("SELECT COUNT(*) as c FROM materials WHERE uploaded_by=$tid")->fetch_assoc()['c'];
$total_assignments = $mysqli->query("SELECT COUNT(*) as c FROM assignments WHERE teacher_id=$tid")->fetch_assoc()['c'];
$pending_submissions = $mysqli->query("SELECT COUNT(*) as c FROM submissions s JOIN assignments a ON s.assignment_id=a.id WHERE a.teacher_id=$tid AND s.mark IS NULL")->fetch_assoc()['c'];
$total_assessments = $mysqli->query("SELECT COUNT(*) as c FROM assessments WHERE teacher_id=$tid")->fetch_assoc()['c'];

// Recent materials
$recent = $mysqli->query("SELECT m.*, d.name as dept FROM materials m LEFT JOIN departments d ON m.department_id=d.id WHERE m.uploaded_by=$tid ORDER BY m.id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teacher Dashboard - BU LearnSpace</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_teacher.php'; ?>
  <main class="main-content">
    <div class="page-title">
      <h1>Welcome, <?= htmlspecialchars($_SESSION['user']['name']) ?></h1>
      <p>Here is your overview for today</p>
    </div>

    <div class="stats-row">
      <div class="stat-box">
        <strong><?= $total_materials ?></strong>
        <span>Materials Uploaded</span>
      </div>
      <div class="stat-box green">
        <strong><?= $total_assignments ?></strong>
        <span>Assignments Created</span>
      </div>
      <div class="stat-box orange">
        <strong><?= $pending_submissions ?></strong>
        <span>Pending Reviews</span>
      </div>
      <div class="stat-box">
        <strong><?= $total_assessments ?></strong>
        <span>Assessments</span>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <h3>Recently Uploaded Materials</h3>
        <a href="materials.php" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Course</th>
              <th>Department</th>
              <th>Semester</th>
              <th>Views</th>
              <th>Downloads</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recent->num_rows === 0): ?>
            <tr><td colspan="6" style="text-align:center;color:#6b7280;padding:32px">No materials uploaded yet.</td></tr>
            <?php else: while ($m = $recent->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($m['title']) ?></td>
              <td><?= htmlspecialchars($m['course_name']) ?></td>
              <td><span class="badge badge-blue"><?= htmlspecialchars($m['dept']) ?></span></td>
              <td><?= htmlspecialchars($m['semester']) ?></td>
              <td><?= (int)$m['view_count'] ?></td>
              <td><?= (int)$m['download_count'] ?></td>
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
