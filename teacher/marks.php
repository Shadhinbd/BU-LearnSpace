<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require '../db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') { header('Location: login.php'); exit; }

// Safely make sure the "others_mark" column exists (checks first, only
// alters if missing — works on all MySQL/MariaDB versions).
ensure_column($mysqli, 'assessment_marks', 'others_mark', 'DECIMAL(6,2) DEFAULT 0');

$tid = (int)$_SESSION['user']['id'];
$msg = ''; $msg_type = 'success';

if (!empty($_SESSION['marks_flash'])) {
    $msg = $_SESSION['marks_flash']['text'];
    $msg_type = $_SESSION['marks_flash']['type'];
    unset($_SESSION['marks_flash']);
}

// Create assessment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assessment'])) {
    $title = trim($_POST['title'] ?? '');
    $course = trim($_POST['course_name'] ?? '');
    $course_code = trim($_POST['course_code'] ?? '');
    $sem = trim($_POST['semester'] ?? '');
    $dept = (int)($_POST['department_id'] ?? 0);
    $batch = trim($_POST['batch'] ?? '');
    $total = (float)($_POST['total_marks'] ?? 0);
    if ($title && $course && $course_code && $sem && $dept && $batch && $total > 0) {
        $stmt = $mysqli->prepare("INSERT INTO assessments (title, course_name, course_code, semester, department_id, batch, teacher_id, total_marks) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sssssiid', $title, $course, $course_code, $sem, $dept, $batch, $tid, $total);
        $stmt->execute();
        $msg = 'Assessment created!';
    } else {
        $msg = 'Please fill in all required fields with a valid total marks value.';
        $msg_type = 'error';
    }
}

// Save mark
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mark'])) {
    $assessment_id = (int)($_POST['assessment_id'] ?? 0);
    $student_id = (int)($_POST['student_id'] ?? 0);

    // Verify the assessment belongs to this teacher and get its marks cap.
    $aCheck = $mysqli->prepare("SELECT total_marks FROM assessments WHERE id=? AND teacher_id=?");
    $aCheck->bind_param('ii', $assessment_id, $tid);
    $aCheck->execute();
    $aRow = $aCheck->get_result()->fetch_assoc();

    if (!$aRow) {
        $msg = 'Invalid assessment.';
        $msg_type = 'error';
    } else {
        $total_marks_cap = (float)$aRow['total_marks'];

        // "Class Test 1/2/3" are stored in the existing class_test / mid / final
        // columns respectively — no risky schema change required for these.
        $class_test_1 = (float)($_POST['class_test_1'] ?? 0);
        $class_test_2 = (float)($_POST['class_test_2'] ?? 0);
        $attendance_mark = (float)($_POST['attendance_mark'] ?? 0);
        $viva = (float)($_POST['viva'] ?? 0);
        $lab = (float)($_POST['lab'] ?? 0);
        $presentation = (float)($_POST['presentation'] ?? 0);
        $others = (float)($_POST['others_mark'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        $obtained = $class_test_1 + $class_test_2 + $attendance_mark + $viva + $lab + $presentation + $others;
        $obtained = round($obtained, 2);

        if ($obtained > $total_marks_cap) {
            $msg = "Total entered marks ($obtained) exceed this assessment's total marks ($total_marks_cap). Please reduce one or more fields.";
            $msg_type = 'error';
        } else {
            $stmt = $mysqli->prepare("INSERT INTO assessment_marks (assessment_id, student_id, obtained_marks, class_test, attendance_mark, viva, lab, presentation, mid, others_mark, remarks) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE obtained_marks=VALUES(obtained_marks), class_test=VALUES(class_test), attendance_mark=VALUES(attendance_mark), viva=VALUES(viva), lab=VALUES(lab), presentation=VALUES(presentation), mid=VALUES(mid), others_mark=VALUES(others_mark), remarks=VALUES(remarks)");
            $stmt->bind_param('iidddddddds', $assessment_id, $student_id, $obtained, $class_test_1, $attendance_mark, $viva, $lab, $presentation, $class_test_2, $others, $remarks);
            $stmt->execute();
            $msg = 'Mark saved!';
        }
    }
}

// Delete an entire assessment (and all of its student marks)
if (isset($_GET['delete_assessment'])) {
    $del_id = (int)$_GET['delete_assessment'];
    $check = $mysqli->prepare("SELECT id FROM assessments WHERE id=? AND teacher_id=?");
    $check->bind_param('ii', $del_id, $tid);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        // Explicit cleanup first, in case the "ON DELETE CASCADE" constraint
        // isn't present on this particular database.
        $delMarks = $mysqli->prepare("DELETE FROM assessment_marks WHERE assessment_id=?");
        $delMarks->bind_param('i', $del_id);
        $delMarks->execute();

        $delAssessment = $mysqli->prepare("DELETE FROM assessments WHERE id=? AND teacher_id=?");
        $delAssessment->bind_param('ii', $del_id, $tid);
        $delAssessment->execute();

        $msg = 'Assessment deleted.';
    } else {
        $msg = 'Unable to delete that assessment.'; $msg_type = 'error';
    }
    $_SESSION['marks_flash'] = ['text' => $msg, 'type' => $msg_type];
    header('Location: marks.php?deleted_assessment=1');
    exit;
}

