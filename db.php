
<?php
mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_NAME");
$port = (int) (getenv("DB_PORT") ?: 3306);
$caFile = __DIR__ . "/ca.pem";

if (!$host || !$user || !$password || !$database) {
    error_log("Missing required database environment variables.");
    http_response_code(500);
    exit("Database configuration is missing.");
}

$conn = mysqli_init();

if (!$conn) {
    error_log("Failed to initialize MySQL connection.");
    http_response_code(500);
    exit("Database connection failed.");
}

if (!is_file($caFile) || !is_readable($caFile)) {
    error_log("CA certificate not found at: " . $caFile);
    http_response_code(500);
    exit("Database SSL certificate configuration is missing.");
}

$conn->ssl_set(null, null, $caFile, null, null);

$connected = $conn->real_connect(
    $host,
    $user,
    $password,
    $database,
    $port,
    null,
    MYSQLI_CLIENT_SSL
);

if (!$connected) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit("Database connection failed.");
}

$conn->set_charset("utf8mb4");
