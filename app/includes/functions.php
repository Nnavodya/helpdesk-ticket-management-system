<?php
/**
 * Help Desk & Ticket Management System
 * Shared Utility Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escapes HTML output safely.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate or get existing CSRF token.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/**
 * Validate submitted CSRF token.
 */
function verifyCsrfToken(?string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

/**
 * Set a flash notification message.
 * Types: 'success', 'error', 'warning', 'info'
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the flash notification message.
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }

    // Backwards compatibility with individual session keys
    if (!empty($_SESSION['error'])) {
        $msg = $_SESSION['error'];
        unset($_SESSION['error']);
        return ['type' => 'error', 'message' => $msg];
    }
    if (!empty($_SESSION['success'])) {
        $msg = $_SESSION['success'];
        unset($_SESSION['success']);
        return ['type' => 'success', 'message' => $msg];
    }
    if (!empty($_SESSION['ticket_error'])) {
        $msg = $_SESSION['ticket_error'];
        unset($_SESSION['ticket_error']);
        return ['type' => 'error', 'message' => $msg];
    }
    if (!empty($_SESSION['ticket_success'])) {
        $msg = $_SESSION['ticket_success'];
        unset($_SESSION['ticket_success']);
        return ['type' => 'success', 'message' => $msg];
    }
    if (!empty($_SESSION['users_error'])) {
        $msg = $_SESSION['users_error'];
        unset($_SESSION['users_error']);
        return ['type' => 'error', 'message' => $msg];
    }
    if (!empty($_SESSION['users_success'])) {
        $msg = $_SESSION['users_success'];
        unset($_SESSION['users_success']);
        return ['type' => 'success', 'message' => $msg];
    }

    return null;
}

/**
 * Render flash alert banner if present.
 */
function renderFlash(): string
{
    $flash = getFlash();
    if (!$flash) {
        return '';
    }

    $type = e($flash['type']);
    $message = e($flash['message']);

    $icon = match ($type) {
        'success' => '<svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        'warning' => '<svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
        'info'    => '<svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
        default   => '<svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
    };

    return <<<HTML
    <div class="alert alert-{$type}" role="alert">
        {$icon}
        <div class="alert-content">{$message}</div>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Close alert">&times;</button>
    </div>
HTML;
}

/**
 * Render a colored badge for ticket status.
 */
function renderStatusBadge(string $status): string
{
    $formatted = ucwords(str_replace('_', ' ', $status));
    $statusClass = 'badge-status-' . strtolower(str_replace('_', '-', $status));

    $dot = '<span class="status-dot"></span>';

    return '<span class="badge badge-status ' . e($statusClass) . '">' . $dot . e($formatted) . '</span>';
}

/**
 * Render a colored badge for ticket priority.
 */
function renderPriorityBadge(string $priority): string
{
    $formatted = ucfirst(strtolower($priority));
    $priorityClass = 'badge-priority-' . strtolower($priority);

    $icon = match (strtolower($priority)) {
        'critical' => '<span class="priority-indicator critical">&bull;</span>',
        'high'     => '<span class="priority-indicator high">&bull;</span>',
        'medium'   => '<span class="priority-indicator medium">&bull;</span>',
        default    => '<span class="priority-indicator low">&bull;</span>',
    };

    return '<span class="badge badge-priority ' . e($priorityClass) . '">' . $icon . e($formatted) . '</span>';
}

/**
 * Render a role badge (Admin, Agent, Employee).
 */
function renderRoleBadge(string $role): string
{
    $roleName = ucfirst($role);
    $roleClass = match (strtolower($role)) {
        'admin' => 'badge-role-admin',
        'agent' => 'badge-role-agent',
        default => 'badge-role-employee',
    };

    return '<span class="badge ' . e($roleClass) . '">' . e($roleName) . '</span>';
}

/**
 * Render user initial avatar circle.
 */
function renderAvatar(string $name, int $size = 36): string
{
    $words = explode(' ', trim($name));
    $initials = '';
    if (!empty($words[0])) {
        $initials .= mb_substr($words[0], 0, 1);
    }
    if (count($words) > 1 && !empty($words[1])) {
        $initials .= mb_substr($words[1], 0, 1);
    }
    $initials = strtoupper($initials ?: 'U');

    // Hash name to pick consistent color
    $colors = ['#4f46e5', '#0891b2', '#059669', '#d97706', '#dc2626', '#7c3aed', '#db2777'];
    $colorIndex = abs(crc32($name)) % count($colors);
    $bg = $colors[$colorIndex];

    return '<span class="user-avatar" style="width:' . $size . 'px;height:' . $size . 'px;line-height:' . $size . 'px;background-color:' . $bg . '" title="' . e($name) . '">' . e($initials) . '</span>';
}

/**
 * Convert timestamp into human friendly relative time (e.g. 5 mins ago).
 */
function timeAgo(string|int|null $datetime): string
{
    if (!$datetime) {
        return 'N/A';
    }

    $timestamp = is_numeric($datetime) ? (int) $datetime : strtotime($datetime);
    if ($timestamp === false) {
        return (string) $datetime;
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = (int) ($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = (int) ($diff / 3600);
        return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = (int) ($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

/**
 * Format full date and time.
 */
function formatDateTime(string|int|null $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $timestamp = is_numeric($datetime) ? (int) $datetime : strtotime($datetime);
    if ($timestamp === false) {
        return (string) $datetime;
    }
    return date('M d, Y h:i A', $timestamp);
}

/**
 * Helper to check if a database table exists.
 */
function tableExists(PDO $pdo, string $tableName): bool
{
    try {
        $result = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tableName));
        return $result && $result->rowCount() > 0;
    } catch (PDOException) {
        return false;
    }
}

/**
 * Auto-create notifications table if not exists (Zero-configuration resilience)
 */
function initNotificationsTable(PDO $pdo): void
{
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                ticket_id INT UNSIGNED NULL,
                title VARCHAR(150) NOT NULL,
                message VARCHAR(500) NOT NULL,
                link VARCHAR(255) NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_notif_user (user_id, is_read),
                INDEX idx_notif_created (created_at),
                CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_notif_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Throwable) {
        // Table or constraints already exist
    }
}

/**
 * Create an in-app notification for a user
 */
function createNotification(PDO $pdo, int $userId, ?int $ticketId, string $title, string $message, ?string $link = null): bool
{
    try {
        initNotificationsTable($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, ticket_id, title, message, link, is_read)
            VALUES (:user_id, :ticket_id, :title, :message, :link, 0)
        ");
        return $stmt->execute([
            ':user_id'   => $userId,
            ':ticket_id' => $ticketId,
            ':title'     => $title,
            ':message'   => $message,
            ':link'      => $link,
        ]);
    } catch (Throwable $e) {
        error_log('createNotification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Broadcast notification to all active administrators
 */
function notifyAdmins(PDO $pdo, ?int $ticketId, string $title, string $message, ?string $link = null, ?int $excludeUserId = null): void
{
    try {
        initNotificationsTable($pdo);

        $sql = "SELECT id FROM users WHERE role = 'admin' AND is_active = 1";
        if ($excludeUserId) {
            $sql .= " AND id != " . (int) $excludeUserId;
        }
        $stmt = $pdo->query($sql);
        $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($adminIds as $adminId) {
            createNotification($pdo, (int) $adminId, $ticketId, $title, $message, $link);
        }
    } catch (Throwable $e) {
        error_log('notifyAdmins error: ' . $e->getMessage());
    }
}

/**
 * Get count of unread notifications for a user
 */
function getUnreadNotificationCount(PDO $pdo, int $userId): int
{
    try {
        initNotificationsTable($pdo);

        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM notifications 
            WHERE user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([':user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

/**
 * Get recent notifications for dropdown or widget
 */
function getRecentNotifications(PDO $pdo, int $userId, int $limit = 6): array
{
    try {
        initNotificationsTable($pdo);

        $stmt = $pdo->prepare("
            SELECT id, ticket_id, title, message, link, is_read, created_at
            FROM notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

