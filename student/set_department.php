<?php
session_start();
if (!isset($_SESSION['student'])) {
    header("Location: login.php");
    exit;
}

require '../db.php';
$student = $_SESSION['student'];

$depts = $mysqli->query("SELECT * FROM departments");
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = intval($_POST['dept']);
    $_SESSION['student_department_id'] = $d;

    $_SESSION['student_department_id'] = $d;

    $column = $mysqli->query("SHOW COLUMNS FROM users LIKE 'department_id'");
    if ($column && $column->num_rows > 0 && isset($student['id'])) {
        $stmt = $mysqli->prepare("UPDATE users SET department_id=? WHERE id=?");
        $stmt->bind_param("ii", $d, (int)$student['id']);
        $stmt->execute();
    }

    header("Location: view.php");
    exit;
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
