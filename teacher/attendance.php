<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require '../db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') { header('Location: login.php'); exit; }

$tid = (int)$_SESSION['user']['id'];
$msg = '';
$msg_type = 'success';

if (!empty($_SESSION['attendance_flash'])) {
    $msg = $_SESSION['attendance_flash']['text'];
    $msg_type = $_SESSION['attendance_flash']['type'];
    unset($_SESSION['attendance_flash']);
}

// Delete an attendance session (and all of its per-student records)
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $check = $mysqli->prepare("SELECT id FROM attendance WHERE id=? AND teacher_id=?");
    $check->bind_param('ii', $del_id, $tid);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        // Explicit cleanup first, in case "ON DELETE CASCADE" isn't present
        // on this particular database.
        $delRecords = $mysqli->prepare("DELETE FROM attendance_records WHERE attendance_id=?");
        $delRecords->bind_param('i', $del_id);
        $delRecords->execute();

        $delSession = $mysqli->prepare("DELETE FROM attendance WHERE id=? AND teacher_id=?");
        $delSession->bind_param('ii', $del_id, $tid);
        $delSession->execute();

        $_SESSION['attendance_flash'] = ['text' => 'Attendance record deleted.', 'type' => 'success'];
    } else {
        $_SESSION['attendance_flash'] = ['text' => 'Unable to delete that attendance record.', 'type' => 'error'];
    }
    header('Location: attendance.php?deleted=1');
    exit;
}

// Create attendance session + save records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['take_attendance'])) {
    $course = trim($_POST['course_name'] ?? '');
    $sem = trim($_POST['semester'] ?? '');
    $dept = (int)($_POST['department_id'] ?? 0);
    $batch = trim($_POST['batch'] ?? '');
    $date = trim($_POST['class_date'] ?? '');
    $statuses = $_POST['status'] ?? [];

    if ($course && $sem && $dept && $batch && $date) {
        $stmt = $mysqli->prepare("INSERT INTO attendance (course_name, semester, department_id, batch, teacher_id, class_date) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssssis', $course, $sem, $dept, $batch, $tid, $date);
        $stmt->execute();
        $att_id = $mysqli->insert_id;

        foreach ($statuses as $stu_id => $status) {
            $stu_id = (int)$stu_id;
            $status = in_array($status, ['present','absent','late']) ? $status : 'absent';
            $s2 = $mysqli->prepare("INSERT INTO attendance_records (attendance_id, student_id, status) VALUES (?,?,?)");
            $s2->bind_param('iis', $att_id, $stu_id, $status);
            $s2->execute();
        }
        $msg = 'Attendance saved for ' . date('d M Y', strtotime($date)) . '!';
    }
}

$depts = $mysqli->query("SELECT * FROM departments");
$selected_dept = (int)($_GET['dept'] ?? 0);
$selected_batch = trim($_GET['batch'] ?? '');
$selected_date = trim($_GET['class_date'] ?? date('Y-m-d'));
$students = [];
if ($selected_dept > 0 && $selected_batch !== '') {
    $sRes = $mysqli->query("SELECT id, name FROM users WHERE role='student' AND department_id=$selected_dept AND batch='$selected_batch' ORDER BY name");
    while ($s = $sRes->fetch_assoc()) $students[] = $s;
}

// Past attendance sessions (paginated)
$allowed_per_page = [10, 20, 50];
$per_page = (int)($_GET['per_page'] ?? 20);
if (!in_array($per_page, $allowed_per_page, true)) { $per_page = 20; }

$history_page = max(1, (int)($_GET['history_page'] ?? 1));

$countRes = $mysqli->query("SELECT COUNT(*) AS total FROM attendance WHERE teacher_id=$tid");
$history_total = (int)$countRes->fetch_assoc()['total'];
$history_total_pages = max(1, (int)ceil($history_total / $per_page));
$history_page = min($history_page, $history_total_pages);
$history_offset = ($history_page - 1) * $per_page;

