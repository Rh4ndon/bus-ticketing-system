<?php
require_once "../admin/config.php";
session_start();

// ✅ Check if driver is already logged in
if (isset($_SESSION['driver_id'])) {
    header("Location: driver_dashboard.php");
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$error = '';
$success = '';

// ✅ Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile_number = sanitizeInput($_POST['mobile_number']);
    $password = $_POST['password'];
    
    if (empty($mobile_number) || empty($password)) {
        $error = 'Please enter both mobile number and password.';
    } else {
        // ✅ Check driver credentials
        $stmt = $conn->prepare("SELECT * FROM drivers WHERE mobile_number = ? AND status = 'active'");
        $stmt->execute([$mobile_number]);
        $driver = $stmt->fetch();
        
        if ($driver && verifyPassword($password, $driver['password'])) {
            // ✅ Login successful
            $_SESSION['driver_id'] = $driver['driver_id'];
            $_SESSION['driver_code'] = $driver['driver_code'];
            $_SESSION['driver_name'] = $driver['full_name'];
            $_SESSION['last_activity'] = time();
            
            // ✅ Generate session token for security
            $session_token = generateSessionToken();
            $_SESSION['session_token'] = $session_token;
        
            
            header("Location: driver_dashboard.php");
            exit();
        } else {
            $error = 'Invalid mobile number or password.';
        }
    }
}

// ✅ Handle logout message
if (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
}

// ✅ Handle session timeout message
if (isset($_GET['timeout'])) {
    $error = 'Your session has expired. Please login again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo SITE_NAME; ?> - Driver Login</title>
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
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 60px;
            color: #2196f3;
            margin-bottom: 10px;
        }
        
        .logo h1 {
            color: #333;
            font-size: 24px;
            font-weight: 600;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group .input-container {
            position: relative;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
        }
        
        .password-toggle {
            cursor: pointer;
            color: #667eea;
        }
        
        .password-toggle:hover {
            color: #5a67d8;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: #2196f3;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .alert-success {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .footer p {
            color: #666;
            font-size: 12px;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .system-info {
            background: rgba(0, 0, 0, 0.05);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
        }
        
        .system-info h4 {
            color: #333;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .system-info p {
            color: #666;
            font-size: 12px;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .logo i {
                font-size: 50px;
            }
            
            .logo h1 {
                font-size: 20px;
            }
        }
        
        /* Loading animation */
        .loading {
            display: none;
        }
        
        .btn.loading .loading {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        
        .btn.loading .login-text {
            display: none;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-bus"></i>
            <h1>Driver Portal</h1>
            <p>Bus Reservation System</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="mobile_number">Mobile Number</label>
                <div class="input-container">
                    <input 
                        type="tel" 
                        id="mobile_number" 
                        name="mobile_number" 
                        placeholder="Enter your mobile number"
                        value="<?php echo htmlspecialchars($_POST['mobile_number'] ?? ''); ?>"
                        required
                        autocomplete="username"
                    >
                    <i class="fas fa-phone input-icon"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-container">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <i class="fas fa-eye password-toggle input-icon" id="passwordToggle"></i>
                </div>
            </div>
            
            <button type="submit" class="btn" id="loginBtn">
                <span class="login-text">
                    <i class="fas fa-sign-in-alt"></i>
                    Login to Dashboard
                </span>
                <span class="loading">
                    <i class="fas fa-spinner"></i>
                    Signing in...
                </span>
            </button>
        </form>
        
        <div class="system-info">
            <h4>System Information</h4>
            <p>For technical support, contact your system administrator</p>
            <p>Current time: <span id="currentTime"><?php echo date('M d, Y - g:i A'); ?></span></p>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Bus Reservation System. All rights reserved.</p>
            <p>Version 2.0 | <a href="#" onclick="showSystemInfo()">System Info</a></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle functionality
            const passwordToggle = document.getElementById('passwordToggle');
            const passwordInput = document.getElementById('password');
            
            passwordToggle.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Form submission with loading state
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            
            loginForm.addEventListener('submit', function() {
                loginBtn.classList.add('loading');
                loginBtn.disabled = true;
                
                // Re-enable button after 5 seconds in case of network issues
                setTimeout(() => {
                    loginBtn.classList.remove('loading');
                    loginBtn.disabled = false;
                }, 5000);
            });
            
            // Update current time every minute
            function updateTime() {
                const now = new Date();
                const options = {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                };
                document.getElementById('currentTime').textContent = now.toLocaleDateString('en-US', options);
            }
            
            setInterval(updateTime, 60000);
            
            // Auto-focus on mobile number field
            document.getElementById('mobile_number').focus();
            
            // Phone number formatting (optional)
            const mobileInput = document.getElementById('mobile_number');
            mobileInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                if (value.length > 11) value = value.slice(0, 11); // Limit to 11 digits
                e.target.value = value;
            });
            
            // Check caps lock
            document.addEventListener('keydown', function(e) {
                if (e.getModifierState && e.getModifierState('CapsLock')) {
                    showCapsLockWarning();
                }
            });
            
            // Auto-clear alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
        
        function showCapsLockWarning() {
            const existing = document.querySelector('.caps-warning');
            if (existing) return;
            
            const warning = document.createElement('div');
            warning.className = 'alert alert-error caps-warning';
            warning.innerHTML = '<i class="fas fa-keyboard"></i> Caps Lock is ON';
            warning.style.position = 'fixed';
            warning.style.top = '20px';
            warning.style.right = '20px';
            warning.style.zIndex = '9999';
            warning.style.minWidth = '200px';
            
            document.body.appendChild(warning);
            
            setTimeout(() => {
                warning.style.opacity = '0';
                setTimeout(() => warning.remove(), 300);
            }, 3000);
        }
        
        function showSystemInfo() {
            alert(`System Information:\n\n` +
                  `• PHP Version: <?php echo phpversion(); ?>\n` +
                  `• Server Time: <?php echo date('Y-m-d H:i:s T'); ?>\n` +
                  `• Database: MySQL\n` +
                  `• Session Timeout: <?php echo SESSION_TIMEOUT/60; ?> minutes\n` +
                  `• Timezone: <?php echo date_default_timezone_get(); ?>`);
        }
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Service worker for offline capability (optional)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').catch(() => {
                console.log('Service worker registration failed');
            });
        }
    </script>
</body>
</html>