<?php
/**
 * Application URL base path.
 *
 * Set APP_BASE_PATH to the URL path that serves the public directory,
 * such as /helpdesk-system/public or / when public is the document root.
 */
function appDeploymentConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $configPath = __DIR__ . '/../config/deployment.php';
    if (!is_file($configPath)) {
        $config = [];
        return $config;
    }

    $loadedConfig = require $configPath;
    if (!is_array($loadedConfig)) {
        throw new RuntimeException('Deployment config must return an array.');
    }

    $config = $loadedConfig;
    return $config;
}

function appSetting(string $name): ?string
{
    $environmentValue = getenv($name);
    if (is_string($environmentValue) && $environmentValue !== '') {
        return $environmentValue;
    }

    $config = appDeploymentConfig();
    $configValue = $config[$name] ?? null;

    return is_string($configValue) ? $configValue : null;
}

function appBasePath(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $configuredPath = appSetting('APP_BASE_PATH');
    $path = $configuredPath === null || trim($configuredPath) === ''
        ? '/helpdesk-system/public'
        : trim($configuredPath);

    if (
        $path[0] !== '/'
        || substr($path, 0, 2) === '//'
        || strpos($path, '//') !== false
        || strpos($path, '\\') !== false
        || strpos($path, '?') !== false
        || strpos($path, '#') !== false
        || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $path) === 1
    ) {
        throw new RuntimeException('APP_BASE_PATH must be a safe absolute URL path.');
    }

    $basePath = rtrim($path, '/');
    return $basePath;
}

function appUrl(string $path = ''): string
{
    $basePath = appBasePath();
    $path = ltrim($path, '/');

    return $basePath . ($path === '' ? '/' : '/' . $path);
}
