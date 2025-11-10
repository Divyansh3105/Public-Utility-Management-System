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

$total_logs = (int)$conn->query("SELECT COUNT(*) AS count FROM activity_log")->fetch_assoc()['count'];
$total_pages = max(1, ceil($total_logs / $results_per_page));
if ($page > $total_pages) $page = $total_pages;

$start_from = ($page - 1) * $results_per_page;

/* --- Fetch logs --- */
$limit = (int)$results_per_page;
$offset = (int)$start_from;

$query = "
    SELECT l.Log_ID, l.Admin_ID, l.Action, l.Log_Time AS Timestamp, a.Name AS Admin_Name
    FROM activity_log l
    LEFT JOIN admin a ON l.Admin_ID = a.Admin_ID
    ORDER BY l.Log_Time DESC, l.Log_ID DESC
    LIMIT $limit OFFSET $offset
";

$result = $conn->query($query);
if (!$result) {
    die('Query failed: ' . $conn->error);
}
?>
<!DOCTYPE html>
<html lang='en'>

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>View Logs - Public Utility System</title>
    <link rel='stylesheet' href='../assets/style.css'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css'>
    <style>
        /* Pagination Styling */
        .pagination {
            width: 100%;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            margin-top: 20px;
            flex-wrap: wrap;
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

        /* Filter Bar */
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
    <header class='dashboard-header' id='header'>
        <div class='header-left'>
            <h1><i class='fas fa-clipboard-list'></i> Activity Logs</h1>
            <p>Track all actions performed by administrators</p>
        </div>
        <div class='header-actions'>
            <button id='toggle-theme' class='btn-icon'><i class='fas fa-moon'></i><span>Dark Mode</span></button>
            <a href='dashboard_admin.php' class='btn-icon'><i class='fas fa-arrow-left'></i><span>Back</span></a>
            <a href='logout.php' class='btn-icon logout'><i class='fas fa-right-from-bracket'></i><span>Logout</span></a>
        </div>
    </header>

    <div class='dashboard-content'>
        <div class='filter-bar'>
            <input type='text' id='searchInput' placeholder='🔍 Search by user, action, or date...'>
            <select id='sortSelect'>
                <option value='id-asc'>Log ID ↑</option>
                <option value='id-desc'>Log ID ↓</option>
                <option value='name-asc'>User A–Z</option>
                <option value='name-desc'>User Z–A</option>
                <option value='date-asc'>Date ↑</option>
                <option value='date-desc'>Date ↓</option>
            </select>
        </div>

        <h2 class='section-header'><i class='fas fa-list'></i> System Activity Logs</h2>
        <div class='table-container'>
            <table id='logsTable'>
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($row['Log_ID']) ?></strong></td>
                                <td><?= htmlspecialchars($row['Admin_Name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($row['Action']) ?></td>
                                <td data-date='<?= htmlspecialchars($row['Timestamp']) ?>'>
                                    <?= date('d M Y, h:i A', strtotime($row['Timestamp'])) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan='4'>
                                <div class='empty-state'><i class='fas fa-inbox'></i>
                                    <p>No log records found</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class='pagination'>
                <?php if ($page > 1): ?>
                    <a href='?page=1' class='page-btn'><i class='fas fa-angle-double-left'></i></a>
                    <a href='?page=<?= $page - 1 ?>' class='page-btn'><i class='fas fa-angle-left'></i> Prev</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class='page-btn active'><?= $i ?></span>
                    <?php else: ?>
                        <a href='?page=<?= $i ?>' class='page-btn'><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href='?page=<?= $page + 1 ?>' class='page-btn'>Next <i class='fas fa-angle-right'></i></a>
                    <a href='?page=<?= $total_pages ?>' class='page-btn'><i class='fas fa-angle-double-right'></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Dark Mode Toggle
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
                btn.innerHTML = mode === 'dark' ?
                    '<i class="fas fa-sun"></i><span>Light Mode</span>' :
                    '<i class="fas fa-moon"></i><span>Dark Mode</span>';
            });
        });

        // Search & Sort
        const searchInput = document.getElementById('searchInput');
        const sortSelect = document.getElementById('sortSelect');
        const tbody = document.querySelector('#logsTable tbody');

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
                const nameA = a.children[1].textContent.toLowerCase();
                const nameB = b.children[1].textContent.toLowerCase();
                const dateA = new Date(a.children[3].dataset.date);
                const dateB = new Date(b.children[3].dataset.date);
                switch (value) {
                    case 'id-asc':
                        return idA - idB;
                    case 'id-desc':
                        return idB - idA;
                    case 'name-asc':
                        return nameA.localeCompare(nameB);
                    case 'name-desc':
                        return nameB.localeCompare(nameA);
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
