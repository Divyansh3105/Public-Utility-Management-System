<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "public_utility_system";

// Create connection with error handling
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log("Database Connection Failed: " . $conn->connect_error);
    die("Unable to connect to database. Please try again later.");
}

// Set charset to prevent encoding issues
$conn->set_charset("utf8mb4");

// Enable query result buffering for large datasets
$conn->query("SET SESSION SQL_BIG_SELECTS = 1");

// Optimize for large datasets
$conn->query("SET SESSION tmp_table_size = 256000000");
$conn->query("SET SESSION max_heap_table_size = 256000000");

// Function to sanitize input
function sanitize_input($data)
{
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $conn->real_escape_string($data);
}

// Function to generate CSRF token
function generate_csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to verify CSRF token
function verify_csrf_token($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to hash passwords
function hash_password($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function to verify passwords
function verify_password($password, $hash)
{
    return password_verify($password, $hash);
}

// Pagination helper function
function get_pagination_params($default_limit = 50)
{
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(500, max(10, intval($_GET['limit']))) : $default_limit;
    $offset = ($page - 1) * $limit;

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset
    ];
}

// Calculate total pages
function calculate_total_pages($total_records, $limit)
{
    return ceil($total_records / $limit);
}
