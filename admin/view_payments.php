<?php
session_start();
include('../includes/db_connect.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

/* --- Pagination Logic --- */
$results_per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$total_payments = (int)$conn->query("SELECT COUNT(*) AS count FROM payment")->fetch_assoc()['count'];
$total_pages = max(1, ceil($total_payments / $results_per_page));
if ($page > $total_pages) $page = $total_pages;

$start_from = ($page - 1) * $results_per_page;

/* --- Fetch Payments --- */
$stmt = $conn->prepare("
    SELECT
        p.Payment_ID,
        p.Bill_Type,
        p.Bill_ID,
        p.Amount_Paid,
        p.Date_of_Payment,
        p.Mode_of_Payment,
        CASE
            WHEN p.Bill_Type = 'Electric' THEN (
                SELECT c.Name FROM electric_bill eb
                LEFT JOIN customer c ON eb.Customer_ID = c.Customer_ID
                WHERE eb.Bill_ID = p.Bill_ID
            )
            WHEN p.Bill_Type = 'Water' THEN (
                SELECT c.Name FROM water_bill wb
                LEFT JOIN customer c ON wb.Customer_ID = c.Customer_ID
                WHERE wb.Bill_ID = p.Bill_ID
            )
        END AS Customer_Name,
        CASE
            WHEN p.Bill_Type = 'Electric' THEN (SELECT Customer_ID FROM electric_bill WHERE Bill_ID = p.Bill_ID)
            WHEN p.Bill_Type = 'Water' THEN (SELECT Customer_ID FROM water_bill WHERE Bill_ID = p.Bill_ID)
        END AS Customer_ID
    FROM payment p
    ORDER BY p.Date_of_Payment DESC, p.Payment_ID DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $results_per_page, $start_from);
$stmt->execute();
$result = $stmt->get_result();

/* --- Summary Stats --- */
$total_amount = 0;
$payment_modes = ['Cash' => 0, 'Online' => 0, 'UPI' => 0, 'Card' => 0];
$res = $conn->query("SELECT Mode_of_Payment, SUM(Amount_Paid) AS sum_amt, COUNT(*) AS cnt FROM payment GROUP BY Mode_of_Payment");
while ($r = $res->fetch_assoc()) {
    $total_amount += $r['sum_amt'];
    if (isset($payment_modes[$r['Mode_of_Payment']])) {
        $payment_modes[$r['Mode_of_Payment']] = $r['cnt'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Payments - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Pagination Styling */
        .pagination {
            width: 100%;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            margin-top: 20px;
            flex-wrap: wrap;
            text-align: center;
            gap: 8px;
        }

        .pagination .page-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 12px;
            min-width: 44px;
            justify-content: center;
            text-decoration: none;
            font-weight: 600;
            background: transparent;
            color: #333;
            border: 2px solid transparent;
            transition: all 0.25s ease;
        }

        body.dark-mode .pagination .page-btn {
            color: #e8e8e8;
        }

        .pagination .page-btn:not(.active) {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(102, 126, 234, 0.15);
        }

        body.dark-mode .pagination .page-btn:not(.active) {
            background: rgba(43, 43, 60, 0.6);
            border-color: rgba(102, 126, 234, 0.1);
        }

        .pagination .page-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff !important;
            box-shadow: 0 8px 24px rgba(118, 75, 162, 0.18);
        }

        .pagination .page-btn:hover:not(.active) {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.18);
        }

        /* Filter & Sort bar */
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-bar input,
        .filter-bar select {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 15px;
            outline: none;
        }

        body.dark-mode .filter-bar input,
        body.dark-mode .filter-bar select {
            background: #2b2b3c;
            color: #eee;
            border: 1px solid #555;
        }
    </style>
</head>

<body>
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-money-check-alt"></i> Payment Records</h1>
            <p>View all payment transactions and revenue details</p>
        </div>
        <div class="header-actions">
            <button id="toggle-theme" class="btn-icon"><i class="fas fa-moon"></i><span>Dark Mode</span></button>
            <a href="dashboard_admin.php" class="btn-icon"><i class="fas fa-arrow-left"></i><span>Back</span></a>
            <a href="../logout.php" class="btn-icon logout"><i class="fas fa-right-from-bracket"></i><span>Logout</span></a>
        </div>
    </header>

    <div class="dashboard-content">
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-rupee-sign"></i> Total Revenue</h3>
                <div class="stat-value">₹<?= number_format($total_amount, 2) ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-money-bill"></i> Cash</h3>
                <div class="stat-value"><?= $payment_modes['Cash'] ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-globe"></i> Online</h3>
                <div class="stat-value"><?= $payment_modes['Online'] ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-mobile-alt"></i> UPI</h3>
                <div class="stat-value"><?= $payment_modes['UPI'] ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-credit-card"></i> Card</h3>
                <div class="stat-value"><?= $payment_modes['Card'] ?></div>
            </div>
        </div>

        <!-- Search + Sort Bar -->
        <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="🔍 Search by ID, customer, or mode...">
            <select id="sortSelect">
                <option value="id-asc">Payment ID ↑</option>
                <option value="id-desc">Payment ID ↓</option>
                <option value="amount-asc">Amount ↑</option>
                <option value="amount-desc">Amount ↓</option>
                <option value="date-asc">Date ↑</option>
                <option value="date-desc">Date ↓</option>
            </select>
        </div>

        <h2 class="section-header"><i class="fas fa-list"></i> All Payment Transactions</h2>
        <div class="table-container">
            <table id="paymentsTable">
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Bill Type</th>
                        <th>Bill ID</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Mode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($row['Payment_ID']) ?></strong></td>
                                <td><?= htmlspecialchars($row['Bill_Type']) ?></td>
                                <td>#<?= htmlspecialchars($row['Bill_ID']) ?></td>
                                <td><?= htmlspecialchars($row['Customer_Name'] ?? 'N/A') ?>
                                    <?php if ($row['Customer_ID']): ?><small>(ID: <?= htmlspecialchars($row['Customer_ID']) ?>)</small><?php endif; ?></td>
                                <td data-amount="<?= $row['Amount_Paid'] ?>"><strong>₹<?= number_format($row['Amount_Paid'], 2) ?></strong></td>
                                <td data-date="<?= $row['Date_of_Payment'] ?>"><?= date('d M Y', strtotime($row['Date_of_Payment'])) ?></td>
                                <td><span class="badge mode-<?= strtolower($row['Mode_of_Payment']) ?>"><?= htmlspecialchars($row['Mode_of_Payment']) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state"><i class="fas fa-inbox"></i>
                                    <p>No payment records found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1" class="page-btn"><i class="fas fa-angle-double-left"></i></a>
                        <a href="?page=<?= $page - 1 ?>" class="page-btn"><i class="fas fa-angle-left"></i> Prev</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?><span class="page-btn active"><?= $i ?></span>
                        <?php else: ?><a href="?page=<?= $i ?>" class="page-btn"><?= $i ?></a><?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="page-btn">Next <i class="fas fa-angle-right"></i></a>
                        <a href="?page=<?= $total_pages ?>" class="page-btn"><i class="fas fa-angle-double-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        /* ---- Dark Mode ---- */
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('toggle-theme');
            const header = document.getElementById('header');
            const saved = localStorage.getItem('theme') || 'light';
            if (saved === 'dark') {
                document.body.classList.add('dark-mode');
                btn.innerHTML = '<i class="fas fa-sun"></i><span>Light Mode</span>';
            }
            btn.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const mode = document.body.classList.contains('dark-mode') ? 'dark' : 'light';
                localStorage.setItem('theme', mode);
                btn.innerHTML = mode === 'dark' ? '<i class="fas fa-sun"></i><span>Light Mode</span>' : '<i class="fas fa-moon"></i><span>Dark Mode</span>';
            });
        });

        /* ---- Search & Sort ---- */
        const searchInput = document.getElementById('searchInput');
        const sortSelect = document.getElementById('sortSelect');
        const tbody = document.querySelector('#paymentsTable tbody');

        function filterTable() {
            const term = searchInput.value.toLowerCase();
            const rows = tbody.querySelectorAll('tr');
            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return;
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        }

        function sortTable() {
            const value = sortSelect.value;
            const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.querySelector('.empty-state'));
            rows.sort((a, b) => {
                const idA = parseInt(a.children[0].textContent.replace('#', ''));
                const idB = parseInt(b.children[0].textContent.replace('#', ''));
                const amountA = parseFloat(a.children[4].dataset.amount);
                const amountB = parseFloat(b.children[4].dataset.amount);
                const dateA = new Date(a.children[5].dataset.date);
                const dateB = new Date(b.children[5].dataset.date);
                switch (value) {
                    case 'id-asc':
                        return idA - idB;
                    case 'id-desc':
                        return idB - idA;
                    case 'amount-asc':
                        return amountA - amountB;
                    case 'amount-desc':
                        return amountB - amountA;
                    case 'date-asc':
                        return dateA - dateB;
                    case 'date-desc':
                        return dateB - dateA;
                    default:
                        return 0;
                }
            });
            tbody.innerHTML = '';
            rows.forEach(r => tbody.appendChild(r));
        }

        searchInput.addEventListener('keyup', filterTable);
        sortSelect.addEventListener('change', sortTable);
    </script>
</body>

</html>
<?php $stmt->close(); ?>
