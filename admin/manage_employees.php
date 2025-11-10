<?php
session_start();
include('../includes/db_connect.php');
include('activity_log.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

/* --- Pagination Logic --- */
$results_per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$total_employees = (int)$conn->query("SELECT COUNT(*) AS count FROM employee")->fetch_assoc()['count'];
$total_pages = max(1, ceil($total_employees / $results_per_page));
if ($page > $total_pages) $page = $total_pages;

$start_from = ($page - 1) * $results_per_page;

/* --- CRUD Operations --- */
// DELETE
if (isset($_POST['confirm_delete']) && isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    $emp_result = mysqli_query($conn, "SELECT Name FROM employee WHERE Employee_ID = $id");
    $emp = mysqli_fetch_assoc($emp_result);
    $emp_name = $emp['Name'] ?? 'Unknown';

    $delete_query = "DELETE FROM employee WHERE Employee_ID = $id";
    if (mysqli_query($conn, $delete_query)) {
        $admin_id = $_SESSION['admin_id'] ?? 1;
        logActivity($conn, $admin_id, "Deleted employee '$emp_name' (ID: $id)");
        $toast = "Employee deleted successfully!";
        $toast_type = "success";
    } else {
        $toast = "Error deleting employee.";
        $toast_type = "error";
    }
}

// ADD
if (isset($_POST['add_employee']) && verify_csrf_token($_POST['csrf_token'])) {
    $name = sanitize_input($_POST['name']);
    $role = sanitize_input($_POST['role']);
    $phone = sanitize_input($_POST['phone']);
    $password = sanitize_input($_POST['password']);

    $stmt = $conn->prepare("INSERT INTO employee (Name, Role, Phone, Password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $role, $phone, $password);

    if ($stmt->execute()) {
        $toast = "Employee added successfully!";
        $toast_type = "success";
    } else {
        $toast = "Error adding employee: " . $conn->error;
        $toast_type = "error";
    }
    $stmt->close();
}

// EDIT
if (isset($_POST['edit_employee']) && verify_csrf_token($_POST['csrf_token'])) {
    $id = intval($_POST['employee_id']);
    $name = sanitize_input($_POST['name']);
    $role = sanitize_input($_POST['role']);
    $phone = sanitize_input($_POST['phone']);

    $stmt = $conn->prepare("UPDATE employee SET Name=?, Role=?, Phone=? WHERE Employee_ID=?");
    $stmt->bind_param("sssi", $name, $role, $phone, $id);
    if ($stmt->execute()) {
        $toast = "Employee updated successfully!";
        $toast_type = "success";
    } else {
        $toast = "Error updating employee: " . $conn->error;
        $toast_type = "error";
    }
    $stmt->close();
}

/* --- Fetch Records for Current Page --- */
$distinct_roles = $conn->query("SELECT COUNT(DISTINCT Role) AS r FROM employee")->fetch_assoc()['r'];
$result = $conn->query("SELECT * FROM employee ORDER BY Employee_ID ASC LIMIT $results_per_page OFFSET $start_from");
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* PAGINATION (centered + gradient style) */
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
            box-shadow: none;
        }

        body.dark-mode .pagination .page-btn {
            color: #e8e8e8;
        }

        .pagination .page-btn i {
            font-size: 14px;
        }

        /* outlined buttons */
        .pagination .page-btn:not(.active) {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(102, 126, 234, 0.15);
            color: #333;
        }

        body.dark-mode .pagination .page-btn:not(.active) {
            background: rgba(43, 43, 60, 0.6);
            border-color: rgba(102, 126, 234, 0.1);
            color: #e8e8e8;
        }

        /* active (filled gradient) */
        .pagination .page-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff !important;
            border-color: transparent;
            box-shadow: 0 8px 24px rgba(118, 75, 162, 0.18);
        }

        /* hover effects */
        .pagination .page-btn:not(.active):hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.18);
            border-color: rgba(118, 75, 162, 0.18);
        }

        .pagination .page-btn:active {
            transform: translateY(0);
        }

        /* smaller screens adjustments */
        @media (max-width: 480px) {
            .pagination .page-btn {
                padding: 6px 10px;
                min-width: 38px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1><i class="fas fa-user-tie"></i> Employee Management</h1>
            <p>Add, view, and manage employee records</p>
        </div>
        <div class="header-actions">
            <button id="toggle-theme" class="btn-icon">
                <i class="fas fa-moon"></i>
                <span>Dark Mode</span>
            </button>
            <a href="dashboard_admin.php" class="btn-icon">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>
            <a href="../logout.php" class="btn-icon logout">
                <i class="fas fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <div class="dashboard-content">
        <?php if (isset($toast)): ?>
            <div class="alert alert-<?= $toast_type ?>">
                <i class="fas <?= $toast_type == 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <span><?= htmlspecialchars($toast) ?></span>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-user-tie"></i> Total Employees</h3>
                <div class="stat-value"><?= $total_employees ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-briefcase"></i> Active Roles</h3>
                <div class="stat-value"><?= $distinct_roles ?></div>
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin:30px 0 20px; flex-wrap:wrap; gap:15px;">
            <button onclick="openAddModal()" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Employee
            </button>
            <div class="search-filter">
                <input type="text" id="searchInput" placeholder="🔍 Search by name, role, or phone...">
                <select id="sortSelect">
                    <option value="id-asc">Sort by ID ↑</option>
                    <option value="id-desc">Sort by ID ↓</option>
                    <option value="name-asc">Name A–Z</option>
                    <option value="name-desc">Name Z–A</option>
                </select>
            </div>
        </div>

        <h2 class="section-header"><i class="fas fa-list"></i> Employee List</h2>

        <div class="table-container">
            <table id="employeesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($row['Employee_ID']) ?></strong></td>
                                <td><?= htmlspecialchars($row['Name']) ?></td>
                                <td><?= htmlspecialchars($row['Role']) ?></td>
                                <td><?= htmlspecialchars($row['Phone']) ?></td>
                                <td>
                                    <button class="btn btn-primary" style="padding:8px 12px; margin-right:5px;"
                                        onclick="openEditModal('<?= $row['Employee_ID'] ?>','<?= htmlspecialchars($row['Name']) ?>','<?= htmlspecialchars($row['Role']) ?>','<?= htmlspecialchars($row['Phone']) ?>','<?= htmlspecialchars($row['Password']) ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="delete_id" value="<?= $row['Employee_ID'] ?>">
                                        <button type="submit" name="confirm_delete" class="btn btn-danger" style="padding:8px 12px;"
                                            onclick="return confirm('Are you sure you want to delete this employee?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-user-tie"></i>
                                    <p>No employees yet</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1" class="page-btn"><i class="fas fa-angle-double-left"></i></a>
                    <a href="?page=<?= $page - 1 ?>" class="page-btn"><i class="fas fa-angle-left"></i> Prev</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="page-btn active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>" class="page-btn"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="page-btn">Next <i class="fas fa-angle-right"></i></a>
                    <a href="?page=<?= $total_pages ?>" class="page-btn"><i class="fas fa-angle-double-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Dark mode toggle
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

            window.addEventListener('scroll', () => {
                if (window.scrollY > 30) header.classList.add('shrink');
                else header.classList.remove('shrink');
            });
        });

        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openEditModal(id, name, role, phone, password) {
            document.getElementById('edit_employee_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_password').value = password;
            document.getElementById('editModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal on outside click
        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Search functionality
        document.getElementById("searchInput").addEventListener("keyup", function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll("#employeesTable tbody tr");
            rows.forEach(r => {
                if (r.querySelector('.empty-state')) return;
                r.style.display = r.textContent.toLowerCase().includes(filter) ? "" : "none";
            });
        });

        // Sort functionality
        const sortSelect = document.getElementById("sortSelect");
        const tbody = document.querySelector("#employeesTable tbody");

        function sortTable(value) {
            const rows = Array.from(tbody.querySelectorAll("tr"));
            if (rows.length === 0 || rows[0].querySelector('.empty-state')) return;

            rows.sort((a, b) => {
                const idA = parseInt(a.children[0].textContent.replace('#', ''));
                const idB = parseInt(b.children[0].textContent.replace('#', ''));
                const nameA = a.children[1].textContent.toLowerCase();
                const nameB = b.children[1].textContent.toLowerCase();

                switch (value) {
                    case "id-asc":
                        return idA - idB;
                    case "id-desc":
                        return idB - idA;
                    case "name-asc":
                        return nameA.localeCompare(nameB);
                    case "name-desc":
                        return nameB.localeCompare(nameA);
                    default:
                        return 0;
                }
            });
            tbody.innerHTML = "";
            rows.forEach(r => tbody.appendChild(r));
        }

        sortSelect.addEventListener("change", function() {
            const selected = this.value;
            localStorage.setItem("employeeSort", selected);
            sortTable(selected);
        });

        window.addEventListener("DOMContentLoaded", () => {
            const savedSort = localStorage.getItem("employeeSort") || "id-asc";
            sortSelect.value = savedSort;
            sortTable(savedSort);
        });
    </script>
</body>

</html>
