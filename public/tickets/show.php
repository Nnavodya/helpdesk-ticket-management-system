
<?php
require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

requireLogin();

// Initialize CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Read messages from session.
$ticketSuccess = $_SESSION['ticket_success'] ?? '';
$ticketError = $_SESSION['ticket_error'] ?? '';

$commentSuccess = $_SESSION['comment_success'] ?? '';
$commentError = $_SESSION['comment_error'] ?? '';

unset(
    $_SESSION['ticket_success'],
    $_SESSION['ticket_error'],
    $_SESSION['comment_success'],
    $_SESSION['comment_error']
);

// Get ticket ID from URL.
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$ticketId || $ticketId < 1) {
    http_response_code(404);
    exit('Ticket not found.');
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

// Helper function for safe HTML output.
function showValue($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Get ticket details with role-based access control.
$sql = "
    SELECT
        t.*,
        c.name AS category_name,
        requester.name AS requester_name,
        requester.email AS requester_email,
        agent.name AS agent_name
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
    $sql .= " AND t.assigned_to = :user_id";
    $params[':user_id'] = $userId;
} elseif ($userRole !== 'admin') {
    http_response_code(403);
    exit('You do not have permission to view this ticket.');
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found or you do not have permission to view it.');
}

// Get ticket comments.
$commentStmt = $pdo->prepare("
    SELECT
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

// Get ticket activity history.
$historyStmt = $pdo->prepare("
    SELECT
        th.action_type,
        th.old_value,
        th.new_value,
        th.note,
        th.created_at,
        u.name AS changed_by_name
    FROM ticket_history th
    JOIN users u ON th.changed_by = u.id
    WHERE th.ticket_id = :ticket_id
    ORDER BY th.created_at DESC
");

$historyStmt->execute([':ticket_id' => $ticketId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ticket Details | Help Desk</title>

    <link rel="stylesheet"
          href="/helpdesk-system/public/assets/css/style.css?v=6">
</head>

<body>

<nav class="navbar">
    <a href="/helpdesk-system/public/dashboard.php">Dashboard</a>
    <a href="/helpdesk-system/public/tickets/index.php">All Tickets</a>

    <?php if ($userRole === 'employee'): ?>
        <a href="/helpdesk-system/public/tickets/create.php">
            Create Ticket
        </a>
    <?php endif; ?>

    <form method="POST"
          action="/helpdesk-system/public/logout.php"
          style="display:inline;">

        <input type="hidden"
               name="csrf_token"
               value="<?= showValue($_SESSION['csrf_token']) ?>">

        <button type="submit">Logout</button>
    </form>
</nav>

<main class="page-container">

    <p>
        <a href="index.php">&larr; Back to Tickets</a>
    </p>

    <!-- Ticket Details -->
    <div class="card">

        <p>
            <strong>Ticket Number:</strong>
            <?= showValue($ticket['ticket_number']) ?>
        </p>

        <h1><?= showValue($ticket['subject']) ?></h1>

        <p>
            <strong>Status:</strong>
            <?= showValue(
                ucwords(str_replace('_', ' ', $ticket['status']))
            ) ?>
        </p>

        <p>
            <strong>Priority:</strong>
            <?= showValue(ucfirst($ticket['priority'])) ?>
        </p>

        <p>
            <strong>Category:</strong>
            <?= showValue($ticket['category_name']) ?>
        </p>

        <hr>

        <h3>Description</h3>

        <p><?= nl2br(showValue($ticket['description'])) ?></p>

        <hr>

        <h3>Requester Information</h3>

        <p>
            <strong>Name:</strong>
            <?= showValue($ticket['requester_name']) ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?= showValue($ticket['requester_email']) ?>
        </p>

        <h3>Assignment</h3>

        <p>
            <strong>Assigned Agent:</strong>
            <?= showValue($ticket['agent_name'] ?? 'Not assigned') ?>
        </p>

        <hr>

        <p>
            <strong>Created:</strong>
            <?= showValue($ticket['created_at']) ?>
        </p>

        <p>
            <strong>Last Updated:</strong>
            <?= showValue($ticket['updated_at']) ?>
        </p>

    </div>

    <!-- Status Update Messages -->
    <?php if ($ticketSuccess !== ''): ?>
        <p class="success">
            <?= showValue($ticketSuccess) ?>
        </p>
    <?php endif; ?>

    <?php if ($ticketError !== ''): ?>
        <p class="error">
            <?= showValue($ticketError) ?>
        </p>
    <?php endif; ?>

    <!-- Status Update Form: Agents and Admins Only -->
    <?php if (in_array($userRole, ['agent', 'admin'], true)): ?>

        <div class="card">

            <h2>Update Ticket Status</h2>

            <form method="POST"
                  action="../../app/actions/ticket_update.php">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= showValue($_SESSION['csrf_token']) ?>">

                <input type="hidden"
                       name="ticket_id"
                       value="<?= (int) $ticket['id'] ?>">

                <label for="status">Select Status</label>

                <select name="status" id="status" required>

                    <option value="open"
                        <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>
                        Open
                    </option>

                    <option value="in_progress"
                        <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>
                        In Progress
                    </option>

                    <option value="pending"
                        <?= $ticket['status'] === 'pending' ? 'selected' : '' ?>>
                        Pending
                    </option>

                    <option value="resolved"
                        <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>>
                        Resolved
                    </option>

                    <option value="closed"
                        <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>
                        Closed
                    </option>

                </select>

                <button type="submit">Update Status</button>

            </form>

        </div>

    <?php endif; ?>

    <!-- Comments -->
    <div class="card">

        <h2>Comments</h2>

        <?php if ($commentSuccess !== ''): ?>
            <p class="success">
                <?= showValue($commentSuccess) ?>
            </p>
        <?php endif; ?>

        <?php if ($commentError !== ''): ?>
            <p class="error">
                <?= showValue($commentError) ?>
            </p>
        <?php endif; ?>

        <h3>Add a Comment</h3>

        <form method="POST"
              action="../../app/actions/comment_create.php">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= showValue($_SESSION['csrf_token']) ?>">

            <input type="hidden"
                   name="ticket_id"
                   value="<?= (int) $ticket['id'] ?>">

            <label for="body">Your Comment</label>

            <textarea
                id="body"
                name="body"
                rows="4"
                maxlength="3000"
                required
                placeholder="Write your comment here..."
            ></textarea>

            <button type="submit">Add Comment</button>

        </form>

        <hr>

        <?php if (empty($comments)): ?>

            <p>No comments have been added yet.</p>

        <?php else: ?>

            <?php foreach ($comments as $comment): ?>

                <div class="comment-item">

                    <p>
                        <strong>
                            <?= showValue($comment['commenter_name']) ?>
                        </strong>

                        (<?= showValue(
                            ucfirst($comment['commenter_role'])
                        ) ?>)
                    </p>

                    <p><?= nl2br(showValue($comment['body'])) ?></p>

                    <small>
                        <?= showValue($comment['created_at']) ?>
                    </small>

                </div>

                <hr>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <!-- Activity History -->
    <div class="card">

        <h2>Activity History</h2>

        <?php if (empty($history)): ?>

            <p>No activity history is available.</p>

        <?php else: ?>

            <?php foreach ($history as $item): ?>

                <div class="history-item">

                    <p>
                        <strong>
                            <?= showValue(
                                ucfirst(
                                    str_replace('_', ' ', $item['action_type'])
                                )
                            ) ?>
                        </strong>

                        by <?= showValue($item['changed_by_name']) ?>
                    </p>

                    <?php if (!empty($item['note'])): ?>
                        <p><?= showValue($item['note']) ?></p>
                    <?php endif; ?>

                    <?php if (
                        $item['old_value'] !== null ||
                        $item['new_value'] !== null
                    ): ?>

                        <p>
                            <strong>Change:</strong>

                            <?= showValue($item['old_value'] ?? 'None') ?>

                            &rarr;

                            <?= showValue($item['new_value'] ?? 'None') ?>
                        </p>

                    <?php endif; ?>

                    <small>
                        <?= showValue($item['created_at']) ?>
                    </small>

                </div>

                <hr>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</main>

</body>
</html>
