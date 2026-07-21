<?php
session_start();
require '../db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') { header('Location: login.php'); exit; }

$tid = (int)$_SESSION['user']['id'];
$submission_id = (int)($_GET['submission_id'] ?? 0);

if ($submission_id <= 0) {
    header('HTTP/1.0 404 Not Found');
    echo 'Invalid request.';
    exit;
}

// Only allow the teacher who owns the related assignment to download the file.
$stmt = $mysqli->prepare("SELECT s.filename, s.original_name FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE s.id = ? AND a.teacher_id = ?");
$stmt->bind_param('ii', $submission_id, $tid);
$stmt->execute();
$submission = $stmt->get_result()->fetch_assoc();

if (!$submission) {
    header('HTTP/1.0 404 Not Found');
    echo 'Submission not found.';
    exit;
}

$filePath = realpath(__DIR__ . '/../uploads/' . $submission['filename']);
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
    'zip'  => 'application/zip',
];
$ext = strtolower(pathinfo($submission['filename'], PATHINFO_EXTENSION));
$contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . basename($submission['original_name']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
