<?php
session_start();
if (!isset($_SESSION['student'])) {
    header('Location: login.php');
    exit;
}

require '../db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('HTTP/1.0 404 Not Found');
    echo 'Invalid request.';
    exit;
}

$stmt = $mysqli->prepare('SELECT filename, title FROM materials WHERE id = ?');
$stmt->bind_param('i', $id);
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

$update = $mysqli->prepare('UPDATE materials SET download_count = download_count + 1 WHERE id = ?');
$update->bind_param('i', $id);
$update->execute();

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
$fileExt = strtolower(pathinfo($material['filename'], PATHINFO_EXTENSION));
$contentType = $mimeTypes[$fileExt] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . basename($material['filename']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
