<?php
include("includes/db_connect.php");

if (!isset($_SESSION["role"]) || ($_SESSION["role"] !== "admin" && $_SESSION["role"] !== "employee")) {
    die("Unauthorized access.");
}

$type = isset($_GET["type"]) ? sanitize_input($_GET["type"]) : "customers";

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=\"{$type}_report_" . date("Ymd_His") . ".csv\"");

$output = fopen("php://output", "w");

if ($type === "customers") {
    fputcsv($output, ["Customer_ID", "Name", "Phone", "Email", "House_ID"]);
    $res = $conn->query("SELECT Customer_ID, Name, Phone, Email, House_ID FROM customer ORDER BY Customer_ID ASC");
    while ($r = $res->fetch_assoc()) fputcsv($output, $r);
} else if ($type === "employees") {
    fputcsv($output, ["Employee_ID", "Name", "Role", "Phone"]);
    $res = $conn->query("SELECT Employee_ID, Name, Role, Phone FROM employee ORDER BY Employee_ID ASC");
    while ($r = $res->fetch_assoc()) fputcsv($output, $r);
} else if ($type === "payments") {
    fputcsv($output, ["Payment_ID", "Bill_Type", "Bill_ID", "Amount_Paid", "Date_of_Payment", "Mode_of_Payment"]);
    $res = $conn->query("SELECT Payment_ID, Bill_Type, Bill_ID, Amount_Paid, Date_of_Payment, Mode_of_Payment FROM payment ORDER BY Date_of_Payment DESC");
    while ($r = $res->fetch_assoc()) fputcsv($output, $r);
} else if ($type === "logs") {
    fputcsv($output, ["Log_ID", "Admin_ID", "Action", "Log_Time"]);
    $res = $conn->query("SELECT Log_ID, Admin_ID, Action, Log_Time FROM activity_log ORDER BY Log_Time DESC");
    while ($r = $res->fetch_assoc()) fputcsv($output, $r);
}

fclose($output);
exit;
