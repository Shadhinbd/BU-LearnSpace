<?php
session_start();
require '../db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') { header('Location: login.php'); exit; }

ensure_column($mysqli, 'assessment_marks', 'others_mark', 'DECIMAL(6,2) DEFAULT 0');

$tid = (int)$_SESSION['user']['id'];
$id = (int)($_GET['id'] ?? 0);

$stmt = $mysqli->prepare("SELECT a.*, d.name as dept FROM assessments a LEFT JOIN departments d ON a.department_id=d.id WHERE a.id=? AND a.teacher_id=?");
$stmt->bind_param('ii', $id, $tid);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

if (!$assessment) {
    header('HTTP/1.0 404 Not Found');
    echo 'Assessment not found.';
    exit;
}

$dept_id = (int)$assessment['department_id'];
$batch = $assessment['batch'] ?? '';
$query = "SELECT id, name, email FROM users WHERE role='student' AND department_id=$dept_id";
if ($batch !== '') { $query .= " AND batch='" . $mysqli->real_escape_string($batch) . "'"; }
$query .= " ORDER BY name";
$sRes = $mysqli->query($query);
$students = [];
while ($s = $sRes->fetch_assoc()) { $students[] = $s; }

$marks_data = [];
$mRes = $mysqli->query("SELECT * FROM assessment_marks WHERE assessment_id=" . (int)$id);
while ($m = $mRes->fetch_assoc()) { $marks_data[$m['student_id']] = $m; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Print: <?= htmlspecialchars($assessment['title']) ?> - BU LearnSpace</title>
  <link rel="stylesheet" href="../style.css">
  <style>
    body { background: #fff !important; color: #111 !important; }
    .print-wrap { max-width: 1000px; margin: 24px auto; padding: 0 16px; color: #111; }
    .print-wrap table { width: 100%; border-collapse: collapse; }
    .print-wrap th, .print-wrap td { border: 1px solid #333; padding: 6px 8px; font-size: 13px; text-align: left; color: #111; }
    .print-wrap th { background: #eee; }
    .print-header { margin-bottom: 16px; }
    .print-header h1 { margin: 0 0 4px 0; font-size: 20px; color: #111; }
    .print-header p { margin: 2px 0; color: #333; font-size: 13px; }
    .no-print { margin-bottom: 16px; }
    @media print {
      .no-print { display: none !important; }
      body { background: #fff !important; }
    }
  </style>
</head>
<body>
  <div class="print-wrap">
    <div class="no-print">
      <button class="btn btn-primary" onclick="window.print()">Print</button>
      <a class="btn btn-ghost" href="marks.php">Back</a>
    </div>

    <div class="print-header">
      <h1><?= htmlspecialchars($assessment['title']) ?></h1>
      <p><strong>Course:</strong> <?= htmlspecialchars($assessment['course_name']) ?> (<?= htmlspecialchars($assessment['course_code']) ?>)</p>
      <p><strong>Department:</strong> <?= htmlspecialchars($assessment['dept']) ?> &nbsp;|&nbsp; <strong>Semester:</strong> <?= htmlspecialchars($assessment['semester']) ?> &nbsp;|&nbsp; <strong>Batch:</strong> <?= htmlspecialchars($assessment['batch'] ?? '—') ?></p>
      <p><strong>Total Marks:</strong> <?= htmlspecialchars($assessment['total_marks']) ?></p>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Student Name</th>
          <th>Email</th>
          <th>CT 1</th>
          <th>CT 2</th>
          <th>Attendance</th>
          <th>Viva</th>
          <th>Assignment</th>
          <th>Presentation</th>
          <th>Others</th>
          <th>Total Obtained</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
        <tr><td colspan="12" style="text-align:center;padding:20px;">No students found in this department/batch.</td></tr>
        <?php else: $i = 1; foreach ($students as $s):
          $m = $marks_data[$s['id']] ?? null;
        ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><?= htmlspecialchars($s['email']) ?></td>
          <td><?= $m && $m['class_test'] !== null ? $m['class_test'] : '—' ?></td>
          <td><?= $m && $m['mid'] !== null ? $m['mid'] : '—' ?></td>
          <td><?= $m && $m['attendance_mark'] !== null ? $m['attendance_mark'] : '—' ?></td>
          <td><?= $m && $m['viva'] !== null ? $m['viva'] : '—' ?></td>
          <td><?= $m && $m['lab'] !== null ? $m['lab'] : '—' ?></td>
          <td><?= $m && $m['presentation'] !== null ? $m['presentation'] : '—' ?></td>
          <td><?= $m && ($m['others_mark'] ?? null) !== null ? $m['others_mark'] : '—' ?></td>
          <td><strong><?= $m && $m['obtained_marks'] !== null ? $m['obtained_marks'] : '—' ?></strong></td>
          <td><?= $m && !empty($m['remarks']) ? htmlspecialchars($m['remarks']) : '—' ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
