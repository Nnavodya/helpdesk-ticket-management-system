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

// Support POST (preferred) or GET (for direct click-through redirection)
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$notifId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$redirectUrl = $_POST['redirect'] ?? $_GET['redirect'] ?? appUrl('notifications.php');

if ($action === 'mark_read' && $notifId) {
    try {
        $stmt = $pdo->prepare('
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = :id AND user_id = :user_id
        ');
        $stmt->execute([
            ':id'      => $notifId,
            ':user_id' => $userId,
        ]);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'unread_count' => getUnreadNotificationCount($pdo, $userId),
            ]);
            exit;
        }
    } catch (PDOException $e) {
        error_log('Notification mark read error: ' . $e->getMessage());
    }
} elseif ($action === 'mark_all_read') {
    // CSRF check for mark all read
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (verifyCsrfToken($token)) {
        try {
            $stmt = $pdo->prepare('
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = :user_id AND is_read = 0
            ');
            $stmt->execute([':user_id' => $userId]);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'unread_count' => 0]);
                exit;
            }
            setFlash('success', 'All notifications marked as read.');
        } catch (PDOException $e) {
            error_log('Notification mark all read error: ' . $e->getMessage());
        }
    }
}

// Redirect back
header('Location: ' . $redirectUrl);
exit;
