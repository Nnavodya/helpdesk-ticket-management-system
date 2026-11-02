
<?php
require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/config/database.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'employee';

$success = $_SESSION['ticket_success'] ?? '';
unset($_SESSION['ticket_success']);

$error = $_SESSION['ticket_error'] ?? '';
unset($_SESSION['ticket_error']);

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$allowedStatuses = [
    'open', 'in_progress', 'pending', 'resolved', 'closed'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

try {
    $sql = '
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

    $params = [];

    // Apply role-based ticket visibility.
    if ($userRole === 'employee') {
        $sql .= ' AND t.created_by = :user_id';
        $params['user_id'] = $userId;
    } elseif ($userRole === 'agent') {
        $sql .= ' AND t.assigned_to = :user_id';
        $params['user_id'] = $userId;
    }

    if ($search !== '') {
        $sql .= ' AND (
            t.ticket_number LIKE :search_number
            OR t.subject LIKE :search_subject
        )';

        $searchTerm = '%' . $search . '%';
        $params['search_number'] = $searchTerm;
        $params['search_subject'] = $searchTerm;
    }

    if ($status !== '') {
        $sql .= ' AND t.status = :status';
        $params['status'] = $status;
    }

    $sql .= ' ORDER BY t.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $tickets = [];
    $error = 'Unable to load tickets. Please check the database.';
}

function ticketListEscape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets | Help Desk</title>
    <link rel="stylesheet" href="/helpdesk-system/public/assets/css/style.css?v=4">
</head>
<body>

<nav class="navbar">
    <h2>Help Desk</h2>

    <div>
        <a href="../dashboard.php">Dashboard</a>
        &nbsp; | &nbsp;
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<main class="page-container">

    <section class="card">
        <h1>
            <?php if ($userRole === 'admin'): ?>
                All Tickets
            <?php elseif ($userRole === 'agent'): ?>
                Assigned Tickets
            <?php else: ?>
                My Tickets
            <?php endif; ?>
        </h1>

        <p class="muted">
            Search and review your support tickets.
        </p>

        <?php if ($success !== ''): ?>
            <p class="success">
                <?= ticketListEscape($success) ?>
            </p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <p class="error">
                <?= ticketListEscape($error) ?>
            </p>
        <?php endif; ?>

        <form method="GET" action="index.php" class="filter-form">
            <label for="search">Search</label>

            <input
                type="search"
                id="search"
                name="search"
                value="<?= ticketListEscape($search) ?>"
                placeholder="Ticket number or subject"
            >

            <label for="status">Filter by Status</label>

            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($allowedStatuses as $option): ?>
                    <option
                        value="<?= ticketListEscape($option) ?>"
                        <?= $status === $option ? 'selected' : '' ?>
                    >
                        <?= ticketListEscape(
                            ucwords(str_replace('_', ' ', $option))
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Search Tickets</button>
            <a class="btn" href="index.php">Clear Filters</a>
        </form>

        <?php if ($userRole === 'employee'): ?>
            <p>
                <a class="btn" href="create.php">+ Create New Ticket</a>
            </p>
        <?php endif; ?>

        <div class="table-wrapper">
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
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($tickets) === 0): ?>
                        <tr>
                            <td
                                colspan="<?= $userRole === 'employee' ? 7 : 9 ?>"
                            >
                                No tickets found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td>
                                    <?= ticketListEscape($ticket['ticket_number']) ?>
                                </td>

                                <td>
                                    <?= ticketListEscape($ticket['subject']) ?>
                                </td>

                                <td>
                                    <?= ticketListEscape($ticket['category_name']) ?>
                                </td>

                                <td>
                                    <?= ticketListEscape(ucfirst($ticket['priority'])) ?>
                                </td>

                                <td>
                                    <?= ticketListEscape(
                                        ucwords(str_replace('_', ' ', $ticket['status']))
                                    ) ?>
                                </td>

                                <td>
                                    <?= ticketListEscape($ticket['created_at']) ?>
                                </td>

                                <?php if ($userRole !== 'employee'): ?>
                                    <td>
                                        <?= ticketListEscape($ticket['creator_name']) ?>
                                    </td>

                                    <td>
                                        <?= ticketListEscape(
                                            $ticket['agent_name'] ?? 'Unassigned'
                                        ) ?>
                                    </td>
                                <?php endif; ?>

                                <td>
                                    <a href="show.php?id=<?= (int) $ticket['id'] ?>">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>
</main>

</body>
</html>
