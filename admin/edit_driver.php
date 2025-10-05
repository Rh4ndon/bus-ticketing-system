<?php
require_once 'admin_auth.php';

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'info';
$driver = null;

// Get driver ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_drivers.php');
    exit;
}

$driver_id = (int)$_GET['id'];

// Fetch driver data
$stmt = $conn->prepare("
    SELECT d.*, b.bus_number, b.plate_number, b.bus_id as current_bus_id
    FROM drivers d 
    LEFT JOIN buses b ON d.assigned_bus_id = b.bus_id 
    WHERE d.driver_id = ?
");
$stmt->execute([$driver_id]);
$driver = $stmt->fetch();

if (!$driver) {
    header('Location: manage_drivers.php');
    exit;
}

// Get available buses for assignment
$stmt = $conn->prepare("
    SELECT bus_id, bus_number, plate_number 
    FROM buses 
    WHERE status IN ('available', 'assigned') 
    AND (assigned_route_id IS NULL OR bus_id = ?)
    ORDER BY bus_number
");
$stmt->execute([$driver['current_bus_id']]);
$available_buses = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $mobile_number = sanitizeInput($_POST['mobile_number'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $license_number = sanitizeInput($_POST['license_number'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? '');
    $assigned_bus_id = !empty($_POST['assigned_bus_id']) ? (int)$_POST['assigned_bus_id'] : null;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validation
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }
    
    if (empty($mobile_number)) {
        $errors[] = "Mobile number is required.";
    } elseif (!preg_match('/^09\d{9}$/', $mobile_number)) {
        $errors[] = "Mobile number must be in format 09XXXXXXXXX.";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if (empty($license_number)) {
        $errors[] = "License number is required.";
    }
    
    if (!in_array($status, ['pending', 'active', 'inactive'])) {
        $errors[] = "Invalid status selected.";
    }
    
    // Password validation (only if provided)
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        if ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
    }
    
    // Check for duplicate mobile number (exclude current driver)
    $stmt = $conn->prepare("SELECT driver_id FROM drivers WHERE mobile_number = ? AND driver_id != ?");
    $stmt->execute([$mobile_number, $driver_id]);
    if ($stmt->fetch()) {
        $errors[] = "Mobile number already exists.";
    }
    
    // Check for duplicate license number (exclude current driver)
    $stmt = $conn->prepare("SELECT driver_id FROM drivers WHERE license_number = ? AND driver_id != ?");
    $stmt->execute([$license_number, $driver_id]);
    if ($stmt->fetch()) {
        $errors[] = "License number already exists.";
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Update driver information
            if (!empty($new_password)) {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE drivers SET 
                    full_name = ?, mobile_number = ?, email = ?, 
                    license_number = ?, password = ?, status = ?, 
                    assigned_bus_id = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE driver_id = ?
                ");
                $stmt->execute([
                    $full_name, $mobile_number, $email, 
                    $license_number, $hashed_password, $status, 
                    $assigned_bus_id, $driver_id
                ]);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("
                    UPDATE drivers SET 
                    full_name = ?, mobile_number = ?, email = ?, 
                    license_number = ?, status = ?, assigned_bus_id = ?, 
                    updated_at = CURRENT_TIMESTAMP
                    WHERE driver_id = ?
                ");
                $stmt->execute([
                    $full_name, $mobile_number, $email, 
                    $license_number, $status, $assigned_bus_id, $driver_id
                ]);
            }
            
            // Handle bus assignment changes
            if ($driver['current_bus_id'] != $assigned_bus_id) {
                // Unassign old bus if exists
                if ($driver['current_bus_id']) {
                    $stmt = $conn->prepare("UPDATE buses SET status = 'available' WHERE bus_id = ?");
                    $stmt->execute([$driver['current_bus_id']]);
                }
                
                // Assign new bus if selected
                if ($assigned_bus_id) {
                    $stmt = $conn->prepare("UPDATE buses SET status = 'assigned' WHERE bus_id = ?");
                    $stmt->execute([$assigned_bus_id]);
                }
            }
            
            $conn->commit();
            
            logAdminActivity('Update Driver', "Driver: $full_name (ID: $driver_id)");
            
            $message = "Driver updated successfully!";
            $message_type = 'success';
            
            // Refresh driver data
            $stmt = $conn->prepare("
                SELECT d.*, b.bus_number, b.plate_number, b.bus_id as current_bus_id
                FROM drivers d 
                LEFT JOIN buses b ON d.assigned_bus_id = b.bus_id 
                WHERE d.driver_id = ?
            ");
            $stmt->execute([$driver_id]);
            $driver = $stmt->fetch();
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error updating driver: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
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
    <title>Edit Driver - <?php echo SITE_NAME; ?></title>
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
        
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group .help-text {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        .driver-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
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
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.3s;
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
        
        .password-section {
            border-top: 1px solid #dee2e6;
            padding-top: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .section-title {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.1rem;
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
            
            .form-row {
                flex-direction: column;
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
                <h2>Edit Driver</h2>
                <p>Update driver information and settings</p>
            </div>
            <a href="manage_drivers.php" class="back-btn">← Back to Drivers</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="driver-info">
            <h3 style="margin-bottom: 1rem;">Current Driver Information</h3>
            <div class="info-item">
                <span class="info-label">Driver Code:</span>
                <span><?php echo htmlspecialchars($driver['driver_code']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Current Status:</span>
                <span class="status-badge status-<?php echo $driver['status']; ?>">
                    <?php echo ucfirst($driver['status']); ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Created:</span>
                <span><?php echo date('M j, Y g:i A', strtotime($driver['created_at'])); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Last Updated:</span>
                <span><?php echo date('M j, Y g:i A', strtotime($driver['updated_at'])); ?></span>
            </div>
        </div>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($driver['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status *</label>
                        <select id="status" name="status" required>
                            <option value="pending" <?php echo $driver['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="active" <?php echo $driver['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $driver['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="mobile_number">Mobile Number *</label>
                        <input type="tel" id="mobile_number" name="mobile_number" 
                               value="<?php echo htmlspecialchars($driver['mobile_number']); ?>" 
                               pattern="09\d{9}" placeholder="09XXXXXXXXX" required>
                        <div class="help-text">Format: 09XXXXXXXXX</div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($driver['email'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="license_number">License Number *</label>
                        <input type="text" id="license_number" name="license_number" 
                               value="<?php echo htmlspecialchars($driver['license_number']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="assigned_bus_id">Assigned Bus</label>
                        <select id="assigned_bus_id" name="assigned_bus_id">
                            <option value="">No Bus Assigned</option>
                            <?php foreach ($available_buses as $bus): ?>
                                <option value="<?php echo $bus['bus_id']; ?>" 
                                        <?php echo $driver['current_bus_id'] == $bus['bus_id'] ? 'selected' : ''; ?>>
                                    Bus #<?php echo htmlspecialchars($bus['bus_number']); ?> 
                                    (<?php echo htmlspecialchars($bus['plate_number']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($driver['bus_number']): ?>
                            <div class="help-text">
                                Currently assigned to: Bus #<?php echo htmlspecialchars($driver['bus_number']); ?> 
                                (<?php echo htmlspecialchars($driver['plate_number']); ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="password-section">
                    <h3 class="section-title">Change Password (Optional)</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" 
                                   minlength="6">
                            <div class="help-text">Leave blank to keep current password. Minimum 6 characters.</div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="manage_drivers.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Driver</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirm = this.value;
            
            if (password && confirm && password !== confirm) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.getElementById('new_password').addEventListener('input', function() {
            const confirm = document.getElementById('confirm_password');
            if (confirm.value) {
                confirm.dispatchEvent(new Event('input'));
            }
        });
        
        // Mobile number validation
        document.getElementById('mobile_number').addEventListener('input', function() {
            const value = this.value;
            if (value && !value.match(/^09\d{9}$/)) {
                this.setCustomValidity('Mobile number must be in format 09XXXXXXXXX');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>