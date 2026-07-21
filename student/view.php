<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
if (!isset($_SESSION['student'])) {
    header('Location: login.php');
    exit;
}

require '../db.php';

$student = $_SESSION['student'];
$student_id = (int)($_SESSION['student_id'] ?? (is_array($student) ? ($student['id'] ?? 0) : (int)$student));
$student_dept = (int)($_SESSION['student_department_id'] ?? (is_array($student) ? ($student['department_id'] ?? 0) : 0));

if ($student_dept === 0 && $student_id > 0) {
    $studentDeptStmt = $mysqli->prepare('SELECT department_id FROM users WHERE id = ?');
    $studentDeptStmt->bind_param('i', $student_id);
    $studentDeptStmt->execute();
    $studentDeptRes = $studentDeptStmt->get_result()->fetch_assoc();
    if ($studentDeptRes && isset($studentDeptRes['department_id'])) {
        $student_dept = (int)$studentDeptRes['department_id'];
        $_SESSION['student_department_id'] = $student_dept;
    }
}

$dept = (int)($_GET['dept'] ?? 0);
// Default to the student's own department on first visit, but let them
// freely switch to any department afterwards via the search form.
if (!isset($_GET['dept']) && $student_dept > 0) {
    $dept = $student_dept;
}

function material_label($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if ($ext === 'pdf') return 'PDF';
    if (in_array($ext, ['doc', 'docx'], true)) return 'DOC';
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) return 'IMAGE';

    return strtoupper($ext ?: 'FILE');
}

$msg = '';
$status = '';

