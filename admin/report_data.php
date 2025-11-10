<?php
include('../includes/db_connect.php');
$year = $_GET['year'] ?? date('Y');
$data = [];
$result = $conn->query("SELECT MONTH(Date_of_Payment) AS m, SUM(Amount_Paid) AS total
                        FROM payment WHERE YEAR(Date_of_Payment)=$year GROUP BY m");
while ($r = $result->fetch_assoc()) {
    $data[$r['m']] = $r['total'];
}
echo json_encode($data);
