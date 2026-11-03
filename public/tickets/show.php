<?php
/**
 * Ticket Details, Comments, and Activity Timeline
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

requireLogin();

$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$ticketId || $ticketId < 1) {
    http_response_code(404);
    setFlash('error', 'Ticket not found.');
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'employee';

// Fetch ticket details with RBAC visibility
$sql = "
    SELECT
        t.*,
        c.name AS category_name,
        requester.name AS requester_name,
        requester.email AS requester_email,
        agent.name AS agent_name,
        agent.email AS agent_email
    FROM tickets t
    JOIN categories c ON t.category_id = c.id
    JOIN users requester ON t.created_by = requester.id
    LEFT JOIN users agent ON t.assigned_to = agent.id
    WHERE t.id = :ticket_id
";

$params = [':ticket_id' => $ticketId];

if ($userRole === 'employee') {
    $sql .= " AND t.created_by = :user_id";
    $params[':user_id'] = $userId;
} elseif ($userRole === 'agent') {
    $sql .= " AND (t.assigned_to = :assigned_user_id OR t.created_by = :requester_user_id)";
    $params[':assigned_user_id'] = $userId;
    $params[':requester_user_id'] = $userId;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    http_response_code(404);
    setFlash('error', 'Ticket not found or you do not have permission to view it.');
    header('Location: index.php');
    exit;
}

$pageTitle = 'Ticket ' . $ticket['ticket_number'];
$activeNav = 'tickets';

// Fetch Comments
$commentStmt = $pdo->prepare("
    SELECT
        tc.id,
        tc.body,
        tc.created_at,
        u.name AS commenter_name,
        u.role AS commenter_role
    FROM ticket_comments tc
    JOIN users u ON tc.user_id = u.id
    WHERE tc.ticket_id = :ticket_id
    ORDER BY tc.created_at ASC
");
$commentStmt->execute([':ticket_id' => $ticketId]);
$comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Activity History
$historyStmt = $pdo->prepare("
    SELECT
        th.action_type,
        th.old_value,
        th.new_value,
        th.note,
        th.created_at,
        u.name AS changed_by_name,
        u.role AS changed_by_role
    FROM ticket_history th
    JOIN users u ON th.changed_by = u.id
    WHERE th.ticket_id = :ticket_id
    ORDER BY th.created_at DESC
");
$historyStmt->execute([':ticket_id' => $ticketId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Attachments if table exists
$attachments = [];
if (tableExists($pdo, 'ticket_attachments')) {
    $attachStmt = $pdo->prepare("
        SELECT ta.*, u.name AS uploader_name
        FROM ticket_attachments ta
        JOIN users u ON ta.uploaded_by = u.id
        WHERE ta.ticket_id = :ticket_id
        ORDER BY ta.created_at ASC
    ");
    $attachStmt->execute([':ticket_id' => $ticketId]);
    $attachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../../app/includes/header.php';
?>

<!-- Top Breadcrumbs & Reference Header -->
<div class="page-header" style="margin-bottom: 20px;">
    <div>
        <a href="index.php" style="display:inline-flex; align-items:center; gap:6px; color:var(--text-muted); font-size:13px; margin-bottom:8px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Tickets
        </a>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span class="badge" style="background:#eef2ff; color:var(--primary); font-family:monospace; font-size:14px; padding:6px 12px;">
                <?= e($ticket['ticket_number']) ?>
            </span>
            <button 
                type="button" 
                class="btn btn-secondary btn-sm" 
                data-copy="<?= e($ticket['ticket_number']) ?>" 
                style="padding: 4px 8px;"
            >
                Copy #
            </button>
            <?= renderStatusBadge($ticket['status']) ?>
            <?= renderPriorityBadge($ticket['priority']) ?>
        </div>
    </div>

    <div>
        <?php if ($userRole === 'admin'): ?>
            <a href="../admin/assignments.php" class="btn btn-secondary btn-sm">
                Reassign Ticket
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="ticket-details-layout">
    <!-- Main Column: Subject, Description, Attachments, Comments -->
    <div>
        <!-- Subject & Description Card -->
        <div class="card">
            <h1 style="font-size: 22px; font-weight: 700; color: var(--text-main); margin-bottom: 12px;">
                <?= e($ticket['subject']) ?>
            </h1>

            <div style="display: flex; gap: 16px; font-size: 13px; color: var(--text-muted); margin-bottom: 18px; border-bottom: 1px solid var(--border); padding-bottom: 14px;">
                <span>Category: <strong><?= e($ticket['category_name']) ?></strong></span>
                <span>&bull;</span>
                <span>Submitted by <strong><?= e($ticket['requester_name']) ?></strong></span>
                <span>&bull;</span>
                <span title="<?= e($ticket['created_at']) ?>"><?= timeAgo($ticket['created_at']) ?></span>
            </div>

            <h3 style="font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 8px;">
                Problem Description
            </h3>

            <div class="ticket-description-box">
                <?= nl2br(e($ticket['description'])) ?>
            </div>

            <!-- Attachments -->
            <?php if (!empty($attachments)): ?>
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border);">
                    <h3 style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 10px;">
                        Attached Files & Screenshots (<?= count($attachments) ?>)
                    </h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                        <?php foreach ($attachments as $att): 
                            $isImg = in_array(strtolower($att['file_type']), ['png', 'jpg', 'jpeg'], true);
                            $fileUrl = '/helpdesk-system/public/' . e($att['file_path']);
                        ?>
                            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 10px; display: flex; align-items: center; gap: 10px; max-width: 320px;">
                                <?php if ($isImg): ?>
                                    <a href="<?= $fileUrl ?>" target="_blank" title="View full image">
                                        <img src="<?= $fileUrl ?>" alt="<?= e($att['file_name']) ?>" style="width: 44px; height: 44px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border);">
                                    </a>
                                <?php else: ?>
                                    <div style="width: 44px; height: 44px; border-radius: 6px; background: #e0e7ff; color: var(--primary); display: flex; align-items: center; justify-content: center;">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                                    </div>
                                <?php endif; ?>

                                <div style="overflow: hidden; flex: 1;">
                                    <a href="<?= $fileUrl ?>" target="_blank" style="font-weight: 600; font-size: 13px; color: var(--primary); display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= e($att['file_name']) ?>
                                    </a>
                                    <span style="font-size: 11px; color: var(--text-muted);">
                                        <?= round($att['file_size'] / 1024, 1) ?> KB &bull; <?= e($att['uploader_name']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Comments & Messages Stream -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Discussion & Communication</h2>
                <span class="badge" style="background: #f1f5f9; color: var(--text-muted);">
                    <?= count($comments) ?> <?= count($comments) === 1 ? 'Message' : 'Messages' ?>
                </span>
            </div>

            <!-- Comment Stream -->
            <div class="comments-stream">
                <?php if (empty($comments)): ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 30px 20px;">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 8px; color: var(--text-light);"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        <p style="font-size: 14px; font-weight: 500;">No comments added yet.</p>
                        <p style="font-size: 12px; color: var(--text-muted);">Use the form below to communicate with the support team.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($comments as $comm): 
                        $isAgent = in_array(strtolower($comm['commenter_role']), ['agent', 'admin'], true);
                    ?>
                        <div class="comment-bubble <?= $isAgent ? 'agent-comment' : '' ?>">
                            <?= renderAvatar($comm['commenter_name'], 36) ?>
                            <div class="comment-bubble-body">
                                <div class="comment-header">
                                    <div class="commenter-name">
                                        <?= e($comm['commenter_name']) ?>
                                        <?= renderRoleBadge($comm['commenter_role']) ?>
                                    </div>
                                    <span class="comment-date" title="<?= e($comm['created_at']) ?>">
                                        <?= timeAgo($comm['created_at']) ?>
                                    </span>
                                </div>
                                <div class="comment-text">
                                    <?= nl2br(e($comm['body'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Add Comment Form -->
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border);">
                <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 12px;">Add a Reply</h3>
                <form method="POST" action="../../app/actions/comment_create.php" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">

                    <div class="form-group">
                        <textarea
                            id="body"
                            name="body"
                            rows="4"
                            maxlength="3000"
                            placeholder="Type your response or update here..."
                            required
                        ></textarea>
                    </div>

                    <!-- Optional attachment with comment -->
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label for="comment_attachment" style="font-size: 12px;">Attach Screenshot / File (Optional)</label>
                        <input 
                            type="file" 
                            id="comment_attachment" 
                            name="attachment" 
                            accept=".png,.jpg,.jpeg,.pdf,.txt,.docx"
                            style="padding: 6px 10px; font-size: 13px;"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        Post Reply
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Sidebar: Status Management, Requester Info, Timeline -->
    <div>
        <!-- Status Management Card: Agents & Admins Only -->
        <?php if (in_array($userRole, ['agent', 'admin'], true)): ?>
            <div class="card" style="border-top: 4px solid var(--primary);">
                <div class="card-header">
                    <h2 class="card-title">Manage Status</h2>
                </div>

                <form method="POST" action="../../app/actions/ticket_update.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">

                    <div class="form-group">
                        <label for="status">Update Ticket Status</label>
                        <select name="status" id="status" required>
                            <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="pending" <?= $ticket['status'] === 'pending' ? 'selected' : '' ?>>Pending (Waiting on User)</option>
                            <option value="resolved" <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Save Status Update
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Ticket Metadata Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Ticket Information</h2>
            </div>

            <!-- Requester -->
            <div style="margin-bottom: 16px;">
                <div class="meta-label" style="font-size:11px; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Requester</div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?= renderAvatar($ticket['requester_name'], 32) ?>
                    <div>
                        <div style="font-weight: 600; font-size: 13px; color: var(--text-main);"><?= e($ticket['requester_name']) ?></div>
                        <div style="font-size: 12px; color: var(--text-muted);"><?= e($ticket['requester_email']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Assigned Agent -->
            <div style="margin-bottom: 16px;">
                <div class="meta-label" style="font-size:11px; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px;">Assigned Agent</div>
                <?php if (!empty($ticket['agent_name'])): ?>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <?= renderAvatar($ticket['agent_name'], 32) ?>
                        <div>
                            <div style="font-weight: 600; font-size: 13px; color: var(--text-main);"><?= e($ticket['agent_name']) ?></div>
                            <div style="font-size: 12px; color: var(--text-muted);"><?= e($ticket['agent_email']) ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span class="badge" style="background:#f1f5f9; color:var(--text-muted);">Unassigned</span>
                        <?php if ($userRole === 'admin'): ?>
                            <a href="../admin/assignments.php" class="btn btn-secondary btn-sm" style="font-size:11px; padding:3px 8px;">Assign &rarr;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Timestamps -->
            <div style="border-top: 1px solid var(--border); padding-top: 14px; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Created:</span>
                    <strong><?= formatDateTime($ticket['created_at']) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Updated:</span>
                    <strong><?= timeAgo($ticket['updated_at']) ?></strong>
                </div>
                <?php if (!empty($ticket['resolved_at'])): ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Resolved:</span>
                        <strong style="color: var(--success);"><?= formatDateTime($ticket['resolved_at']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity History Timeline -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Activity Audit Log</h2>
            </div>

            <?php if (empty($history)): ?>
                <p style="color: var(--text-muted); font-size: 13px;">No history records found.</p>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($history as $h): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <div style="font-weight: 600; color: var(--text-main); margin-bottom: 2px;">
                                    <?= e(ucfirst(str_replace('_', ' ', $h['action_type']))) ?>
                                </div>
                                <div style="color: var(--text-muted); font-size: 12px;">
                                    by <strong><?= e($h['changed_by_name']) ?></strong>
                                    <?php if (!empty($h['note'])): ?>
                                        &bull; <?= e($h['note']) ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($h['old_value'] !== null || $h['new_value'] !== null): ?>
                                    <div style="margin-top: 6px; font-size: 12px; background: #ffffff; padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border);">
                                        <span style="color: var(--text-muted);"><?= e($h['old_value'] ?? 'None') ?></span>
                                        &rarr;
                                        <strong style="color: var(--primary);"><?= e($h['new_value'] ?? 'None') ?></strong>
                                    </div>
                                <?php endif; ?>

                                <div class="timeline-meta">
                                    <span><?= timeAgo($h['created_at']) ?></span>
                                    <span title="<?= e($h['created_at']) ?>"><?= date('h:i A', strtotime($h['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>
