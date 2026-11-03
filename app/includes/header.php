<?php
/**
 * Global Header Template
 * Help Desk & Ticket Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Help Desk & Ticket Management System';
$activeNav = $activeNav ?? '';

$isLoggedIn = isset($_SESSION['user_id']);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'employee';

// Base application path for links
$baseUri = '/helpdesk-system/public';

$unreadNotifCount = 0;
$recentNotifs = [];
if ($isLoggedIn) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/database.php';
    }
    $unreadNotifCount = getUnreadNotificationCount($pdo, $userId);
    $recentNotifs = getRecentNotifications($pdo, $userId, 5);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($pageTitle) ?> | Help Desk</title>
    
    <!-- Google Fonts for clean SaaS typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Master CSS -->
    <link rel="stylesheet" href="<?= $baseUri ?>/assets/css/style.css?v=14">
</head>
<body>

<header class="app-navbar">
    <div class="navbar-container">
        <a href="<?= $isLoggedIn ? $baseUri . '/dashboard.php' : $baseUri . '/index.php' ?>" class="navbar-brand">
            <span class="brand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </span>
            <span>HelpDesk Pro</span>
        </a>

        <?php if ($isLoggedIn): ?>
            <nav>
                <ul class="navbar-links">
                    <li>
                        <a href="<?= $baseUri ?>/dashboard.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                            Dashboard
                        </a>
                    </li>

                    <li>
                        <a href="<?= $baseUri ?>/tickets/index.php" class="<?= $activeNav === 'tickets' ? 'active' : '' ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            <?= $userRole === 'admin' ? 'All Tickets' : ($userRole === 'agent' ? 'Assigned Tickets' : 'My Tickets') ?>
                        </a>
                    </li>

                    <?php if ($userRole === 'employee'): ?>
                        <li>
                            <a href="<?= $baseUri ?>/tickets/create.php" class="<?= $activeNav === 'create_ticket' ? 'active' : '' ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                New Ticket
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($userRole === 'admin'): ?>
                        <li>
                            <a href="<?= $baseUri ?>/admin/assignments.php" class="<?= $activeNav === 'assignments' ? 'active' : '' ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                                Assignments
                            </a>
                        </li>
                        <li>
                            <a href="<?= $baseUri ?>/admin/users.php" class="<?= $activeNav === 'users' ? 'active' : '' ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                Users
                            </a>
                        </li>
                        <li>
                            <a href="<?= $baseUri ?>/admin/reports.php" class="<?= $activeNav === 'reports' ? 'active' : '' ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                                Reports & Analytics
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="navbar-actions">
                <!-- In-App Notification Bell -->
                <div class="notification-wrapper">
                    <button type="button" class="notification-btn" id="notifBtn" aria-label="Notifications" title="Notifications">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <?php if ($unreadNotifCount > 0): ?>
                            <span class="notification-badge"><?= $unreadNotifCount > 9 ? '9+' : $unreadNotifCount ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="notification-dropdown" id="notifDropdown">
                        <div class="notification-dropdown-header">
                            <h4>Notifications</h4>
                            <?php if ($unreadNotifCount > 0): ?>
                                <button type="button" id="dropdownMarkAllRead" data-csrf="<?= e(csrfToken()) ?>" style="background:none; border:none; font-size:12px; color:var(--primary); font-weight:600; cursor:pointer;">
                                    Mark all read
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="notification-dropdown-list">
                            <?php if (empty($recentNotifs)): ?>
                                <div style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 13px;">
                                    No notifications yet.
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentNotifs as $rn): 
                                    $isItemRead = (int)$rn['is_read'] === 1;
                                    $itemLink = !empty($rn['link']) ? e($rn['link']) : $baseUri . '/notifications.php';
                                ?>
                                    <a href="<?= $itemLink ?>" class="notification-item <?= !$isItemRead ? 'unread' : '' ?>">
                                        <span class="notification-dot" style="margin-top: 5px; width: 6px; height: 6px; border-radius: 50%; background: <?= !$isItemRead ? 'var(--primary)' : 'transparent' ?>; flex-shrink: 0;"></span>
                                        <div class="notification-item-content">
                                            <div class="notification-item-title"><?= e($rn['title']) ?></div>
                                            <div class="notification-item-msg"><?= e($rn['message']) ?></div>
                                            <div class="notification-item-time"><?= timeAgo($rn['created_at']) ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="notification-dropdown-footer">
                            <a href="<?= $baseUri ?>/notifications.php" style="color: var(--primary);">View all notifications &rarr;</a>
                        </div>
                    </div>
                </div>

                <div class="user-profile-badge">
                    <?= renderAvatar($userName, 32) ?>
                    <div class="user-info">
                        <span class="user-info-name"><?= e($userName) ?></span>
                        <span class="user-info-role"><?= renderRoleBadge($userRole) ?></span>
                    </div>
                </div>

                <form method="POST" action="<?= $baseUri ?>/logout.php" style="display:inline; margin:0;">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-secondary btn-sm" title="Log out of Help Desk">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        Logout
                    </button>
                </form>

                <button class="mobile-toggle" aria-label="Toggle navigation menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
            </div>
        <?php else: ?>
            <div class="navbar-actions">
                <a href="<?= $baseUri ?>/login.php" class="btn btn-secondary btn-sm">Login</a>
                <a href="<?= $baseUri ?>/register.php" class="btn btn-primary btn-sm">Register</a>
            </div>
        <?php endif; ?>
    </div>
</header>

<main class="page-container">
    <!-- Render queued flash alerts -->
    <?= renderFlash() ?>