// Delete a single student's mark entry for an assessment
if (isset($_GET['delete_mark'])) {
    $del_assessment_id = (int)($_GET['assessment_id'] ?? 0);
    $del_student_id = (int)$_GET['delete_mark'];

    // Ownership check: the assessment must belong to this teacher.
    $check = $mysqli->prepare("SELECT id FROM assessments WHERE id=? AND teacher_id=?");
    $check->bind_param('ii', $del_assessment_id, $tid);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $delMark = $mysqli->prepare("DELETE FROM assessment_marks WHERE assessment_id=? AND student_id=?");
        $delMark->bind_param('ii', $del_assessment_id, $del_student_id);
        $delMark->execute();
        $msg = "Student's mark entry deleted.";
    } else {
        $msg = 'Unable to delete that mark entry.'; $msg_type = 'error';
    }
    $_SESSION['marks_flash'] = ['text' => $msg, 'type' => $msg_type];
    header('Location: marks.php?assessment_id=' . $del_assessment_id . '&deleted_mark=1');
    exit;
}

$depts = $mysqli->query("SELECT * FROM departments");

$allowed_per_page = [10, 20, 50];
$per_page = (int)($_GET['per_page'] ?? 10);
if (!in_array($per_page, $allowed_per_page, true)) { $per_page = 10; }

$assessments_page = max(1, (int)($_GET['assessments_page'] ?? 1));
$countRes = $mysqli->query("SELECT COUNT(*) AS total FROM assessments WHERE teacher_id=$tid");
$assessments_total = (int)$countRes->fetch_assoc()['total'];
$assessments_total_pages = max(1, (int)ceil($assessments_total / $per_page));
$assessments_page = min($assessments_page, $assessments_total_pages);
$assessments_offset = ($assessments_page - 1) * $per_page;

$assessments = $mysqli->query("SELECT a.*, d.name as dept FROM assessments a LEFT JOIN departments d ON a.department_id=d.id WHERE a.teacher_id=$tid ORDER BY a.created_at DESC LIMIT $per_page OFFSET $assessments_offset");

$selected_assessment = (int)($_GET['assessment_id'] ?? 0);
$students = []; $marks_data = [];
$allowed_students_per_page = [20, 50, 100];
$students_per_page = (int)($_GET['students_per_page'] ?? 20);
if (!in_array($students_per_page, $allowed_students_per_page, true)) { $students_per_page = 20; }
$students_page = max(1, (int)($_GET['students_page'] ?? 1));
$students_total = 0;
$students_total_pages = 1;

