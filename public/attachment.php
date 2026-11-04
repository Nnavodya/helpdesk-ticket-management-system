<?php
/**
 * Authorized ticket attachment delivery.
 */

require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/config/database.php';

requireLogin();

$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$attachmentId || $attachmentId < 1) {
    http_response_code(404);
    exit('Attachment not found.');
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

try {
    $stmt = $pdo->prepare('
        SELECT
            ta.file_name,
            ta.file_path,
            ta.file_type,
            t.created_by,
            t.assigned_to
        FROM ticket_attachments ta
        JOIN tickets t ON t.id = ta.ticket_id
        WHERE ta.id = :attachment_id
    ');
    $stmt->execute([':attachment_id' => $attachmentId]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Attachment lookup failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to retrieve attachment.');
}

if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found.');
}

$canView = $userRole === 'admin'
    || ($userRole === 'employee' && (int) $attachment['created_by'] === $userId)
    || ($userRole === 'agent' && (
        (int) $attachment['assigned_to'] === $userId
        || (int) $attachment['created_by'] === $userId
    ));

if (!$canView) {
    http_response_code(404);
    exit('Attachment not found.');
}

$storedPath = str_replace('\\', '/', (string) $attachment['file_path']);
$isLegacyPath = preg_match(
    '#\Auploads/tickets/([A-Za-z0-9_-]+\.(?:png|jpe?g|pdf|txt|docx))\z#i',
    $storedPath,
    $matches
) === 1;

if ($isLegacyPath) {
    $fileName = $matches[1];
    $baseDirectory = __DIR__ . '/uploads/tickets';
} elseif (preg_match('/\A([A-Za-z0-9_-]+\.(?:png|jpe?g|pdf|txt|docx))\z/i', $storedPath, $matches) === 1) {
    $fileName = $matches[1];
    $baseDirectory = dirname(__DIR__) . '/storage/ticket-uploads';
} else {
    http_response_code(404);
    exit('Attachment not found.');
}

$resolvedDirectory = realpath($baseDirectory);
$resolvedFile = realpath($baseDirectory . DIRECTORY_SEPARATOR . $fileName);
$allowedExtensions = ['png', 'jpg', 'jpeg', 'pdf', 'txt', 'docx'];
$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (
    $resolvedDirectory === false
    || $resolvedFile === false
    || !is_file($resolvedFile)
    || !in_array($extension, $allowedExtensions, true)
) {
    http_response_code(404);
    exit('Attachment not found.');
}

$directoryPrefix = rtrim($resolvedDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
if (strpos(strtolower($resolvedFile), strtolower($directoryPrefix)) !== 0) {
    http_response_code(404);
    exit('Attachment not found.');
}

$downloadName = str_replace(["\r", "\n", '"', '\\'], '_', basename((string) $attachment['file_name']));
$isImage = in_array($extension, ['png', 'jpg', 'jpeg'], true);
$contentType = $extension === 'png'
    ? 'image/png'
    : (in_array($extension, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'application/octet-stream');

header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: sandbox');
header('Cache-Control: private, no-store');
header('Content-Type: ' . $contentType);
header('Content-Disposition: ' . ($isImage ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($downloadName));
header('Content-Length: ' . (string) filesize($resolvedFile));
readfile($resolvedFile);
