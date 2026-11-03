<?php
/**
 * User Dashboard
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'employee';

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

try {
    // 1. Calculate Dashboard Statistics
    if ($userRole === 'admin') {
        $ticketSql = '
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = "open"), 0) AS open_count,
                COALESCE(SUM(status = "in_progress"), 0) AS progress_count,
                COALESCE(SUM(status = "resolved"), 0) AS resolved_count
            FROM tickets
        ';
        $stmt = $pdo->query($ticketSql);
    } elseif ($userRole === 'agent') {
        $ticketSql = '
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = "open"), 0) AS open_count,
                COALESCE(SUM(status = "in_progress"), 0) AS progress_count,
                COALESCE(SUM(status = "resolved"), 0) AS resolved_count
            FROM tickets
            WHERE assigned_to = :user_id
        ';
        $stmt = $pdo->prepare($ticketSql);
        $stmt->execute(['user_id' => $userId]);
    } else {
        $ticketSql = '
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = "open"), 0) AS open_count,
                COALESCE(SUM(status = "in_progress"), 0) AS progress_count,
                COALESCE(SUM(status = "resolved"), 0) AS resolved_count
            FROM tickets
            WHERE created_by = :user_id
        ';
        $stmt = $pdo->prepare($ticketSql);
        $stmt->execute(['user_id' => $userId]);
    }

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch Recent 5 Tickets for Fast Access
    $recentSql = '
        SELECT
            t.id,
            t.ticket_number,
            t.subject,
            t.priority,
            t.status,
            t.created_at,
            c.name AS category_name,
            u.name AS creator_name,
            a.name AS agent_name
        FROM tickets t
        INNER JOIN categories c ON c.id = t.category_id
        INNER JOIN users u ON u.id = t.created_by
        LEFT JOIN users a ON a.id = t.assigned_to
        WHERE 1 = 1
    ';

    $recentParams = [];
    if ($userRole === 'employee') {
        $recentSql .= ' AND t.created_by = :user_id';
        $recentParams['user_id'] = $userId;
    } elseif ($userRole === 'agent') {
        $recentSql .= ' AND t.assigned_to = :user_id';
        $recentParams['user_id'] = $userId;
    }

    $recentSql .= ' ORDER BY t.created_at DESC LIMIT 5';
    $recentStmt = $pdo->prepare($recentSql);
    $recentStmt->execute($recentParams);
    $recentTickets = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $stats = ['total' => 0, 'open_count' => 0, 'progress_count' => 0, 'resolved_count' => 0];
    $recentTickets = [];
    $errorMessage = 'Could not load ticket statistics. Please check the database.';
}

require_once __DIR__ . '/../app/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card" style="background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%); border-left: 5px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <?= renderAvatar($userName, 52) ?>
            <div>
                <h1 style="font-size: 22px; font-weight: 700; color: var(--text-main);">
                    Welcome back, <?= e($userName) ?>!
                </h1>
                <p style="color: var(--text-muted); font-size: 14px; margin-top: 2px;">
                    Role: <strong><?= e(ucfirst($userRole)) ?></strong> &bull; <?= date('l, F j, Y') ?>
                </p>
            </div>
        </div>

        <div>
            <?php if ($userRole === 'employee'): ?>
                <a class="btn btn-primary btn-lg" href="tickets/create.php">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    New Support Ticket
                </a>
            <?php elseif ($userRole === 'admin'): ?>
                <a class="btn btn-primary" href="admin/assignments.php">
                    Assign Pending Tickets
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error">
        <div class="alert-content"><?= e($errorMessage) ?></div>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<section class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Total Tickets</div>
            <div class="stat-value"><?= (int) $stats['total'] ?></div>
        </div>
        <div class="stat-icon total">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Open Tickets</div>
            <div class="stat-value" style="color: var(--status-open);"><?= (int) $stats['open_count'] ?></div>
        </div>
        <div class="stat-icon open">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">In Progress</div>
            <div class="stat-value" style="color: var(--status-progress);"><?= (int) $stats['progress_count'] ?></div>
        </div>
        <div class="stat-icon progress">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Resolved</div>
            <div class="stat-value" style="color: var(--status-resolved);"><?= (int) $stats['resolved_count'] ?></div>
        </div>
        <div class="stat-icon resolved">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        </div>
    </div>
</section>

<!-- Quick Action Shortcuts -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Quick Actions & Navigation</h2>
    </div>

    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php if ($userRole === 'employee'): ?>
            <a class="btn btn-primary" href="tickets/create.php">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Create New Ticket
            </a>
            <a class="btn btn-secondary" href="tickets/index.php">
                View My Tickets (<?= (int)$stats['total'] ?>)
            </a>

        <?php elseif ($userRole === 'agent'): ?>
            <a class="btn btn-primary" href="tickets/index.php">
                View My Assigned Tickets
            </a>
            <a class="btn btn-secondary" href="tickets/index.php?status=in_progress">
                Active In-Progress (<?= (int)$stats['progress_count'] ?>)
            </a>

        <?php elseif ($userRole === 'admin'): ?>
            <a class="btn btn-secondary" href="tickets/index.php">
                All Tickets (<?= (int)$stats['total'] ?>)
            </a>
            <a class="btn btn-primary" href="admin/assignments.php">
                Assign Tickets
            </a>
            <a class="btn btn-secondary" href="admin/users.php">
                Manage Users
            </a>
            <a class="btn btn-secondary" href="admin/reports.php">
                Reports & Analytics
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Tickets Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Recent Tickets</h2>
            <span class="form-hint">Displaying the most recent support requests</span>
        </div>
        <a href="tickets/index.php" class="btn btn-secondary btn-sm">
            View All Tickets &rarr;
        </a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Ticket Ref</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentTickets)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 28px;">
                            No tickets found. Need assistance? <a href="tickets/create.php">Create a ticket now</a>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentTickets as $ticket): ?>
                        <tr>
                            <td>
                                <a href="tickets/show.php?id=<?= (int) $ticket['id'] ?>" class="ticket-num-link">
                                    <?= e($ticket['ticket_number']) ?>
                                </a>
                            </td>
                            <td>
                                <div class="ticket-subject-cell" title="<?= e($ticket['subject']) ?>">
                                    <?= e($ticket['subject']) ?>
                                </div>
                            </td>
                            <td><?= e($ticket['category_name']) ?></td>
                            <td><?= renderPriorityBadge($ticket['priority']) ?></td>
                            <td><?= renderStatusBadge($ticket['status']) ?></td>
                            <td><span title="<?= e($ticket['created_at']) ?>"><?= timeAgo($ticket['created_at']) ?></span></td>
                            <td>
                                <a href="tickets/show.php?id=<?= (int) $ticket['id'] ?>" class="btn btn-secondary btn-sm">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
