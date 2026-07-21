<?php
session_start();

// Prevent the browser from caching this protected page (fixes broken back/forward navigation between pages)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require '../db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

$teacher_id = (int)$_SESSION['user']['id'];
$msg = '';
$msg_type = 'success';

// Pick up a "flash" message left behind by a redirect (e.g. after a
// successful upload/edit), since PRG-style redirects happen before this
// page can render anything itself.
if (!empty($_SESSION['materials_flash'])) {
    $msg = $_SESSION['materials_flash']['text'];
    $msg_type = $_SESSION['materials_flash']['type'];
    unset($_SESSION['materials_flash']);
}

$uploadsAbsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsAbsDir)) {
    @mkdir($uploadsAbsDir, 0775, true);
}
// Try to self-heal permissions first (e.g. after a fresh deploy/extract
// resets them) before bothering an admin about it.
if (!is_writable($uploadsAbsDir)) {
    @chmod($uploadsAbsDir, 0775);
}
$uploads_writable = is_writable($uploadsAbsDir);

function cleanup_file($path) {
    if ($path && file_exists($path)) {
        @unlink($path);
    }
}

// File types students/teachers are allowed to upload as study material.
const ALLOWED_MATERIAL_EXTS = [
    'pdf',
    'doc', 'docx',
    'ppt', 'pptx',
    'xls', 'xlsx',
    'jpg', 'jpeg', 'png', 'gif', 'webp',
];

const MATERIAL_EXT_LABELS = [
    'pdf'  => 'PDF',
    'doc'  => 'DOC', 'docx' => 'DOCX',
    'ppt'  => 'PPT', 'pptx' => 'PPTX',
    'xls'  => 'XLS', 'xlsx' => 'XLSX',
    'jpg'  => 'IMG', 'jpeg' => 'IMG', 'png' => 'IMG', 'gif' => 'IMG', 'webp' => 'IMG',
];

// Accent color per file type, used only when we have to fall back to a
// generated placeholder cover (i.e. we can't render a real thumbnail).
const MATERIAL_EXT_COLORS = [
    'pdf'  => [231, 76, 60],
    'doc'  => [41, 98, 255], 'docx' => [41, 98, 255],
    'ppt'  => [230, 126, 34], 'pptx' => [230, 126, 34],
    'xls'  => [39, 174, 96], 'xlsx' => [39, 174, 96],
];

/**
 * Automatically derive a cover image for an uploaded material straight from
 * its content — no manual "cover image" upload needed.
 *  - Images are used as their own cover.
 *  - PDFs get a real first-page thumbnail when Imagick/Ghostscript is
 *    available on the server, otherwise a labeled placeholder.
 *  - Other document types (Word/PowerPoint/Excel) get a clean, color-coded
 *    placeholder card labeled with the file type.
 */
function auto_generate_cover($sourcePath, $ext, $uploadsDir) {
    $ext = strtolower($ext);
    $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (in_array($ext, $imageExts, true)) {
        return basename($sourcePath);
    }

    if ($ext === 'pdf' && class_exists('Imagick')) {
        try {
            $imagick = new Imagick();
            $imagick->setResolution(150, 150);
            $imagick->readImage($sourcePath . '[0]');
            $imagick->setImageFormat('png');
            $imagick->thumbnailImage(500, 0);
            $coverName = time() . '_cover_' . uniqid() . '.png';
            $imagick->writeImage($uploadsDir . '/' . $coverName);
            $imagick->clear();
            $imagick->destroy();
            return $coverName;
        } catch (\Throwable $e) {
            // Fall through to the generic placeholder below.
        }
    }

    return generate_placeholder_cover($ext, $uploadsDir);
}

/**
 * Renders a simple, modern color-coded "file type" card as SVG. This needs
 * no PHP image extensions (GD/Imagick), so it always works as a fallback
 * cover even on minimal hosting setups.
 */
