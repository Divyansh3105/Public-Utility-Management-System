<?php
/**
 * Public Utility Management System — Global Configuration
 */

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Public Utility Management System');
}

if (!defined('BASE_URL')) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (stripos($script, '/Public_Utility_Management_System/') !== false) {
        define('BASE_URL', '/Public_Utility_Management_System/');
    } else {
        define('BASE_URL', '/');
    }
}

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'public_utility_system');
