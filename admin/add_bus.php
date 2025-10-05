<?php
require_once 'admin_auth.php';

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'info';
$form_data = [];

// Get available drivers for assignment (drivers without assigned buses)
$stmt = $conn->prepare("
    SELECT driver_id, driver_code, full_name 
    FROM drivers 
    WHERE status = 'active' AND (assigned_bus_id IS NULL OR assigned_bus_id = 0)
    ORDER BY full_name
");
$stmt->execute();
$available_drivers = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $form_data = [
        'bus_number' => sanitizeInput($_POST['bus_number'] ?? ''),
        'plate_number' => sanitizeInput($_POST['plate_number'] ?? ''),
        'capacity' => (int)($_POST['capacity'] ?? 40),
        'assigned_driver_id' => !empty($_POST['assigned_driver_id']) ? (int)$_POST['assigned_driver_id'] : null,
        'status' => sanitizeInput($_POST['status'] ?? 'available')
    ];
    
    // Validation
    $errors = [];
    
    if (empty($form_data['bus_number'])) {
        $errors[] = 'Bus number is required.';
    }
    
    if (empty($form_data['plate_number'])) {
        $errors[] = 'Plate number is required.';
    }
    
    if ($form_data['capacity'] < 10 || $form_data['capacity'] > 100) {
        $errors[] = 'Bus capacity must be between 10 and 100 seats.';
    }
    
    // Check for duplicate bus number
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT bus_number FROM buses WHERE bus_number = ?");
        $stmt->execute([$form_data['bus_number']]);
        if ($stmt->fetch()) {
            $errors[] = 'Bus number already exists.';
        }
        
        // Check for duplicate plate number
        $stmt = $conn->prepare("SELECT plate_number FROM buses WHERE plate_number = ?");
        $stmt->execute([$form_data['plate_number']]);
        if ($stmt->fetch()) {
            $errors[] = 'Plate number already exists.';
        }
        
        // Validate assigned driver if selected
        if ($form_data['assigned_driver_id']) {
            $stmt = $conn->prepare("
                SELECT driver_id FROM drivers 
                WHERE driver_id = ? AND status = 'active' AND (assigned_bus_id IS NULL OR assigned_bus_id = 0)
            ");
            $stmt->execute([$form_data['assigned_driver_id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'Selected driver is not available for assignment.';
            }
        }
    }
    
    // If no errors, create the bus
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Insert bus
            $stmt = $conn->prepare("
                INSERT INTO buses (bus_number, plate_number, capacity, status) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $form_data['bus_number'],
                $form_data['plate_number'],
                $form_data['capacity'],
                $form_data['status']
            ]);
            
            $bus_id = $conn->lastInsertId();
            
            // Assign driver if selected
            if ($form_data['assigned_driver_id']) {
                $stmt = $conn->prepare("UPDATE drivers SET assigned_bus_id = ? WHERE driver_id = ?");
                $stmt->execute([$bus_id, $form_data['assigned_driver_id']]);
            }
            
            $conn->commit();
            
            logAdminActivity('Create Bus', "Bus: {$form_data['bus_number']} - {$form_data['plate_number']}");
            
            $message = "Bus created successfully! Bus Number: <strong>{$form_data['bus_number']}</strong>";
            $message_type = 'success';
            
            // Clear form data on success
            $form_data = [];
            
            // Refresh available drivers list
            $stmt = $conn->prepare("
                SELECT driver_id, driver_code, full_name 
                FROM drivers 
                WHERE status = 'active' AND (assigned_bus_id IS NULL OR assigned_bus_id = 0)
                ORDER BY full_name
            ");
            $stmt->execute();
            $available_drivers = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Failed to create bus. Please try again.';
            error_log("Bus creation error: " . $e->getMessage());
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
    <title>Add New Bus - <?php echo SITE_NAME; ?></title>
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
        
        .capacity-info {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 5px;
            border-left: 4px solid #2196f3;
            margin-bottom: 1rem;
        }
        
        .capacity-info h4 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }
        
        .capacity-info ul {
            margin-left: 1rem;
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
                <li><a href="manage_drivers.php">Manage Drivers</a></li>
                <li><a href="manage_buses.php" class="active">Manage Buses</a></li>
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
                <h2>Add New Bus</h2>
                <p>Add a new bus to the fleet</p>
            </div>
            <a href="manage_buses.php" class="back-btn">← Back to Buses</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="" id="busForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="bus_number" class="required">Bus Number</label>
                        <input type="text" id="bus_number" name="bus_number" required 
                               value="<?php echo htmlspecialchars($form_data['bus_number'] ?? ''); ?>"
                               placeholder="e.g., BUS001, A-123">
                        <small>Unique identifier for the bus</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="plate_number" class="required">Plate Number</label>
                        <input type="text" id="plate_number" name="plate_number" required 
                               value="<?php echo htmlspecialchars($form_data['plate_number'] ?? ''); ?>"
                               placeholder="e.g., ABC-1234, XYZ-5678">
                        <small>Official vehicle plate number</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="capacity" class="required">Seating Capacity</label>
                        <input type="number" id="capacity" name="capacity" required 
                               min="10" max="100" step="1"
                               value="<?php echo $form_data['capacity'] ?? 40; ?>">
                        <small>Number of passenger seats (10-100)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Initial Status</label>
                        <select id="status" name="status">
                            <option value="available" <?php echo ($form_data['status'] ?? 'available') === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="maintenance" <?php echo ($form_data['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="inactive" <?php echo ($form_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <small>Initial status for the bus</small>
                    </div>
                </div>
                
                <div class="capacity-info">
                    <h4>📋 Bus Capacity Guidelines</h4>
                    <ul>
                        <li><strong>Mini Bus:</strong> 10-20 seats</li>
                        <li><strong>Standard Bus:</strong> 30-45 seats</li>
                        <li><strong>Large Bus:</strong> 50-60 seats</li>
                        <li><strong>Double Decker:</strong> 70-100 seats</li>
                    </ul>
                </div>
                
                <div class="form-group full-width">
                    <label for="assigned_driver_id">Assign Driver (Optional)</label>
                    <select id="assigned_driver_id" name="assigned_driver_id">
                        <option value="">Select a driver (optional)</option>
                        <?php foreach ($available_drivers as $driver): ?>
                            <option value="<?php echo $driver['driver_id']; ?>" 
                                    <?php echo ($form_data['assigned_driver_id'] ?? '') == $driver['driver_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($driver['full_name'] . ' (' . $driver['driver_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Assign a driver to this bus (can be changed later)</small>
                    
                    <?php if (empty($available_drivers)): ?>
                        <div style="background: #fff3cd; color: #856404; padding: 0.5rem; border-radius: 3px; margin-top: 0.5rem; font-size: 0.9rem;">
                            ⚠️ No available drivers. All active drivers are already assigned to buses.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <a href="manage_buses.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Bus</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('busForm').addEventListener('submit', function(e) {
            const busNumber = document.getElementById('bus_number').value.trim();
            const plateNumber = document.getElementById('plate_number').value.trim();
            const capacity = parseInt(document.getElementById('capacity').value);
            
            if (!busNumber) {
                e.preventDefault();
                alert('Bus number is required!');
                document.getElementById('bus_number').focus();
                return false;
            }
            
            if (!plateNumber) {
                e.preventDefault();
                alert('Plate number is required!');
                document.getElementById('plate_number').focus();
                return false;
            }
            
            if (capacity < 10 || capacity > 100) {
                e.preventDefault();
                alert('Bus capacity must be between 10 and 100 seats!');
                document.getElementById('capacity').focus();
                return false;
            }
        });
        
        // Format bus number and plate number to uppercase
        document.getElementById('bus_number').addEventListener('blur', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        document.getElementById('plate_number').addEventListener('blur', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        // Update capacity info based on selected capacity
        document.getElementById('capacity').addEventListener('input', function(e) {
            const capacity = parseInt(e.target.value);
            let busType = '';
            
            if (capacity >= 10 && capacity <= 20) {
                busType = 'Mini Bus';
            } else if (capacity >= 21 && capacity <= 45) {
                busType = 'Standard Bus';
            } else if (capacity >= 46 && capacity <= 60) {
                busType = 'Large Bus';
            } else if (capacity >= 61 && capacity <= 100) {
                busType = 'Double Decker';
            }
            
            if (busType) {
                const small = e.target.nextElementSibling;
                small.textContent = `Number of passenger seats (10-100) - ${busType} range`;
            }
        });
    </script>
</body>
</html>