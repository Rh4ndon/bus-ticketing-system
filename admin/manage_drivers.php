<?php
require_once 'admin_auth.php';

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'info';

// Handle actions (activate/deactivate/delete driver)
if (isset($_GET['action']) && isset($_GET['driver_id'])) {
    $action = $_GET['action'];
    $driver_id = (int)$_GET['driver_id'];
    
    switch ($action) {
        case 'activate':
            $stmt = $conn->prepare("UPDATE drivers SET status = 'active' WHERE driver_id = ?");
            $stmt->execute([$driver_id]);
            $message = "Driver activated successfully!";
            $message_type = 'success';
            logAdminActivity('Activate Driver', "Driver ID: $driver_id");
            break;
            
        case 'deactivate':
            $stmt = $conn->prepare("UPDATE drivers SET status = 'inactive' WHERE driver_id = ?");
            $stmt->execute([$driver_id]);
            $message = "Driver deactivated successfully!";
            $message_type = 'success';
            logAdminActivity('Deactivate Driver', "Driver ID: $driver_id");
            break;
            
        case 'delete':
            // Check if driver has any bookings first
            $stmt = $conn->prepare("
                SELECT COUNT(*) as booking_count 
                FROM bookings b 
                JOIN buses bus ON b.bus_id = bus.bus_id 
                JOIN drivers d ON bus.bus_id = d.assigned_bus_id 
                WHERE d.driver_id = ?
            ");
            $stmt->execute([$driver_id]);
            $result = $stmt->fetch();
            
            if ($result['booking_count'] > 0) {
                $message = "Cannot delete driver: Driver has associated bookings. Deactivate instead.";
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("DELETE FROM drivers WHERE driver_id = ?");
                $stmt->execute([$driver_id]);
                $message = "Driver deleted successfully!";
                $message_type = 'success';
                logAdminActivity('Delete Driver', "Driver ID: $driver_id");
            }
            break;
    }
}

// Get search and filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Build query with filters
$query = "
    SELECT d.*, b.bus_number, b.plate_number 
    FROM drivers d 
    LEFT JOIN buses b ON d.assigned_bus_id = b.bus_id 
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (d.full_name LIKE ? OR d.mobile_number LIKE ? OR d.driver_code LIKE ? OR d.license_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $query .= " AND d.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY d.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$drivers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Drivers - <?php echo SITE_NAME; ?></title>
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
            max-width: 1200px;
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
        
        .add-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        
        .add-btn:hover {
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
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .filter-btn {
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            height: fit-content;
        }
        
        .clear-btn {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            height: fit-content;
        }
        
        .drivers-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
            text-decoration: none;
            color: white;
            font-weight: 500;
        }
        
        .btn-edit {
            background: #17a2b8;
        }
        
        .btn-activate {
            background: #28a745;
        }
        
        .btn-deactivate {
            background: #ffc107;
            color: #333;
        }
        
        .btn-delete {
            background: #dc3545;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #666;
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
            
            .filters-row {
                flex-direction: column;
            }
            
            .drivers-table {
                overflow-x: auto;
            }
            
            .actions {
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
                <li><a href="generate_booking_report.php">Reports</a></li>
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
                <h2>Manage Drivers</h2>
                <p>Add, edit, and manage driver accounts</p>
            </div>
            <a href="add_driver.php" class="add-btn">+ Add New Driver</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" 
                               placeholder="Name, phone, driver code, or license..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn">Filter</button>
                    <a href="manage_drivers.php" class="clear-btn">Clear</a>
                </div>
            </form>
        </div>
        
        <div class="drivers-table">
            <?php if (empty($drivers)): ?>
                <div class="no-data">
                    <h3>No drivers found</h3>
                    <p>No drivers match your search criteria or no drivers have been added yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Driver Code</th>
                            <th>Full Name</th>
                            <th>Mobile Number</th>
                            <th>License Number</th>
                            <th>Assigned Bus</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drivers as $driver): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($driver['driver_code']); ?></td>
                            <td><?php echo htmlspecialchars($driver['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($driver['mobile_number']); ?></td>
                            <td><?php echo htmlspecialchars($driver['license_number']); ?></td>
                            <td>
                                <?php if ($driver['bus_number']): ?>
                                    <?php echo htmlspecialchars($driver['bus_number']); ?> 
                                    (<?php echo htmlspecialchars($driver['plate_number']); ?>)
                                <?php else: ?>
                                    <em>Not assigned</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $driver['status']; ?>">
                                    <?php echo ucfirst($driver['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($driver['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="edit_driver.php?id=<?php echo $driver['driver_id']; ?>" 
                                       class="action-btn btn-edit">Edit</a>
                                    
                                    <?php if ($driver['status'] === 'active'): ?>
                                        <a href="?action=deactivate&driver_id=<?php echo $driver['driver_id']; ?>" 
                                           class="action-btn btn-deactivate"
                                           onclick="return confirm('Are you sure you want to deactivate this driver?')">Deactivate</a>
                                    <?php else: ?>
                                        <a href="?action=activate&driver_id=<?php echo $driver['driver_id']; ?>" 
                                           class="action-btn btn-activate"
                                           onclick="return confirm('Are you sure you want to activate this driver?')">Activate</a>
                                    <?php endif; ?>
                                    
                                    <a href="?action=delete&driver_id=<?php echo $driver['driver_id']; ?>" 
                                       class="action-btn btn-delete"
                                       onclick="return confirm('Are you sure you want to delete this driver? This action cannot be undone.')">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-submit form on status change
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
        
        // Confirm delete actions
        document.querySelectorAll('.btn-delete').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this driver? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>