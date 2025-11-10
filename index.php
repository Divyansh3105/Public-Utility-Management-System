<?php
session_start();
include('includes/db_connect.php');

// Redirect if already logged in
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin/dashboard_admin.php");
            exit;
        case 'employee':
            header("Location: employee/dashboard_employee.php");
            exit;
        case 'customer':
            header("Location: customer/dashboard_customer.php");
            exit;
    }
}

if (isset($_POST['login']) && isset($_POST['csrf_token'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $username = sanitize_input($_POST['email']);
        $password = $_POST['password'];

        // Check Admin
        $stmt = $conn->prepare("SELECT * FROM admin WHERE Username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows == 1) {
            $admin = $res->fetch_assoc();
            if ($password === $admin['Password']) {
                session_regenerate_id(true);
                $_SESSION['role'] = 'admin';
                $_SESSION['name'] = $admin['Name'];
                $_SESSION['admin_name'] = $admin['Name'];
                $_SESSION['admin_id'] = $admin['Admin_ID'];
                $stmt->close();
                header("Location: admin/dashboard_admin.php");
                exit;
            }
        }
        $stmt->close();

        // Check Employee
        $stmt = $conn->prepare("SELECT * FROM employee WHERE Phone=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows == 1) {
            $employee = $res->fetch_assoc();
            if ($password === $employee['Password']) {
                session_regenerate_id(true);
                $_SESSION['role'] = 'employee';
                $_SESSION['name'] = $employee['Name'];
                $_SESSION['employee_id'] = $employee['Employee_ID'];
                $stmt->close();
                header("Location: employee/dashboard_employee.php");
                exit;
            }
        }
        $stmt->close();

        // Check Customer
        $stmt = $conn->prepare("SELECT * FROM customer WHERE Email=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows == 1) {
            $customer = $res->fetch_assoc();
            if ($password === $customer['Password']) {
                session_regenerate_id(true);
                $_SESSION['role'] = 'customer';
                $_SESSION['name'] = $customer['Name'];
                $_SESSION['customer_id'] = $customer['Customer_ID'];
                $stmt->close();
                header("Location: customer/dashboard_customer.php");
                exit;
            }
        }
        $stmt->close();

        $error = "Invalid credentials. Please try again.";
    } else {
        $error = "Invalid request. Please try again.";
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Public Utility Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            transition: all 0.4s ease;
        }

        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }

        .login-container {
            background: white;
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            animation: slideIn 0.6s ease;
        }

        body.dark-mode .login-container {
            background: #2b2b3c;
            color: #f1f1f1;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .login-header .logo i {
            font-size: 40px;
            color: white;
        }

        .login-header h1 {
            font-size: 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        body.dark-mode .login-header h1 {
            background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-header p {
            color: #666;
            font-size: 14px;
        }

        body.dark-mode .login-header p {
            color: #a0a0a0;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        body.dark-mode .form-group label {
            color: #e0e0e0;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 16px;
        }

        .form-control {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        body.dark-mode .form-control {
            background: #1e1e2e;
            border-color: #3a3a4a;
            color: #f1f1f1;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        body.dark-mode .form-control:focus {
            background: #2b2b3c;
            border-color: #818cf8;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        .error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s;
        }

        body.dark-mode .error-message {
            background: linear-gradient(135deg, #3a1a1a 0%, #4a2a2a 100%);
            color: #ff6b6b;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-10px);
            }

            75% {
                transform: translateX(10px);
            }
        }

        .demo-credentials {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 12px;
            margin-top: 25px;
            border: 2px solid #dee2e6;
        }

        body.dark-mode .demo-credentials {
            background: linear-gradient(135deg, #1e1e2e 0%, #2a2a3c 100%);
            border-color: #3a3a4a;
        }

        .demo-credentials h4 {
            color: #667eea;
            margin-bottom: 12px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        body.dark-mode .demo-credentials h4 {
            color: #818cf8;
        }

        .demo-item {
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }

        body.dark-mode .demo-item {
            border-bottom-color: #3a3a4a;
        }

        .demo-item:last-child {
            border-bottom: none;
        }

        .demo-item strong {
            color: #333;
            min-width: 80px;
            display: inline-block;
        }

        body.dark-mode .demo-item strong {
            color: #e0e0e0;
        }

        .demo-item span {
            color: #666;
        }

        body.dark-mode .demo-item span {
            color: #a0a0a0;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border: 2px solid #667eea;
            border-radius: 50px;
            padding: 10px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #667eea;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            z-index: 1000;
        }

        body.dark-mode .theme-toggle {
            background: #2b2b3c;
            border-color: #818cf8;
            color: #818cf8;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 35px 25px;
            }

            .theme-toggle {
                top: 10px;
                right: 10px;
                padding: 8px 15px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
        <span>Dark Mode</span>
    </button>

    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-plug"></i>
            </div>
            <h1>Public Utility System</h1>
            <p>Electricity & Water Bill Management</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">

            <div class="form-group">
                <label>Email / Phone / Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" name="email" class="form-control" required autocomplete="username" autofocus>
                </div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
            </div>

            <button type="submit" name="login" class="btn-login">
                <i class="fas fa-sign-in-alt"></i>
                <span>Login</span>
            </button>
        </form>

        <div class="demo-credentials">
            <h4>
                <i class="fas fa-info-circle"></i>
                Demo Credentials
            </h4>
            <div class="demo-item">
                <strong>Admin:</strong>
                <span>admin / 1234</span>
            </div>
            <div class="demo-item">
                <strong>Employee:</strong>
                <span>9876543210 / emp101</span>
            </div>
            <div class="demo-item">
                <strong>Customer:</strong>
                <span>divyansh@gmail.com / cust201</span>
            </div>
        </div>
    </div>

    <script>
        // Dark mode toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const saved = localStorage.getItem('theme') || 'light';

        if (saved === 'dark') {
            body.classList.add('dark-mode');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i><span>Light Mode</span>';
        }

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const mode = body.classList.contains('dark-mode') ? 'dark' : 'light';
            localStorage.setItem('theme', mode);
            themeToggle.innerHTML = mode === 'dark' ?
                '<i class="fas fa-sun"></i><span>Light Mode</span>' :
                '<i class="fas fa-moon"></i><span>Dark Mode</span>';
        });

        // Auto-focus on error
        <?php if (isset($error)): ?>
            document.querySelector('input[name="email"]').focus();
        <?php endif; ?>
    </script>
</body>

</html>
