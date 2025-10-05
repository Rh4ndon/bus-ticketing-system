<?php
session_start();
require_once __DIR__ . "/../admin/config.php";

$db = new Database();
$conn = $db->getConnection();

// Initialize variables
$error_message = '';
$success_message = '';
$debug_info = '';

// Check for timeout parameter
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error_message = 'Your session has expired. Please login again.';
}

// Check for success messages
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success_message = 'Registration successful! Please login with your credentials.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile_number = isset($_POST['mobile_number']) ? sanitizeInput($_POST['mobile_number']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($mobile_number) || empty($password)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Validate mobile number format
        if (!preg_match('/^09[0-9]{9}$/', $mobile_number)) {
            $error_message = 'Please enter a valid mobile number (09XXXXXXXXX).';
        } else {
            try {
                // First, let's check if there are any users at all
                $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
                $count_stmt->execute();
                $count_result = $count_stmt->fetch();
                $debug_info .= "Total users in database: " . $count_result['total'] . "<br>";

                // Check if the specific mobile number exists
                $check_stmt = $conn->prepare("SELECT user_id, mobile_number, full_name FROM users WHERE mobile_number = ?");
                $check_stmt->execute([$mobile_number]);
                $user_check = $check_stmt->fetch();

                if ($user_check) {
                    $debug_info .= "User found: " . $user_check['full_name'] . " (ID: " . $user_check['user_id'] . ")<br>";
                } else {
                    $debug_info .= "No user found with mobile number: " . $mobile_number . "<br>";
                }

                // Now try the full login query
                $stmt = $conn->prepare("SELECT user_id, mobile_number, password, full_name, status FROM users WHERE mobile_number = ? LIMIT 1");
                $stmt->execute([$mobile_number]);
                $user = $stmt->fetch();

                if ($user) {
                    $debug_info .= "User status: " . $user['status'] . "<br>";
                    $debug_info .= "Stored password hash: " . substr($user['password'], 0, 20) . "...<br>";
                    $debug_info .= "Attempting password verification...<br>";

                    // Try password verification
                    $password_check = verifyPassword($password, $user['password']);
                    $debug_info .= "Password verification result: " . ($password_check ? "SUCCESS" : "FAILED") . "<br>";

                    // Also try direct password_verify without salt
                    $direct_check = password_verify($password, $user['password']);
                    $debug_info .= "Direct password_verify result: " . ($direct_check ? "SUCCESS" : "FAILED") . "<br>";

                    if ($password_check || $direct_check) {
                        // Check if account is active
                        if ($user['status'] !== 'active') {
                            $error_message = 'Your account has been deactivated. Please contact support.';
                        } else {
                            // Login successful
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['mobile_number'] = $user['mobile_number'];
                            $_SESSION['full_name'] = $user['full_name'];
                            $_SESSION['last_activity'] = time();

                            // Redirect to dashboard
                            header("Location: passenger_dashboard.php");
                            exit();
                        }
                    } else {
                        $error_message = 'Invalid mobile number or password.';
                    }
                } else {
                    $error_message = 'Invalid mobile number or password.';
                }
            } catch (PDOException $e) {
                $error_message = 'Database error. Please try again later.';
                $debug_info .= "Database error: " . $e->getMessage() . "<br>";
                error_log("Login error: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php echo SITE_NAME; ?> - Passenger Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #eef1f3ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header i {
            font-size: 60px;
            color: #2196f3;
            margin-bottom: 15px;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 16px;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .alert-success {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
            font-size: 12px;
            line-height: 1.4;
        }

        .debug-info {
            background: #fff3e0;
            border: 1px solid #ffcc02;
            color: #e65100;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 12px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            color: #2196f3;
            border-color: #2196f3;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group .input-icon {
            position: absolute;
            right: 20px;
            top: 48px;
            color: #666;
            font-size: 18px;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            color: #2196f3;
            background: #2196f3;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .test-user-btn {
            width: 100%;
            padding: 12px;
            border: 2px solid #ff9800;
            background: transparent;
            color: #ff9800;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .test-user-btn:hover {
            background: #ff9800;
            color: white;
        }

        .form-footer {
            text-align: center;
            margin-top: 30px;
        }

        .form-footer a {
            color: #2196f3;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .divider {
            text-align: center;
            margin: 30px 0;
            color: #666;
            font-size: 14px;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e0e0e0;
        }

        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 20px;
            position: relative;
        }

        .register-link {
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid #2196f3;
            color: #2196f3;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            display: block;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .register-link:hover {
            background: #2196f3;
            color: white;
            transform: translateY(-1px);
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 30px 25px;
                margin: 10px;
            }

            .header h1 {
                font-size: 24px;
            }

            .header i {
                font-size: 50px;
            }
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="header">
            <i class="fas fa-bus"></i>
            <h1>Login</h1>
        </div>

        <?php if ($debug_info): ?>
            <div class="debug-info">
                <strong>Debug Information:</strong><br>
                <?php echo $debug_info; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>



        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="mobile_number">Mobile Number</label>
                <input type="text"
                    id="mobile_number"
                    name="mobile_number"
                    placeholder="09XXXXXXXXX"
                    pattern="09[0-9]{9}"
                    maxlength="11"
                    value="<?php echo isset($_POST['mobile_number']) ? htmlspecialchars($_POST['mobile_number']) : ''; ?>"
                    required>
                <i class="fas fa-phone input-icon"></i>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required>
                <i class="fas fa-lock input-icon"></i>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i>
                <span>Login</span>
            </button>
        </form>



        <div class="divider">
            <span>Don't have an account?</span>
        </div>

        <a href="passenger_register.php" class="register-link">
            <i class="fas fa-user-plus"></i> Create New Account
        </a>
    </div>

    <script>
        function createTestUser() {
            if (confirm('Create a test user with:\nMobile: 09123456789\nPassword: password123\nName: Test User')) {
                fetch('create_test_user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'create_test=1'
                    })
                    .then(response => response.text())
                    .then(data => {
                        alert(data);
                        location.reload();
                    })
                    .catch(error => {
                        alert('Error: ' + error);
                    });
            }
        }


        // Form submission handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const mobileNumber = document.getElementById('mobile_number').value;
            const password = document.getElementById('password').value;

            // Basic validation
            if (!mobileNumber || !password) {
                alert('Please fill in all fields');
                e.preventDefault();
                return;
            }

            if (!/^09[0-9]{9}$/.test(mobileNumber)) {
                alert('Please enter a valid mobile number (09XXXXXXXXX)');
                e.preventDefault();
                return;
            }
        });

        // Auto-focus mobile number field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('mobile_number').focus();
        });
    </script>
</body>

</html>