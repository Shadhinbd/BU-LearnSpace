<?php
session_start();
if (!isset($_SESSION['teacher'])) header("Location: login.php");

require '../db.php';
$teacher = $_SESSION['teacher'];

$depts = $mysqli->query("SELECT * FROM departments");
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = intval($_POST['dept']);
    $stmt = $mysqli->prepare("UPDATE users SET department_id=? WHERE id=?");
    $stmt->bind_param("ii", $d, $teacher['id']);
    $stmt->execute();
    header("Location: upload.php");
}
?>
<!DOCTYPE html>
<html><head><link rel="stylesheet" href="../style.css"></head>
<body><div class="container">
<h2>Select Your Department</h2>
<form method="post">
<select name="dept">
<?php while($d=$depts->fetch_assoc()): ?>
<option value="<?= $d['id'] ?>"><?= $d['name'] ?></option>
<?php endwhile; ?>
</select>
<button>Save</button>
</form>
</div></body></html>
