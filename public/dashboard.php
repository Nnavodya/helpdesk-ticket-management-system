
<?php
require_once __DIR__ . '/../app/includes/auth.php';
require_once __DIR__ . '/../app/config/database.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'employee';

try {
    // Employees see their own tickets.
    // Agents see tickets assigned to them.
    // Admins see all tickets.
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

} catch (PDOException $e) {
    error_log($e->getMessage());

    $stats = [
        'total' => 0,
        'open_count' => 0,
        'progress_count' => 0,
        'resolved_count' => 0
    ];

    $errorMessage = 'Could not load ticket statistics. Please check the database.';
}

function escapeHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Help Desk</title>

    <link
        rel="stylesheet"
        href="/helpdesk-system/public/assets/css/style.css?v=3"
    >
</head>
<body>

    <nav class="navbar">
        <h2>Help Desk</h2>

        <div>
            <span>
                <?= escapeHtml($userName) ?>
            </span>
            |
            <?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<form method="POST" action="logout.php" style="display:inline;">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= escapeHtml($_SESSION['csrf_token']) ?>"
    >
    <button type="submit">Logout</button>
</form>
        </div>
    </nav>

    <main class="page-container">

        <section class="card">
            <h1>Welcome, <?= escapeHtml($userName) ?>!</h1>

            <p class="muted">
                Your role:
                <strong><?= escapeHtml(ucfirst($userRole)) ?></strong>
            </p>

            <p>
                Welcome to your IT Help Desk dashboard.
                Here you can view and manage support tickets.
            </p>

            <?php if (!empty($errorMessage)): ?>
                <p class="error">
                    <?= escapeHtml($errorMessage) ?>
                </p>
            <?php endif; ?>
        </section>

        <section>
            <h2>Ticket Overview</h2>

            <div class="dashboard-stats">
                <article class="card">
                    <p class="muted">Total Tickets</p>
                    <h2><?= (int) $stats['total'] ?></h2>
                </article>

                <article class="card">
                    <p class="muted">Open Tickets</p>
                    <h2><?= (int) $stats['open_count'] ?></h2>
                </article>

                <article class="card">
                    <p class="muted">In Progress</p>
                    <h2><?= (int) $stats['progress_count'] ?></h2>
                </article>

                <article class="card">
                    <p class="muted">Resolved</p>
                    <h2><?= (int) $stats['resolved_count'] ?></h2>
                </article>
            </div>
        </section>

        <section class="card">
            <h2>Quick Actions</h2>

            <?php if ($userRole === 'employee'): ?>
                <p>Need technical support? Create a new ticket.</p>
                <a class="btn" href="tickets/create.php">
                    Create New Ticket
                </a>
                <a class="btn" href="tickets/index.php">
                    View My Tickets
                </a>

            <?php elseif ($userRole === 'agent'): ?>
                <p>Manage tickets assigned to you.</p>
                <a class="btn" href="tickets/index.php">
                    View Assigned Tickets
                </a>

            <?php elseif ($userRole === 'admin'): ?>
                <p>Manage and monitor the Help Desk system.</p>
                <a class="btn" href="tickets/index.php">
                    View All Tickets
                </a>
                <a class="btn" href="admin/users.php">
                    Manage Users
                </a>
                <a class="btn" href="admin/reports.php">
                    View Reports
                </a>
            <?php endif; ?>
        </section>

    </main>

</body>
</html>
