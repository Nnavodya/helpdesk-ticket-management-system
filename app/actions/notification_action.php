<?php
/**
 * In-App Notification Action Handler
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../includes/session.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$respond = static function (int $status, array $payload) use ($isAjax): void {
    if ($isAjax) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    if ($status >= 400) {
        setFlash('error', $payload['message']);
    } else {
        setFlash('success', $payload['message']);
    }

    header('Location: ' . appUrl('notifications.php'));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$postedToken = $_POST['csrf_token'] ?? '';
if (!is_string($postedToken) || !verifyCsrfToken($postedToken)) {
    $respond(403, ['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.']);
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'mark_read') {
        $notifId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$notifId || $notifId < 1) {
            $respond(400, ['success' => false, 'message' => 'Invalid notification.']);
        }

        $stmt = $pdo->prepare('
            UPDATE notifications
            SET is_read = 1
            WHERE id = :id AND user_id = :user_id AND is_read = 0
        ');
        $stmt->execute([
            ':id' => $notifId,
            ':user_id' => $userId,
        ]);

        if ($stmt->rowCount() === 0) {
            $ownedStmt = $pdo->prepare('
                SELECT is_read
                FROM notifications
                WHERE id = :id AND user_id = :user_id
            ');
            $ownedStmt->execute([
                ':id' => $notifId,
                ':user_id' => $userId,
            ]);
            $isAlreadyRead = $ownedStmt->fetchColumn();

            if ($isAlreadyRead === false) {
                $respond(404, ['success' => false, 'message' => 'Notification not found.']);
            }
        }

        $respond(200, [
            'success' => true,
            'unread_count' => getUnreadNotificationCount($pdo, $userId),
            'message' => 'Notification marked as read.',
        ]);
    }

    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare('
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = :user_id AND is_read = 0
        ');
        $stmt->execute([':user_id' => $userId]);

        $respond(200, [
            'success' => true,
            'unread_count' => 0,
            'message' => 'All notifications marked as read.',
        ]);
    }

    $respond(400, ['success' => false, 'message' => 'Unknown notification action.']);
} catch (PDOException $e) {
    error_log('Notification action failed: ' . $e->getMessage());
    $respond(500, ['success' => false, 'message' => 'Could not update the notification. Please try again.']);
}

header('Location: ' . appUrl('notifications.php'));
exit;
