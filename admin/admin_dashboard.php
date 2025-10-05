<?php
require_once 'admin_auth.php';

$db = new Database();
$conn = $db->getConnection();

// Get dashboard statistics
$stats = [];

// Total customers
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$stmt->execute();
$stats['customers'] = $stmt->fetch()['count'];

// Total drivers
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM drivers WHERE status = 'active'");
$stmt->execute();
$stats['drivers'] = $stmt->fetch()['count'];

// Total buses
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM buses WHERE status != 'inactive'");
$stmt->execute();
$stats['buses'] = $stmt->fetch()['count'];

// Today's bookings
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$stats['today_bookings'] = $stmt->fetch()['count'];

// Recent activities (drivers, customers, bookings)
$stmt = $conn->prepare("
    SELECT 'driver' as type, full_name as name, created_at FROM drivers 
    WHERE status = 'active' ORDER BY created_at DESC LIMIT 5
");
$stmt->execute();
$recent_activities = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
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
        
        .nav-menu a:hover {
            opacity: 0.8;
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
        
        .dashboard-header {
            margin-bottom: 2rem;
        }
        
        .dashboard-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1rem;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .action-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .action-card h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .action-btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: transform 0.3s;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .recent-activity {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-type {
            background: #667eea;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }
        
        .activity-date {
            color: #666;
            font-size: 0.9rem;
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
        <div class="dashboard-header">
            <h2>Dashboard Overview</h2>
            <p>Welcome to the Bus Reservation System admin panel</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['customers']; ?></div>
                <div class="stat-label">Active Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['drivers']; ?></div>
                <div class="stat-label">Active Drivers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['buses']; ?></div>
                <div class="stat-label">Available Buses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['today_bookings']; ?></div>
                <div class="stat-label">Today's Bookings</div>
            </div>
        </div>
        
        <div class="actions-grid">
            <div class="action-card">
                <h3>Driver Management</h3>
                <p>Add, edit, or manage driver accounts and assignments.</p>
                <a href="manage_drivers.php" class="action-btn">Manage Drivers</a>
                <a href="add_driver.php" class="action-btn">Add New Driver</a>
            </div>
            
            <div class="action-card">
                <h3>Bus Management</h3>
                <p>Manage bus fleet, assignments, and maintenance schedules.</p>
                <a href="manage_buses.php" class="action-btn">Manage Buses</a>
                <a href="add_bus.php" class="action-btn">Add New Bus</a>
            </div>
            
            <div class="action-card">
                <h3>Route Management</h3>
                <p>Create and manage bus routes, schedules, and fares.</p>
                <a href="manage_routes.php" class="action-btn">Manage Routes</a>
                <a href="add_route.php" class="action-btn">Add New Route</a>
            </div>

        </div>
        
        <?php if (!empty($recent_activities)): ?>
        <div class="recent-activity">
            <h3>Recent Activity</h3>
            <?php foreach ($recent_activities as $activity): ?>
            <div class="activity-item">
                <div class="activity-info">
                    <span class="activity-type"><?php echo ucfirst($activity['type']); ?></span>
                    <?php echo htmlspecialchars($activity['name']); ?> was added
                </div>
                <div class="activity-date">
                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>