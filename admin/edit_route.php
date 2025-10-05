<?php
require_once 'admin_auth.php';

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'info';
$route_data = [];
$route_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Pre-defined locations for San Mateo - Cauayan route
$locations = [
    'San Mateo, Isabela' => ['lat' => 16.9167, 'lng' => 121.5833],
    'Cauayan City, Isabela' => ['lat' => 16.9272, 'lng' => 121.7708]
];

// Fetch existing route data
if ($route_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM routes WHERE route_id = ?");
    $stmt->execute([$route_id]);
    $route_data = $stmt->fetch();
    
    if (!$route_data) {
        header('Location: manage_routes.php');
        exit;
    }
} else {
    header('Location: manage_routes.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input data
    $form_data = [
        'route_name' => sanitizeInput($_POST['route_name'] ?? ''),
        'origin' => sanitizeInput($_POST['origin'] ?? ''),
        'destination' => sanitizeInput($_POST['destination'] ?? ''),
        'distance_km' => !empty($_POST['distance_km']) ? (float)$_POST['distance_km'] : null,
        'estimated_duration_minutes' => !empty($_POST['estimated_duration_minutes']) ? (int)$_POST['estimated_duration_minutes'] : null,
        'fare' => (float)($_POST['fare'] ?? 0),
        'status' => sanitizeInput($_POST['status'] ?? 'active')
    ];
    
    // Validation
    $errors = [];
    
    if (empty($form_data['route_name'])) {
        $errors[] = 'Route name is required.';
    }
    
    if (empty($form_data['origin'])) {
        $errors[] = 'Origin is required.';
    }
    
    if (empty($form_data['destination'])) {
        $errors[] = 'Destination is required.';
    }
    
    if ($form_data['origin'] === $form_data['destination']) {
        $errors[] = 'Origin and destination cannot be the same.';
    }
    
    if ($form_data['fare'] <= 0) {
        $errors[] = 'Fare must be greater than 0.';
    }
    
    if ($form_data['distance_km'] !== null && $form_data['distance_km'] <= 0) {
        $errors[] = 'Distance must be greater than 0.';
    }
    
    if ($form_data['estimated_duration_minutes'] !== null && $form_data['estimated_duration_minutes'] <= 0) {
        $errors[] = 'Estimated duration must be greater than 0.';
    }
    
    // Check for duplicate route name (excluding current route)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT route_name FROM routes WHERE route_name = ? AND route_id != ?");
        $stmt->execute([$form_data['route_name'], $route_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Route name already exists.';
        }
        
        // Check for duplicate route (same origin and destination, excluding current route)
        $stmt = $conn->prepare("SELECT route_id FROM routes WHERE origin = ? AND destination = ? AND route_id != ?");
        $stmt->execute([$form_data['origin'], $form_data['destination'], $route_id]);
        if ($stmt->fetch()) {
            $errors[] = 'A route with the same origin and destination already exists.';
        }
    }
    
    // If no errors, update the route
    if (empty($errors)) {
        try {
            // Auto-calculate distance and duration if not provided
            if ($form_data['distance_km'] === null || $form_data['estimated_duration_minutes'] === null) {
                // Set default values based on San Mateo - Cauayan distance
                if ($form_data['distance_km'] === null) {
                    $form_data['distance_km'] = 25.5; // Approximate distance between cities
                }
                if ($form_data['estimated_duration_minutes'] === null) {
                    // Estimate based on fare (higher fare = express route = shorter time)
                    $form_data['estimated_duration_minutes'] = ($form_data['fare'] >= 70) ? 45 : 60;
                }
            }
            
            // Update route
            $stmt = $conn->prepare("
                UPDATE routes SET 
                    route_name = ?, origin = ?, destination = ?, distance_km = ?, 
                    estimated_duration_minutes = ?, fare = ?, status = ?
                WHERE route_id = ?
            ");
            
            $stmt->execute([
                $form_data['route_name'],
                $form_data['origin'],
                $form_data['destination'],
                $form_data['distance_km'],
                $form_data['estimated_duration_minutes'],
                $form_data['fare'],
                $form_data['status'],
                $route_id
            ]);
            
            // Log admin activity
            logAdminActivity('Update Route', "Route ID: {$route_id} - {$form_data['route_name']} ({$form_data['origin']} → {$form_data['destination']})");
            
            $message = "Route updated successfully! Route: <strong>{$form_data['route_name']}</strong>";
            $message_type = 'success';
            
            // Update route_data with new values for display
            $route_data = array_merge($route_data, $form_data);
            
        } catch (Exception $e) {
            $errors[] = 'Failed to update route. Please try again.';
            error_log("Route update error: " . $e->getMessage());
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
    <title>Edit Route - <?php echo SITE_NAME; ?></title>
    
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
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }
        
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .route-info-card {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .route-info-card h4 {
            color: #1565c0;
            margin-bottom: 0.5rem;
        }
        
        .route-info-card p {
            color: #424242;
            margin: 0;
        }
        
        .map-preview {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .map-header {
            padding: 1rem;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .map-header h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        #routePreviewMap {
            height: 400px;
            width: 100%;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        
        .route-preview {
            background: #e9ecef;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
        }
        
        .route-preview h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .route-path {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }
        
        .route-arrow {
            color: #667eea;
            font-weight: bold;
        }
        
        .route-details {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-value {
            font-weight: bold;
            font-size: 1.1rem;
            color: #333;
        }
        
        .detail-label {
            color: #666;
            font-size: 0.9rem;
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .map-preview {
                order: -1;
            }
            
            #routePreviewMap {
                height: 300px;
            }
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
                <li><a href="manage_buses.php">Manage Buses</a></li>
                <li><a href="manage_routes.php" class="active">Manage Routes</a></li>
                <li><a href="manage_bookings.php">Bookings</a></li>
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
                <h2>Edit Route</h2>
                <p>Update route information and settings</p>
            </div>
            <a href="manage_routes.php" class="back-btn">← Back to Routes</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="content-grid">
            <div class="form-container">
                <div class="route-info-card">
                    <h4>Editing Route</h4>
                    <p><strong>Route ID:</strong> <?php echo $route_data['route_id']; ?></p>
                    <p><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($route_data['created_at'])); ?></p>
                </div>
                
                <form method="POST" action="" id="routeForm">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="route_name" class="required">Route Name</label>
                            <input type="text" id="route_name" name="route_name" required 
                                   placeholder="e.g., San Mateo to Cauayan Express"
                                   value="<?php echo htmlspecialchars($route_data['route_name']); ?>">
                            <small>Give this route a descriptive name</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="origin" class="required">Origin</label>
                            <select id="origin" name="origin" required>
                                <option value="">Select origin...</option>
                                <?php foreach ($locations as $location => $coords): ?>
                                    <option value="<?php echo $location; ?>" 
                                            <?php echo $route_data['origin'] === $location ? 'selected' : ''; ?>>
                                        <?php echo $location; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Starting point of the route</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="destination" class="required">Destination</label>
                            <select id="destination" name="destination" required>
                                <option value="">Select destination...</option>
                                <?php foreach ($locations as $location => $coords): ?>
                                    <option value="<?php echo $location; ?>" 
                                            <?php echo $route_data['destination'] === $location ? 'selected' : ''; ?>>
                                        <?php echo $location; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>End point of the route</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="fare" class="required">Fare (₱)</label>
                            <input type="number" id="fare" name="fare" required min="0" step="0.01"
                                   placeholder="75.00"
                                   value="<?php echo $route_data['fare']; ?>">
                            <small>Ticket price for this route</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="distance_km">Distance (km)</label>
                            <input type="number" id="distance_km" name="distance_km" min="0" step="0.1"
                                   placeholder="25.5"
                                   value="<?php echo $route_data['distance_km']; ?>">
                            <small>Leave empty for auto-calculation</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="estimated_duration_minutes">Duration (minutes)</label>
                            <input type="number" id="estimated_duration_minutes" name="estimated_duration_minutes" 
                                   min="0" placeholder="45"
                                   value="<?php echo $route_data['estimated_duration_minutes']; ?>">
                            <small>Estimated travel time</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="active" <?php echo $route_data['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $route_data['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <small>Current status of the route</small>
                        </div>
                    </div>
                    
                    <div class="route-preview" id="routePreview">
                        <h4>Route Preview</h4>
                        <div class="route-path">
                            <span id="previewOrigin"><?php echo htmlspecialchars($route_data['origin']); ?></span>
                            <span class="route-arrow">→</span>
                            <span id="previewDestination"><?php echo htmlspecialchars($route_data['destination']); ?></span>
                        </div>
                        <div class="route-details">
                            <div class="detail-item">
                                <div class="detail-value" id="previewFare">₱<?php echo number_format($route_data['fare'], 2); ?></div>
                                <div class="detail-label">Fare</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-value" id="previewDistance"><?php echo $route_data['distance_km'] ? $route_data['distance_km'] . ' km' : 'Auto-calc'; ?></div>
                                <div class="detail-label">Distance</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-value" id="previewDuration"><?php echo $route_data['estimated_duration_minutes'] ? $route_data['estimated_duration_minutes'] . ' min' : 'Auto-calc'; ?></div>
                                <div class="detail-label">Duration</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="manage_routes.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Route</button>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete Route</button>
                    </div>
                </form>
            </div>
            
            <div class="map-preview">
                <div class="map-header">
                    <h3>Route Map Preview</h3>
                    <p><?php echo htmlspecialchars($route_data['route_name']); ?></p>
                </div>
                <div id="routePreviewMap"></div>
            </div>
        </div>
    </div>

    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.2.0/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    
    <script>
        let previewMap;
        let routingControl = null;
        
        // Coordinates for locations
        const coordinates = {
            'San Mateo, Isabela': [16.9167, 121.5833],
            'Cauayan City, Isabela': [16.9272, 121.7708]
        };
        
        // Initialize preview map
        function initPreviewMap() {
            // Center map between the two cities
            const centerLat = (16.9167 + 16.9272) / 2;
            const centerLng = (121.5833 + 121.7708) / 2;
            
            previewMap = L.map('routePreviewMap').setView([centerLat, centerLng], 11);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(previewMap);
            
            // Show initial route
            updateMapRoute();
        }
        
        // Update route preview
        function updateRoutePreview() {
            const origin = document.getElementById('origin').value;
            const destination = document.getElementById('destination').value;
            const fare = document.getElementById('fare').value;
            const distance = document.getElementById('distance_km').value;
            const duration = document.getElementById('estimated_duration_minutes').value;
            
            if (origin && destination && origin !== destination) {
                // Update preview text
                document.getElementById('previewOrigin').textContent = origin;
                document.getElementById('previewDestination').textContent = destination;
                document.getElementById('previewFare').textContent = fare ? `₱${parseFloat(fare).toFixed(2)}` : '₱0.00';
                document.getElementById('previewDistance').textContent = distance ? `${distance} km` : 'Auto-calc';
                document.getElementById('previewDuration').textContent = duration ? `${duration} min` : 'Auto-calc';
                
                // Update map route
                updateMapRoute(origin, destination);
            } else {
                clearMapRoute();
            }
        }
        
        // Update map route visualization using Leaflet Routing Machine
        function updateMapRoute(origin, destination) {
            // Use current form values if parameters not provided
            if (!origin) origin = document.getElementById('origin').value;
            if (!destination) destination = document.getElementById('destination').value;
            
            // Remove existing routing control if any
            if (routingControl) {
                previewMap.removeControl(routingControl);
                routingControl = null;
            }
            
            if (coordinates[origin] && coordinates[destination]) {
                // Create routing control
                routingControl = L.Routing.control({
                    waypoints: [
                        L.latLng(coordinates[origin][0], coordinates[origin][1]),
                        L.latLng(coordinates[destination][0], coordinates[destination][1])
                    ],
                    routeWhileDragging: false,
                    showAlternatives: false,
                    lineOptions: {
                        styles: [{color: '#28a745', opacity: 0.8, weight: 4}]
                    },
                    createMarker: function(i, waypoint, n) {
                        // Custom markers for origin and destination
                        const iconOptions = {
                            iconUrl: i === 0 ? 
                                'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png' : 
                                'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41],
                            popupAnchor: [1, -34],
                            shadowSize: [41, 41]
                        };
                        
                        const customIcon = L.icon(iconOptions);
                        
                        return L.marker(waypoint.latLng, {
                            icon: customIcon,
                            draggable: false
                        }).bindPopup(i === 0 ? `<b>${origin}</b><br>Origin` : `<b>${destination}</b><br>Destination`);
                    },
                    // Hide the routing instructions panel
                    addWaypoints: false,
                    routeWhileDragging: false,
                    show: false
                }).addTo(previewMap);
                
                // Fit map to show the route
                routingControl.on('routesfound', function(e) {
                    const routes = e.routes;
                    const bounds = new L.LatLngBounds();
                    
                    routes[0].coordinates.forEach(function(coord) {
                        bounds.extend(coord);
                    });
                    
                    previewMap.fitBounds(bounds, { padding: [50, 50] });
                });
            }
        }
        
        // Clear map route
        function clearMapRoute() {
            if (routingControl) {
                previewMap.removeControl(routingControl);
                routingControl = null;
            }
        }
        
        // Auto-calculate distance and duration
        function autoCalculate() {
            const origin = document.getElementById('origin').value;
            const destination = document.getElementById('destination').value;
            const fare = parseFloat(document.getElementById('fare').value) || 0;
            
            if (origin && destination && origin !== destination) {
                // Auto-fill distance if empty
                const distanceField = document.getElementById('distance_km');
                if (!distanceField.value) {
                    distanceField.value = '25.5'; // Standard distance between cities
                }
                
                // Auto-fill duration based on fare (higher fare = express = shorter time)
                const durationField = document.getElementById('estimated_duration_minutes');
                if (!durationField.value && fare > 0) {
                    durationField.value = fare >= 70 ? '45' : '60';
                }
            }
        }
        
        // Form validation
        function validateForm() {
            const origin = document.getElementById('origin').value;
            const destination = document.getElementById('destination').value;
            const fare = parseFloat(document.getElementById('fare').value) || 0;
            
            if (origin === destination) {
                alert('Origin and destination cannot be the same!');
                return false;
            }
            
            if (fare <= 0) {
                alert('Fare must be greater than 0!');
                return false;
            }
            
            return true;
        }
        
        // Delete confirmation
        function confirmDelete() {
            if (confirm('Are you sure you want to delete this route? This action cannot be undone.\n\nNote: Routes with active bookings cannot be deleted.')) {
                window.location.href = 'delete_route.php?id=<?php echo $route_id; ?>';
            }
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            initPreviewMap();
            
            // Form field change listeners
            ['origin', 'destination', 'fare', 'distance_km', 'estimated_duration_minutes'].forEach(fieldId => {
                document.getElementById(fieldId).addEventListener('change', updateRoutePreview);
                document.getElementById(fieldId).addEventListener('input', updateRoutePreview);
            });
            
            // Auto-calculate when origin/destination changes
            document.getElementById('origin').addEventListener('change', autoCalculate);
            document.getElementById('destination').addEventListener('change', autoCalculate);
            document.getElementById('fare').addEventListener('blur', autoCalculate);
            
            // Form validation
            document.getElementById('routeForm').addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                }
            });
        });
        
        // Ensure destination cannot be same as origin
        document.getElementById('origin').addEventListener('change', function() {
            const originValue = this.value;
            const destinationSelect = document.getElementById('destination');
            
            // Re-enable all options
            Array.from(destinationSelect.options).forEach(option => {
                option.disabled = false;
            });
            
            // Disable the selected origin in destination
            if (originValue) {
                Array.from(destinationSelect.options).forEach(option => {
                    if (option.value === originValue) {
                        option.disabled = true;
                    }
                });
                
                // Clear destination if it matches origin
                if (destinationSelect.value === originValue) {
                    destinationSelect.value = '';
                }
            }
        });
        
        document.getElementById('destination').addEventListener('change', function() {
            const destinationValue = this.value;
            const originSelect = document.getElementById('origin');
            
            // Re-enable all options
            Array.from(originSelect.options).forEach(option => {
                option.disabled = false;
            });
            
            // Disable the selected destination in origin
            if (destinationValue) {
                Array.from(originSelect.options).forEach(option => {
                    if (option.value === destinationValue) {
                        option.disabled = true;
                    }
                });
                
                // Clear origin if it matches destination
                if (originSelect.value === destinationValue) {
                    originSelect.value = '';
                }
            }
        });
    </script>
</body>
</html>