<?php

require_once __DIR__ . '/session.php';

/**
 * Check whether a user is logged in.
 */
function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please log in to continue.';

        header('Location: /helpdesk-system/public/login.php');
        exit;
    }
}

/**
 * Check whether the logged-in user has the required role.
 */
function requireRole(array $allowedRoles): void
{
    requireLogin();

    $userRole = $_SESSION['user_role'] ?? '';

    if (!in_array($userRole, $allowedRoles, true)) {
        http_response_code(403);
        exit('403 Forbidden: You do not have permission to access this page.');
    }
}