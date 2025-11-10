<?php
if (!function_exists('logActivity')) {
    function logActivity($conn, $admin_id, $action)
    {
        // Safety checks
        if (!$conn || empty($action)) {
            return;
        }

        // If admin_id is not provided, use 1 (System Admin)
        if (empty($admin_id)) {
            $admin_id = 1;
        }

        $stmt = $conn->prepare("INSERT INTO activity_log (Admin_ID, Action) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("is", $admin_id, $action);
            $stmt->execute();
            $stmt->close();
        }
    }
}