if ($selected_assessment > 0) {
    $aInfo = $mysqli->query("SELECT * FROM assessments WHERE id=$selected_assessment AND teacher_id=$tid")->fetch_assoc();
    if ($aInfo) {
        $dept_id = (int)$aInfo['department_id'];
        $batch = $aInfo['batch'] ?? '';
        $batch_sql = $batch !== '' ? " AND batch='" . $mysqli->real_escape_string($batch) . "'" : '';

        $countRes = $mysqli->query("SELECT COUNT(*) AS total FROM users WHERE role='student' AND department_id=$dept_id" . $batch_sql);
        $students_total = (int)$countRes->fetch_assoc()['total'];
        $students_total_pages = max(1, (int)ceil($students_total / $students_per_page));
        $students_page = min($students_page, $students_total_pages);
        $students_offset = ($students_page - 1) * $students_per_page;

        $query = "SELECT id, name, email FROM users WHERE role='student' AND department_id=$dept_id" . $batch_sql;
        $query .= " ORDER BY name LIMIT $students_per_page OFFSET $students_offset";
        $sRes = $mysqli->query($query);
        while ($s = $sRes->fetch_assoc()) $students[] = $s;
        $mRes = $mysqli->query("SELECT * FROM assessment_marks WHERE assessment_id=$selected_assessment");
        while ($m = $mRes->fetch_assoc()) $marks_data[$m['student_id']] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Assessment & Marks - BU LearnSpace</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_teacher.php'; ?>
  <main class="main-content">
    <div class="page-title">
      <h1>Assessment & Marks</h1>
      <p>Create assessments and add marks for each student</p>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msg_type === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-header"><h3>Create New Assessment</h3></div>
      <div class="panel-body">
        <form method="post">
          <input type="hidden" name="create_assessment" value="1">
          <div class="form-row">
            <div class="form-group">
              <label>Assessment Title *</label>
              <input name="title" placeholder="e.g. Mid Term Exam" required>
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
                <option value="">Select</option>
                <?php $depts->data_seek(0); while ($d = $depts->fetch_assoc()): ?>
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
              <label>Course Code *</label>
              <input name="course_code" placeholder="e.g. CSE301" required>
            </div>
            <div class="form-group">
              <label>Batch *</label>
              <input name="batch" placeholder="e.g. 2024" required>
            </div>
          </div>
          <div class="form-group" style="max-width:220px">
            <label>Total Marks *</label>
            <input type="number" name="total_marks" step="0.5" min="1" value="40" required>
            <small style="color:#6b7280">The maximum combined marks a student can receive for this assessment.</small>
          </div>
          <button type="submit" class="btn btn-primary">Create Assessment</button>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h3>Your Assessments</h3>
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
            <tr><th>Title</th><th>Course</th><th>Department</th><th>Semester</th><th>Batch</th><th>Total Marks</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php $assessments->data_seek(0); if ($assessments->num_rows === 0): ?>
            <tr><td colspan="7" style="text-align:center;padding:24px;color:#6b7280">No assessments created yet.</td></tr>
            <?php else: while ($a = $assessments->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($a['title']) ?></td>
              <td><?= htmlspecialchars($a['course_name']) ?></td>
              <td><span class="badge badge-blue"><?= htmlspecialchars($a['dept']) ?></span></td>
              <td><?= htmlspecialchars($a['semester']) ?></td>
              <td><?= htmlspecialchars($a['batch'] ?? '—') ?></td>
              <td><?= htmlspecialchars($a['total_marks']) ?></td>
              <td>
                <div class="action-group" style="justify-content:flex-start;gap:8px;flex-wrap:wrap;">
                  <a class="btn btn-sm btn-primary" href="marks.php?assessment_id=<?= (int)$a['id'] ?>">Enter Marks</a>
                  <a class="btn btn-sm btn-ghost" href="print_assessment.php?id=<?= (int)$a['id'] ?>" target="_blank">Print</a>
                  <a class="btn btn-sm btn-danger" href="marks.php?delete_assessment=<?= (int)$a['id'] ?>" onclick="return confirm('Delete this assessment and all of its saved student marks? This cannot be undone.');">Delete</a>
                </div>
              </td>
            </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($assessments_total > 0):
        $a_prev = max(1, $assessments_page - 1);
        $a_next = min($assessments_total_pages, $assessments_page + 1);
        $a_is_first = $assessments_page === 1;
        $a_is_last = $assessments_page === $assessments_total_pages;
      ?>
      <div class="pagination-wrap">
        <?php if ($a_is_first): ?>
          <span class="ghost-button disabled" aria-disabled="true">Previous</span>
        <?php else: ?>
          <a class="ghost-button" href="marks.php?per_page=<?= $per_page ?>&assessments_page=<?= $a_prev ?>">Previous</a>
        <?php endif; ?>

        <span class="page-chip">Page <?= (int)$assessments_page ?> of <?= (int)$assessments_total_pages ?></span>

        <?php if ($a_is_last): ?>
          <span class="ghost-button disabled" aria-disabled="true">Next</span>
        <?php else: ?>
          <a class="ghost-button" href="marks.php?per_page=<?= $per_page ?>&assessments_page=<?= $a_next ?>">Next</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($selected_assessment > 0 && isset($aInfo) && $aInfo): ?>
    <div class="panel">
      <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h3>Enter Marks: <?= htmlspecialchars($aInfo['title']) ?> (Out of <?= $aInfo['total_marks'] ?>)</h3>
        <form method="get" style="display:flex;align-items:center;gap:8px;">
          <input type="hidden" name="assessment_id" value="<?= $selected_assessment ?>">
          <label style="font-size:13px;color:#94a3b8;">Show</label>
          <select name="students_per_page" onchange="this.form.submit()" style="padding:5px 8px;">
            <?php foreach ($allowed_students_per_page as $spp): ?>
              <option value="<?= $spp ?>" <?= $students_per_page === $spp ? 'selected' : '' ?>><?= $spp ?></option>
            <?php endforeach; ?>
          </select>
          <span style="font-size:13px;color:#94a3b8;">per page</span>
        </form>
      </div>
      <div class="panel-body">
        <?php if (empty($students)): ?>
        <div class="empty-state"><p>No students found in this department.</p></div>
        <?php else: ?>
        <table>
          <thead>
            <tr><th>Student</th><th>Marks Entry</th></tr>
          </thead>
          <tbody>
            <?php foreach ($students as $s):
              $existing = $marks_data[$s['id']] ?? null;
            ?>
            <tr>
              <td>
                <div style="font-weight:600"><?= htmlspecialchars($s['name']) ?></div>
                <div style="font-size:12px;color:#6b7280"><?= htmlspecialchars($s['email']) ?></div>
              </td>
              <td>
                <form method="post" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
                  <input type="hidden" name="save_mark" value="1">
                  <input type="hidden" name="assessment_id" value="<?= $selected_assessment ?>">
                  <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                  <div>
                    <label style="display:block;font-size:11px;color:#6b7280;margin-bottom:4px;">Class Test 1</label>
                    <input type="number" step="0.5" min="0" name="class_test_1" value="<?= $existing ? $existing['class_test'] : '' ?>" style="width:70px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px">
                  </div>
                  <div>
                    <label style="display:block;font-size:11px;color:#6b7280;margin-bottom:4px;">Class Test 2</label>
                    <input type="number" step="0.5" min="0" name="class_test_2" value="<?= $existing ? $existing['mid'] : '' ?>" style="width:70px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px">
                  </div>
                  <div>
                    <label style="display:block;font-size:11px;color:#6b7280;margin-bottom:4px;">Attendance</label>
                    <input type="number" step="0.5" min="0" name="attendance_mark" value="<?= $existing ? $existing['attendance_mark'] : '' ?>" style="width:70px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px">
                  </div>
                  <div>
                    <label style="display:block;font-size:11px;color:#6b7280;margin-bottom:4px;">Viva</label>
                    <input type="number" step="0.5" min="0" name="viva" value="<?= $existing ? $existing['viva'] : '' ?>" style="width:70px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px">
                  </div>
                  <div>
                    <label style="display:block;font-size:11px;color:#6b7280;margin-bottom:4px;">Assignment</label>
                    <input type="number" step="0.5" min="0" name="lab" value="<?= $existing ? $existing['lab'] : '' ?>" style="width:70px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px">
                  </div>
                  <div>
                    <label style="display:block;font-size:11px;color:#6b7280;margin-bottom:4px;">Presentation</label>
                    <input type="number" step="0.5" min="0" name="presentation" value="<?= $existing ? $existing['presentation'] : '' ?>" style="width:70px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px">
                  </div>
                  <div>
                    <label style="display:block;font-size:11px;color:#6b7280;margin-bottom:4px;">Others</label>
                    <input type="number" step="0.5" min="0" name="others_mark" value="<?= $existing ? ($existing['others_mark'] ?? '') : '' ?>" style="width:70px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px">
                  </div>
                  <div>
                    <label style="display:block;font-size:11px;color:#6b7280;margin-bottom:4px;">Remarks</label>
                    <input type="text" name="remarks" value="<?= htmlspecialchars($existing['remarks'] ?? '') ?>" placeholder="Optional" style="width:130px;padding:6px 8px;border:1px solid #d1d5db;border-radius:6px">
                  </div>
                  <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;max-width:200px;">
                    <button type="submit" class="btn btn-success btn-sm">Save</button>
                    <?php if ($existing): ?>
                    <span class="badge badge-green">Saved</span>
                    <a class="btn btn-sm btn-danger"
                       href="marks.php?delete_mark=<?= (int)$s['id'] ?>&assessment_id=<?= $selected_assessment ?>"
                       onclick="return confirm('Delete <?= htmlspecialchars(addslashes($s['name'])) ?>&#39;s mark entry for this assessment?');">Delete Entry</a>
                    <?php endif; ?>
                  </div>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($students_total > $students_per_page):
          $sp_prev = max(1, $students_page - 1);
          $sp_next = min($students_total_pages, $students_page + 1);
          $sp_is_first = $students_page === 1;
          $sp_is_last = $students_page === $students_total_pages;
        ?>
        <div class="pagination-wrap">
          <?php if ($sp_is_first): ?>
            <span class="ghost-button disabled" aria-disabled="true">Previous</span>
          <?php else: ?>
            <a class="ghost-button" href="marks.php?assessment_id=<?= $selected_assessment ?>&students_per_page=<?= $students_per_page ?>&students_page=<?= $sp_prev ?>">Previous</a>
          <?php endif; ?>

          <span class="page-chip">Page <?= (int)$students_page ?> of <?= (int)$students_total_pages ?></span>

          <?php if ($sp_is_last): ?>
            <span class="ghost-button disabled" aria-disabled="true">Next</span>
          <?php else: ?>
            <a class="ghost-button" href="marks.php?assessment_id=<?= $selected_assessment ?>&students_per_page=<?= $students_per_page ?>&students_page=<?= $sp_next ?>">Next</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
