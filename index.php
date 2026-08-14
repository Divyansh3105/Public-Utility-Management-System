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
            if (verify_user_password($conn, 'admin', 'Admin_ID', $admin['Admin_ID'], $password, $admin['Password'])) {
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
            if (verify_user_password($conn, 'employee', 'Employee_ID', $employee['Employee_ID'], $password, $employee['Password'])) {
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
            if (verify_user_password($conn, 'customer', 'Customer_ID', $customer['Customer_ID'], $password, $customer['Password'])) {
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
    <link rel="icon" href="assets/public.png" type="image/png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Public Utility Management System</title>
    <meta name="description" content="A full-featured PHP and MySQL-based system for managing electricity and water utility services. Includes modules for billing, payments, employee management, and real-time analytics.">
    <meta name="keywords" content="Utility Management System, PHP MySQL Project, Electricity Billing System, Water Bill Management, Admin Dashboard, Public Utility, Smart Billing, College Project, Divyansh, Public Utility System">
    <meta name="author" content="Divyansh">
    <meta name="robots" content="index, follow">
    <meta name="language" content="English">

    <!-- ========== Open Graph (OG) Tags ========== -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Public Utility Management System">
    <meta property="og:description" content="An advanced public utility management platform for handling billing, payments, and employee operations using PHP and MySQL. Perfect for B.Tech and IT project use.">
    <meta property="og:site_name" content="Public Utility Management System">
    <meta property="og:image" content="https://raw.githubusercontent.com/divyansh/Public-Utility-Management-System/main/assets/preview.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            transition: all 0.4s ease;
        }

        body.dark-mode {
            background: #0f1419;
        }

        .login-wrapper {
            display: flex;
            width: 100%;
            max-width: 1400px;
            min-height: 650px;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        body.dark-mode .login-wrapper {
            background: #1a1f2e;
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.6);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(40px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* ========== LEFT SIDE - IMAGE ========== */
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #d0e8f2 0%, #b8dce8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            position: relative;
            overflow: hidden;
        }

        body.dark-mode .login-left {
            background: linear-gradient(135deg, #1a2332 0%, #0f1823 100%);
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: movePattern 20s linear infinite;
        }

        @keyframes movePattern {
            0% {
                transform: translate(0, 0);
            }

            100% {
                transform: translate(40px, 40px);
            }
        }

        .banner-image-container {
            position: relative;
            z-index: 1;
            max-width: 600px;
            width: 100%;
        }

        .banner-image-container img {
            width: 100%;
            height: auto;
            display: block;
            filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.2));
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        body.dark-mode .banner-image-container img {
            filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.5)) brightness(0.95);
        }

        /* ========== RIGHT SIDE - LOGIN FORM ========== */
        .login-right {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            background: white;
        }

        body.dark-mode .login-right {
            background: #1a1f2e;
        }

        .theme-toggle {
            position: absolute;
            top: 25px;
            right: 25px;
            background: transparent;
            border: 2px solid #667eea;
            border-radius: 50px;
            padding: 10px 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #667eea;
            transition: all 0.3s ease;
            font-size: 13px;
        }

        body.dark-mode .theme-toggle {
            border-color: #818cf8;
            color: #818cf8;
        }

        .theme-toggle:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        body.dark-mode .theme-toggle:hover {
            background: #818cf8;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header .logo {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.35);
        }

        body.dark-mode .login-header .logo {
            background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
        }

        .login-header .logo i {
            font-size: 45px;
            color: white;
        }

        .login-header h1 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 8px;
            font-weight: 700;
        }

        body.dark-mode .login-header h1 {
            color: #f1f1f1;
        }

        .login-header p {
            color: #666;
            font-size: 15px;
            font-weight: 500;
        }

        body.dark-mode .login-header p {
            color: #a0a0a0;
        }

        .form-container {
            max-width: 420px;
            margin: 0 auto;
            width: 100%;
        }

        .error-message {
            background: linear-gradient(135deg, #fee 0%, #fdd 100%);
            border-left: 5px solid #dc3545;
            color: #721c24;
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: shake 0.6s;
            font-weight: 500;
            font-size: 14px;
        }

        body.dark-mode .error-message {
            background: linear-gradient(135deg, #3a1a1a 0%, #4a2020 100%);
            color: #ff6b6b;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-10px);
            }

            40%,
            80% {
                transform: translateX(10px);
            }
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #2c3e50;
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
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        body.dark-mode .input-wrapper i {
            color: #666;
        }

        .form-control {
            width: 100%;
            padding: 16px 50px 16px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            font-family: inherit;
            color: #2c3e50;
        }

        body.dark-mode .form-control {
            background: #252b3a;
            border-color: #3a3a4a;
            color: #f1f1f1;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        body.dark-mode .form-control:focus {
            background: #2b3142;
            border-color: #818cf8;
        }

        .form-control::placeholder {
            color: #aaa;
        }

        body.dark-mode .form-control::placeholder {
            color: #666;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 45px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        body.dark-mode .btn-login {
            background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
        }

        .demo-credentials {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 14px;
            margin-top: 30px;
            border: 2px solid #e0e0e0;
        }

        body.dark-mode .demo-credentials {
            background: linear-gradient(135deg, #252b3a 0%, #1f2532 100%);
            border-color: #3a3a4a;
        }

        .demo-credentials h4 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
        }

        body.dark-mode .demo-credentials h4 {
            color: #818cf8;
        }

        .demo-item {
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        body.dark-mode .demo-item {
            border-bottom-color: #3a3a4a;
        }

        .demo-item:last-child {
            border-bottom: none;
        }

        .demo-item strong {
            color: #2c3e50;
            font-weight: 600;
        }

        body.dark-mode .demo-item strong {
            color: #e0e0e0;
        }

        .demo-item span {
            color: #666;
            font-family: 'Courier New', monospace;
            font-weight: 500;
        }

        body.dark-mode .demo-item span {
            color: #a0a0a0;
        }

        /* ========== RESPONSIVE DESIGN ========== */
        @media (max-width: 1024px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 600px;
            }

            .login-left {
                padding: 40px 30px;
                min-height: 350px;
            }

            .login-right {
                padding: 50px 40px;
            }

            .banner-image-container {
                max-width: 500px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .login-wrapper {
                min-height: auto;
            }

            .login-left {
                padding: 30px 20px;
                min-height: 280px;
            }

            .login-right {
                padding: 40px 30px;
            }

            .login-header h1 {
                font-size: 26px;
            }

            .login-header .logo {
                width: 75px;
                height: 75px;
            }

            .login-header .logo i {
                font-size: 38px;
            }

            .theme-toggle {
                padding: 8px 15px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .login-left {
                padding: 25px 15px;
                min-height: 220px;
            }

            .login-right {
                padding: 35px 25px;
            }

            .login-header h1 {
                font-size: 24px;
            }

            .demo-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .form-container {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <!-- Left Side - Banner Image -->
        <div class="login-left">
            <div class="banner-image-container">
                <img src="assets/public-utility-banner.png" alt="Public Utility Connect - Manage Your Essential Services" loading="lazy">
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
                <span>Dark</span>
            </button>

            <div class="form-container">
                <div class="login-header">
                    <div class="logo">
                        <i class="fas fa-plug"></i>
                    </div>
                    <h1>Login to Your Account</h1>
                    <p>Public Utility Management System</p>
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
                            <input type="text" name="email" class="form-control" placeholder="Enter your email or phone" required autocomplete="username" autofocus>
                            <i class="fas fa-user"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                            <i class="fas fa-lock"></i>
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
        </div>
    </div>

    <script>
        // Dark mode toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const saved = localStorage.getItem('theme') || 'light';

        if (saved === 'dark') {
            body.classList.add('dark-mode');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i><span>Light</span>';
        }

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const mode = body.classList.contains('dark-mode') ? 'dark' : 'light';
            localStorage.setItem('theme', mode);
            themeToggle.innerHTML = mode === 'dark' ?
                '<i class="fas fa-sun"></i><span>Light</span>' :
                '<i class="fas fa-moon"></i><span>Dark</span>';
        });

        // Auto-focus on error
        <?php if (isset($error)): ?>
            document.querySelector('input[name="email"]').focus();
        <?php endif; ?>

        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>

</html>
