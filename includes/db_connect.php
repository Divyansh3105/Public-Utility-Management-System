<?php
require_once(__DIR__ . '/config.php');
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
// Function to clean input strings safely for processing and prepared statements
function sanitize_input($data)
{
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return trim((string)$data);
}

// Function to safely escape string for HTML rendering (XSS protection)
function e($data)
{
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
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

// Function to verify user password with automatic legacy plain-text password migration
function verify_user_password($conn, $table, $id_field, $user_id, $input_password, $stored_hash)
{
    if (empty($stored_hash)) {
        return false;
    }

    // Standard Bcrypt verification
    if (password_verify($input_password, $stored_hash)) {
        if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
            $new_hash = password_hash($input_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE `$table` SET `Password` = ? WHERE `$id_field` = ?");
            if ($stmt) {
                $stmt->bind_param("si", $new_hash, $user_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        return true;
    }

    // Transparent migration for legacy plain text passwords
    if ($input_password === $stored_hash) {
        $new_hash = password_hash($input_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE `$table` SET `Password` = ? WHERE `$id_field` = ?");
        if ($stmt) {
            $stmt->bind_param("si", $new_hash, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        return true;
    }

    return false;
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


// Secure Session Management with Cookie Security & Inactivity Timeout
function secure_session_start()
{
    if (session_status() === PHP_SESSION_NONE) {
        $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    // Inactivity timeout check (30 minutes = 1800 seconds)
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        $role = $_SESSION['role'] ?? null;
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['timeout_msg'] = "Your session expired due to inactivity. Please log in again.";
    }
    $_SESSION['last_activity'] = time();
}

// Auto-invoke secure_session_start when db_connect is included
secure_session_start();

// Helper for safe URL redirection using BASE_URL
function redirect($path)
{
    $target = (strpos($path, 'http') === 0) ? $path : BASE_URL . ltrim($path, '/');
    if (!headers_sent()) {
        header("Location: " . $target);
        exit;
    } else {
        echo "<script>window.location.href='" . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . "';</script>";
        exit;
    }
}

// Login Rate Limiting & Brute-Force Protection
function check_login_rate_limit()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['lockout_until'])) {
        $remaining = $_SESSION['lockout_until'] - time();
        if ($remaining > 0) {
            $mins = ceil($remaining / 60);
            return "Too many failed login attempts. Please wait {$mins} minute(s) before trying again.";
        } else {
            unset($_SESSION['lockout_until']);
            $_SESSION['failed_login_attempts'] = 0;
        }
    }

    return false;
}

function record_failed_login()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['failed_login_attempts'] = ($_SESSION['failed_login_attempts'] ?? 0) + 1;

    if ($_SESSION['failed_login_attempts'] >= 5) {
        $_SESSION['lockout_until'] = time() + 300; // 5-minute lockout
    }
}

function reset_login_rate_limit()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['failed_login_attempts']);
    unset($_SESSION['lockout_until']);
}

// Helper to render hidden CSRF token input field in HTML forms
function csrf_field()
{
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// Helper to strictly enforce CSRF verification on incoming POST requests
function enforce_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!verify_csrf_token($token)) {
            http_response_code(403);
            die("Invalid or expired CSRF token. Please refresh the page and try again.");
        }
    }
}
