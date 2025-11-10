<?php
session_start();
include('../includes/db_connect.php');
include('activity_log.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

/**
 * Provide pagination parameters.
 * If you already have a get_pagination_params() function (from includes),
 * this won't override it.
 */
if (!function_exists('get_pagination_params')) {
    function get_pagination_params($default_limit = 50)
    {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $default_limit;
        // enforce reasonable bounds to avoid abuse
        if ($limit <= 0) $limit = $default_limit;
        if ($limit > 500) $limit = 500;

        $offset = ($page - 1) * $limit;
        if ($offset < 0) $offset = 0;

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
}

// Get pagination parameters
$pagination = get_pagination_params(50);
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

// DELETE
if (isset($_POST['confirm_delete']) && isset($_POST['csrf_token'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $id = intval($_POST['delete_id']);
        $check_bills = $conn->prepare("
            SELECT COUNT(*) as unpaid_count FROM (
                SELECT Bill_ID FROM electric_bill WHERE Customer_ID=? AND Status='Unpaid'
                UNION ALL
                SELECT Bill_ID FROM water_bill WHERE Customer_ID=? AND Status='Unpaid'
            ) as bills
        ");
        $check_bills->bind_param("ii", $id, $id);
        $check_bills->execute();
        $unpaid = $check_bills->get_result()->fetch_assoc()['unpaid_count'];
        $check_bills->close();

        if ($unpaid > 0) {
            $toast = "Cannot delete! Customer has {$unpaid} unpaid bill(s).";
            $toast_type = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM customer WHERE Customer_ID=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $toast = "Customer deleted successfully!";
                $toast_type = "success";
            } else {
                $toast = "Error deleting customer: " . $conn->error;
                $toast_type = "error";
            }
            $stmt->close();
        }
    }
}

// ADD
if (isset($_POST['add_customer']) && isset($_POST['csrf_token'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $house_num = sanitize_input($_POST['house_number']);
        $owner = sanitize_input($_POST['owner_name']);
        $address = sanitize_input($_POST['address']);
        $name = sanitize_input($_POST['name']);
        $phone = sanitize_input($_POST['phone']);
        $email = sanitize_input($_POST['email']);
        $password = sanitize_input($_POST['password']);

        $house_stmt = $conn->prepare("INSERT INTO house (House_Number, Owner_Name, Address) VALUES (?, ?, ?)");
        $house_stmt->bind_param("sss", $house_num, $owner, $address);

        if ($house_stmt->execute()) {
            $house_id = $house_stmt->insert_id;
            $cust_stmt = $conn->prepare("INSERT INTO customer (Name, Phone, Email, Password, House_ID) VALUES (?, ?, ?, ?, ?)");
            $cust_stmt->bind_param("ssssi", $name, $phone, $email, $password, $house_id);

            if ($cust_stmt->execute()) {
                $toast = "Customer and House added successfully!";
                $toast_type = "success";
            } else {
                $toast = "Failed to add customer: " . $conn->error;
                $toast_type = "error";
            }
            $cust_stmt->close();
        } else {
            $toast = "Failed to add house: " . $conn->error;
            $toast_type = "error";
        }
        $house_stmt->close();
    }
}

// EDIT
if (isset($_POST['edit_customer']) && isset($_POST['csrf_token'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $customer_id = intval($_POST['customer_id']);
        $house_id = intval($_POST['house_id']);
        $name = sanitize_input($_POST['name']);
        $phone = sanitize_input($_POST['phone']);
        $email = sanitize_input($_POST['email']);
        $house_number = sanitize_input($_POST['house_number']);
        $owner_name = sanitize_input($_POST['owner_name']);
        $address = sanitize_input($_POST['address']);

        $hstmt = $conn->prepare("UPDATE house SET House_Number=?, Owner_Name=?, Address=? WHERE House_ID=?");
        $hstmt->bind_param("sssi", $house_number, $owner_name, $address, $house_id);
        $h_ok = $hstmt->execute();
        $hstmt->close();

        $cstmt = $conn->prepare("UPDATE customer SET Name=?, Phone=?, Email=? WHERE Customer_ID=?");
        $cstmt->bind_param("sssi", $name, $phone, $email, $customer_id);
        $c_ok = $cstmt->execute();
        $cstmt->close();

        if ($h_ok && $c_ok) {
            $toast = "Customer & House updated successfully!";
            $toast_type = "success";
        } else {
            $toast = "Error updating records: " . $conn->error;
            $toast_type = "error";
        }
    }
}

// FETCH counts
$total_customers = (int)$conn->query("SELECT COUNT(*) as count FROM customer")->fetch_assoc()['count'];
$active_bills = $conn->query("
    SELECT COUNT(*) as count FROM (
        SELECT Bill_ID FROM electric_bill WHERE Status='Unpaid'
        UNION ALL
        SELECT Bill_ID FROM water_bill WHERE Status='Unpaid'
    ) as bills
")->fetch_assoc()['count'];

// Compute total pages
$total_pages = $limit > 0 ? (int)ceil($total_customers / $limit) : 1;
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;

// FETCH customers with LIMIT/OFFSET for pagination
$limit_safe = (int)$limit;
$offset_safe = (int)$offset;
$sql = "SELECT c.*, h.House_ID, h.House_Number, h.Owner_Name, h.Address
        FROM customer c
        LEFT JOIN house h ON c.House_ID = h.House_ID
        ORDER BY Customer_ID ASC
        LIMIT $limit_safe OFFSET $offset_safe";
$result = $conn->query($sql);

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" href="../assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Public Utility System</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
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
    <!-- Fixed Header -->
    <header class="dashboard-header" id="header">
        <div class="header-left">
            <h1>
                <i class="fas fa-users"></i>
                Customer Management
            </h1>
            <p>Add, view, and manage customers and their linked houses</p>
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

    <!-- Main Content -->
    <div class="dashboard-content">

        <?php if (isset($toast)): ?>
            <div class="alert alert-<?= $toast_type ?>">
                <i class="fas <?= $toast_type == 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                <span><?= htmlspecialchars($toast) ?></span>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Total Customers</h3>
                <div class="stat-value"><?= $total_customers ?></div>
            </div>
            <div class="stat-card danger">
                <h3><i class="fas fa-exclamation-circle"></i> Active Unpaid Bills</h3>
                <div class="stat-value"><?= $active_bills ?></div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0 20px 0; flex-wrap: wrap; gap: 15px;">
            <button onclick="openAddModal()" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Add Customer
            </button>
            <div class="search-filter">
                <input type="text" id="searchInput" placeholder="🔍 Search by name, email, or house...">
                <select id="sortSelect">
                    <option value="id-asc">Sort by ID ↑</option>
                    <option value="id-desc">Sort by ID ↓</option>
                    <option value="name-asc">Name A–Z</option>
                    <option value="name-desc">Name Z–A</option>
                </select>
            </div>
        </div>

        <h2 class="section-header">
            <i class="fas fa-list"></i>
            Customer List
        </h2>

        <div class="table-container">
            <table id="customersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>House ID</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($row['Customer_ID']) ?></strong></td>
                                <td><?= htmlspecialchars($row['Name']) ?></td>
                                <td><?= htmlspecialchars($row['Phone']) ?></td>
                                <td><?= htmlspecialchars($row['Email']) ?></td>
                                <td>#<?= htmlspecialchars($row['House_ID']) ?></td>
                                <td>
                                    <button class="btn btn-primary" style="padding: 8px 12px; margin-right: 5px;"
                                        onclick='openEditModal(<?= json_encode($row["Customer_ID"]) ?>, <?= json_encode($row["Name"]) ?>, <?= json_encode($row["Phone"]) ?>, <?= json_encode($row["Email"]) ?>, <?= json_encode($row["House_ID"]) ?>, <?= json_encode($row["House_Number"]) ?>, <?= json_encode($row["Owner_Name"]) ?>, <?= json_encode($row["Address"]) ?>, <?= json_encode($row["Password"]) ?>)'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-danger" style="padding: 8px 12px;"
                                        onclick='confirmDelete(<?= json_encode($row["Customer_ID"]) ?>, <?= json_encode($row["Name"]) ?>)'>
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <p>No customers yet</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination (below table) -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin-top: 20px; text-align: center;">
                <?php if ($page > 1): ?>
                    <a href="?page=1&limit=<?= $limit ?>" class="page-btn"><i class="fas fa-angle-double-left"></i></a>
                    <a href="?page=<?= $page - 1 ?>&limit=<?= $limit ?>" class="page-btn"><i class="fas fa-angle-left"></i> Prev</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="page-btn active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&limit=<?= $limit ?>" class="page-btn"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&limit=<?= $limit ?>" class="page-btn">Next <i class="fas fa-angle-right"></i></a>
                    <a href="?page=<?= $total_pages ?>&limit=<?= $limit ?>" class="page-btn"><i class="fas fa-angle-double-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
    <!-- ADD MODAL -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add New Customer & House</h2>
                <button class="close-btn" onclick="closeAddModal()">✕</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <h3 style="color: #667eea; margin: 20px 0 15px 0; font-size: 18px;">
                    <i class="fas fa-home"></i> House Details
                </h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>House Number</label>
                        <input type="text" name="house_number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="2" required></textarea>
                </div>

                <h3 style="color: #667eea; margin: 20px 0 15px 0; font-size: 18px;">
                    <i class="fas fa-user"></i> Customer Details
                </h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="text" name="password" class="form-control" required>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="add_customer" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Customer
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Customer & House</h2>
                <button class="close-btn" onclick="closeEditModal()">✕</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                <input type="hidden" name="house_id" id="edit_house_id">

                <h3 style="color: #667eea; margin: 20px 0 15px 0; font-size: 18px;">
                    <i class="fas fa-home"></i> House Details
                </h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>House Number</label>
                        <input type="text" name="house_number" id="edit_house_number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" id="edit_owner_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2" required></textarea>
                </div>

                <h3 style="color: #667eea; margin: 20px 0 15px 0; font-size: 18px;">
                    <i class="fas fa-user"></i> Customer Details
                </h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password (Read-only)</label>
                        <input type="text" id="edit_password" class="form-control" disabled>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="edit_customer" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE MODAL -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h2>
                <button class="close-btn" onclick="closeDeleteModal()">✕</button>
            </div>
            <p style="margin: 20px 0; font-size: 16px;">
                Are you sure you want to delete <strong id="customerName"></strong>?
            </p>
            <p style="color: #dc3545; margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i> This action cannot be undone.
            </p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="delete_id" id="deleteId">
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="confirm_delete" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Now
                    </button>
                </div>
            </form>
        </div>
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
                if (window.scrollY > 30) {
                    header.classList.add('shrink');
                } else {
                    header.classList.remove('shrink');
                }
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

        function openEditModal(cId, name, phone, email, hId, hNum, owner, addr, pwd) {
            document.getElementById('edit_customer_id').value = cId;
            document.getElementById('edit_house_id').value = hId;
            document.getElementById('edit_house_number').value = hNum;
            document.getElementById('edit_owner_name').value = owner;
            document.getElementById('edit_address').value = addr;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_password').value = pwd;
            document.getElementById('editModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function confirmDelete(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('customerName').textContent = name;
            document.getElementById('deleteModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
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
            document.querySelectorAll("#customersTable tbody tr").forEach(r => {
                if (r.querySelector('.empty-state')) return;
                r.style.display = r.textContent.toLowerCase().includes(filter) ? "" : "none";
            });
        });

        // Sort functionality
        const sortSelect = document.getElementById("sortSelect");
        const tbody = document.querySelector("#customersTable tbody");

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
