<?php
/**
 * Public Landing Page & Ticket Tracker Portal
 * IT Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../app/includes/session.php';

// Ticket status is only available to authenticated users.
$lookupNumber = is_string($_GET['ticket_number'] ?? null)
    ? trim($_GET['ticket_number'])
    : '';
if ($lookupNumber !== '' && !isset($_SESSION['user_id'])) {
    $_SESSION['pending_ticket_lookup'] = $lookupNumber;
    $_SESSION['error'] = 'Please sign in to view ticket status.';
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/includes/functions.php';

$pageTitle = 'Enterprise IT Support Portal';
$activeNav = 'home';

// Handle quick ticket tracking lookup
$lookupResult = null;
$lookupError = '';

if ($lookupNumber !== '') {
    try {
        $sql = '
            SELECT 
                t.ticket_number,
                t.subject,
                t.status,
                t.priority,
                t.created_at,
                t.updated_at,
                t.resolved_at,
                c.name AS category_name
            FROM tickets t
            INNER JOIN categories c ON c.id = t.category_id
            WHERE t.ticket_number = :ticket_number
        ';
        $params = [':ticket_number' => $lookupNumber];
        $userId = (int) $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'] ?? '';

        if ($userRole === 'employee') {
            $sql .= ' AND t.created_by = :requester_user_id';
            $params[':requester_user_id'] = $userId;
        } elseif ($userRole === 'agent') {
            $sql .= ' AND (t.assigned_to = :assigned_user_id OR t.created_by = :requester_user_id)';
            $params[':assigned_user_id'] = $userId;
            $params[':requester_user_id'] = $userId;
        } elseif ($userRole !== 'admin') {
            $lookupError = 'No ticket found, or you do not have permission to view it.';
        }

        if ($lookupError === '') {
            $sql .= ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $lookupResult = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lookupResult) {
                $lookupError = 'No ticket found, or you do not have permission to view it.';
            }
        }
    } catch (PDOException $e) {
        error_log('Ticket lookup error: ' . $e->getMessage());
        $lookupError = 'Unable to look up ticket at this moment. Please try again.';
    }
}

require_once __DIR__ . '/../app/includes/header.php';
?>

<!-- Hero Banner -->
<section class="portal-hero">
    <h1>IT Help Desk & Support Portal</h1>
    <p>
        Submit IT support requests, track ongoing resolutions, and collaborate with enterprise support agents in real time.
    </p>

    <!-- Quick Ticket Status Tracker -->
    <div class="lookup-card">
        <form method="GET" action="index.php" class="lookup-form">
            <input 
                type="text" 
                name="ticket_number" 
                value="<?= e($lookupNumber) ?>" 
                placeholder="Enter Ticket Reference (e.g. HD-20261102-AB12CD)" 
                required
            >
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                Track Status
            </button>
        </form>
    </div>
</section>

<!-- Lookup Results Display -->
<?php if ($lookupError !== ''): ?>
    <div class="alert alert-error">
        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        <div class="alert-content"><?= e($lookupError) ?></div>
    </div>
<?php elseif ($lookupResult): ?>
    <section class="card" style="border-top: 4px solid var(--primary);">
        <div class="card-header">
            <div>
                <span class="badge" style="background:#eef2ff; color:var(--primary); font-family:monospace; font-size:14px;">
                    <?= e($lookupResult['ticket_number']) ?>
                </span>
                <h2 style="font-size:20px; margin-top:8px;"><?= e($lookupResult['subject']) ?></h2>
            </div>
            <div>
                <?= renderStatusBadge($lookupResult['status']) ?>
            </div>
        </div>

        <div class="meta-grid" style="margin-bottom: 20px;">
            <div class="meta-item">
                <div class="meta-label">Category</div>
                <div class="meta-value"><?= e($lookupResult['category_name']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Priority</div>
                <div class="meta-value"><?= renderPriorityBadge($lookupResult['priority']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Submitted On</div>
                <div class="meta-value"><?= formatDateTime($lookupResult['created_at']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Last Updated</div>
                <div class="meta-value"><?= timeAgo($lookupResult['updated_at']) ?></div>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 14px 18px; border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <span style="color: var(--text-muted); font-size: 13px;">
                Want to read comments or update this ticket?
            </span>
            <a href="login.php" class="btn btn-primary btn-sm">
                Log In to View Discussion &rarr;
            </a>
        </div>
    </section>
<?php endif; ?>

<!-- Feature Highlights -->
<div class="portal-features-grid">
    <div class="feature-box">
        <div class="feature-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        </div>
        <h3 class="feature-title">Fast Ticket Creation</h3>
        <p class="feature-desc">
            Easily report technical issues across hardware, software, network, and account access with clear priority levels.
        </p>
    </div>

    <div class="feature-box">
        <div class="feature-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
        <h3 class="feature-title">Dedicated Support Agents</h3>
        <p class="feature-desc">
            Tickets are assigned to qualified support engineers for prompt diagnosis, status updates, and direct messaging.
        </p>
    </div>

    <div class="feature-box">
        <div class="feature-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
        </div>
        <h3 class="feature-title">Activity Timeline & Auditing</h3>
        <p class="feature-desc">
            Every status change, assignment, and comment is permanently recorded in a transparent audit history log.
        </p>
    </div>
</div>

<!-- Quick Action Card -->
<section class="card" style="text-align: center; padding: 40px 24px;">
    <h2 style="font-size: 22px; margin-bottom: 8px;">Ready to submit a support request?</h2>
    <p style="color: var(--text-muted); max-width: 520px; margin: 0 auto 24px;">
        Join your organizational workspace to submit tickets, track IT hardware/software assets, and resolve issues faster.
    </p>
    <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
        <a href="register.php" class="btn btn-primary btn-lg">Create Employee Account</a>
        <a href="login.php" class="btn btn-secondary btn-lg">Staff Sign In</a>
    </div>
</section>

<?php require_once __DIR__ . '/../app/includes/footer.php'; ?>
