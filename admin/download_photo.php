<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/auth.php';
require __DIR__ . '/../config/db.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die("Invalid application ID.");
}

$stmt = $pdo->prepare("SELECT id, firstname, surname, othernames, class_of_entry, index_number, passport_photo FROM applications WHERE id = ?");
$stmt->execute([$id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    http_response_code(404);
    die("Application not found.");
}

$photoPath = $app['passport_photo'] ?? '';
if (empty($photoPath)) {
    http_response_code(404);
    die("No passport photo uploaded for this applicant.");
}

$cleanRelPath = ltrim(str_replace('\\', '/', $photoPath), './');
$fullPath = realpath(__DIR__ . '/../' . $cleanRelPath);

if (!$fullPath || !file_exists($fullPath)) {
    http_response_code(404);
    die("Photo file not found on server.");
}

$ext = pathinfo($fullPath, PATHINFO_EXTENSION);
if (empty($ext)) $ext = 'jpg';

// Build safe, clean filename: Firstname_Surname_Class_IndexNumber.ext
$safeFirst = preg_replace('/[^a-zA-Z0-9]/', '', $app['firstname'] ?? 'Student');
$safeLast  = preg_replace('/[^a-zA-Z0-9]/', '', $app['surname'] ?? '');
$safeClass = preg_replace('/[^a-zA-Z0-9]/', '', $app['class_of_entry'] ?? 'Class');
$safeIndex = preg_replace('/[^a-zA-Z0-9]/', '', $app['index_number'] ?? (string)$app['id']);

$downloadFilename = "{$safeFirst}_{$safeLast}_{$safeClass}_{$safeIndex}.{$ext}";
$mime = mime_content_type($fullPath) ?: 'image/jpeg';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
