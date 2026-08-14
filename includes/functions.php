<?php
/**
 * Public Utility Management System — Global Helper Functions
 */

require_once(__DIR__ . "/config.php");

/**
 * Escape HTML output for XSS protection
 */
if (!function_exists("e")) {
    function e($data) {
        return htmlspecialchars($data ?? "", ENT_QUOTES, "UTF-8");
    }
}

/**
 * Format currency with symbol and 2 decimals
 */
if (!function_exists("format_currency")) {
    function format_currency($amount, $symbol = null) {
        $sym = $symbol ?? (defined("CURRENCY_SYMBOL") ? CURRENCY_SYMBOL : "₹");
        return $sym . number_format((float)$amount, 2);
    }
}

/**
 * Format date string safely
 */
if (!function_exists("format_date")) {
    function format_date($date_str, $format = "d M Y") {
        if (empty($date_str)) return "N/A";
        $timestamp = strtotime($date_str);
        return $timestamp ? date($format, $timestamp) : $date_str;
    }
}

/**
 * Render HTML status badge
 */
if (!function_exists("status_badge")) {
    function status_badge($status) {
        $status_clean = strtolower(trim($status));
        $class = ($status_clean === "paid" || $status_clean === "completed") ? "badge-success" : "badge-danger";
        $icon = ($status_clean === "paid" || $status_clean === "completed") ? "fa-check-circle" : "fa-clock";
        return '<span class="badge ' . $class . '"><i class="fas ' . $icon . '"></i> ' . e(ucfirst($status)) . '</span>';
    }
}

/**
 * Render utility type badge
 */
if (!function_exists("utility_badge")) {
    function utility_badge($type) {
        $type_clean = strtolower(trim($type));
        $is_electric = ($type_clean === "electric");
        $class = $is_electric ? "badge-warning" : "badge-info";
        $icon = $is_electric ? "fa-bolt" : "fa-droplet";
        return '<span class="badge ' . $class . '"><i class="fas ' . $icon . '"></i> ' . e(ucfirst($type)) . '</span>';
    }
}

/**
 * Get pagination parameters safely
 */
if (!function_exists("get_pagination_params")) {
    function get_pagination_params($default_limit = 50) {
        $page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
        $limit = isset($_GET["limit"]) ? min(500, max(10, (int)$_GET["limit"])) : $default_limit;
        $offset = ($page - 1) * $limit;

        return [
            "page" => $page,
            "limit" => $limit,
            "offset" => $offset
        ];
    }
}

/**
 * Calculate total pagination pages
 */
if (!function_exists("calculate_total_pages")) {
    function calculate_total_pages($total_records, $limit) {
        return max(1, (int)ceil($total_records / max(1, $limit)));
    }
}

/**
 * Set flash message in session
 */
if (!function_exists("set_flash_msg")) {
    function set_flash_msg($msg, $type = "success") {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION["flash_msg"] = $msg;
        $_SESSION["flash_type"] = $type;
    }
}

/**
 * Get and clear flash message from session
 */
if (!function_exists("get_flash_msg")) {
    function get_flash_msg() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION["flash_msg"])) {
            $msg = $_SESSION["flash_msg"];
            $type = $_SESSION["flash_type"] ?? "success";
            unset($_SESSION["flash_msg"], $_SESSION["flash_type"]);
            return ["msg" => $msg, "type" => $type];
        }
        return null;
    }
}

/**
 * Render standardized flash message alert banner
 */
if (!function_exists("display_flash_msg")) {
    function display_flash_msg($fallback_msg = null, $fallback_type = "success") {
        $flash = get_flash_msg();
        $msg = $flash["msg"] ?? $fallback_msg;
        $type = $flash["type"] ?? $fallback_type;

        if (empty($msg)) return "";

        $type_clean = strtolower(trim($type));
        $bg_class = "alert-info";
        $icon = "fa-info-circle";

        if ($type_clean === "success") {
            $bg_class = "alert-success";
            $icon = "fa-check-circle";
        } else if ($type_clean === "error" || $type_clean === "danger") {
            $bg_class = "alert-danger";
            $icon = "fa-exclamation-circle";
        } else if ($type_clean === "warning") {
            $bg_class = "alert-warning";
            $icon = "fa-triangle-exclamation";
        }

        return '<div class="alert ' . $bg_class . '" style="display:flex; align-items:center; gap:10px; margin: 15px 0;">' .
               '<i class="fas ' . $icon . '"></i>' .
               '<span>' . e($msg) . '</span>' .
               '</div>';
    }
}