function generate_placeholder_cover($ext, $uploadsDir) {
    $label = MATERIAL_EXT_LABELS[$ext] ?? strtoupper($ext ?: 'FILE');
    [$r, $g, $b] = MATERIAL_EXT_COLORS[$ext] ?? [71, 85, 105];
    $hex = sprintf('#%02x%02x%02x', $r, $g, $b);
    $darkHex = sprintf('#%02x%02x%02x', max($r - 30, 0), max($g - 30, 0), max($b - 30, 0));
    $safeLabel = htmlspecialchars($label, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="260" viewBox="0 0 400 260">
  <rect width="400" height="260" fill="{$hex}"/>
  <rect x="0" y="216" width="400" height="44" fill="{$darkHex}"/>
  <text x="200" y="118" font-family="Arial, sans-serif" font-size="42" font-weight="bold" fill="#ffffff" text-anchor="middle" dominant-baseline="middle">{$safeLabel}</text>
</svg>
SVG;

    $coverName = time() . '_cover_' . uniqid() . '.svg';
    file_put_contents($uploadsDir . '/' . $coverName, $svg);

    return $coverName;
}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $mysqli->prepare('SELECT filename, cover_image FROM materials WHERE id = ? AND uploaded_by = ?');
    $stmt->bind_param('ii', $delete_id, $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($material = $result->fetch_assoc()) {
        cleanup_file('../uploads/' . $material['filename']);
        if (!empty($material['cover_image'])) {
            cleanup_file('../uploads/' . $material['cover_image']);
        }
        $deleteStmt = $mysqli->prepare('DELETE FROM materials WHERE id = ? AND uploaded_by = ?');
        $deleteStmt->bind_param('ii', $delete_id, $teacher_id);
        $deleteStmt->execute();
        $msg = 'Material deleted successfully.';
    }
}

$edit_material = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $mysqli->prepare('SELECT * FROM materials WHERE id = ? AND uploaded_by = ?');
    $stmt->bind_param('ii', $edit_id, $teacher_id);
    $stmt->execute();
    $edit_material = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $material_id = intval($_POST['material_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $dept = intval($_POST['dept'] ?? 0);

    if ($title === '' || $course === '' || $semester === '' || $dept <= 0) {
        $msg = 'Please fill in all required fields.';
        $msg_type = 'error';
    } else {
        $pdf_name = '';
        $cover_name = '';

        if ($material_id > 0) {
            if (!empty($_FILES['file']['name'])) {
                $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ALLOWED_MATERIAL_EXTS, true)) {
                    $msg = 'Unsupported file type. Allowed: PDF, DOC(X), PPT(X), XLS(X), JPG, PNG, GIF, WEBP.';
                    $msg_type = 'error';
                } elseif (!$uploads_writable) {
                    $msg = uploads_permission_message($uploadsAbsDir);
                    $msg_type = 'error';
                } else {
                    $stmt = $mysqli->prepare('SELECT filename, cover_image FROM materials WHERE id = ? AND uploaded_by = ?');
                    $stmt->bind_param('ii', $material_id, $teacher_id);
                    $stmt->execute();
                    $old = $stmt->get_result()->fetch_assoc();

                    $new_pdf_name = time() . '_' . uniqid() . '.' . $ext;
                    $destPath = '../uploads/' . $new_pdf_name;

                    if (!@move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
                        $msg = 'Failed to save the uploaded file. Please check that the "uploads" folder is writable by the web server.';
                        $msg_type = 'error';
                    } else {
                        if ($old) {
                            cleanup_file('../uploads/' . $old['filename']);
                            if (!empty($old['cover_image']) && $old['cover_image'] !== 'no-cover.png') {
                                cleanup_file('../uploads/' . $old['cover_image']);
                            }
                        }
                        $pdf_name = $new_pdf_name;
                        // Cover image is derived automatically from the new file's content.
                        $cover_name = auto_generate_cover($destPath, $ext, '../uploads');
                    }
                }
            }

            if ($msg === '') {
                if ($pdf_name !== '' && $cover_name !== '') {
                    $stmt = $mysqli->prepare('UPDATE materials SET title = ?, course_name = ?, semester = ?, department_id = ?, filename = ?, cover_image = ? WHERE id = ? AND uploaded_by = ?');
                    $stmt->bind_param('sssisiii', $title, $course, $semester, $dept, $pdf_name, $cover_name, $material_id, $teacher_id);
                } elseif ($pdf_name !== '') {
                    $stmt = $mysqli->prepare('UPDATE materials SET title = ?, course_name = ?, semester = ?, department_id = ?, filename = ? WHERE id = ? AND uploaded_by = ?');
                    $stmt->bind_param('sssisii', $title, $course, $semester, $dept, $pdf_name, $material_id, $teacher_id);
                } elseif ($cover_name !== '') {
                    $stmt = $mysqli->prepare('UPDATE materials SET title = ?, course_name = ?, semester = ?, department_id = ?, cover_image = ? WHERE id = ? AND uploaded_by = ?');
                    $stmt->bind_param('sssiiii', $title, $course, $semester, $dept, $cover_name, $material_id, $teacher_id);
                } else {
                    $stmt = $mysqli->prepare('UPDATE materials SET title = ?, course_name = ?, semester = ?, department_id = ? WHERE id = ? AND uploaded_by = ?');
                    $stmt->bind_param('sssiii', $title, $course, $semester, $dept, $material_id, $teacher_id);
                }
                $stmt->execute();
                $_SESSION['materials_flash'] = ['text' => 'Material updated successfully.', 'type' => 'success'];
                header('Location: materials.php?edited=1');
                exit;
            }
        } else {
            if (empty($_FILES['file']['name'])) {
                $msg = 'Please upload a file.';
                $msg_type = 'error';
            } else {
                $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ALLOWED_MATERIAL_EXTS, true)) {
                    $msg = 'Unsupported file type. Allowed: PDF, DOC(X), PPT(X), XLS(X), JPG, PNG, GIF, WEBP.';
                    $msg_type = 'error';
                } elseif (!$uploads_writable) {
                    $msg = uploads_permission_message($uploadsAbsDir);
                    $msg_type = 'error';
                } else {
                    $new_pdf_name = time() . '_' . uniqid() . '.' . $ext;
                    $destPath = '../uploads/' . $new_pdf_name;
                    if (!@move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
                        $msg = 'Failed to save the uploaded file. Please check that the "uploads" folder is writable by the web server.';
                        $msg_type = 'error';
                    } else {
                        $pdf_name = $new_pdf_name;
                        // Cover image is derived automatically from the file's content.
                        $cover_name = auto_generate_cover($destPath, $ext, '../uploads');
                    }
                }
            }

            if ($msg === '') {
                $stmt = $mysqli->prepare('INSERT INTO materials (title, filename, cover_image, course_name, semester, department_id, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssssi', $title, $pdf_name, $cover_name, $course, $semester, $dept, $teacher_id);
                $stmt->execute();
                $_SESSION['materials_flash'] = ['text' => 'Material uploaded successfully.', 'type' => 'success'];
                header('Location: materials.php?added=1');
                exit;
            }
        }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$countResult = $mysqli->query("SELECT COUNT(*) AS total FROM materials WHERE uploaded_by = $teacher_id");
