<?php
include("includes/db_connect.php");

if (!isset($_SESSION["role"])) {
    redirect("index.php");
    exit;
}

$role = $_SESSION["role"];
$user_id = $_SESSION[$role . "_id"] ?? 1;
$msg = null;
$msg_type = "success";

// Handle Password Change
if (isset($_POST["change_password"]) && verify_csrf_token($_POST["csrf_token"] ?? "")) {
    $current = $_POST["current_password"];
    $new = $_POST["new_password"];
    $confirm = $_POST["confirm_password"];

    if (empty($current) || empty($new) || empty($confirm)) {
        $msg = "Please fill all password fields.";
        $msg_type = "error";
    } else if ($new !== $confirm) {
        $msg = "New password and confirmation do not match.";
        $msg_type = "error";
    } else if (strlen($new) < 6) {
        $msg = "New password must be at least 6 characters long.";
        $msg_type = "error";
    } else {
        $table = ($role === "admin") ? "admin" : (($role === "employee") ? "employee" : "customer");
        $id_field = ($role === "admin") ? "Admin_ID" : (($role === "employee") ? "Employee_ID" : "Customer_ID");

        $stmt = $conn->prepare("SELECT Password FROM `$table` WHERE `$id_field` = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res && verify_password($current, $res["Password"])) {
            $hashed = hash_password($new);
            $stmt = $conn->prepare("UPDATE `$table` SET Password = ? WHERE `$id_field` = ?");
            $stmt->bind_param("si", $hashed, $user_id);
            if ($stmt->execute()) {
                $msg = "Password updated successfully!";
                $msg_type = "success";
            } else {
                $msg = "Error updating password.";
                $msg_type = "error";
            }
            $stmt->close();
        } else {
            $msg = "Incorrect current password!";
            $msg_type = "error";
        }
    }
}

$page_title = "User Profile & Security";
$active_page = "profile";
?>
<?php include("includes/header.php"); ?>

<div class="dashboard-content">
    <?= display_flash_msg($msg, $msg_type) ?>

    <h2 class="section-header"><i class="fas fa-user-gear"></i> Account Profile & Security</h2>

    <div class="charts-grid">
        <div class="chart-container">
            <h3><i class="fas fa-id-badge"></i> Account Details</h3>
            <p><strong>Name:</strong> <?= e($_SESSION["name"] ?? "User") ?></p>
            <p style="margin-top:10px;"><strong>Role:</strong> <span class="badge badge-info"><?= ucfirst($role) ?></span></p>
            <p style="margin-top:10px;"><strong>Account ID:</strong> #<?= e($user_id) ?></p>
        </div>

        <div class="chart-container">
            <h3><i class="fas fa-key"></i> Change Password</h3>
            <form method="POST" style="display:flex; flex-direction:column; gap:15px;">
                <?= csrf_field() ?>
                <div>
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div>
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div>
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <button type="submit" name="change_password" class="btn btn-primary"><i class="fas fa-lock"></i> Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php include("includes/footer.php"); ?>
