<?php
require_once "../admin/config.php";

$db = new Database();
$conn = $db->getConnection();

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST["full_name"]);
    $mobile_number = trim($_POST["mobile_number"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // Validate mobile number format
    if (!preg_match('/^09[0-9]{9}$/', $mobile_number)) {
        $error = 'Please enter a valid mobile number (09XXXXXXXXX).';
    }
    // Password match check
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Check for duplicate mobile/email
        $check = $conn->prepare("SELECT * FROM users WHERE mobile_number = :mobile OR email = :email LIMIT 1");
        $check->bindParam(":mobile", $mobile_number);
        $check->bindParam(":email", $email);
        $check->execute();

        if ($check->rowCount() > 0) {
            $error = "Mobile number or email already exists!";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (full_name, mobile_number, email, password) 
                                    VALUES (:full_name, :mobile, :email, :password)");
            $stmt->bindParam(":full_name", $full_name);
            $stmt->bindParam(":mobile", $mobile_number);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":password", $password_hash);

            if ($stmt->execute()) {
                $success = "Account created successfully! Redirecting to login...";
                echo "<script>
                        setTimeout(function() {
                            window.location.href = 'passenger_login.php?registered=1';
                        }, 2000);
                      </script>";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo SITE_NAME; ?> - Create Account</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #eef1f3ff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-card {
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
        
        .register-card::before {
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
        
        .register-btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            color: white;
            background: #2196f3;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .register-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 30px;
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
        
        .login-link {
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
        
        .login-link:hover {
            background: #2196f3;
            color: white;
            transform: translateY(-1px);
            text-decoration: none;
        }
        
        .password-strength {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
        }
        
        .password-strength.weak { color: #f44336; }
        .password-strength.medium { color: #ff9800; }
        .password-strength.strong { color: #4caf50; }
        
        @media (max-width: 480px) {
            .register-card {
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
    <div class="register-card">
        <div class="header">
            <i class="fas fa-user-plus"></i>
            <h1>Create Account</h1>
            <p>Join our platform today</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="registerForm">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" 
                       id="full_name" 
                       name="full_name" 
                       placeholder="Enter your full name" 
                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                       required>
                <i class="fas fa-user input-icon"></i>
            </div>
            
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
                <label for="email">Email Address</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       placeholder="Enter your email address" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required>
                <i class="fas fa-envelope input-icon"></i>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       placeholder="Create a strong password" 
                       required>
                <i class="fas fa-lock input-icon"></i>
                <div id="password-strength" class="password-strength"></div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       placeholder="Confirm your password" 
                       required>
                <i class="fas fa-lock input-icon"></i>
            </div>
            
            <button type="submit" class="register-btn" id="registerBtn">
                <i class="fas fa-user-plus"></i>
                <span>Create Account</span>
            </button>
        </form>
        
        <div class="divider">
            <span>Already have an account?</span>
        </div>
        
        <a href="passenger_login.php" class="login-link">
            <i class="fas fa-sign-in-alt"></i> Login Here
        </a>
    </div>

    <script>
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                strengthDiv.className = 'password-strength';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            // Length check
            if (password.length >= 8) strength++;
            else feedback.push('at least 8 characters');
            
            // Uppercase check
            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('uppercase letter');
            
            // Lowercase check
            if (/[a-z]/.test(password)) strength++;
            else feedback.push('lowercase letter');
            
            // Number check
            if (/[0-9]/.test(password)) strength++;
            else feedback.push('number');
            
            // Special character check
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            else feedback.push('special character');
            
            if (strength <= 2) {
                strengthDiv.className = 'password-strength weak';
                strengthDiv.textContent = 'Weak - Add: ' + feedback.slice(0, 2).join(', ');
            } else if (strength <= 3) {
                strengthDiv.className = 'password-strength medium';
                strengthDiv.textContent = 'Medium - Consider adding: ' + feedback.slice(0, 1).join(', ');
            } else {
                strengthDiv.className = 'password-strength strong';
                strengthDiv.textContent = 'Strong password ✓';
            }
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name').value.trim();
            const mobileNumber = document.getElementById('mobile_number').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Basic validation
            if (!fullName || !mobileNumber || !email || !password || !confirmPassword) {
                alert('Please fill in all fields');
                e.preventDefault();
                return;
            }
            
            // Mobile number validation
            if (!/^09[0-9]{9}$/.test(mobileNumber)) {
                alert('Please enter a valid mobile number (09XXXXXXXXX)');
                e.preventDefault();
                return;
            }
            
            // Email validation
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address');
                e.preventDefault();
                return;
            }
            
            // Password match validation
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                e.preventDefault();
                return;
            }
            
            // Password strength validation
            if (password.length < 6) {
                alert('Password must be at least 6 characters long');
                e.preventDefault();
                return;
            }
        });
        
        // Real-time password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#f44336';
            } else {
                this.style.borderColor = '#e0e0e0';
            }
        });
        
        // Mobile number formatting
        document.getElementById('mobile_number').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, ''); // Remove non-digits
            
            // Ensure it starts with 09
            if (value.length >= 2 && !value.startsWith('09')) {
                if (value.startsWith('9')) {
                    value = '0' + value;
                } else if (!value.startsWith('0')) {
                    value = '09' + value.substring(2);
                }
            }
            
            // Limit to 11 characters
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            
            this.value = value;
        });
        
        // Auto-focus first field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('full_name').focus();
        });
    </script>
</body>
</html>