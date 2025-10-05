<?php
require_once 'admin_auth.php';

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'info';
$bus = null;

// Get bus ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_buses.php');
    exit;
}

$bus_id = (int)$_GET['id'];

// Fetch bus data with related information
$stmt = $conn->prepare("
    SELECT b.*, 
           d.full_name as driver_name,
           d.driver_code,
           d.driver_id,
           r.route_name,
           r.origin,
           r.destination,
           (SELECT COUNT(*) FROM bookings bk WHERE bk.bus_id = b.bus_id AND bk.booking_status = 'confirmed' AND bk.travel_date >= CURDATE()) as active_bookings
    FROM buses b 
    LEFT JOIN drivers d ON b.bus_id = d.assigned_bus_id AND d.status = 'active'
    LEFT JOIN routes r ON b.assigned_route_id = r.route_id
    WHERE b.bus_id = ?
");
$stmt->execute([$bus_id]);
$bus = $stmt->fetch();

if (!$bus) {
    header('Location: manage_buses.php');
    exit;
}

// Get available routes for assignment
$stmt = $conn->prepare("
    SELECT route_id, route_name, origin, destination 
    FROM routes 
    WHERE status = 'active'
    ORDER BY route_name
");
$stmt->execute();
$available_routes = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bus_number = sanitizeInput($_POST['bus_number'] ?? '');
    $plate_number = sanitizeInput($_POST['plate_number'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    $assigned_route_id = !empty($_POST['assigned_route_id']) ? (int)$_POST['assigned_route_id'] : null;
    
    $errors = [];
    
    // Validation
    if (empty($bus_number)) {
        $errors[] = "Bus number is required.";
    }
    
    if (empty($plate_number)) {
        $errors[] = "Plate number is required.";
    }
    
    if ($capacity < 1 || $capacity > 100) {
        $errors[] = "Capacity must be between 1 and 100 seats.";
    }
    
    if (!in_array($status, ['available', 'assigned', 'on_trip', 'maintenance', 'inactive'])) {
        $errors[] = "Invalid status selected.";
    }
    
    // Check for duplicate bus number (exclude current bus)
    $stmt = $conn->prepare("SELECT bus_id FROM buses WHERE bus_number = ? AND bus_id != ?");
    $stmt->execute([$bus_number, $bus_id]);
    if ($stmt->fetch()) {
        $errors[] = "Bus number already exists.";
    }
    
    // Check for duplicate plate number (exclude current bus)
    $stmt = $conn->prepare("SELECT bus_id FROM buses WHERE plate_number = ? AND bus_id != ?");
    $stmt->execute([$plate_number, $bus_id]);
    if ($stmt->fetch()) {
        $errors[] = "Plate number already exists.";
    }
    
    // Status-specific validations
    if ($status === 'on_trip' && $bus['active_bookings'] == 0) {
        $errors[] = "Cannot set status to 'On Trip' without active bookings.";
    }
    
    if ($status === 'assigned' && !$assigned_route_id) {
        $errors[] = "Cannot set status to 'Assigned' without selecting a route.";
    }
    
    // Check if bus has active driver when trying to delete assignment
    if ($bus['driver_id'] && $status === 'maintenance') {
        // This is okay - driver can stay assigned to bus in maintenance
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Update bus information
            $stmt = $conn->prepare("
                UPDATE buses SET 
                bus_number = ?, plate_number = ?, capacity = ?, 
                status = ?, assigned_route_id = ?, updated_at = CURRENT_TIMESTAMP
                WHERE bus_id = ?
            ");
            $stmt->execute([
                $bus_number, $plate_number, $capacity, 
                $status, $assigned_route_id, $bus_id
            ]);
            
            // Handle route assignment changes
            if ($bus['assigned_route_id'] != $assigned_route_id) {
                if ($assigned_route_id) {
                    // Get route info for logging
                    $stmt = $conn->prepare("SELECT route_name FROM routes WHERE route_id = ?");
                    $stmt->execute([$assigned_route_id]);
                    $route_info = $stmt->fetch();
                    
                    logAdminActivity('Assign Bus to Route', "Bus #$bus_number ($plate_number) assigned to Route ID: $assigned_route_id ({$route_info['route_name']})");
                } else {
                    logAdminActivity('Unassign Bus from Route', "Bus #$bus_number ($plate_number) unassigned from route");
                }
            }
            
            $conn->commit();
            
            logAdminActivity('Update Bus', "Bus: #$bus_number ($plate_number) - ID: $bus_id");
            
            $message = "Bus updated successfully!";
            $message_type = 'success';
            
            // Refresh bus data
            $stmt = $conn->prepare("
                SELECT b.*, 
                       d.full_name as driver_name,
                       d.driver_code,
                       d.driver_id,
                       r.route_name,
                       r.origin,
                       r.destination,
                       (SELECT COUNT(*) FROM bookings bk WHERE bk.bus_id = b.bus_id AND bk.booking_status = 'confirmed' AND bk.travel_date >= CURDATE()) as active_bookings
                FROM buses b 
                LEFT JOIN drivers d ON b.bus_id = d.assigned_bus_id AND d.status = 'active'
                LEFT JOIN routes r ON b.assigned_route_id = r.route_id
                WHERE b.bus_id = ?
            ");
            $stmt->execute([$bus_id]);
            $bus = $stmt->fetch();
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error updating bus: " . $e->getMessage();
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
    <title>Edit Bus - <?php echo SITE_NAME; ?></title>
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
        
        .bus-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
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
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-available { background: #d4edda; color: #155724; }
        .status-assigned { background: #cce5ff; color: #004085; }
        .status-on_trip { background: #e2e3e5; color: #383d41; }
        .status-maintenance { background: #fff3cd; color: #856404; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .warning-box {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .warning-box strong {
            display: block;
            margin-bottom: 0.5rem;
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
            
            .info-grid {
                grid-template-columns: 1fr;
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
                <h2>Edit Bus</h2>
                <p>Update bus information and settings</p>
            </div>
            <a href="manage_buses.php" class="back-btn">← Back to Buses</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="bus-info">
            <h3 style="margin-bottom: 1rem;">Current Bus Information</h3>
            <div class="info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">Bus Number:</span>
                        <span><strong><?php echo htmlspecialchars($bus['bus_number']); ?></strong></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Plate Number:</span>
                        <span><?php echo htmlspecialchars($bus['plate_number']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Current Status:</span>
                        <span class="status-badge status-<?php echo $bus['status']; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $bus['status'])); ?>
                        </span>
                    </div>
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Capacity:</span>
                        <span><?php echo $bus['capacity']; ?> seats</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Assigned Driver:</span>
                        <span>
                            <?php if ($bus['driver_name']): ?>
                                <?php echo htmlspecialchars($bus['driver_name']); ?>
                                <br><small>(<?php echo htmlspecialchars($bus['driver_code']); ?>)</small>
                            <?php else: ?>
                                <em>No driver assigned</em>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Active Bookings:</span>
                        <span>
                            <?php if ($bus['active_bookings'] > 0): ?>
                                <strong><?php echo $bus['active_bookings']; ?> bookings</strong>
                            <?php else: ?>
                                No active bookings
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            <div style="margin-top: 1rem;">
                <div class="info-item">
                    <span class="info-label">Created:</span>
                    <span><?php echo date('M j, Y g:i A', strtotime($bus['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Last Updated:</span>
                    <span><?php echo date('M j, Y g:i A', strtotime($bus['updated_at'])); ?></span>
                </div>
            </div>
        </div>
        
        <?php if ($bus['active_bookings'] > 0): ?>
            <div class="warning-box">
                <strong>⚠️ Warning</strong>
                This bus has <?php echo $bus['active_bookings']; ?> active booking(s). Be careful when changing status or capacity.
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="bus_number">Bus Number *</label>
                        <input type="text" id="bus_number" name="bus_number" 
                               value="<?php echo htmlspecialchars($bus['bus_number']); ?>" required>
                        <div class="help-text">Unique identifier for the bus</div>
                    </div>
                    <div class="form-group">
                        <label for="plate_number">Plate Number *</label>
                        <input type="text" id="plate_number" name="plate_number" 
                               value="<?php echo htmlspecialchars($bus['plate_number']); ?>" required>
                        <div class="help-text">Vehicle registration plate number</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="capacity">Capacity (Seats) *</label>
                        <input type="number" id="capacity" name="capacity" 
                               value="<?php echo $bus['capacity']; ?>" 
                               min="1" max="100" required>
                        <div class="help-text">Number of passenger seats (1-100)</div>
                    </div>
                
                <div class="form-group">
                    <label for="assigned_route_id">Assigned Route</label>
                    <select id="assigned_route_id" name="assigned_route_id">
                        <option value="">No Route Assigned</option>
                        <?php foreach ($available_routes as $route): ?>
                            <option value="<?php echo $route['route_id']; ?>" 
                                    <?php echo $bus['assigned_route_id'] == $route['route_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($route['route_name']); ?> 
                                (<?php echo htmlspecialchars($route['origin']); ?> → <?php echo htmlspecialchars($route['destination']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($bus['route_name']): ?>
                        <div class="help-text">
                            Currently assigned to: <?php echo htmlspecialchars($bus['route_name']); ?>
                            (<?php echo htmlspecialchars($bus['origin']); ?> → <?php echo htmlspecialchars($bus['destination']); ?>)
                        </div>
                    <?php else: ?>
                        <div class="help-text">Select a route to assign this bus to specific trips</div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <a href="manage_buses.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Bus</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        
        // Capacity validation
        document.getElementById('capacity').addEventListener('input', function() {
            const capacity = parseInt(this.value);
            const activeBookings = <?php echo $bus['active_bookings']; ?>;
            
            if (capacity < activeBookings && activeBookings > 0) {
                alert(`Warning: Capacity cannot be less than active bookings (${activeBookings})`);
                this.value = Math.max(capacity, activeBookings);
            }
        });
    </script>
</body>
</html>