<?php
/**
 * Public Utility Management System — Global Configuration & Environment Settings
 */

// Application Metadata
if (!defined('APP_NAME')) define('APP_NAME', 'Public Utility Management System');
if (!defined('APP_VERSION')) define('APP_VERSION', '2.0.0');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '₹');

// Environment & Error Handling
if (!defined('ENVIRONMENT')) define('ENVIRONMENT', 'development');

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Timezone Configuration
date_default_timezone_set('Asia/Kolkata');

// Base URL Definition
if (!defined('BASE_URL')) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (stripos($script, '/Public_Utility_Management_System/') !== false) {
        define('BASE_URL', '/Public_Utility_Management_System/');
    } else {
        define('BASE_URL', '/');
    }
}

// Database Connection Constants
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'public_utility_system');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
