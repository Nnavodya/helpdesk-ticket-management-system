<?php
/**
 * Create Ticket Page
 * Help Desk & Ticket Management System
 */

require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/includes/functions.php';

requireLogin();

$pageTitle = 'Create Support Ticket';
$activeNav = 'create_ticket';

$formError = $_SESSION['ticket_error'] ?? '';
unset($_SESSION['ticket_error']);

$subject = $_SESSION['old_subject'] ?? '';
$description = $_SESSION['old_description'] ?? '';
$priority = $_SESSION['old_priority'] ?? 'medium';
$categoryId = $_SESSION['old_category_id'] ?? '';

unset($_SESSION['old_subject'], $_SESSION['old_description'], $_SESSION['old_priority'], $_SESSION['old_category_id']);

try {
    $stmt = $pdo->query('SELECT id, name, description FROM categories ORDER BY name ASC');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $categories = [];
    $formError = 'Could not load ticket categories. Please verify your database connection.';
}

require_once __DIR__ . '/../../app/includes/header.php';
?>

<div class="page-header">
    <div>
        <a href="index.php" style="display:inline-flex; align-items:center; gap:6px; color:var(--text-muted); font-size:13px; margin-bottom:8px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Tickets
        </a>
        <h1 class="page-title">Submit a New Support Ticket</h1>
        <p class="page-subtitle">Please provide details regarding your technical problem so our support team can assist you.</p>
    </div>
</div>

<?php if (!empty($formError)): ?>
    <div class="alert alert-error">
        <div class="alert-content"><?= e($formError) ?></div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 820px; margin: 0 auto;">
    <form method="POST" action="../../app/actions/ticket_create.php" enctype="multipart/form-data">
        <?= csrfField() ?>

        <!-- Subject -->
        <div class="form-group">
            <label for="subject">Ticket Subject <span style="color:var(--danger)">*</span></label>
            <input
                type="text"
                id="subject"
                name="subject"
                maxlength="200"
                value="<?= e($subject) ?>"
                placeholder="Brief summary of the issue (e.g. Cannot access Outlook email, VPN failing)"
                required
            >
            <span class="form-hint">Keep it clear and specific so agents can identify the problem quickly.</span>
        </div>

        <div class="form-row">
            <!-- Category -->
            <div class="form-group">
                <label for="category_id">Issue Category <span style="color:var(--danger)">*</span></label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option
                            value="<?= (int) $cat['id'] ?>"
                            <?= (string) $categoryId === (string) $cat['id'] ? 'selected' : '' ?>
                        >
                            <?= e($cat['name']) ?> <?= !empty($cat['description']) ? '— ' . e($cat['description']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Priority -->
            <div class="form-group">
                <label for="priority">Priority Level <span style="color:var(--danger)">*</span></label>
                <select id="priority" name="priority" required>
                    <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low &mdash; Minor question or request</option>
                    <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium &mdash; Standard operational issue</option>
                    <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High &mdash; Significant work impediment</option>
                    <option value="critical" <?= $priority === 'critical' ? 'selected' : '' ?>>Critical &mdash; System down, complete stoppage</option>
                </select>
            </div>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label for="description">Detailed Description <span style="color:var(--danger)">*</span></label>
            <textarea
                id="description"
                name="description"
                rows="6"
                maxlength="5000"
                placeholder="Provide steps to reproduce, error codes, what troubleshooting you have already tried..."
                required
            ><?= e($description) ?></textarea>
        </div>

        <!-- File Attachment -->
        <div class="form-group">
            <label for="attachment">Attachment (Optional screenshot or error log)</label>
            <input 
                type="file" 
                id="attachment" 
                name="attachment" 
                accept=".png,.jpg,.jpeg,.pdf,.txt,.docx"
                style="padding: 8px 12px;"
            >
            <span class="form-hint">Accepted formats: PNG, JPG, PDF, TXT, DOCX (Max 5MB)</span>
        </div>

        <div style="display: flex; gap: 12px; margin-top: 10px; align-items: center;">
            <button type="submit" class="btn btn-primary btn-lg">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                Submit Ticket
            </button>
            <a href="index.php" class="btn btn-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../app/includes/footer.php'; ?>
