<?php
require_once 'admin_auth.php';

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'info';
$form_data = [];

// Get available buses for assignment
$stmt = $conn->prepare("
    SELECT b.bus_id, b.bus_number, b.plate_number 
    FROM buses b 
    LEFT JOIN drivers d ON b.bus_id = d.assigned_bus_id AND d.status = 'active'
    WHERE b.status = 'available' AND d.driver_id IS NULL
    ORDER BY b.bus_number
");
$stmt->execute();
$available_buses = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $form_data = [
        'full_name' => sanitizeInput($_POST['full_name'] ?? ''),
        'mobile_number' => sanitizeInput($_POST['mobile_number'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'license_number' => sanitizeInput($_POST['license_number'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'assigned_bus_id' => !empty($_POST['assigned_bus_id']) ? (int)$_POST['assigned_bus_id'] : null,
        'status' => sanitizeInput($_POST['status'] ?? 'active')
    ];
    
    // Validation
    $errors = [];
    
    if (empty($form_data['full_name'])) {
        $errors[] = 'Full name is required.';
    }
    
    if (empty($form_data['mobile_number'])) {
        $errors[] = 'Mobile number is required.';
    } elseif (!preg_match('/^[0-9]{11}$/', $form_data['mobile_number'])) {
        $errors[] = 'Mobile number must be 11 digits.';
    }
    
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($form_data['license_number'])) {
        $errors[] = 'License number is required.';
    }
    
    if (empty($form_data['password'])) {
        $errors[] = 'Password is required.';
    } elseif (strlen($form_data['password']) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    
    if ($form_data['password'] !== $form_data['confirm_password']) {
        $errors[] = 'Password and confirm password do not match.';
    }
    
    // Check for duplicate mobile number
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT mobile_number FROM drivers WHERE mobile_number = ?");
        $stmt->execute([$form_data['mobile_number']]);
        if ($stmt->fetch()) {
            $errors[] = 'Mobile number already exists.';
        }
        
        // Check for duplicate license number
        $stmt = $conn->prepare("SELECT license_number FROM drivers WHERE license_number = ?");
        $stmt->execute([$form_data['license_number']]);
        if ($stmt->fetch()) {
            $errors[] = 'License number already exists.';
        }
        
        // Check for duplicate email if provided
        if (!empty($form_data['email'])) {
            $stmt = $conn->prepare("SELECT email FROM drivers WHERE email = ?");
            $stmt->execute([$form_data['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'Email address already exists.';
            }
        }
    }
    
    // If no errors, create the driver
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Generate unique driver code
            do {
                $driver_code = generateDriverCode();
                $stmt = $conn->prepare("SELECT driver_code FROM drivers WHERE driver_code = ?");
                $stmt->execute([$driver_code]);
            } while ($stmt->fetch());
            
            // Insert driver
            $stmt = $conn->prepare("
                INSERT INTO drivers (
                    driver_code, full_name, mobile_number, email, license_number, 
                    password, assigned_bus_id, status, created_by_admin
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $hashed_password = hashPassword($form_data['password']);
            $email = !empty($form_data['email']) ? $form_data['email'] : null;
            
            $stmt->execute([
                $driver_code,
                $form_data['full_name'],
                $form_data['mobile_number'],
                $email,
                $form_data['license_number'],
                $hashed_password,
                $form_data['assigned_bus_id'],
                $form_data['status']
            ]);
            
            $driver_id = $conn->lastInsertId();
            
            // Update bus status if assigned
            if ($form_data['assigned_bus_id']) {
                $stmt = $conn->prepare("UPDATE buses SET status = 'available' WHERE bus_id = ?");
                $stmt->execute([$form_data['assigned_bus_id']]);
            }
            
            $conn->commit();
            
            logAdminActivity('Create Driver', "Driver: {$form_data['full_name']} (Code: $driver_code)");
            
            $message = "Driver created successfully! Driver Code: <strong>$driver_code</strong>";
            $message_type = 'success';
            
            // Clear form data on success
            $form_data = [];
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Failed to create driver. Please try again.';
            error_log("Driver creation error: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Driver - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            line-height: 1.6;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }
        
        .nav-brand h1 {
            font-size: 1.5rem;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
        }
        
        .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        
        .nav-menu a:hover, .nav-menu a.active {
            opacity: 0.8;
            text-decoration: underline;
        }
        
        .nav-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            font-weight: 500;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
        }
        
        .required::after {
            content: ' *';
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .password-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.3s;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .password-group {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <h1><?php echo SITE_NAME; ?></h1>
            </div>
            <ul class="nav-menu">
                <li><a href="admin_dashboard.php">Dashboard</a></li>
                <li><a href="manage_drivers.php" class="active">Manage Drivers</a></li>
                <li><a href="manage_buses.php">Manage Buses</a></li>
                <li><a href="manage_routes.php">Manage Routes</a></li>
            </ul>
            <div class="nav-user">
                <span>Welcome, <?php echo getAdminUsername(); ?></span>
                <a href="admin_logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2>Add New Driver</h2>
                <p>Create a new driver account</p>
            </div>
            <a href="manage_drivers.php" class="back-btn">← Back to Drivers</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="" id="driverForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="full_name" class="required">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required 
                               value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>">
                        <small>Enter driver's complete name</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="mobile_number" class="required">Mobile Number</label>
                        <input type="tel" id="mobile_number" name="mobile_number" required 
                               pattern="[0-9]{11}" maxlength="11"
                               value="<?php echo htmlspecialchars($form_data['mobile_number'] ?? ''); ?>">
                        <small>11-digit mobile number (e.g., 09171234567)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                        <small>Optional email address</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="license_number" class="required">License Number</label>
                        <input type="text" id="license_number" name="license_number" required 
                               value="<?php echo htmlspecialchars($form_data['license_number'] ?? ''); ?>">
                        <small>Driver's license number</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="assigned_bus_id">Assign Bus</label>
                        <select id="assigned_bus_id" name="assigned_bus_id">
                            <option value="">Select a bus (optional)</option>
                            <?php foreach ($available_buses as $bus): ?>
                                <option value="<?php echo $bus['bus_id']; ?>" 
                                        <?php echo ($form_data['assigned_bus_id'] ?? '') == $bus['bus_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bus['bus_number'] . ' - ' . $bus['plate_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Assign driver to a specific bus (can be changed later)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo ($form_data['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($form_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <small>Initial status for the driver account</small>
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <h3 style="margin-bottom: 1rem; color: #333;">Account Credentials</h3>
                    <div class="password-group">
                        <div class="form-group">
                            <label for="password" class="required">Password</label>
                            <input type="password" id="password" name="password" required minlength="6">
                            <small>Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="required">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                            <small>Re-enter the password</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="manage_drivers.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Driver</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('driverForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password and confirm password do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });
        
        // Format mobile number input
        document.getElementById('mobile_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            e.target.value = value;
        });
        
        // Validate mobile number format
        document.getElementById('mobile_number').addEventListener('blur', function(e) {
            const mobile = e.target.value;
            if (mobile && mobile.length !== 11) {
                alert('Mobile number must be exactly 11 digits!');
                e.target.focus();
            }
        });
        
        // Real-time password match validation
        document.getElementById('confirm_password').addEventListener('input', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = e.target.value;
            
            if (confirmPassword && password !== confirmPassword) {
                e.target.setCustomValidity('Passwords do not match');
            } else {
                e.target.setCustomValidity('');
            }
        });
    </script>
</body>
</html>