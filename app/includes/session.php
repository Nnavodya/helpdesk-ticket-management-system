<?php
/**
 * Start sessions with production-safe cookie defaults.
 */

if (session_status() === PHP_SESSION_NONE) {
    $appEnvironment = strtolower(trim(getenv('APP_ENV') ?: 'development'));
    $httpsValue = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $isHttps = $httpsValue !== '' && $httpsValue !== 'off' && $httpsValue !== '0';
    $secureCookie = $isHttps
        || $appEnvironment === 'production'
        || getenv('SESSION_COOKIE_SECURE') === '1';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
