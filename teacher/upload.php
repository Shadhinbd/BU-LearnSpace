<?php
session_start();
if (!isset($_SESSION['teacher'])) header("Location: login.php");

require '../db.php';

$teacher_id = $_SESSION['teacher'];
$msg = "";

/* DELETE MATERIAL */
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    $res = $mysqli->query("SELECT filename, cover_image FROM materials WHERE id=$delete_id AND uploaded_by=$teacher_id");

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        @unlink("../uploads/" . $row['filename']);
        @unlink("../uploads/" . $row['cover_image']);
        $mysqli->query("DELETE FROM materials WHERE id=$delete_id");
        $msg = "Material Deleted Successfully!";
    }
}

/* ADD MATERIAL */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = $_POST['title'];
    $course = $_POST['course'];
    $semester = $_POST['semester'];
    $dept = $_POST['dept'];

    // -------- PDF UPLOAD --------
    $pdf_name = time() . "_" . basename($_FILES['file']['name']);
    move_uploaded_file($_FILES['file']['tmp_name'], "../uploads/" . $pdf_name);

    // -------- COVER IMAGE UPLOAD --------
    $cover_name = "";
    if (!empty($_FILES['cover']['name'])) {
        $cover_name = time() . "_cover_" . basename($_FILES['cover']['name']);
        move_uploaded_file($_FILES['cover']['tmp_name'], "../uploads/" . $cover_name);
    }

    $stmt = $mysqli->prepare("
        INSERT INTO materials (title, filename, cover_image, course_name, semester, department_id, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssssi", $title, $pdf_name, $cover_name, $course, $semester, $dept, $teacher_id);
    $stmt->execute();

    $msg = "Uploaded Successfully!";
}

/* Material list */
$my_materials = $mysqli->query("
    SELECT * FROM materials WHERE uploaded_by=$teacher_id ORDER BY id DESC
");

$depts = $mysqli->query("SELECT * FROM departments");
?>
<!DOCTYPE html>
<html>
<head><link rel="stylesheet" href="../style.css"></head>
<body>

<div class="container">

<h2>Upload Material</h2>
<p style="color:green;"><?= $msg ?></p>

<form method="post" enctype="multipart/form-data">
<input name="title" placeholder="Material Title" required>
<input name="course" placeholder="Course Name" required>
<input name="semester" placeholder="Semester" required>

<select name="dept">
<?php while($d=$depts->fetch_assoc()): ?>
<option value="<?= $d['id'] ?>"><?= $d['name'] ?></option>
<?php endwhile; ?>
</select>

<label>PDF File:</label>
<input type="file" name="file" required>

<label>Cover Image (optional):</label>
<input type="file" name="cover" accept="image/*">

<button>Upload</button>
</form>

<br><br>
<h3>Your Uploaded Materials</h3>

<div class="card">
<?php while($m = $my_materials->fetch_assoc()): ?>
<div class="material-item">
    
    <?php if (!empty($m['cover_image'])): ?>
        <img src="../uploads/<?= $m['cover_image'] ?>" 
             style="width:120px;height:160px;object-fit:cover;border-radius:6px;">
        <br>
    <?php endif; ?>

    <b><?= $m['title'] ?></b><br>
    <?= $m['course_name'] ?> — Semester <?= $m['semester'] ?><br>

    <a href="../uploads/<?= $m['filename'] ?>" target="_blank">Open</a> |
    <a href="?delete=<?= $m['id'] ?>" style="color:red;"
       onclick="return confirm('Delete this material?');">Delete</a>
</div>
<?php endwhile; ?>
</div>

</div>

</body>
</html>
