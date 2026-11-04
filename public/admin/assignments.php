<?php
/**
 * Ticket Assignment Management
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

requireLogin();
requireRole(['admin']);

$pageTitle = 'Ticket Assignments';
$activeNav = 'assignments';

// Filter
$filter = $_GET['filter'] ?? 'unassigned'; // 'unassigned', 'assigned', 'all'

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($postedToken)) {
        http_response_code(403);
        exit('Invalid security token. Please try again.');
    }

    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $agentInput = $_POST['assigned_to'] ?? '';

    if (!$ticketId || $ticketId < 1) {
        setFlash('error', 'Invalid ticket selected.');
    } elseif (!is_string($agentInput) || ($agentInput !== '' && (!ctype_digit($agentInput) || (int) $agentInput < 1))) {
        setFlash('error', 'Please select a valid agent.');
    } else {
        $agentId = $agentInput === '' ? null : (int) $agentInput;

        try {
            $pdo->beginTransaction();

            $ticketStmt = $pdo->prepare("SELECT id, ticket_number, subject, created_by, assigned_to FROM tickets WHERE id = :ticket_id FOR UPDATE");
            $ticketStmt->execute([':ticket_id' => $ticketId]);
            $ticket = $ticketStmt->fetch(PDO::FETCH_ASSOC);

            if (!$ticket) {
                throw new RuntimeException('Ticket not found.');
            }

            $agentName = 'Unassigned';
            if ($agentId !== null) {
                $agentStmt = $pdo->prepare("SELECT id, name FROM users WHERE id = :agent_id AND role = 'agent' AND is_active = 1");
                $agentStmt->execute([':agent_id' => $agentId]);
                $agent = $agentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$agent) {
                    throw new RuntimeException('Selected agent is invalid or inactive.');
                }
                $agentName = $agent['name'];
            }

            $oldAgentId = $ticket['assigned_to'];
            $oldAgentName = 'None';
            if ($oldAgentId !== null) {
                $oldAgentStmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
                $oldAgentStmt->execute([':id' => $oldAgentId]);
                $oldAgent = $oldAgentStmt->fetch(PDO::FETCH_ASSOC);
                $oldAgentName = $oldAgent['name'] ?? 'Unknown';
            }

            // Update assignment
            $updateStmt = $pdo->prepare("
                UPDATE tickets
                SET assigned_to = :agent_id, updated_at = CURRENT_TIMESTAMP
                WHERE id = :ticket_id
            ");
            $updateStmt->execute([
                ':agent_id'  => $agentId,
                ':ticket_id' => $ticketId,
            ]);

            // Record assignment in history
            $historyStmt = $pdo->prepare("
                INSERT INTO ticket_history (ticket_id, changed_by, action_type, old_value, new_value, note)
                VALUES (:ticket_id, :changed_by, 'assigned', :old_val, :new_val, :note)
            ");
            $note = $agentId === null ? 'Ticket unassigned' : 'Assigned to ' . $agentName;
            $historyStmt->execute([
                ':ticket_id'   => $ticketId,
                ':changed_by'  => (int) $_SESSION['user_id'],
                ':old_val'     => $oldAgentName,
                ':new_val'     => $agentName,
                ':note'        => $note,
            ]);

            $pdo->commit();

            // In-app notifications
            if ($agentId !== null && $agentId !== (int) $_SESSION['user_id']) {
                createNotification(
                    $pdo,
                    $agentId,
                    $ticketId,
                    'Ticket Assigned: ' . $ticket['ticket_number'],
                    'You have been assigned ticket ' . $ticket['ticket_number'] . ' (' . $ticket['subject'] . ')',
                    appUrl('tickets/show.php') . '?id=' . $ticketId
                );
            }

            if (!empty($ticket['created_by']) && (int) $ticket['created_by'] !== (int) $_SESSION['user_id']) {
                createNotification(
                    $pdo,
                    (int) $ticket['created_by'],
                    $ticketId,
                    'Agent Assigned: ' . $ticket['ticket_number'],
                    $note . ' for your ticket ' . $ticket['ticket_number'],
                    appUrl('tickets/show.php') . '?id=' . $ticketId
                );
            }

            setFlash('success', "Ticket {$ticket['ticket_number']} updated: {$note}.");
            header('Location: assignments.php?filter=' . urlencode($filter));
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Assignment error: ' . $e->getMessage());
            setFlash('error', $e->getMessage());
        }
    }
}

try {
    // 1. Fetch Active Support Agents
    $agentsStmt = $pdo->query("SELECT id, name, email FROM users WHERE role = 'agent' AND is_active = 1 ORDER BY name ASC");
    $agents = $agentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Tickets based on filter
    $sql = "
        SELECT
            t.id,
            t.ticket_number,
            t.subject,
            t.priority,
            t.status,
            t.created_at,
            t.assigned_to,
            c.name AS category_name,
            u.name AS requester_name,
            a.name AS agent_name
        FROM tickets t
        JOIN categories c ON t.category_id = c.id
        JOIN users u ON t.created_by = u.id
        LEFT JOIN users a ON t.assigned_to = a.id
        WHERE 1 = 1
    ";

    if ($filter === 'unassigned') {
        $sql .= " AND t.assigned_to IS NULL AND t.status NOT IN ('resolved', 'closed')";
    } elseif ($filter === 'assigned') {
        $sql .= " AND t.assigned_to IS NOT NULL";
    }

    $sql .= " ORDER BY CASE WHEN t.assigned_to IS NULL THEN 0 ELSE 1 END, t.created_at DESC";

    $ticketsStmt = $pdo->query($sql);
    $tickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Unassigned count
    $unassignedCount = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE assigned_to IS NULL AND status NOT IN ('resolved', 'closed')")->fetchColumn();

} catch (PDOException $e) {
    error_log('Assignment data load error: ' . $e->getMessage());
    $agents = [];
    $tickets = [];
    $unassignedCount = 0;
    setFlash('error', 'Unable to load assignment data. Please verify database connection.');
}

require_once __DIR__ . '/../../app/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Ticket Assignments</h1>
        <p class="page-subtitle">Assign incoming IT tickets to certified support agents for prompt resolution.</p>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
        <span class="badge" style="background: <?= $unassignedCount > 0 ? '#fff1f2' : '#ecfdf5' ?>; color: <?= $unassignedCount > 0 ? 'var(--prio-critical)' : 'var(--success)' ?>; font-size: 13px; padding: 6px 12px;">
            <?= $unassignedCount ?> Unassigned Pending
        </span>
        <span class="badge" style="background: #eef2ff; color: var(--primary); font-size: 13px; padding: 6px 12px;">
            <?= count($agents) ?> Active Agents
        </span>
    </div>
</div>

<?php if (empty($agents)): ?>
    <div class="alert alert-warning">
        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <div class="alert-content">
            <strong>No active support agents available!</strong> Go to <a href="users.php" style="text-decoration: underline; font-weight: 600;">Manage Users</a> and change an employee's role to <code>agent</code>.
        </div>
    </div>
<?php endif; ?>

<!-- Filter Tabs & Quick Search -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
    <div style="display: flex; gap: 6px;">
        <a href="assignments.php?filter=unassigned" class="btn <?= $filter === 'unassigned' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            Unassigned Only (<?= $unassignedCount ?>)
        </a>
        <a href="assignments.php?filter=assigned" class="btn <?= $filter === 'assigned' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            Assigned Tickets
        </a>
        <a href="assignments.php?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            All Tickets
        </a>
    </div>

    <div style="max-width: 280px; width: 100%;">
        <input type="search" id="tableSearchLive" placeholder="Live search tickets..." style="padding: 7px 12px; font-size: 13px;">
    </div>
</div>

<!-- Assignments Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-wrapper" style="border: none; box-shadow: none;">
        <table>
            <thead>
                <tr>
                    <th>Ticket Ref</th>
                    <th>Subject & Category</th>
                    <th>Requester</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Current Agent</th>
                    <th style="min-width: 260px;">Assign To</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 36px;">
                            No tickets matching "<?= e($filter) ?>" filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <a href="../tickets/show.php?id=<?= (int) $t['id'] ?>" class="ticket-num-link">
                                    <?= e($t['ticket_number']) ?>
                                </a>
                            </td>

                            <td>
                                <div style="font-weight: 600; color: var(--text-main); margin-bottom: 2px;">
                                    <a href="../tickets/show.php?id=<?= (int) $t['id'] ?>" style="color:inherit;">
                                        <?= e($t['subject']) ?>
                                    </a>
                                </div>
                                <span style="font-size: 12px; color: var(--text-muted);"><?= e($t['category_name']) ?></span>
                            </td>

                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?= renderAvatar($t['requester_name'], 24) ?>
                                    <span><?= e($t['requester_name']) ?></span>
                                </div>
                            </td>

                            <td><?= renderPriorityBadge($t['priority']) ?></td>
                            <td><?= renderStatusBadge($t['status']) ?></td>

                            <td>
                                <?php if (!empty($t['agent_name'])): ?>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <?= renderAvatar($t['agent_name'], 24) ?>
                                        <span style="font-weight: 500;"><?= e($t['agent_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="badge" style="background: #fff1f2; color: var(--danger);">Unassigned</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <form method="POST" action="assignments.php?filter=<?= urlencode($filter) ?>" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">

                                    <select name="assigned_to" style="padding: 6px 10px; font-size: 13px; min-width: 150px;" required>
                                        <option value="">-- Unassigned --</option>
                                        <?php foreach ($agents as $agent): ?>
                                            <option 
                                                value="<?= (int) $agent['id'] ?>"
                                                <?= (int) ($t['assigned_to'] ?? 0) === (int) $agent['id'] ? 'selected' : '' ?>
                                            >
                                                <?= e($agent['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit" class="btn btn-primary btn-sm" <?= empty($agents) ? 'disabled' : '' ?>>
                                        Assign
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>
