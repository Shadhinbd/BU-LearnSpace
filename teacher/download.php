<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

require '../db.php';

$teacher_id = (int)$_SESSION['user']['id'];
$id = intval($_GET['id'] ?? 0);

$stmt = $mysqli->prepare('SELECT filename FROM materials WHERE id = ? AND uploaded_by = ?');
$stmt->bind_param('ii', $id, $teacher_id);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();

if (!$material) {
    header('HTTP/1.0 404 Not Found');
    echo 'Material not found.';
    exit;
}

$filePath = realpath(__DIR__ . '/../uploads/' . $material['filename']);
$uploadsDir = realpath(__DIR__ . '/../uploads');

if (!$filePath || strpos($filePath, $uploadsDir) !== 0 || !file_exists($filePath)) {
    header('HTTP/1.0 404 Not Found');
    echo 'File not found.';
    exit;
}

$mimeTypes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];
$ext = strtolower(pathinfo($material['filename'], PATHINFO_EXTENSION));
$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . basename($material['filename']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
