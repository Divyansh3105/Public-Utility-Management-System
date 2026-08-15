<?php
include('../includes/db_connect.php');
include('activity_log.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    redirect('index.php');
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
if (isset($_POST['confirm_delete']) && isset($_POST['delete_id']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
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
if (isset($_POST['add_employee']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $name = sanitize_input($_POST['name']);
    $role = sanitize_input($_POST['role']);
    $phone = sanitize_input($_POST['phone']);
    $password = hash_password($_POST['password']);

    if (!validate_phone($phone)) {
        $toast = "Invalid phone number! Please enter a 10-15 digit phone number.";
        $toast_type = "error";
    } else {
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
}

// EDIT
if (isset($_POST['update_employee']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $id = intval($_POST['id']);
    $name = sanitize_input($_POST['name']);
    $role = sanitize_input($_POST['role']);
    $phone = sanitize_input($_POST['phone']);

    if (!validate_phone($phone)) {
        $toast = "Invalid phone number! Please enter a 10-15 digit phone number.";
        $toast_type = "error";
    } else {
        if (!empty($_POST['password'])) {
            $password = hash_password($_POST['password']);
            $stmt = $conn->prepare("UPDATE employee SET Name=?, Role=?, Phone=?, Password=? WHERE Employee_ID=?");
            $stmt->bind_param("ssssi", $name, $role, $phone, $password, $id);
        } else {
            $stmt = $conn->prepare("UPDATE employee SET Name=?, Role=?, Phone=? WHERE Employee_ID=?");
            $stmt->bind_param("sssi", $name, $role, $phone, $id);
        }

        if ($stmt->execute()) {
            $toast = "Employee updated successfully!";
            $toast_type = "success";
        } else {
            $toast = "Error updating employee: " . $conn->error;
            $toast_type = "error";
        }
        $stmt->close();
    }
}

/* --- Fetch Records for Current Page --- */
$distinct_roles = $conn->query("SELECT COUNT(DISTINCT Role) AS r FROM employee")->fetch_assoc()['r'];
$result = $conn->query("SELECT * FROM employee ORDER BY Employee_ID ASC LIMIT $results_per_page OFFSET $start_from");
$csrf_token = generate_csrf_token();
$active_page = 'employees';
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
</head>

<body>
    <div class="dashboard-layout">
        <?php include('../includes/sidebar_admin.php'); ?>

        <div class="main-content">
            <header class="dashboard-header" id="header">
                <div class="header-left">
                    <button class="sidebar-mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="header-title-block">
                        <h1><i class="fas fa-user-tie"></i> Employee Management</h1>
                        <p>Add, view, and manage employee records</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button id="toggle-theme" class="btn-icon">
                        <i class="fas fa-moon"></i>
                        <span>Dark Mode</span>
                    </button>
                    <a href="dashboard_admin.php" class="btn-icon">
                        <i class="fas fa-arrow-left"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="../logout.php" class="btn-icon logout">
                        <i class="fas fa-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </header>

            <div class="dashboard-content">
                <?= display_flash_msg($toast ?? $msg ?? null, $toast_type ?? $msg_type ?? "success") ?>

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
                    <div class="search-filter" style="display: flex; gap: 10px;">
                        <input type="text" id="searchInput" placeholder="🔍 Search by name, role, or phone..." class="form-control" style="width: 260px;">
                        <select id="sortSelect" class="form-control" style="width: 170px;">
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
                                            <button class="btn btn-primary btn-sm" style="margin-right:5px;"
                                                onclick="openEditModal('<?= $row['Employee_ID'] ?>','<?= htmlspecialchars($row['Name']) ?>','<?= htmlspecialchars($row['Role']) ?>','<?= htmlspecialchars($row['Phone']) ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="delete_id" value="<?= $row['Employee_ID'] ?>">
                                                <button type="button" class="btn btn-danger btn-sm"
                                                    onclick="openDeleteModal('<?= $row['Employee_ID'] ?>', '<?= htmlspecialchars($row['Name']) ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; color:#999; padding: 25px;">No employees found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ADD MODAL -->
                <div id="addModal" class="modal">
                    <div class="modal-content">
                        <h2><i class="fas fa-user-plus"></i> Add New Employee</h2>
                        <form method="POST" style="margin-top: 15px;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name" class="form-control" placeholder="Enter Full Name" required>
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" name="role" class="form-control" placeholder="e.g. Field Officer, Inspector" required>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="10-digit mobile number" required>
                            </div>
                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Set temporary password" required minlength="6">
                            </div>
                            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                                <button type="submit" name="add_employee" class="btn btn-primary"><i class="fas fa-save"></i> Save Employee</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- EDIT MODAL -->
                <div id="editModal" class="modal">
                    <div class="modal-content">
                        <h2><i class="fas fa-user-pen"></i> Edit Employee</h2>
                        <form method="POST" style="margin-top: 15px;">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="id" id="edit_id">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" name="role" id="edit_role" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" name="phone" id="edit_phone" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>New Password (Leave blank to keep unchanged)</label>
                                <input type="password" name="password" class="form-control" placeholder="Optional new password">
                            </div>
                            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                                <button type="submit" name="update_employee" class="btn btn-primary"><i class="fas fa-save"></i> Update Employee</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- DELETE MODAL -->
                <div id="deleteModal" class="modal">
                    <div class="modal-content">
                        <h2 style="color: #ef4444;"><i class="fas fa-triangle-exclamation"></i> Confirm Delete</h2>
                        <p id="delete_msg" style="margin: 15px 0;">Are you sure you want to delete this employee?</p>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="delete_id" id="delete_emp_id">
                            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                                <button type="submit" name="confirm_delete" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div> <!-- close .dashboard-content -->
        </div> <!-- close .main-content -->
    </div> <!-- close .dashboard-layout -->

    <script>
        function openAddModal() { document.getElementById('addModal').classList.add('active'); }
        function closeAddModal() { document.getElementById('addModal').classList.remove('active'); }

        function openEditModal(id, name, role, phone) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('editModal').classList.add('active');
        }
        function closeEditModal() { document.getElementById('editModal').classList.remove('active'); }

        function openDeleteModal(id, name) {
            document.getElementById('delete_emp_id').value = id;
            document.getElementById('delete_msg').innerText = `Are you sure you want to permanently delete employee "${name}" (#${id})?`;
            document.getElementById('deleteModal').classList.add('active');
        }
        function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('active'); }

        // Live Filter
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#employeesTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // Sort Functionality
        const sortSelect = document.getElementById("sortSelect");
        const tbody = document.querySelector("#employeesTable tbody");
        function sortTable(value) {
            const rows = Array.from(tbody.querySelectorAll("tr"));
            if (rows.length === 0) return;
            rows.sort((a, b) => {
                const idA = parseInt(a.children[0].textContent.replace('#', '')) || 0;
                const idB = parseInt(b.children[0].textContent.replace('#', '')) || 0;
                const nameA = (a.children[1]?.textContent || '').toLowerCase();
                const nameB = (b.children[1]?.textContent || '').toLowerCase();
                if (value === "id-asc") return idA - idB;
                if (value === "id-desc") return idB - idA;
                if (value === "name-asc") return nameA.localeCompare(nameB);
                if (value === "name-desc") return nameB.localeCompare(nameA);
                return 0;
            });
            tbody.innerHTML = "";
            rows.forEach(r => tbody.appendChild(r));
        }
        if (sortSelect) {
            sortSelect.addEventListener("change", function() {
                sortTable(this.value);
            });
        }
    </script>

    <?php include('../includes/footer.php'); ?>
