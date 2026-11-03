<?php
/**
 * Ticket Listing & Search
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'employee';

$pageTitle = $userRole === 'admin' ? 'All Tickets' : ($userRole === 'agent' ? 'Assigned Tickets' : 'My Support Tickets');
$activeNav = 'tickets';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);

$allowedStatuses = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$allowedPriorities = ['critical', 'high', 'medium', 'low'];
if (!in_array($priority, $allowedPriorities, true)) {
    $priority = '';
}

try {
    // Fetch categories for filter dropdown
    $catStmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // Build tickets query
    $sql = '
        SELECT
            t.id,
            t.ticket_number,
            t.subject,
            t.priority,
            t.status,
            t.created_at,
            t.updated_at,
            c.name AS category_name,
            u.name AS creator_name,
            u.email AS creator_email,
            a.name AS agent_name
        FROM tickets t
        INNER JOIN categories c ON c.id = t.category_id
        INNER JOIN users u ON u.id = t.created_by
        LEFT JOIN users a ON a.id = t.assigned_to
        WHERE 1 = 1
    ';

    $params = [];

    // Role-based visibility
    if ($userRole === 'employee') {
        $sql .= ' AND t.created_by = :user_id';
        $params['user_id'] = $userId;
    } elseif ($userRole === 'agent') {
        $sql .= ' AND t.assigned_to = :user_id';
        $params['user_id'] = $userId;
    }

    // Search query
    if ($search !== '') {
        $sql .= ' AND (t.ticket_number LIKE :search_num OR t.subject LIKE :search_subj OR u.name LIKE :search_creator)';
        $searchTerm = '%' . $search . '%';
        $params['search_num'] = $searchTerm;
        $params['search_subj'] = $searchTerm;
        $params['search_creator'] = $searchTerm;
    }

    // Filter by status
    if ($status !== '') {
        $sql .= ' AND t.status = :status';
        $params['status'] = $status;
    }

    // Filter by priority
    if ($priority !== '') {
        $sql .= ' AND t.priority = :priority';
        $params['priority'] = $priority;
    }

    // Filter by category
    if ($categoryId && $categoryId > 0) {
        $sql .= ' AND t.category_id = :category_id';
        $params['category_id'] = $categoryId;
    }

    $sql .= ' ORDER BY t.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $tickets = [];
    $categories = [];
    $errorMessage = 'Unable to load tickets. Please check the database.';
}

require_once __DIR__ . '/../../app/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($pageTitle) ?></h1>
        <p class="page-subtitle">
            Showing <?= count($tickets) ?> <?= count($tickets) === 1 ? 'ticket' : 'tickets' ?> matching your criteria.
        </p>
    </div>

    <div>
        <?php if ($userRole === 'employee'): ?>
            <a class="btn btn-primary btn-lg" href="create.php">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Create New Ticket
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error">
        <div class="alert-content"><?= e($errorMessage) ?></div>
    </div>
<?php endif; ?>

<!-- Filter & Search Toolbar -->
<form method="GET" action="index.php" class="filter-bar">
    <div class="form-group" style="margin-bottom: 0;">
        <label for="search">Search Keywords</label>
        <input
            type="search"
            id="search"
            name="search"
            value="<?= e($search) ?>"
            placeholder="Search by ticket #, subject, or requester..."
        >
    </div>

    <div class="form-group" style="margin-bottom: 0;">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All Statuses</option>
            <?php foreach ($allowedStatuses as $opt): ?>
                <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>>
                    <?= e(ucwords(str_replace('_', ' ', $opt))) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="margin-bottom: 0;">
        <label for="priority">Priority</label>
        <select id="priority" name="priority">
            <option value="">All Priorities</option>
            <?php foreach ($allowedPriorities as $p): ?>
                <option value="<?= e($p) ?>" <?= $priority === $p ? 'selected' : '' ?>>
                    <?= e(ucfirst($p)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group" style="margin-bottom: 0;">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display: flex; gap: 8px;">
        <button type="submit" class="btn btn-primary" style="height: 42px;">
            Filter
        </button>
        <?php if ($search !== '' || $status !== '' || $priority !== '' || $categoryId): ?>
            <a href="index.php" class="btn btn-secondary" style="height: 42px;" title="Reset filters">
                Clear
            </a>
        <?php endif; ?>
    </div>
</form>

<!-- Tickets Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-wrapper" style="border: none; box-shadow: none;">
        <table>
            <thead>
                <tr>
                    <th>Ticket No.</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created</th>
                    <?php if ($userRole !== 'employee'): ?>
                        <th>Requester</th>
                        <th>Assigned Agent</th>
                    <?php endif; ?>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="<?= $userRole === 'employee' ? 7 : 9 ?>" style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
                            <div style="max-width: 380px; margin: 0 auto;">
                                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 12px; color: var(--text-light);"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                <p style="font-size: 16px; font-weight: 600; color: var(--text-main); margin-bottom: 4px;">No support tickets found</p>
                                <p style="font-size: 13px; color: var(--text-muted);">
                                    <?= ($search !== '' || $status !== '') ? 'Try clearing or modifying your search filters.' : 'There are currently no tickets in this view.' ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <a href="show.php?id=<?= (int) $t['id'] ?>" class="ticket-num-link">
                                        <?= e($t['ticket_number']) ?>
                                    </a>
                                    <button 
                                        type="button" 
                                        data-copy="<?= e($t['ticket_number']) ?>" 
                                        style="background:none; border:none; color:var(--text-light); cursor:pointer; font-size:12px; padding:2px;" 
                                        title="Copy reference"
                                    >
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    </button>
                                </div>
                            </td>

                            <td>
                                <div class="ticket-subject-cell" title="<?= e($t['subject']) ?>">
                                    <a href="show.php?id=<?= (int) $t['id'] ?>" style="color:inherit; font-weight: 600;">
                                        <?= e($t['subject']) ?>
                                    </a>
                                </div>
                            </td>

                            <td><?= e($t['category_name']) ?></td>
                            <td><?= renderPriorityBadge($t['priority']) ?></td>
                            <td><?= renderStatusBadge($t['status']) ?></td>
                            <td><span title="<?= e($t['created_at']) ?>"><?= timeAgo($t['created_at']) ?></span></td>

                            <?php if ($userRole !== 'employee'): ?>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <?= renderAvatar($t['creator_name'], 24) ?>
                                        <span><?= e($t['creator_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($t['agent_name'])): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <?= renderAvatar($t['agent_name'], 24) ?>
                                            <span><?= e($t['agent_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge" style="background:#f1f5f9; color:var(--text-muted);">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <td style="text-align: right;">
                                <a href="show.php?id=<?= (int) $t['id'] ?>" class="btn btn-secondary btn-sm">
                                    Details &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>
