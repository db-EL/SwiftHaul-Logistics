<?php
// Streams a KYC document/photo to an authenticated admin ONLY.
// This is the single point through which any uploaded document is
// ever served — there is no direct URL to a file under /uploads
// that works (see uploads/.htaccess), specifically so this access
// check can't be bypassed.
//
// Usage: admin/view_document.php?account_type=user|rider&id=123&field=selfie_path|verification_doc_path|vehicle_photo_path
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$accountType = $_GET['account_type'] ?? '';
$accountId = (int) ($_GET['id'] ?? 0);
$field = $_GET['field'] ?? '';

$allowedFields = [
    'user' => ['selfie_path', 'verification_doc_path'],
    'rider' => ['selfie_path', 'verification_doc_path', 'vehicle_photo_path'],
    'shipment' => ['signature_path'],
];

if (!isset($allowedFields[$accountType]) || !in_array($field, $allowedFields[$accountType], true) || !$accountId) {
    http_response_code(400);
    die('Invalid request.');
}

$tableMap = ['user' => 'users', 'rider' => 'riders', 'shipment' => 'shipments'];
$table = $tableMap[$accountType];
$stmt = $pdo->prepare("SELECT `$field` AS path FROM `$table` WHERE id = ?");
$stmt->execute([$accountId]);
$row = $stmt->fetch();

if (!$row || !$row['path']) {
    http_response_code(404);
    die('Document not found.');
}

$fullPath = realpath(__DIR__ . '/../uploads/' . $row['path']);
$uploadsRoot = realpath(__DIR__ . '/../uploads');

// Defense in depth: even though $row['path'] came from our own database
// (not directly from user input), double-check the resolved path is
// still within the uploads directory before reading it.
if (!$fullPath || strpos($fullPath, $uploadsRoot) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    die('Document not found.');
}

$mime = mime_content_type($fullPath) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($fullPath);