$history = $mysqli->query("SELECT a.*, d.name as dept, (SELECT COUNT(*) FROM attendance_records ar WHERE ar.attendance_id=a.id AND ar.status='present') as present_count, (SELECT COUNT(*) FROM attendance_records ar WHERE ar.attendance_id=a.id) as total_count FROM attendance a LEFT JOIN departments d ON a.department_id=d.id WHERE a.teacher_id=$tid ORDER BY a.class_date DESC LIMIT $per_page OFFSET $history_offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance - BU LearnSpace</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_teacher.php'; ?>
  <main class="main-content">
    <div class="page-title">
      <h1>Attendance Management</h1>
      <p>Take and track student attendance by class session</p>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-header"><h3>Select Department to Load Students</h3></div>
      <div class="panel-body">
        <form method="get" class="toolbar-grid" style="gap:12px;">
          <div class="form-group" style="margin:0;min-width:200px">
            <label>Department</label>
            <select name="dept">
              <option value="">Select Department</option>
              <?php $depts->data_seek(0); while ($d = $depts->fetch_assoc()): ?>
              <option value="<?= $d['id'] ?>" <?= $selected_dept===$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group" style="margin:0;min-width:150px">
            <label>Batch</label>
            <input type="text" name="batch" value="<?= htmlspecialchars($selected_batch) ?>" placeholder="e.g. 2024">
          </div>
          <div class="form-group" style="margin:0;min-width:180px">
            <label>Date</label>
            <input type="date" name="class_date" value="<?= htmlspecialchars($selected_date) ?>">
          </div>
          <button type="submit" class="btn btn-primary">Load Students</button>
        </form>
      </div>
    </div>

    <?php if ($selected_dept > 0 && $selected_batch !== '' && !empty($students)): ?>
    <div class="panel">
      <div class="panel-header"><h3>Take Attendance</h3></div>
      <div class="panel-body">
        <form method="post">
          <input type="hidden" name="take_attendance" value="1">
          <input type="hidden" name="department_id" value="<?= $selected_dept ?>">
          <input type="hidden" name="batch" value="<?= htmlspecialchars($selected_batch) ?>">
          <div class="form-row">
            <div class="form-group">
              <label>Course Name *</label>
              <input name="course_name" placeholder="e.g. CSE301" required>
            </div>
            <div class="form-group">
              <label>Semester *</label>
              <input name="semester" placeholder="e.g. 3rd Semester" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group" style="max-width:220px">
              <label>Class Date *</label>
              <input type="date" name="class_date" value="<?= htmlspecialchars($selected_date) ?>" required>
            </div>
            <div class="form-group" style="max-width:220px">
              <label>Batch</label>
              <input type="text" name="batch_display" value="<?= htmlspecialchars($selected_batch) ?>" disabled>
            </div>
          </div>

          <table style="margin-top:12px">
            <thead>
              <tr><th>#</th><th>Student Name</th><th>Present</th><th>Late</th><th>Absent</th></tr>
            </thead>
            <tbody>
              <?php foreach ($students as $i => $s): ?>
              <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td style="text-align:center"><input type="radio" name="status[<?= $s['id'] ?>]" value="present" checked></td>
                <td style="text-align:center"><input type="radio" name="status[<?= $s['id'] ?>]" value="late"></td>
                <td style="text-align:center"><input type="radio" name="status[<?= $s['id'] ?>]" value="absent"></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <br>
          <button type="submit" class="btn btn-primary">Save Attendance</button>
        </form>
      </div>
    </div>
    <?php elseif ($selected_dept > 0 && $selected_batch !== ''): ?>
    <div class="panel"><div class="panel-body"><div class="empty-state"><p>No students found in this department.</p></div></div></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h3>Attendance History</h3>
        <form method="get" style="display:flex;align-items:center;gap:8px;">
          <label style="font-size:13px;color:#94a3b8;">Show</label>
          <select name="per_page" onchange="this.form.submit()" style="padding:5px 8px;">
            <?php foreach ($allowed_per_page as $pp): ?>
              <option value="<?= $pp ?>" <?= $per_page === $pp ? 'selected' : '' ?>><?= $pp ?></option>
            <?php endforeach; ?>
          </select>
          <span style="font-size:13px;color:#94a3b8;">per page</span>
        </form>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Date</th><th>Course</th><th>Department</th><th>Batch</th><th>Semester</th><th>Present</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if ($history->num_rows === 0): ?>
            <tr><td colspan="7" style="text-align:center;padding:32px;color:#6b7280">No attendance records yet.</td></tr>
            <?php else: while ($h = $history->fetch_assoc()): ?>
            <tr>
              <td><?= date('d M Y', strtotime($h['class_date'])) ?></td>
              <td><?= htmlspecialchars($h['course_name']) ?></td>
              <td><span class="badge badge-blue"><?= htmlspecialchars($h['dept']) ?></span></td>
              <td><span class="badge badge-gray"><?= htmlspecialchars($h['batch'] ?? '—') ?></span></td>
              <td><?= htmlspecialchars($h['semester']) ?></td>
              <td>
                <?php $pct = $h['total_count'] > 0 ? round(($h['present_count']/$h['total_count'])*100) : 0; ?>
                <span class="badge <?= $pct >= 70 ? 'badge-green' : 'badge-red' ?>">
                  <?= $h['present_count'] ?>/<?= $h['total_count'] ?> (<?= $pct ?>%)
                </span>
              </td>
              <td>
                <div class="action-group" style="justify-content:flex-start;gap:8px;flex-wrap:wrap;">
                  <a href="attendance_detail.php?id=<?= $h['id'] ?>" class="btn btn-ghost btn-sm">View</a>
                  <a href="attendance_detail.php?id=<?= $h['id'] ?>&edit=1" class="btn btn-primary btn-sm">Edit</a>
                  <a href="attendance.php?delete=<?= $h['id'] ?>&per_page=<?= $per_page ?>&history_page=<?= $history_page ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this attendance record and all student statuses for it? This cannot be undone.');">Delete</a>
                </div>
              </td>
            </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($history_total > 0):
        $h_prev = max(1, $history_page - 1);
        $h_next = min($history_total_pages, $history_page + 1);
        $h_is_first = $history_page === 1;
        $h_is_last = $history_page === $history_total_pages;
      ?>
      <div class="pagination-wrap">
        <?php if ($h_is_first): ?>
          <span class="ghost-button disabled" aria-disabled="true">Previous</span>
        <?php else: ?>
          <a class="ghost-button" href="attendance.php?per_page=<?= $per_page ?>&history_page=<?= $h_prev ?>">Previous</a>
        <?php endif; ?>

        <span class="page-chip">Page <?= (int)$history_page ?> of <?= (int)$history_total_pages ?></span>

        <?php if ($h_is_last): ?>
          <span class="ghost-button disabled" aria-disabled="true">Next</span>
        <?php else: ?>
          <a class="ghost-button" href="attendance.php?per_page=<?= $per_page ?>&history_page=<?= $h_next ?>">Next</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
