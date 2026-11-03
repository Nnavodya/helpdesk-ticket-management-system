<?php
/**
 * Global Footer Template
 * Help Desk & Ticket Management System
 */

$baseUri = '/helpdesk-system/public';
?>
</main><!-- /.page-container -->

<footer class="app-footer">
    <div class="app-footer-inner">
        <div>
            &copy; <?= date('Y') ?> <strong>HelpDesk Pro</strong> &mdash; IT Support & Ticket Management System
        </div>
        <div style="display:flex; gap: 16px; align-items:center;">
            <span>Version 2.0</span>
            <span>&bull;</span>
            <a href="<?= $baseUri ?>/index.php">Portal Home</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <span>&bull;</span>
                <a href="<?= $baseUri ?>/dashboard.php">Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</footer>

<!-- Interactive Scripts -->
<script src="<?= $baseUri ?>/assets/js/app.js?v=4"></script>
</body>
</html>
