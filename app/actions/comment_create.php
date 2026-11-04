<?php
/**
 * Comment Creation Action Handler
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . appUrl('tickets/index.php'));
    exit;
}

$sessionToken = $_SESSION['csrf_token'] ?? '';
$postedToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($postedToken)) {
    http_response_code(403);
    exit('Invalid security token. Please go back and try again.');
}

$ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
if (!$ticketId || $ticketId < 1) {
    exit('Invalid ticket ID.');
}

$body = trim($_POST['body'] ?? '');
if ($body === '' || mb_strlen($body) > 3000) {
    setFlash('error', 'Comment is required and must not exceed 3000 characters.');
    header('Location: ' . appUrl('tickets/show.php') . '?id=' . $ticketId);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

try {
    // Check permission to comment on this ticket
    $sql = "SELECT id, ticket_number, subject, created_by, assigned_to FROM tickets WHERE id = :ticket_id";
    $params = [':ticket_id' => $ticketId];

    if ($userRole === 'employee') {
        $sql .= " AND created_by = :user_id";
        $params[':user_id'] = $userId;
    } elseif ($userRole === 'agent') {
        $sql .= " AND (assigned_to = :assigned_user_id OR created_by = :requester_user_id)";
        $params[':assigned_user_id'] = $userId;
        $params[':requester_user_id'] = $userId;
    }

    $check = $pdo->prepare($sql);
    $check->execute($params);
    $ticket = $check->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        http_response_code(403);
        exit('Ticket not found or you do not have permission to comment on this ticket.');
    }

    $pdo->beginTransaction();

    // Insert comment
    $commentStmt = $pdo->prepare("
        INSERT INTO ticket_comments (ticket_id, user_id, body, created_at)
        VALUES (:ticket_id, :user_id, :body, CURRENT_TIMESTAMP)
    ");
    $commentStmt->execute([
        ':ticket_id' => $ticketId,
        ':user_id'   => $userId,
        ':body'      => $body,
    ]);

    $commentId = (int) $pdo->lastInsertId();

    // Record in history
    $historyStmt = $pdo->prepare("
        INSERT INTO ticket_history (ticket_id, changed_by, action_type, note)
        VALUES (:ticket_id, :changed_by, 'comment_added', 'Added a comment')
    ");
    $historyStmt->execute([
        ':ticket_id'   => $ticketId,
        ':changed_by'  => $userId,
    ]);

    // Update ticket timestamp
    $updateStmt = $pdo->prepare("
        UPDATE tickets
        SET updated_at = CURRENT_TIMESTAMP
        WHERE id = :ticket_id
    ");
    $updateStmt->execute([':ticket_id' => $ticketId]);

    // Handle optional attachment with comment
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

            $safeFileName = 'comment_' . $commentId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destPath = $uploadDir . $safeFileName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                if (tableExists($pdo, 'ticket_attachments')) {
                    $attachStmt = $pdo->prepare('
                        INSERT INTO ticket_attachments (ticket_id, comment_id, uploaded_by, file_name, file_path, file_size, file_type)
                        VALUES (:ticket_id, :comment_id, :uploaded_by, :file_name, :file_path, :file_size, :file_type)
                    ');
                    $attachStmt->execute([
                        'ticket_id'   => $ticketId,
                        'comment_id'  => $commentId,
                        'uploaded_by' => $userId,
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

    // In-app notifications for new replies
    $commentSnippet = mb_strlen($body) > 60 ? mb_substr($body, 0, 57) . '...' : $body;
    $commenterName = $_SESSION['user_name'] ?? 'A user';

    // If commenter is the ticket requester -> notify assigned agent (or admins if unassigned)
    if ($userId === (int) $ticket['created_by']) {
        if (!empty($ticket['assigned_to'])) {
            createNotification(
                $pdo,
                (int) $ticket['assigned_to'],
                $ticketId,
                'New Reply: ' . $ticket['ticket_number'],
                $commenterName . ' commented: "' . $commentSnippet . '"',
                appUrl('tickets/show.php') . '?id=' . $ticketId
            );
        } else {
            notifyAdmins(
                $pdo,
                $ticketId,
                'New Reply: ' . $ticket['ticket_number'],
                $commenterName . ' commented: "' . $commentSnippet . '"',
                appUrl('tickets/show.php') . '?id=' . $ticketId,
                $userId
            );
        }
    } else {
        // If commenter is staff (Agent/Admin) -> notify the ticket requester
        if (!empty($ticket['created_by'])) {
            createNotification(
                $pdo,
                (int) $ticket['created_by'],
                $ticketId,
                'Staff Reply: ' . $ticket['ticket_number'],
                $commenterName . ' replied: "' . $commentSnippet . '"',
                appUrl('tickets/show.php') . '?id=' . $ticketId
            );
        }
        // Also if an admin replied and agent is assigned, notify agent
        if (!empty($ticket['assigned_to']) && $userId !== (int) $ticket['assigned_to']) {
            createNotification(
                $pdo,
                (int) $ticket['assigned_to'],
                $ticketId,
                'Staff Update: ' . $ticket['ticket_number'],
                $commenterName . ' commented on assigned ticket: "' . $commentSnippet . '"',
                appUrl('tickets/show.php') . '?id=' . $ticketId
            );
        }
    }

    setFlash('success', 'Your reply was added successfully.');
    header('Location: ' . appUrl('tickets/show.php') . '?id=' . $ticketId);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Comment creation failed: ' . $e->getMessage());
    setFlash('error', 'Could not save your comment. Please try again.');
    header('Location: ' . appUrl('tickets/show.php') . '?id=' . $ticketId);
    exit;
}
