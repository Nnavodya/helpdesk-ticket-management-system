<?php
/**
 * Ticket Creation Action Handler
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

function redirectWithError(string $message): void
{
    $_SESSION['ticket_error'] = $message;
    $_SESSION['old_subject'] = $_POST['subject'] ?? '';
    $_SESSION['old_description'] = $_POST['description'] ?? '';
    $_SESSION['old_priority'] = $_POST['priority'] ?? 'medium';
    $_SESSION['old_category_id'] = $_POST['category_id'] ?? '';
    header('Location: ' . appUrl('tickets/create.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . appUrl('tickets/create.php'));
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($submittedToken)) {
    http_response_code(403);
    exit('Invalid security token. Please return to the form and try again.');
}

$subject = trim($_POST['subject'] ?? '');
$description = trim($_POST['description'] ?? '');
$categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
$priority = $_POST['priority'] ?? 'medium';

if ($subject === '' || mb_strlen($subject) > 200) {
    redirectWithError('Enter a subject between 1 and 200 characters.');
}

if ($description === '' || mb_strlen($description) > 5000) {
    redirectWithError('Enter a description between 1 and 5000 characters.');
}

if (!$categoryId || $categoryId < 1) {
    redirectWithError('Please select a valid category.');
}

$allowedPriorities = ['low', 'medium', 'high', 'critical'];
if (!in_array($priority, $allowedPriorities, true)) {
    redirectWithError('Please select a valid priority.');
}

try {
    // Confirm category exists
    $check = $pdo->prepare('SELECT id FROM categories WHERE id = :id');
    $check->execute(['id' => $categoryId]);
    if (!$check->fetch()) {
        redirectWithError('The selected category does not exist.');
    }

    $pdo->beginTransaction();

    // Generate unique ticket reference
    $ticketNumber = 'HD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

    $stmt = $pdo->prepare('
        INSERT INTO tickets (ticket_number, created_by, category_id, subject, description, priority, status)
        VALUES (:ticket_number, :created_by, :category_id, :subject, :description, :priority, :status)
    ');

    $stmt->execute([
        'ticket_number' => $ticketNumber,
        'created_by'    => (int) $_SESSION['user_id'],
        'category_id'   => $categoryId,
        'subject'       => $subject,
        'description'   => $description,
        'priority'      => $priority,
        'status'        => 'open',
    ]);

    $ticketId = (int) $pdo->lastInsertId();

    // Record ticket creation in history
    $history = $pdo->prepare('
        INSERT INTO ticket_history (ticket_id, changed_by, action_type, note)
        VALUES (:ticket_id, :changed_by, :action_type, :note)
    ');

    $history->execute([
        'ticket_id'   => $ticketId,
        'changed_by'  => (int) $_SESSION['user_id'],
        'action_type' => 'created',
        'note'        => 'Ticket created by user',
    ]);

    // Handle optional file attachment
    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $maxBytes = 5 * 1024 * 1024; // 5 MB
        $allowedExts = ['png', 'jpg', 'jpeg', 'pdf', 'txt', 'docx'];

        $originalName = basename($file['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($file['size'] <= $maxBytes && in_array($ext, $allowedExts, true)) {
            $uploadDir = __DIR__ . '/../../storage/ticket-uploads/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
                throw new RuntimeException('Could not create the private ticket upload directory.');
            }

            $safeFileName = 'ticket_' . $ticketId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destPath = $uploadDir . $safeFileName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                // Check if ticket_attachments table exists
                if (tableExists($pdo, 'ticket_attachments')) {
                    $attachStmt = $pdo->prepare('
                        INSERT INTO ticket_attachments (ticket_id, uploaded_by, file_name, file_path, file_size, file_type)
                        VALUES (:ticket_id, :uploaded_by, :file_name, :file_path, :file_size, :file_type)
                    ');
                    $attachStmt->execute([
                        'ticket_id'   => $ticketId,
                        'uploaded_by' => (int) $_SESSION['user_id'],
                        'file_name'   => $originalName,
                        'file_path'   => $safeFileName,
                        'file_size'   => (int) $file['size'],
                        'file_type'   => $ext,
                    ]);
                }
            }
        }
    }

    $pdo->commit();

    // Notify all active administrators about the new ticket
    notifyAdmins(
        $pdo,
        $ticketId,
        'New Ticket: ' . $ticketNumber,
        ($_SESSION['user_name'] ?? 'An employee') . ' created ticket "' . $subject . '"',
        appUrl('tickets/show.php') . '?id=' . $ticketId,
        (int) $_SESSION['user_id']
    );

    setFlash('success', 'Ticket ' . $ticketNumber . ' was submitted successfully.');
    header('Location: ' . appUrl('tickets/show.php') . '?id=' . $ticketId);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Ticket create failed: ' . $e->getMessage());
    redirectWithError('Could not create ticket. Please check the database setup and try again.');
}
