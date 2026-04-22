<?php
session_start();
if (!isset($_SESSION['student'])) header("Location: login.php");

require '../db.php';

$q = $_GET['q'] ?? "";
$dept = $_GET['dept'] ?? "0";

$sql = "
SELECT m.*, u.name AS teacher 
FROM materials m
LEFT JOIN users u ON m.uploaded_by = u.id
WHERE (m.title LIKE ? OR m.course_name LIKE ?)
";

$params = [];
$search = "%$q%";
$params[] = $search;
$params[] = $search;

if ($dept !== "0") {
    $sql .= " AND m.department_id = ?";
    $params[] = $dept;
}

$stmt = $mysqli->prepare($sql);

if (count($params) == 3)
    $stmt->bind_param("ssi", $params[0], $params[1], $params[2]);
else
    $stmt->bind_param("ss", $params[0], $params[1]);

$stmt->execute();
$res = $stmt->get_result();

$depts = $mysqli->query("SELECT * FROM departments");
?>
<!DOCTYPE html>
<html>
<head><link rel="stylesheet" href="../style.css"></head>
<body>

<div class="container">

<h2>Study Materials</h2>

<form method="get" style="text-align:center; margin-bottom:20px;">
    <input name="q" placeholder="Search..." 
           value="<?= $q ?>" style="width:60%;padding:12px;">
    
    <select name="dept" style="width:30%;padding:12px;">
        <option value="0">All Departments</option>
        <?php while($d=$depts->fetch_assoc()): ?>
            <option value="<?= $d['id'] ?>" <?= $dept==$d['id']?"selected":"" ?>>
                <?= $d['name'] ?>
            </option>
        <?php endwhile; ?>
    </select>
    <button>Search</button>
</form>

<div class="card">
<?php while($m=$res->fetch_assoc()): ?>
    <div class="material-item" style="margin-bottom:20px;">

        <?php if (!empty($m['cover_image'])): ?>
            <img src="../uploads/<?= $m['cover_image'] ?>"  
                 style="width:140px;height:180px;object-fit:cover;border-radius:6px;">
        <?php else: ?>
            <div style="width:140px;height:180px;background:#ddd;border-radius:6px;
                        display:flex;align-items:center;justify-content:center;">
                No Cover
            </div>
        <?php endif; ?>
        <br><br>

        <b><?= $m['title'] ?></b><br>
        <?= $m['course_name'] ?> — Semester <?= $m['semester'] ?><br>
        <small>Teacher: <?= $m['teacher'] ?></small><br><br>

        <a href="preview.php?id=<?= $m['id'] ?>">Read</a> |
        <a href="../uploads/<?= $m['filename'] ?>" download>Download</a>

    </div>
<?php endwhile; ?>
</div>

</div>

</body>
</html>
