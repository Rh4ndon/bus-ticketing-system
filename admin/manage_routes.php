<?php
require_once 'admin_auth.php';

$db = new Database();
$conn = $db->getConnection();

// Debug function to log errors to console
function debugJS($msg) {
    echo "<script>console.log(" . json_encode($msg) . ");</script>";
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {

            case 'toggle_status':
                $route_id = (int)$_POST['route_id'];
                $current_status = $_POST['current_status'];
                $new_status = $current_status === 'active' ? 'inactive' : 'active';

                $stmt = $conn->prepare("UPDATE routes SET status = ? WHERE route_id = ?");
                $stmt->execute([$new_status, $route_id]);

                logAdminActivity('Update Route Status', "Route ID: {$route_id} → {$new_status}");

                echo json_encode(['success' => true, 'new_status' => $new_status]);
                exit;

case 'get_available_buses':
    $route_id = isset($_POST['route_id']) ? (int)$_POST['route_id'] : 0;
    
    $stmt = $conn->prepare("
        SELECT b.bus_id, b.bus_number, b.plate_number, b.capacity, b.status,
            CASE WHEN bra.route_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned_to_current_route,
            CASE WHEN EXISTS (
                SELECT 1 FROM bus_route_assignments bra2 
                WHERE bra2.bus_id = b.bus_id AND bra2.route_id != ?
            ) THEN 1 ELSE 0 END as is_assigned_to_other_route
        FROM buses b
        LEFT JOIN bus_route_assignments bra ON b.bus_id = bra.bus_id AND bra.route_id = ?
        WHERE b.status IN ('available', 'assigned')
        ORDER BY b.bus_number
    ");
    $stmt->execute([$route_id, $route_id]);
    $buses = $stmt->fetchAll();
    echo json_encode(['success' => true, 'buses' => $buses]);
    exit;

case 'assign_bus':
    $route_id = (int)$_POST['route_id'];
    $bus_id = (int)$_POST['bus_id'];

    try {
        // Check if bus exists
        $check_bus = $conn->prepare("SELECT status FROM buses WHERE bus_id = ?");
        $check_bus->execute([$bus_id]);
        $bus = $check_bus->fetch();
        
        if (!$bus) {
            echo json_encode(['success' => false, 'error' => 'Bus not found']);
            exit;
        }
        
        // Check if bus is already assigned to this specific route
        $check_assignment = $conn->prepare("SELECT * FROM bus_route_assignments WHERE bus_id = ? AND route_id = ?");
        $check_assignment->execute([$bus_id, $route_id]);
        
        if ($check_assignment->rowCount() > 0) {
            echo json_encode(['success' => false, 'error' => 'This bus is already assigned to this route']);
            exit;
        }

        // Assign bus to route
        $stmt = $conn->prepare("INSERT INTO bus_route_assignments (route_id, bus_id) VALUES (?, ?)");
        $stmt->execute([$route_id, $bus_id]);
        
        // Update bus status to assigned
        $update_bus = $conn->prepare("UPDATE buses SET status = 'assigned' WHERE bus_id = ?");
        $update_bus->execute([$bus_id]);

        logAdminActivity('Assign Bus', "Bus ID $bus_id assigned to Route ID $route_id");

        echo json_encode(['success' => true, 'message' => 'Bus assigned successfully']);
        exit;
    
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
   

                catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }

                // Assign bus to route
                $stmt = $conn->prepare("INSERT INTO bus_route_assignments (route_id, bus_id) VALUES (?, ?)");
                $stmt->execute([$route_id, $bus_id]);
                
                // Update bus status
                $update_bus = $conn->prepare("UPDATE buses SET status = 'assigned' WHERE bus_id = ?");
                $update_bus->execute([$bus_id]);

                logAdminActivity('Assign Bus', "Bus ID $bus_id assigned to Route ID $route_id");

                echo json_encode(['success' => true, 'message' => 'Bus assigned successfully']);
                exit;

            case 'unassign_bus':
                $assignment_id = (int)$_POST['assignment_id'];
                $bus_id = (int)$_POST['bus_id'];

                // Remove bus assignment
                $stmt = $conn->prepare("DELETE FROM bus_route_assignments WHERE id = ?");
                $stmt->execute([$assignment_id]);
                
                // Update bus status
                $update_bus = $conn->prepare("UPDATE buses SET status = 'available' WHERE bus_id = ?");
                $update_bus->execute([$bus_id]);

                logAdminActivity('Unassign Bus', "Bus ID $bus_id unassigned from route");

                echo json_encode(['success' => true, 'message' => 'Bus unassigned successfully']);
                exit;

            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Fetch routes with their assigned buses
$stmt = $conn->prepare("
    SELECT r.*, 
           CASE 
               WHEN EXISTS (
                   SELECT 1 FROM routes r2 
                   WHERE r2.origin = r.destination AND r2.destination = r.origin 
                   AND r2.route_id != r.route_id
               ) THEN 1 ELSE 0 
           END as has_reverse_route
    FROM routes r
    ORDER BY r.created_at DESC
");
$stmt->execute();
$routes = $stmt->fetchAll();

// Get assigned buses for each route
foreach ($routes as &$route) {
    $bus_stmt = $conn->prepare("
        SELECT b.bus_id, b.bus_number, b.plate_number, b.capacity, 
               bra.id as assignment_id
        FROM bus_route_assignments bra
        JOIN buses b ON bra.bus_id = b.bus_id
        WHERE bra.route_id = ?
    ");
    $bus_stmt->execute([$route['route_id']]);
    $route['assigned_buses'] = $bus_stmt->fetchAll();
}
unset($route); // Break the reference
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Routes - <?php echo SITE_NAME; ?></title>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.2.0/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />

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
        
        .routes-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            display: grid;
            grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr 1.5fr 1fr;
            gap: 1rem;
            align-items: center;
        }
        
        .route-row {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: grid;
            grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr 1.5fr 1fr;
            gap: 1rem;
            align-items: center;
            transition: background 0.3s;
        }
        
        .route-row:hover {
            background: #f8f9fa;
        }
        
        .route-row:last-child {
            border-bottom: none;
        }
        
        .route-info h4 {
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .route-path {
            color: #666;
            font-size: 0.9rem;
        }
        
        .route-arrow {
            color: #667eea;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .bus-assignment {
            font-size: 0.9rem;
        }
        
        .bus-assigned {
            color: #155724;
            font-weight: 600;
        }
        
        .bus-unassigned {
            color: #721c24;
            font-style: italic;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn-toggle {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-assign {
            background: #28a745;
            color: white;
        }
        
        .btn-unassign {
            background: #dc3545;
            color: white;
        }
        
        .btn-edit {
            background: #17a2b8;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .modal-title {
            color: #333;
            margin: 0;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-btn:hover {
            color: #333;
        }
        
        .bus-list {
            margin-bottom: 1.5rem;
        }
        
        .bus-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .bus-item:hover {
            border-color: #667eea;
        }
        
        .bus-item.selected {
            border-color: #28a745;
            background: #f8fff9;
        }
        
        .bus-info h4 {
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .bus-details {
            color: #666;
            font-size: 0.9rem;
        }
        
        .bus-status {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-assigned {
            background: #fff3cd;
            color: #856404;
        }
        
        .assignment-options {
            margin: 1.5rem 0;
        }
        
        .assignment-option {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .assignment-option input[type="radio"] {
            margin-right: 0.5rem;
        }
        
        .assignment-option label {
            color: #333;
            font-weight: 500;
        }
        
        .assignment-description {
            color: #666;
            font-size: 0.9rem;
            margin-left: 1.5rem;
            margin-top: 0.25rem;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }
        
        .btn-modal {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .table-header, .route-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .modal-content {
                margin: 1rem;
                padding: 1rem;
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
                <li><a href="manage_routes.php" class="active">Manage Routes</a></li>
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
                <h2>Manage Routes</h2>
                <p>View and manage all bus routes</p>
            </div>
            <a href="add_route.php" class="add-btn">+ Add New Route</a>
        </div>
        
        <div id="alertContainer"></div>
        
        <div class="routes-table">
            <div class="table-header">
                <div>Route Information</div>
                <div>Route Path</div>
                <div>Fare</div>
                <div>Distance</div>
                <div>Status</div>
                <div>Assigned Buses</div>
                <div>Actions</div>
            </div>
            
            <?php if (empty($routes)): ?>
                <div class="route-row" style="grid-column: 1 / -1; text-align: center; color: #666;">
                    <p>No routes found. <a href="add_route.php">Create your first route</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($routes as $route): ?>
                    <div class="route-row" data-route-id="<?php echo $route['route_id']; ?>">
                        <div class="route-info">
                            <h4><?php echo htmlspecialchars($route['route_name']); ?></h4>
                            <small>Created: <?php echo date('M j, Y', strtotime($route['created_at'])); ?></small>
                        </div>
                        
                        <div class="route-path">
                            <?php echo htmlspecialchars($route['origin']); ?>
                            <span class="route-arrow">→</span>
                            <?php echo htmlspecialchars($route['destination']); ?>
                        </div>
                        
                        <div>₱<?php echo number_format($route['fare'], 2); ?></div>
                        
                        <div>
                            <?php echo $route['distance_km'] ? $route['distance_km'] . ' km' : 'N/A'; ?>
                        </div>
                        
                        <div>
                            <span class="status-badge status-<?php echo $route['status']; ?>">
                                <?php echo ucfirst($route['status']); ?>
                            </span>
                        </div>
                        
                        <div class="bus-assignment">
                            <?php if (!empty($route['assigned_buses'])): ?>
                                <div class="assigned-buses">
                                    <?php foreach ($route['assigned_buses'] as $bus): ?>
                                        <div class="bus-tag">
                                            Bus #<?php echo $bus['bus_number']; ?>
                                            <span class="remove-bus" onclick="unassignBus(<?php echo $bus['assignment_id']; ?>, <?php echo $bus['bus_id']; ?>)">&times;</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="bus-unassigned">No buses assigned</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn btn-toggle" onclick="toggleRouteStatus(<?php echo $route['route_id']; ?>, '<?php echo $route['status']; ?>')">
                                <?php echo $route['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                            </button>
                            
                            <button class="btn btn-assign" onclick="showBusAssignmentModal(<?php echo $route['route_id']; ?>, '<?php echo htmlspecialchars($route['route_name']); ?>', <?php echo $route['has_reverse_route']; ?>)">
                                Assign Bus
                            </button>
                            
                            <a href="edit_route.php?id=<?php echo $route['route_id']; ?>" class="btn btn-edit">Edit</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bus Assignment Modal -->
    <div id="busAssignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Bus to Route</h3>
                <button class="close-btn" onclick="closeBusAssignmentModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <p><strong>Route:</strong> <span id="modalRouteName"></span></p>
                
                <h4>Available Buses:</h4>
                <div id="busListContainer" class="bus-list">
                    <div style="text-align: center; color: #666;">Loading buses...</div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn-modal btn-secondary" onclick="closeBusAssignmentModal()">Cancel</button>
                <button class="btn-modal btn-primary" onclick="assignBus()" id="assignBtn">Assign Bus</button>
            </div>
        </div>
    </div>

    <!-- Leaflet and Routing Machine JS -->
    <script src="https://unpkg.com/leaflet@1.2.0/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>

<script>
let currentRouteId = null;
let selectedBusId = null;

function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert ${type}`;
    alert.innerHTML = message;
    alertContainer.appendChild(alert);
    setTimeout(() => alert.remove(), 5000);
}

function toggleRouteStatus(routeId, currentStatus) {
    if (!confirm(`Are you sure you want to ${currentStatus === 'active' ? 'deactivate' : 'activate'} this route?`)) return;

    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('route_id', routeId);
    formData.append('current_status', currentStatus);

    fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            console.log('toggleRouteStatus response:', data);
            if (data.success) {
                const row = document.querySelector(`[data-route-id="${routeId}"]`);
                const statusBadge = row.querySelector('.status-badge');
                const toggleBtn = row.querySelector('.btn-toggle');

                statusBadge.className = `status-badge status-${data.new_status}`;
                statusBadge.textContent = data.new_status.charAt(0).toUpperCase() + data.new_status.slice(1);

                toggleBtn.textContent = data.new_status === 'active' ? 'Deactivate' : 'Activate';
                toggleBtn.setAttribute('onclick', `toggleRouteStatus(${routeId}, '${data.new_status}')`);

                showAlert(`Route status updated to ${data.new_status}`, 'success');
            } else {
                showAlert(data.error || 'Failed to update route status', 'error');
            }
        })
        .catch(err => {
            console.error('Error in toggleRouteStatus:', err);
            showAlert('An error occurred while updating route status', 'error');
        });
}

function showBusAssignmentModal(routeId, routeName, hasReverseRoute) {
    currentRouteId = routeId;
    selectedBusId = null;

    document.getElementById('modalRouteName').textContent = routeName;
    loadAvailableBuses();
    document.getElementById('busAssignmentModal').classList.add('show');
}

function closeBusAssignmentModal() {
    document.getElementById('busAssignmentModal').classList.remove('show');
    currentRouteId = null;
    selectedBusId = null;
}

function loadAvailableBuses() {
    const container = document.getElementById('busListContainer');
    container.innerHTML = '<div style="text-align: center; color: #666;">Loading buses...</div>';

    const formData = new FormData();
    formData.append('action', 'get_available_buses');
    formData.append('route_id', currentRouteId); // Add this line

    fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            console.log('get_available_buses response:', data);
            if (data.success) displayBuses(data.buses);
            else container.innerHTML = '<div style="text-align: center; color: #dc3545;">Failed to load buses</div>';
        })
        .catch(err => {
            console.error('Error in loadAvailableBuses:', err);
            container.innerHTML = '<div style="text-align: center; color: #dc3545;">Error loading buses</div>';
        });
}

function displayBuses(buses) {
    const container = document.getElementById('busListContainer');
    if (buses.length === 0) {
        container.innerHTML = '<div style="text-align: center; color: #666;">No available buses found</div>';
        return;
    }

    container.innerHTML = '';
    buses.forEach(bus => {
        const busItem = document.createElement('div');
        busItem.className = 'bus-item';
        busItem.onclick = () => selectBus(bus.bus_id, busItem);

        // Check if bus is already assigned to current route
        const isAssignedToCurrent = bus.is_assigned_to_current_route === 1;
        
        busItem.innerHTML = `
            <div class="bus-info">
                <h4>Bus #${bus.bus_number}</h4>
                <div class="bus-details">Plate: ${bus.plate_number} | Capacity: ${bus.capacity} seats</div>
            </div>
            <div class="bus-status ${isAssignedToCurrent ? 'status-assigned' : 'status-available'}">
                ${isAssignedToCurrent ? 'Already Assigned' : 'Available'}
            </div>
        `;

        // Disable selection if already assigned to this route
        if (isAssignedToCurrent) {
            busItem.style.opacity = '0.6';
            busItem.style.cursor = 'not-allowed';
            busItem.onclick = null;
        }

        container.appendChild(busItem);
    });
}

function selectBus(busId, element) {
    document.querySelectorAll('.bus-item.selected').forEach(item => item.classList.remove('selected'));
    element.classList.add('selected');
    selectedBusId = busId;
    document.getElementById('assignBtn').disabled = false;
}

function assignBus() {
    if (!selectedBusId) {
        showAlert('Please select a bus first', 'error');
        return;
    }

    if (!confirm('Are you sure you want to assign this bus to the route?')) return;

    const assignBtn = document.getElementById('assignBtn');
    assignBtn.disabled = true;
    assignBtn.textContent = 'Assigning...';

    const formData = new FormData();
    formData.append('action', 'assign_bus');
    formData.append('route_id', currentRouteId);
    formData.append('bus_id', selectedBusId);
    formData.append('assignment_type', 'single'); // Keeping this for compatibility

    fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            console.log('assignBus response:', data);
            if (data.success) {
                showAlert(data.message, 'success');
                closeBusAssignmentModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.error || 'Failed to assign bus', 'error');
            }
        })
        .catch(err => {
            console.error('Error in assignBus:', err);
            showAlert('An error occurred while assigning bus', 'error');
        })
        .finally(() => {
            assignBtn.disabled = false;
            assignBtn.textContent = 'Assign Bus';
        });
}

function unassignBus(assignmentId, busId) {
    if (!confirm('Are you sure you want to unassign this bus from the route?')) return;

    const formData = new FormData();
    formData.append('action', 'unassign_bus');
    formData.append('assignment_id', assignmentId);
    formData.append('bus_id', busId);

    fetch('', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            console.log('unassignBus response:', data);
            if (data.success) {
                showAlert(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.error || 'Failed to unassign bus', 'error');
            }
        })
        .catch(err => {
            console.error('Error in unassignBus:', err);
            showAlert('An error occurred while unassigning bus', 'error');
        });
}

// Modal close logic
document.getElementById('busAssignmentModal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeBusAssignmentModal();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('busAssignmentModal').classList.contains('show')) {
        closeBusAssignmentModal();
    }
});
</script>

</body>
</html>