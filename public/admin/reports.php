<?php
/**
 * Admin Reports & Analytics Dashboard
 * IT Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

requireLogin();
requireRole(['admin']);

$pageTitle = 'Reports & Analytics';
$activeNav = 'reports';

// Time range filter
$period = $_GET['period'] ?? 'all';
$dateCondition = '';
$params = [];

if ($period === '7d') {
    $dateCondition = ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($period === '30d') {
    $dateCondition = ' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
} elseif ($period === 'today') {
    $dateCondition = ' AND DATE(t.created_at) = CURDATE()';
}

try {
    // 1. Overall Key Performance Indicators
    $kpiSql = "
        SELECT
            COUNT(*) AS total_tickets,
            COALESCE(SUM(status IN ('resolved', 'closed')), 0) AS resolved_tickets,
            COALESCE(SUM(status IN ('open', 'in_progress', 'pending')), 0) AS pending_tickets,
            COALESCE(SUM(priority = 'critical'), 0) AS critical_tickets,
            COALESCE(AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END), 0) AS avg_resolution_hours
        FROM tickets t
        WHERE 1=1 {$dateCondition}
    ";
    $kpiStmt = $pdo->prepare($kpiSql);
    $kpiStmt->execute($params);
    $kpis = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    $totalTickets = (int) ($kpis['total_tickets'] ?? 0);
    $resolvedTickets = (int) ($kpis['resolved_tickets'] ?? 0);
    $pendingTickets = (int) ($kpis['pending_tickets'] ?? 0);
    $criticalTickets = (int) ($kpis['critical_tickets'] ?? 0);
    $avgHours = round((float) ($kpis['avg_resolution_hours'] ?? 0), 1);

    $resolutionRate = $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100, 1) : 0;

    // 2. Status Breakdown
    $statusSql = "
        SELECT status, COUNT(*) AS count
        FROM tickets t
        WHERE 1=1 {$dateCondition}
        GROUP BY status
    ";
    $statusStmt = $pdo->prepare($statusSql);
    $statusStmt->execute($params);
    $statusRows = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $allStatuses = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
    $statusCounts = [];
    foreach ($allStatuses as $st) {
        $statusCounts[$st] = (int) ($statusRows[$st] ?? 0);
    }

    // 3. Priority Breakdown
    $prioSql = "
        SELECT priority, COUNT(*) AS count
        FROM tickets t
        WHERE 1=1 {$dateCondition}
        GROUP BY priority
    ";
    $prioStmt = $pdo->prepare($prioSql);
    $prioStmt->execute($params);
    $prioRows = $prioStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $allPriorities = ['critical', 'high', 'medium', 'low'];
    $prioCounts = [];
    foreach ($allPriorities as $pr) {
        $prioCounts[$pr] = (int) ($prioRows[$pr] ?? 0);
    }

    // 4. Category Breakdown
    $catSql = "
        SELECT c.name, COUNT(t.id) AS count
        FROM categories c
        LEFT JOIN tickets t ON t.category_id = c.id {$dateCondition}
        GROUP BY c.id, c.name
        ORDER BY count DESC
    ";
    $catStmt = $pdo->prepare($catSql);
    $catStmt->execute($params);
    $categoryData = $catStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Agent Performance
    $agentSql = "
        SELECT
            u.id,
            u.name,
            u.email,
            COUNT(t.id) AS total_assigned,
            COALESCE(SUM(t.status IN ('resolved', 'closed')), 0) AS total_resolved,
            COALESCE(SUM(t.status IN ('open', 'in_progress', 'pending')), 0) AS active_tickets,
            COALESCE(AVG(CASE WHEN t.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) END), 0) AS avg_hours
        FROM users u
        LEFT JOIN tickets t ON t.assigned_to = u.id {$dateCondition}
        WHERE u.role = 'agent' AND u.is_active = 1
        GROUP BY u.id, u.name, u.email
        ORDER BY total_resolved DESC, total_assigned DESC
    ";
    $agentStmt = $pdo->prepare($agentSql);
    $agentStmt->execute($params);
    $agentPerformance = $agentStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Reports query failed: ' . $e->getMessage());
    $errorMessage = 'Failed to load report analytics. Please verify database connection.';
}

require_once __DIR__ . '/../../app/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Help Desk Reports & Analytics</h1>
        <p class="page-subtitle">Real-time performance metrics, ticket volumes, and agent resolution statistics.</p>
    </div>

    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <!-- Filter Period -->
        <form method="GET" action="reports.php" style="display:flex; gap: 8px; align-items:center; margin:0;">
            <select name="period" onchange="this.form.submit()" style="padding: 7px 12px; font-size:13px; width:auto;">
                <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All Time</option>
                <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
            </select>
        </form>

        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print
        </button>

        <button type="button" class="btn btn-primary btn-sm" onclick="exportTableToCSV('agentPerfTable', 'agent-performance-report.csv')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export Agent CSV
        </button>
    </div>
</div>

<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error">
        <div class="alert-content"><?= e($errorMessage) ?></div>
    </div>
<?php endif; ?>

<!-- Top KPI Overview Cards -->
<section class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Total Volume</div>
            <div class="stat-value"><?= $totalTickets ?></div>
        </div>
        <div class="stat-icon total">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Resolution Rate</div>
            <div class="stat-value" style="color: var(--success);"><?= $resolutionRate ?>%</div>
        </div>
        <div class="stat-icon resolved">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Active Backlog</div>
            <div class="stat-value" style="color: var(--status-progress);"><?= $pendingTickets ?></div>
        </div>
        <div class="stat-icon progress">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <div class="stat-label">Avg. Resolution Time</div>
            <div class="stat-value"><?= $avgHours > 0 ? $avgHours . 'h' : '—' ?></div>
        </div>
        <div class="stat-icon open">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
    </div>
</section>

<!-- Analytics Grid: Status, Priority, Categories -->
<div class="analytics-grid">
    <!-- Status Breakdown -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Tickets by Status</h2>
            <span class="badge" style="background:#f1f5f9;"><?= $totalTickets ?> Total</span>
        </div>

        <?php 
        $statusColors = [
            'open' => 'var(--status-open)',
            'in_progress' => 'var(--status-progress)',
            'pending' => 'var(--status-pending)',
            'resolved' => 'var(--status-resolved)',
            'closed' => 'var(--status-closed)',
        ];
        foreach ($statusCounts as $stName => $stCount): 
            $pct = $totalTickets > 0 ? round(($stCount / $totalTickets) * 100, 1) : 0;
            $color = $statusColors[$stName] ?? 'var(--primary)';
        ?>
            <div class="progress-bar-container">
                <div class="progress-bar-label">
                    <span><?= renderStatusBadge($stName) ?></span>
                    <span style="color: var(--text-muted); font-size:12px;"><strong><?= $stCount ?></strong> (<?= $pct ?>%)</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width: <?= $pct ?>%; background-color: <?= $color ?>;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Priority Breakdown -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Tickets by Priority</h2>
            <span class="badge" style="background:#fff1f2; color:var(--prio-critical);">
                <?= $criticalTickets ?> Critical
            </span>
        </div>

        <?php 
        $prioColors = [
            'critical' => 'var(--prio-critical)',
            'high' => 'var(--prio-high)',
            'medium' => 'var(--prio-medium)',
            'low' => 'var(--prio-low)',
        ];
        foreach ($prioCounts as $prName => $prCount): 
            $pct = $totalTickets > 0 ? round(($prCount / $totalTickets) * 100, 1) : 0;
            $color = $prioColors[$prName] ?? 'var(--primary)';
        ?>
            <div class="progress-bar-container">
                <div class="progress-bar-label">
                    <span><?= renderPriorityBadge($prName) ?></span>
                    <span style="color: var(--text-muted); font-size:12px;"><strong><?= $prCount ?></strong> (<?= $pct ?>%)</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width: <?= $pct ?>%; background-color: <?= $color ?>;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Category Distribution -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Tickets by Technical Category</h2>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
        <?php foreach ($categoryData as $cat): 
            $catCount = (int) $cat['count'];
            $pct = $totalTickets > 0 ? round(($catCount / $totalTickets) * 100, 1) : 0;
        ?>
            <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px;">
                    <strong style="color: var(--text-main); font-size:14px;"><?= e($cat['name']) ?></strong>
                    <span style="font-size: 16px; font-weight: 700; color: var(--primary);"><?= $catCount ?></span>
                </div>
                <div class="progress-track" style="height: 6px;">
                    <div class="progress-fill" style="width: <?= $pct ?>%; background-color: var(--primary);"></div>
                </div>
                <div style="font-size: 11px; color: var(--text-muted); text-align: right; margin-top: 4px;"><?= $pct ?>% of total</div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Agent Performance Leaderboard Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Support Agent Performance</h2>
        <span class="form-hint">Metrics reflect assigned and resolved tickets</span>
    </div>

    <div class="table-wrapper">
        <table id="agentPerfTable">
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Email</th>
                    <th>Assigned</th>
                    <th>Resolved</th>
                    <th>Active</th>
                    <th>Resolution Rate</th>
                    <th>Avg. Resolution</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agentPerformance)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            No active agents found in the system.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($agentPerformance as $agent): 
                        $totalAssigned = (int) $agent['total_assigned'];
                        $totalRes = (int) $agent['total_resolved'];
                        $active = (int) $agent['active_tickets'];
                        $agentRate = $totalAssigned > 0 ? round(($totalRes / $totalAssigned) * 100, 1) : 0;
                        $agentAvgHours = round((float) $agent['avg_hours'], 1);
                    ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?= renderAvatar($agent['name'], 28) ?>
                                    <strong><?= e($agent['name']) ?></strong>
                                </div>
                            </td>
                            <td><?= e($agent['email']) ?></td>
                            <td><strong><?= $totalAssigned ?></strong></td>
                            <td><span style="color: var(--success); font-weight: 600;"><?= $totalRes ?></span></td>
                            <td><span style="color: var(--status-progress); font-weight: 600;"><?= $active ?></span></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span><?= $agentRate ?>%</span>
                                    <div class="progress-track" style="width: 60px; height: 6px;">
                                        <div class="progress-fill" style="width: <?= $agentRate ?>%; background-color: var(--success);"></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= $agentAvgHours > 0 ? $agentAvgHours . ' hrs' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>
