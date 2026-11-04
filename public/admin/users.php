<?php
/**
 * User Account & Role Management
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

requireLogin();
requireRole(['admin']);

$pageTitle = 'User Management';
$activeNav = 'users';

$allowedRoles = ['employee', 'agent', 'admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        setFlash('error', 'Invalid security token. Please try again.');
        header('Location: ' . appUrl('admin/users.php'));
        exit;
    }

    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $newRole = $_POST['role'] ?? '';
    $newActiveStatus = $_POST['is_active'] ?? '';

    if (!$userId || !in_array($newRole, $allowedRoles, true) || !in_array($newActiveStatus, ['0', '1'], true)) {
        setFlash('error', 'Please select valid user settings.');
        header('Location: ' . appUrl('admin/users.php'));
        exit;
    }

    // Prevent current admin from deactivating self or losing admin role
    if ((int) $userId === (int) $_SESSION['user_id'] && ($newRole !== 'admin' || $newActiveStatus !== '1')) {
        setFlash('error', 'You cannot deactivate your own account or remove your admin role.');
        header('Location: ' . appUrl('admin/users.php'));
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, name, role, is_active FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            setFlash('error', 'Target user not found.');
        } else {
            // Guarantee at least 1 active admin remains
            $isRemovingAdminAccess = $targetUser['role'] === 'admin'
                && (int) $targetUser['is_active'] === 1
                && ($newRole !== 'admin' || $newActiveStatus !== '1');

            if ($isRemovingAdminAccess) {
                $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
                $activeAdminCount = (int) $countStmt->fetchColumn();

                if ($activeAdminCount <= 1) {
                    setFlash('error', 'The system must have at least one active administrator.');
                    header('Location: ' . appUrl('admin/users.php'));
                    exit;
                }
            }

            $updateStmt = $pdo->prepare('UPDATE users SET role = :role, is_active = :is_active WHERE id = :id');
            $updateStmt->execute([
                'role'      => $newRole,
                'is_active' => (int) $newActiveStatus,
                'id'        => $userId,
            ]);

            setFlash('success', 'User ' . e($targetUser['name']) . ' settings updated successfully.');
        }
    } catch (Throwable $e) {
        error_log('User management error: ' . $e->getMessage());
        setFlash('error', 'Could not update user settings. Please try again.');
    }

    header('Location: ' . appUrl('admin/users.php'));
    exit;
}

try {
    $stmt = $pdo->query('
        SELECT 
            u.id, 
            u.name, 
            u.email, 
            u.role, 
            u.is_active, 
            u.created_at,
            (SELECT COUNT(*) FROM tickets WHERE created_by = u.id) AS tickets_created,
            (SELECT COUNT(*) FROM tickets WHERE assigned_to = u.id) AS tickets_assigned
        FROM users u 
        ORDER BY u.created_at DESC
    ');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Counts
    $adminCount = 0;
    $agentCount = 0;
    $employeeCount = 0;
    foreach ($users as $u) {
        if ($u['role'] === 'admin') $adminCount++;
        elseif ($u['role'] === 'agent') $agentCount++;
        else $employeeCount++;
    }

} catch (PDOException $e) {
    error_log('User load error: ' . $e->getMessage());
    $users = [];
    $adminCount = $agentCount = $employeeCount = 0;
    setFlash('error', 'Could not load users. Please check the database.');
}

require_once __DIR__ . '/../../app/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">User Account & Role Management</h1>
        <p class="page-subtitle">Manage user permissions, assign staff roles, and activate/deactivate accounts.</p>
    </div>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <span class="badge" style="background:#eef2ff; color:var(--primary); font-size:13px; padding:6px 10px;">
            <?= count($users) ?> Total Users
        </span>
        <span class="badge" style="background:#fef3c7; color:#92400e; font-size:13px; padding:6px 10px;">
            <?= $adminCount ?> Admins
        </span>
        <span class="badge" style="background:#e0e7ff; color:#3730a3; font-size:13px; padding:6px 10px;">
            <?= $agentCount ?> Support Agents
        </span>
        <span class="badge" style="background:#f1f5f9; color:#475569; font-size:13px; padding:6px 10px;">
            <?= $employeeCount ?> Employees
        </span>
    </div>
</div>

<!-- Toolbar with Live Search -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div style="max-width: 320px; width: 100%;">
        <input type="search" id="tableSearchLive" placeholder="Live search by name or email..." style="padding: 8px 12px; font-size: 13px;">
    </div>
    <span class="form-hint">Tip: Changes take effect on the user's next request or login.</span>
</div>

<!-- Users Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-wrapper" style="border: none; box-shadow: none;">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Account Status</th>
                    <th>Joined</th>
                    <th style="text-align: right;">Save Changes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 36px;">
                            No users found in the database.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): 
                        $isSelf = (int) $u['id'] === (int) $_SESSION['user_id'];
                        $formId = 'user-form-' . $u['id'];
                    ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?= renderAvatar($u['name'], 34) ?>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-main);">
                                            <?= e($u['name']) ?>
                                            <?php if ($isSelf): ?>
                                                <span class="badge" style="background:#dbeafe; color:#1e40af; font-size:10px; padding:2px 6px;">You</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 11px; color: var(--text-muted);">
                                            <?php if ($u['role'] === 'agent'): ?>
                                                <?= (int)$u['tickets_assigned'] ?> tickets assigned
                                            <?php else: ?>
                                                <?= (int)$u['tickets_created'] ?> tickets submitted
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span style="font-family: ui-monospace, monospace; font-size: 13px; color: var(--text-muted);">
                                    <?= e($u['email']) ?>
                                </span>
                            </td>

                            <td>
                                <!-- Form container for this user -->
                                <form id="<?= $formId ?>" method="POST" action="<?= e(appUrl('admin/users.php')) ?>" style="margin: 0; display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                </form>

                                <select name="role" form="<?= $formId ?>" style="padding: 6px 10px; font-size: 13px; width: 120px;" <?= $isSelf ? 'disabled title="You cannot demote yourself"' : 'required' ?>>
                                    <?php foreach ($allowedRoles as $role): ?>
                                        <option value="<?= e($role) ?>" <?= $u['role'] === $role ? 'selected' : '' ?>>
                                            <?= e(ucfirst($role)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($isSelf): ?>
                                    <input type="hidden" name="role" value="admin" form="<?= $formId ?>">
                                <?php endif; ?>
                            </td>

                            <td>
                                <select name="is_active" form="<?= $formId ?>" style="padding: 6px 10px; font-size: 13px; width: 110px;" <?= $isSelf ? 'disabled title="You cannot deactivate yourself"' : 'required' ?>>
                                    <option value="1" <?= (int) $u['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= (int) $u['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
                                </select>
                                <?php if ($isSelf): ?>
                                    <input type="hidden" name="is_active" value="1" form="<?= $formId ?>">
                                <?php endif; ?>
                            </td>

                            <td>
                                <span title="<?= e($u['created_at']) ?>"><?= timeAgo($u['created_at']) ?></span>
                            </td>

                            <td style="text-align: right;">
                                <button 
                                    type="submit" 
                                    form="<?= $formId ?>" 
                                    class="btn btn-secondary btn-sm"
                                    <?= $isSelf ? 'disabled' : '' ?>
                                    data-confirm="Update settings for user <?= e($u['name']) ?>?"
                                >
                                    Save
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>