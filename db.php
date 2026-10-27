
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$password = getenv("DB_PASSWORD");
$database = getenv("DB_NAME");
$port = (int) (getenv("DB_PORT") ?: 3306);
$caFile = getenv("DB_SSL_CA") ?: "/etc/secrets/ca.pem";

// Make sure required environment variables exist.
if (!$host || !$user || !$password || !$database) {
    error_log("Missing required database environment variables.");
    http_response_code(500);
    exit("Database configuration is missing.");
}

$conn = mysqli_init();

if (!$conn) {
    http_response_code(500);
    exit("Database connection failed.");
}

// Connect using SSL. The CA file is optional here,
// but Aiven may require it depending on the connection settings.
if (is_file($caFile)) {
    $conn->ssl_set(null, null, $caFile, null, null);
}

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
