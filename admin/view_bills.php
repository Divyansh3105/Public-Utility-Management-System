<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// Get pagination parameters
$pagination = get_pagination_params(50);
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter && in_array($status_filter, ['Paid', 'Unpaid'])) {
    $where_conditions[] = "Status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $where_conditions[] = "(c.Name LIKE ? OR eb.Bill_ID LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Count total electric bills
$count_query = "SELECT COUNT(*) as total FROM electric_bill eb
                LEFT JOIN customer c ON eb.Customer_ID = c.Customer_ID
                $where_clause";
$count_stmt = $conn->prepare($count_query);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$electric_total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$electric_total_pages = calculate_total_pages($electric_total_records, $limit);
$count_stmt->close();

// Fetch paginated electric bills
$electric_query = "
    SELECT eb.*, c.Name as Customer_Name
    FROM electric_bill eb
    LEFT JOIN customer c ON eb.Customer_ID = c.Customer_ID
    $where_clause
    ORDER BY eb.Bill_ID DESC
    LIMIT ? OFFSET ?
";

$electric_stmt = $conn->prepare($electric_query);
$params_with_limit = $params;
$params_with_limit[] = $limit;
$params_with_limit[] = $offset;
$types_with_limit = $types . 'ii';

if ($types_with_limit) {
    $electric_stmt->bind_param($types_with_limit, ...$params_with_limit);
}
$electric_stmt->execute();
$electric = $electric_stmt->get_result();

// Reset for water bills
$where_conditions = [];
$params = [];
$types = '';

if ($status_filter && in_array($status_filter, ['Paid', 'Unpaid'])) {
    $where_conditions[] = "Status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search) {
    $where_conditions[] = "(c.Name LIKE ? OR wb.Bill_ID LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Count total water bills
$count_query = "SELECT COUNT(*) as total FROM water_bill wb
                LEFT JOIN customer c ON wb.Customer_ID = c.Customer_ID
                $where_clause";
$count_stmt = $conn->prepare($count_query);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$water_total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$water_total_pages = calculate_total_pages($water_total_records, $limit);
$count_stmt->close();

// Fetch paginated water bills
$water_query = "
    SELECT wb.*, c.Name as Customer_Name
    FROM water_bill wb
    LEFT JOIN customer c ON wb.Customer_ID = c.Customer_ID
    $where_clause
    ORDER BY wb.Bill_ID DESC
    LIMIT ? OFFSET ?
";

$water_stmt = $conn->prepare($water_query);
$params_with_limit = $params;
$params_with_limit[] = $limit;
$params_with_limit[] = $offset;
$types_with_limit = $types . 'ii';

if ($types_with_limit) {
    $water_stmt->bind_param($types_with_limit, ...$params_with_limit);
}
$water_stmt->execute();
$water = $water_stmt->get_result();

// Calculate electric bill summary (optimized query)
$summary_query = "
    SELECT
        SUM(Bill_Amount) as total,
        SUM(CASE WHEN Status='Paid' THEN Bill_Amount ELSE 0 END) as paid,
        SUM(CASE WHEN Status='Unpaid' THEN Bill_Amount ELSE 0 END) as unpaid,
        COUNT(CASE WHEN Status='Paid' THEN 1 END) as count_paid,
        COUNT(CASE WHEN Status='Unpaid' THEN 1 END) as count_unpaid
    FROM electric_bill
";
$electric_summary = $conn->query($summary_query)->fetch_assoc();

// Calculate water bill summary (optimized query)
$summary_query = "
    SELECT
        SUM(Bill_Amount) as total,
        SUM(CASE WHEN Status='Paid' THEN Bill_Amount ELSE 0 END) as paid,
        SUM(CASE WHEN Status='Unpaid' THEN Bill_Amount ELSE 0 END) as unpaid,
        COUNT(CASE WHEN Status='Paid' THEN 1 END) as count_paid,
        COUNT(CASE WHEN Status='Unpaid' THEN 1 END) as count_unpaid
    FROM water_bill
";
$water_summary = $conn->query($summary_query)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bills - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 30px 0;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border: 2px solid #667eea;
            border-radius: 8px;
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        body.dark-mode .pagination a,
        body.dark-mode .pagination span {
            border-color: #818cf8;
            color: #818cf8;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
        }

        body.dark-mode .pagination a:hover {
            background: #818cf8;
        }

        .pagination .current {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .results-info {
            text-align: center;
            margin: 20px 0;
            color: #666;
            font-size: 14px;
        }

        body.dark-mode .results-info {
            color: #a0a0a0;
        }

        .filter-form {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        body.dark-mode .filter-form {
            background: #2b2b3c;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-file-invoice"></i> Bill Management</h1>
            <p>View and manage all electricity and water bills</p>
        </div>
        <div class="header-actions">
            <button id="toggle-theme" class="btn-icon">
                <i class="fas fa-moon"></i><span>Dark Mode</span>
            </button>
            <a href="dashboard_admin.php" class="btn-icon">
                <i class="fas fa-arrow-left"></i><span>Back</span>
            </a>
            <a href="../logout.php" class="btn-icon logout">
                <i class="fas fa-right-from-bracket"></i><span>Logout</span>
            </a>
        </div>
    </header>

    <div class="dashboard-content">
        <!-- Filter Form -->
        <div class="filter-form">
            <form method="GET" id="filterForm">
                <input type="hidden" name="tab" id="currentTab" value="<?= $_GET['tab'] ?? 'electric' ?>">
                <div class="filter-row">
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control"
                            placeholder="Customer name or Bill ID..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Paid" <?= $status_filter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="Unpaid" <?= $status_filter === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Records per page</label>
                        <select name="limit" class="form-control">
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                            <option value="200" <?= $limit === 200 ? 'selected' : '' ?>>200</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tab Content -->
        <div id="electric-tab">
            <h2 class="section-header">
                <i class="fas fa-bolt"></i> Electricity Bills
            </h2>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="fas fa-rupee-sign"></i> Total Amount</h3>
                    <div class="stat-value">₹<?= number_format($electric_summary['total'], 2) ?></div>
                </div>
                <div class="stat-card success">
                    <h3><i class="fas fa-check-circle"></i> Paid (<?= $electric_summary['count_paid'] ?> bills)</h3>
                    <div class="stat-value">₹<?= number_format($electric_summary['paid'], 2) ?></div>
                </div>
                <div class="stat-card danger">
                    <h3><i class="fas fa-exclamation-circle"></i> Unpaid (<?= $electric_summary['count_unpaid'] ?> bills)</h3>
                    <div class="stat-value">₹<?= number_format($electric_summary['unpaid'], 2) ?></div>
                </div>
            </div>

            <div class="results-info">
                Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $electric_total_records) ?> of <?= $electric_total_records ?> results
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Customer</th>
                            <th>House ID</th>
                            <th>Units</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($electric->num_rows > 0): ?>
                            <?php while ($row = $electric->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($row['Bill_ID']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['Customer_Name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['House_ID']) ?></td>
                                    <td><?= number_format($row['Units_Consumed'], 2) ?> kWh</td>
                                    <td><strong>₹<?= number_format($row['Bill_Amount'], 2) ?></strong></td>
                                    <td><?= date('d M Y', strtotime($row['Due_Date'])) ?></td>
                                    <td>
                                        <span class="badge status-<?= strtolower($row['Status']) ?>">
                                            <?= htmlspecialchars($row['Status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No bills found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($electric_total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&limit=<?= $limit ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                            <i class="fas fa-angle-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($electric_total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>&limit=<?= $limit ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $electric_total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                            Next <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?= $electric_total_pages ?>&limit=<?= $limit ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('toggle-theme');
            const saved = localStorage.getItem('theme') || 'light';

            if (saved === 'dark') {
                document.body.classList.add('dark-mode');
                btn.innerHTML = '<i class="fas fa-sun"></i><span>Light Mode</span>';
            }

            btn.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const mode = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                localStorage.setItem('theme', mode);
                btn.innerHTML = mode === 'dark' ?
                    '<i class="fas fa-sun"></i><span>Light Mode</span>' :
                    '<i class="fas fa-moon"></i><span>Dark Mode</span>';
            });
        });
    </script>
</body>

</html>
<?php
$electric_stmt->close();
$water_stmt->close();
?>
