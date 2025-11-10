<?php
function logEmployeeAction($conn, $employee_id, $action, $description = null)
{
    if (!isset($conn) || !$employee_id) return;
    $stmt = $conn->prepare("INSERT INTO employee_log (Employee_ID, Action, Description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $employee_id, $action, $description);
    $stmt->execute();
    $stmt->close();
}
