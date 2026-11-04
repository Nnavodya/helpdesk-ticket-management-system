
<?php

$appEnvironment = strtolower(trim(getenv('APP_ENV') ?: 'development'));
$host = getenv('DB_HOST') ?: ($appEnvironment === 'production' ? '' : '127.0.0.1');
$dbname = getenv('DB_NAME') ?: ($appEnvironment === 'production' ? '' : 'helpdesk_db');
$username = getenv('DB_USER') ?: ($appEnvironment === 'production' ? '' : 'root');
$password = getenv('DB_PASSWORD');
$password = is_string($password) ? $password : '';

if (
    $host === ''
    || $dbname === ''
    || $username === ''
    || ($appEnvironment === 'production' && ($password === '' || strtolower($username) === 'root'))
) {
    error_log('Database configuration is incomplete for the current environment.');
    http_response_code(500);
    exit('Database configuration is incomplete.');
}

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}