$total_items = (int)$countResult->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total_items / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

$materials = $mysqli->query("SELECT m.id, m.title, m.filename, m.cover_image, m.course_name, m.semester, m.department_id, m.uploaded_by, m.view_count, m.download_count, m.created_at, d.name AS department, IFNULL(ROUND(AVG(r.rating), 1), 0) AS avg_rating, COUNT(r.id) AS ratings_count FROM materials m LEFT JOIN material_ratings r ON r.material_id = m.id LEFT JOIN departments d ON m.department_id = d.id WHERE m.uploaded_by = $teacher_id GROUP BY m.id ORDER BY m.id DESC LIMIT $limit OFFSET $offset");
$depts = $mysqli->query('SELECT * FROM departments');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Material Management - Teacher</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include '../includes/sidebar_teacher.php'; ?>
  <main class="main-content">
    <div class="page-title">
      <h1>Material Management</h1>
      <p>Upload, edit, delete and monitor your study materials.</p>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert-message <?= $msg_type === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-header">
        <h3><?= $edit_material ? 'Edit Material' : 'Upload New Material' ?></h3>
      </div>
      <div class="panel-body">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="material_id" value="<?= (int)($edit_material['id'] ?? 0) ?>">

          <div class="form-group">
            <label>Title</label>
            <input name="title" value="<?= htmlspecialchars($edit_material['title'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label>Course Name</label>
            <input name="course" value="<?= htmlspecialchars($edit_material['course_name'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label>Semester</label>
            <input name="semester" value="<?= htmlspecialchars($edit_material['semester'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label>Department</label>
            <select name="dept" required>
              <option value="">Select department</option>
              <?php while ($dept = $depts->fetch_assoc()): ?>
                <option value="<?= (int)$dept['id'] ?>" <?= isset($edit_material['department_id']) && (int)$edit_material['department_id'] === (int)$dept['id'] ? 'selected' : '' ?>><?= htmlspecialchars($dept['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Material File <?= $edit_material ? '(Leave blank to keep current)' : '' ?></label>
            <input type="file" name="file" <?= $edit_material ? '' : 'required' ?> accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp">
            <small style="color:#94a3b8;display:block;margin-top:6px;">
              Supported: PDF, Word, PowerPoint, Excel, or image files. A cover thumbnail is generated automatically — no need to upload one.
            </small>
          </div>

          <button type="submit" class="btn btn-primary"><?= $edit_material ? 'Save changes' : 'Upload Material' ?></button>
          <?php if ($edit_material): ?>
            <a class="btn btn-ghost" href="materials.php">Cancel</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <h3>Your Uploaded Materials</h3>
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
              <th>Rating</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($materials->num_rows === 0): ?>
              <tr><td colspan="8" style="text-align:center;color:#6b7280;padding:24px">No materials uploaded yet.</td></tr>
            <?php else: while ($mat = $materials->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($mat['title']) ?></td>
                <td><?= htmlspecialchars($mat['course_name']) ?></td>
                <td><span class="badge badge-blue"><?= htmlspecialchars($mat['department']) ?></span></td>
                <td><?= htmlspecialchars($mat['semester']) ?></td>
                <td><?= (int)$mat['view_count'] ?></td>
                <td><?= (int)$mat['download_count'] ?></td>
                <td><?= (float)$mat['avg_rating'] ?> out of 5 (<?= (int)$mat['ratings_count'] ?>)</td>
                <td>
                  <div class="action-group" style="justify-content:flex-start;gap:8px;flex-wrap:wrap;">
                    <a class="btn btn-sm btn-primary" href="preview.php?id=<?= (int)$mat['id'] ?>" target="_blank">Preview</a>
                    <a class="btn btn-sm btn-ghost" href="materials.php?edit=<?= (int)$mat['id'] ?>">Edit</a>
                    <a class="btn btn-sm btn-danger" href="materials.php?delete=<?= (int)$mat['id'] ?>" onclick="return confirm('Delete this material?');">Delete</a>
                  </div>
                </td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($total_items > 0):
        $prev_page = max(1, $page - 1);
        $next_page = min($total_pages, $page + 1);
        $is_first_page = $page === 1;
        $is_last_page = $page === $total_pages;
      ?>
      <div class="pagination-wrap">
        <?php if ($is_first_page): ?>
          <span class="ghost-button disabled" aria-disabled="true">Previous</span>
        <?php else: ?>
          <a class="ghost-button" href="materials.php?page=<?= $prev_page ?>">Previous</a>
        <?php endif; ?>

        <span class="page-chip">Page <?= (int)$page ?> of <?= (int)$total_pages ?></span>

        <?php if ($is_last_page): ?>
          <span class="ghost-button disabled" aria-disabled="true">Next</span>
        <?php else: ?>
          <a class="ghost-button" href="materials.php?page=<?= $next_page ?>">Next</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
