<?php
session_start();
if (!isset($_SESSION['student'])) header("Location: login.php");

require '../db.php';

$id = intval($_GET['id']);
$stmt = $mysqli->prepare("SELECT * FROM materials WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$mat = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html><head><link rel="stylesheet" href="../style.css"></head>
<body><div class="container">
<h2><?= $mat['title'] ?></h2>

<iframe src="../uploads/<?= $mat['filename'] ?>" style="width:100%;height:600px;"></iframe>

<a class="link" href="../uploads/<?= $mat['filename'] ?>" download>Download</a>

</div></body></html>
