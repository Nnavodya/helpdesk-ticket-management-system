<?php
/**
 * User Notifications Inbox Page
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$pageTitle = 'My Notifications';
$activeNav = 'notifications';

try {
    initNotificationsTable($pdo);

    $stmt = $pdo->prepare('
        SELECT n.*, t.ticket_number
        FROM notifications n
        LEFT JOIN tickets t ON n.ticket_id = t.id
        WHERE n.user_id = :user_id
        ORDER BY n.created_at DESC
        LIMIT 100
    ');
    $stmt->execute([':user_id' => $userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $unreadCount = getUnreadNotificationCount($pdo, $userId);

} catch (PDOException $e) {
    error_log('Notifications page error: ' . $e->getMessage());
    $notifications = [];
    $unreadCount = 0;
    setFlash('error', 'Could not load notifications. Please check your database.');
}

require_once __DIR__ . '/../app/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Notification Center</h1>
        <p class="page-subtitle">Stay updated on ticket assignments, status updates, and staff replies.</p>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
        <?php if ($unreadCount > 0): ?>
            <form method="POST" action="<?= e(appUrl('actions/notification_action.php')) ?>" style="margin: 0;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <input type="hidden" name="redirect" value="<?= e(appUrl('notifications.php')) ?>">
                <button type="submit" class="btn btn-secondary btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden; max-width: 900px; margin: 0 auto;">
    <?php if (empty($notifications)): ?>
        <div style="text-align: center; color: var(--text-muted); padding: 50px 20px;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #eef2ff; color: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            </div>
            <h3 style="font-size: 17px; font-weight: 600; color: var(--text-main); margin-bottom: 6px;">All Caught Up!</h3>
            <p style="font-size: 13px; color: var(--text-muted);">You have no notifications at this time.</p>
        </div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($notifications as $n): 
                $isRead = (int) $n['is_read'] === 1;
                $targetLink = !empty($n['link']) ? e($n['link']) : '#';
            ?>
                <div class="notification-row <?= !$isRead ? 'unread' : '' ?>" style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 22px; border-bottom: 1px solid var(--border); transition: background 0.15s; background: <?= !$isRead ? '#f8faff' : '#ffffff' ?>;">
                    <div style="display: flex; gap: 14px; align-items: flex-start; flex: 1;">
                        <span class="notification-dot" style="margin-top: 6px; width: 8px; height: 8px; border-radius: 50%; background-color: <?= !$isRead ? 'var(--primary)' : 'transparent' ?>; display: inline-block; flex-shrink: 0;"></span>

                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <strong style="font-size: 14px; color: var(--text-main);"><?= e($n['title']) ?></strong>
                                <?php if (!empty($n['ticket_number'])): ?>
                                    <span class="badge" style="font-size: 11px; padding: 2px 6px; background: #eef2ff; color: var(--primary); font-family: monospace;">
                                        <?= e($n['ticket_number']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 6px;">
                                <?= e($n['message']) ?>
                            </p>

                            <div style="display: flex; align-items: center; gap: 12px; font-size: 12px; color: var(--text-light);">
                                <span title="<?= e($n['created_at']) ?>"><?= timeAgo($n['created_at']) ?></span>
                                <?php if (!empty($n['link'])): ?>
                                    <span>&bull;</span>
                                    <a href="<?= $targetLink ?>" style="font-weight: 600; color: var(--primary);">
                                        View Ticket Details &rarr;
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!$isRead): ?>
                        <form method="POST" action="<?= e(appUrl('actions/notification_action.php')) ?>" style="margin: 0; flex-shrink: 0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                            <input type="hidden" name="redirect" value="<?= e(appUrl('notifications.php')) ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" title="Mark as read" style="padding: 4px 10px; font-size: 12px;">
                                ✓ Mark read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
