<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Env.php';
\App\Core\Env::load(dirname(__DIR__) . '/.env');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$host = getenv('DB_HOST') ?: '';
$database = getenv('DB_DATABASE') ?: '';
$user = getenv('DB_USER') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
$port = (int)(getenv('DB_PORT') ?: 3306);

mysqli_report(MYSQLI_REPORT_OFF);
$connection = $host !== '' && $database !== '' && $user !== ''
    ? @mysqli_connect($host, $user, $password, $database, $port)
    : false;

if (!$connection || !$connection->ping()) {
    if ($connection) {
        $connection->close();
    }
    error_log('Health check could not connect to database.');
    http_response_code(503);
    echo json_encode(['status' => 'unavailable']);
    return;
}

$connection->close();
echo json_encode(['status' => 'ok']);