if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $material_id = intval($_GET['id']);
    if ($material_id > 0 && in_array($action, ['bookmark', 'unbookmark'], true)) {
        if ($action === 'bookmark') {
            $stmt = $mysqli->prepare('INSERT IGNORE INTO material_bookmarks (material_id, student_id) VALUES (?, ?)');
            $stmt->bind_param('ii', $material_id, $student_id);
            $stmt->execute();
            $status = 'Material bookmarked.';
        } else {
            $stmt = $mysqli->prepare('DELETE FROM material_bookmarks WHERE material_id = ? AND student_id = ?');
            $stmt->bind_param('ii', $material_id, $student_id);
            $stmt->execute();
            $status = 'Bookmark removed.';
        }
    }
    $query = http_build_query(['q' => $_GET['q'] ?? '', 'dept' => $dept, 'limit' => $_GET['limit'] ?? 20, 'page' => $_GET['page'] ?? 1]);
    header('Location: view.php?' . $query . '&status=' . urlencode($status));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_material'])) {
    $material_id = intval($_POST['material_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);

    if ($material_id > 0 && $rating >= 1 && $rating <= 5) {
        $stmt = $mysqli->prepare('INSERT INTO material_ratings (material_id, student_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), created_at = NOW()');
        $stmt->bind_param('iii', $material_id, $student_id, $rating);
        $stmt->execute();
        $status = 'Rating saved.';
    } else {
        $status = 'Invalid rating provided.';
    }
    $query = http_build_query(['q' => $_GET['q'] ?? '', 'dept' => $dept, 'limit' => $_GET['limit'] ?? 20, 'page' => $_GET['page'] ?? 1]);
    header('Location: view.php?' . $query . '&status=' . urlencode($status));
    exit;
}

$q = trim($_GET['q'] ?? '');
$limit = (int)($_GET['limit'] ?? 20);

if (!in_array($limit, [10, 20, 50], true)) {
    $limit = 20;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$search = '%' . $q . '%';

$where = 'WHERE (m.title LIKE ? OR m.course_name LIKE ? OR m.semester LIKE ?)';
$params = [$search, $search, $search];
$types = 'sss';

if ($dept > 0) {
    $where .= ' AND m.department_id = ?';
    $params[] = $dept;
    $types .= 'i';
}

$countSql = 'SELECT COUNT(*) FROM materials m ' . $where;
$countStmt = $mysqli->prepare($countSql);
$countRefs = [&$types];
foreach ($params as $key => $value) {
    $countRefs[] = &$params[$key];
}
call_user_func_array([$countStmt, 'bind_param'], $countRefs);
$countStmt->execute();
$countStmt->bind_result($total_items);
$countStmt->fetch();
$countStmt->close();

$total_pages = max(1, (int)ceil($total_items / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

$listSql = 'SELECT m.*, u.name AS teacher, (SELECT IFNULL(ROUND(AVG(r.rating), 1), 0) FROM material_ratings r WHERE r.material_id = m.id) AS avg_rating, (SELECT COUNT(*) FROM material_ratings r WHERE r.material_id = m.id) AS ratings_count, (SELECT rating FROM material_ratings r WHERE r.material_id = m.id AND r.student_id = ' . $student_id . ') AS user_rating, EXISTS(SELECT 1 FROM material_bookmarks b WHERE b.material_id = m.id AND b.student_id = ' . $student_id . ') AS bookmarked FROM materials m LEFT JOIN users u ON m.uploaded_by = u.id ' . $where . ' ORDER BY m.id DESC LIMIT ? OFFSET ?';
$listTypes = $types . 'ii';
$listParams = $params;
$listParams[] = $limit;
$listParams[] = $offset;

$listStmt = $mysqli->prepare($listSql);
$listRefs = [&$listTypes];
foreach ($listParams as $key => $value) {
    $listRefs[] = &$listParams[$key];
}
call_user_func_array([$listStmt, 'bind_param'], $listRefs);
$listStmt->execute();
$res = $listStmt->get_result();

$depts = $mysqli->query('SELECT * FROM departments');
$department_list = [];
while ($d = $depts->fetch_assoc()) {
    $department_list[] = $d;
}

$student_department_name = '';
foreach ($department_list as $dept_item) {
    if ((int)$dept_item['id'] === $student_dept) {
        $student_department_name = (string)$dept_item['name'];
        break;
    }
}

$statusMessage = trim($_GET['status'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Study Materials</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_student.php'; ?>
  <main class="main-content">
    <div class="page-title">
      <h1>Study Materials</h1>
      <p>Browse and download course materials for your department.</p>
    </div>

    <?php if (!empty($statusMessage)): ?>
      <div class="alert-message success"><?= htmlspecialchars($statusMessage) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-header">
        <h3>Search Materials</h3>
      </div>
      <div class="panel-body">
        <form method="get" class="toolbar-grid">
          <div class="toolbar-group">
            <label for="q">Search</label>
            <input id="q" name="q" placeholder="Search by title or course" value="<?= htmlspecialchars($q) ?>">
          </div>
          <div class="toolbar-group">
            <label for="dept">Department</label>
            <select id="dept" name="dept">
              <option value="0">All departments</option>
              <?php foreach ($department_list as $d): ?>
              <option value="<?= (int)$d['id'] ?>" <?= $dept === (int)$d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="toolbar-group">
            <label for="limit">Per page</label>
            <select id="limit" name="limit">
              <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
              <option value="20" <?= $limit === 20 ? 'selected' : '' ?>>20</option>
              <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
            </select>
          </div>
          <div class="toolbar-group toolbar-actions">
            <label>&nbsp;</label>
            <div class="action-row" style="gap:8px;flex-wrap:wrap;">
              <button class="btn btn-primary" type="submit">Apply</button>
              <a class="btn btn-ghost" href="view.php">Reset</a>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <h3>Available Materials</h3>
        <span class="badge badge-blue"><?= (int)$total_items ?> result(s)</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Material</th>
              <th>Course</th>
              <th>Semester</th>
              <th>Teacher</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($total_items === 0): ?>
            <tr><td colspan="6" style="text-align:center;padding:28px;color:#6b7280">No materials match your search.</td></tr>
            <?php else: while ($m = $res->fetch_assoc()):
              $is_bookmarked = (int)$m['bookmarked'];
            ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <img src="../uploads/<?= rawurlencode($m['cover_image'] ?: '') ?>"
                       alt=""
                       style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--border);flex-shrink:0;background:#1e293b;"
                       onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect width=%2248%22 height=%2248%22 rx=%228%22 fill=%22%23334155%22/%3E%3Ctext x=%2224%22 y=%2229%22 font-size=%2210%22 fill=%22%23cbd5e1%22 text-anchor=%22middle%22 font-family=%22sans-serif%22%3EFILE%3C/text%3E%3C/svg%3E';">
                  <div>
                    <strong><?= htmlspecialchars($m['title']) ?></strong><br>
                    <span class="badge badge-gray"><?= htmlspecialchars($m['filename']) ?></span>
                  </div>
                </div>
              </td>
              <td><?= htmlspecialchars($m['course_name'] ?: 'General') ?></td>
              <td><?= htmlspecialchars($m['semester']) ?></td>
              <td><?= htmlspecialchars($m['teacher'] ?: 'Unknown') ?></td>
              <td>
                <span class="badge <?= $is_bookmarked ? 'badge-green' : 'badge-yellow' ?>"><?= $is_bookmarked ? 'Bookmarked' : 'Available' ?></span><br>
                <span class="badge badge-gray">Rating <?= htmlspecialchars($m['avg_rating']) ?>/5</span>
              </td>
              <td>
                <div class="action-group" style="justify-content:flex-start;gap:8px;flex-wrap:wrap;">
                  <a class="btn btn-sm btn-primary" href="preview.php?id=<?= (int)$m['id'] ?>">Preview</a>
                  <a class="btn btn-sm btn-ghost" href="download.php?id=<?= (int)$m['id'] ?>">Download</a>
                  <?php if ($is_bookmarked): ?>
                    <a class="btn btn-sm btn-danger" href="view.php?action=unbookmark&id=<?= (int)$m['id'] ?>&q=<?= urlencode($q) ?>&dept=<?= (int)$dept ?>&limit=<?= (int)$limit ?>&page=<?= (int)$page ?>">Remove</a>
                  <?php else: ?>
                    <a class="btn btn-sm btn-success" href="view.php?action=bookmark&id=<?= (int)$m['id'] ?>&q=<?= urlencode($q) ?>&dept=<?= (int)$dept ?>&limit=<?= (int)$limit ?>&page=<?= (int)$page ?>">Bookmark</a>
                  <?php endif; ?>
                </div>
                <form method="post" class="rating-form" style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                  <input type="hidden" name="rate_material" value="1">
                  <input type="hidden" name="material_id" value="<?= (int)$m['id'] ?>">
                  <div class="star-rating" title="Rate this material">
                    <?php for ($i = 5; $i >= 1; $i--):
                      $star_id = 'star' . $i . '_' . (int)$m['id'];
                      $checked = isset($m['user_rating']) && (int)$m['user_rating'] === $i;
                    ?>
                    <input type="radio" id="<?= $star_id ?>" name="rating" value="<?= $i ?>" <?= $checked ? 'checked' : '' ?> <?= $i === 5 ? 'required' : '' ?>>
                    <label for="<?= $star_id ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">&#9733;</label>
                    <?php endfor; ?>
                  </div>
                  <button type="submit" class="btn btn-sm btn-primary">Save</button>
                </form>
              </td>
            </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($total_items > 0):
        $prev_page = max(1, $page - 1);
        $next_page = min($total_pages, $page + 1);
        $prev_query = http_build_query(['q' => $q, 'dept' => $dept, 'limit' => $limit, 'page' => $prev_page]);
        $next_query = http_build_query(['q' => $q, 'dept' => $dept, 'limit' => $limit, 'page' => $next_page]);
        $is_first_page = $page === 1;
        $is_last_page = $page === $total_pages;
      ?>
      <div class="pagination-wrap">
        <?php if ($is_first_page): ?>
          <span class="ghost-button disabled" aria-disabled="true">Previous</span>
        <?php else: ?>
          <a class="ghost-button" href="view.php?<?= $prev_query ?>">Previous</a>
        <?php endif; ?>

        <span class="page-chip">Page <?= (int)$page ?> of <?= (int)$total_pages ?></span>

        <?php if ($is_last_page): ?>
          <span class="ghost-button disabled" aria-disabled="true">Next</span>
        <?php else: ?>
          <a class="ghost-button" href="view.php?<?= $next_query ?>">Next</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
