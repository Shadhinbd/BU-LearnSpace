<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require '../db.php';
if (!isset($_SESSION['student'])) { header('Location: login.php'); exit; }

// Safely make sure the "others_mark" column exists (checks first, only
// alters if missing — works on all MySQL/MariaDB versions).
ensure_column($mysqli, 'assessment_marks', 'others_mark', 'DECIMAL(6,2) DEFAULT 0');

$stu = $_SESSION['student'];
$sid = (int)$stu['id'];
$dept_id = (int)($stu['department_id'] ?? 0);
$batch = trim($stu['batch'] ?? '');

$assessment_query = "SELECT a.*, am.obtained_marks, am.remarks, am.class_test, am.attendance_mark, am.viva, am.lab, am.presentation, am.mid, am.final, am.others_mark FROM assessments a LEFT JOIN assessment_marks am ON a.id=am.assessment_id AND am.student_id=$sid WHERE a.department_id=$dept_id";
if ($batch !== '') { $assessment_query .= " AND a.batch='$batch'"; }
$assessment_query .= " ORDER BY a.created_at DESC";
$assessments = $mysqli->query($assessment_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Marks - BU LearnSpace</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_student.php'; ?>
  <main class="main-content">
    <div class="page-title" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
      <div>
        <h1>My Assessment Marks</h1>
        <p>View your marks for all assessments</p>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header"><h3>All Assessments</h3></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Assessment</th><th>Course</th><th>Semester</th><th>Class Test 1</th><th>Class Test 2</th><th>Attendance</th><th>Viva</th><th>Assignment</th><th>Presentation</th><th>Others</th><th>Total</th><th>Remarks</th></tr>
          </thead>
          <tbody>
            <?php if ($assessments->num_rows === 0): ?>
            <tr><td colspan="12" style="text-align:center;padding:32px;color:#6b7280">No assessments available yet.</td></tr>
            <?php else: while ($a = $assessments->fetch_assoc()):
              $pct = ($a['obtained_marks'] !== null && $a['total_marks'] > 0) ? round(($a['obtained_marks']/$a['total_marks'])*100) : null;
            ?>
            <tr>
              <td><?= htmlspecialchars($a['title']) ?></td>
              <td><?= htmlspecialchars($a['course_name']) ?></td>
              <td><?= htmlspecialchars($a['semester']) ?></td>
              <td><?= $a['class_test'] !== null ? $a['class_test'] : '—' ?></td>
              <td><?= $a['mid'] !== null ? $a['mid'] : '—' ?></td>
              <td><?= $a['attendance_mark'] !== null ? $a['attendance_mark'] : '—' ?></td>
              <td><?= $a['viva'] !== null ? $a['viva'] : '—' ?></td>
              <td><?= $a['lab'] !== null ? $a['lab'] : '—' ?></td>
              <td><?= $a['presentation'] !== null ? $a['presentation'] : '—' ?></td>
              <td><?= $a['others_mark'] !== null ? $a['others_mark'] : '—' ?></td>
              <td>
                <?php if ($a['obtained_marks'] !== null): ?>
                <strong><?= $a['obtained_marks'] ?></strong>
                <?php else: ?>
                <span style="color:#6b7280">Not graded</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($pct !== null): ?>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="progress-bar" style="width:80px">
                    <div class="progress-fill <?= $pct >= 50 ? 'green' : 'red' ?>" style="width:<?= $pct ?>%"></div>
                  </div>
                  <span class="badge <?= $pct >= 50 ? 'badge-green' : 'badge-red' ?>"><?= $pct ?>%</span>
                </div>
                <?php else: echo '—'; endif; ?>
                <div style="margin-top:6px;color:#6b7280;font-size:12px"><?= htmlspecialchars($a['remarks'] ?? '—') ?></div>
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
