<?php
session_start();
require '../db.php';

// ✅ session check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$msg = "";

/* =========================
   DELETE MATERIAL
========================= */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    $stmt = $mysqli->prepare("SELECT filename, cover_image FROM materials WHERE id=? AND uploaded_by=?");
    $stmt->bind_param("ii", $id, $_SESSION['user']['id']);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();

    if ($file) {

        // delete main file
        if (file_exists("../uploads/" . $file['filename'])) {
            unlink("../uploads/" . $file['filename']);
        }

        // delete thumbnail if exists
        if (!empty($file['cover_image']) && $file['cover_image'] !== "no-cover.png") {
            if (file_exists("../uploads/" . $file['cover_image'])) {
                unlink("../uploads/" . $file['cover_image']);
            }
        }

        // delete DB record
        $del = $mysqli->prepare("DELETE FROM materials WHERE id=? AND uploaded_by=?");
        $del->bind_param("ii", $id, $_SESSION['user']['id']);
        $del->execute();
    }

    header("Location: dashboard.php");
    exit;
}

/* =========================
   UPLOAD MATERIAL
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {

    $title = trim($_POST['title']);
    $department = (int)$_POST['department'];
    $semester = trim($_POST['semester']);

    if (!$title || !$department || !$semester) {
        $msg = "Please fill all fields.";
    } elseif (empty($_FILES['file']['name'])) {
        $msg = "Please choose a file.";
    } else {

        $allowed = ['pdf','docx','doc','jpg','jpeg','png'];
        $orig = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $msg = "File type not allowed.";
        } else {

            $new_name = time() . "_" . preg_replace("/[^a-zA-Z0-9_.-]/", "_", $orig);
            $target = "../uploads/" . $new_name;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {

                // thumbnail
                $thumb = "no-cover.png";
                if (!empty($_FILES['thumbnail']['name'])) {
                    $tname = time() . "_thumb_" . $_FILES['thumbnail']['name'];
                    move_uploaded_file($_FILES['thumbnail']['tmp_name'], "../uploads/" . $tname);
                    $thumb = $tname;
                }

                $course_name = "CSE"; // fixed for now

                $stmt = $mysqli->prepare("
                    INSERT INTO materials 
                    (title, filename, cover_image, course_name, uploaded_by, department_id, semester) 
                    VALUES (?,?,?,?,?,?,?)
                ");

                $stmt->bind_param(
                    "sssssis",
                    $title,
                    $new_name,
                    $thumb,
                    $course_name,
                    $_SESSION['user']['id'],
                    $department,
                    $semester
                );

                if (!$stmt->execute()) {
                    die("Insert failed: " . $stmt->error);
                }

                $msg = "Uploaded successfully.";

            } else {
                $msg = "Upload failed.";
            }
        }
    }
}

/* =========================
   FETCH DATA
========================= */
$depts = $mysqli->query("SELECT * FROM departments");

$my = $mysqli->prepare("
    SELECT m.*, d.name AS dept 
    FROM materials m
    LEFT JOIN departments d ON m.department_id = d.id
    WHERE uploaded_by = ?
    ORDER BY m.id DESC
");

$my->bind_param("i", $_SESSION['user']['id']);
$my->execute();
$res = $my->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../style.css">
</head>

<body>
<div class="container">

<h2>Teacher Dashboard</h2>

<p>Welcome, <?= $_SESSION['user']['name'] ?></p>

<?php if ($msg): ?>
<p style="color:green"><?= $msg ?></p>
<?php endif; ?>

<hr>

<h3>Upload Material</h3>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="upload" value="1">

<input name="title" placeholder="Title" required><br><br>

<select name="department" required>
<option value="">Select Department</option>
<?php while($d=$depts->fetch_assoc()): ?>
<option value="<?= $d['id'] ?>"><?= $d['name'] ?></option>
<?php endwhile; ?>
</select><br><br>

<input name="semester" placeholder="Semester" required><br><br>

File: <input type="file" name="file" required><br><br>
Thumbnail: <input type="file" name="thumbnail"><br><br>

<button type="submit">Upload</button>
</form>

<hr>

<h3>Your Materials</h3>

<?php while($m=$res->fetch_assoc()): ?>
<div style="margin-bottom:15px; padding:10px; border:1px solid #ccc;">

<b><?= htmlspecialchars($m['title']) ?></b><br>
Dept: <?= htmlspecialchars($m['dept']) ?> | Semester: <?= htmlspecialchars($m['semester']) ?><br>

<a href="../uploads/<?= $m['filename'] ?>" target="_blank">View</a> |
<a href="../uploads/<?= $m['filename'] ?>" download>Download</a> |
<a href="?delete=<?= $m['id'] ?>"
   onclick="return confirm('Are you sure you want to delete this file?')"
   style="color:red;">
   Delete
</a>

</div>
<?php endwhile; ?>

</div>
</body>
</html>