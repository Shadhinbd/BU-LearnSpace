<?php
session_start();
if (!isset($_SESSION['admin'])) header("Location: login.php");
require '../db.php';

$msg = "";

// ✅ DELETE USER
if (isset($_POST['delete'])) {
    $id = $_POST['id'];
    $mysqli->query("DELETE FROM users WHERE id=$id");
    $msg = "User deleted!";
}

// ✅ CREATE USER
if (isset($_POST['name'])) {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = $_POST['password'];

    $pass = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $mysqli->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $name, $email, $pass, $role);
    $stmt->execute();

    $msg = "User Created!";
}

// FETCH USERS
$teachers = $mysqli->query("SELECT * FROM users WHERE role='teacher'");
$students = $mysqli->query("SELECT * FROM users WHERE role='student'");
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="../style.css">

<style>
/* ✅ FIX ALIGNMENT */
.material-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
}

.material-item span {
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

/* ✅ BACK BUTTON */
.back-btn {
    display: inline-block;
    margin-bottom: 15px;
    padding: 8px 12px;
    background: #0f83b9;
    color: #fff;
    text-decoration: none;
    border-radius: 5px;
}

.back-btn:hover {
    background: #000;
}
.logout {
    justify-content : center; 
    text-align : center;
    margin : 10px;
    padding : 10px ; 
}
</style>

</head>
<body>

<div class="container">

<!-- ✅ BACK BUTTON -->
<a href="../index.php" class="back-btn">⬅ Back to BU-LearnSpace Dashboard</a>

<h1>Admin Dashboard</h1>

<!-- ✅ MESSAGE -->
<?php if($msg != ""): ?>
<p style="color:green; font-weight:bold;"><?= $msg ?></p>
<?php endif; ?>

<!-- ✅ CREATE USER -->
<div class="card">
<h3>Create Teacher / Student</h3>
<form method="post">
<input name="name" placeholder="Name" required>
<input name="email" placeholder="Email" required>
<input name="password" placeholder="Password" required>

<select name="role">
<option value="teacher">Teacher</option>
<option value="student">Student</option>
</select>

<button type="submit">Create</button>
</form>
</div>

<!-- ✅ TEACHERS -->
<div class="card">
<h3>Teachers</h3>

<?php while($t=$teachers->fetch_assoc()): ?>
<div class="material-item">
    <span><?= $t['name'] ?> — <?= $t['email'] ?></span>

    <form method="post">
        <input type="hidden" name="id" value="<?= $t['id'] ?>">
        <button type="submit" name="delete"
            onclick="return confirm('Delete this user?')"
            style="background:red;color:white;border:none;padding:5px 10px;">
            Delete
        </button>
    </form>
</div>
<?php endwhile; ?>

</div>

<!-- ✅ STUDENTS -->
<div class="card">
<h3>Students</h3>

<?php while($s=$students->fetch_assoc()): ?>
    <div class="material-item">
        <span><?= $s['name'] ?> — <?= $s['email'] ?></span>

        <form method="post">
        <input type="hidden" name="id" value="<?= $s['id'] ?>">
        <button type="submit" name="delete"
            onclick="return confirm('Delete this user?')"
            style="background:red;color:white;border:none;padding:5px 10px;">
            Delete
        </button>
        </form>
    </div>
<?php endwhile; ?>

</div>

</div>
    <div class="logout">
        <a href="logout.php"><button>Logout</button></a>
    </div>
</body>
</html>