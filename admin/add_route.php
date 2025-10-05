<?php
require_once 'admin_auth.php';

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'info';
$form_data = [];

// Pre-defined locations for San Mateo - Cauayan route


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
    
    // Check for duplicate route name
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT route_name FROM routes WHERE route_name = ?");
        $stmt->execute([$form_data['route_name']]);
        if ($stmt->fetch()) {
            $errors[] = 'Route name already exists.';
        }
        
        // Check for duplicate route (same origin and destination)
        $stmt = $conn->prepare("SELECT route_id FROM routes WHERE origin = ? AND destination = ?");
        $stmt->execute([$form_data['origin'], $form_data['destination']]);
        if ($stmt->fetch()) {
            $errors[] = 'A route with the same origin and destination already exists.';
        }
    }
    
    // If no errors, create the route
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
            
            // Insert route
            $stmt = $conn->prepare("
                INSERT INTO routes (
                    route_name, origin, destination, distance_km, 
                    estimated_duration_minutes, fare, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $form_data['route_name'],
                $form_data['origin'],
                $form_data['destination'],
                $form_data['distance_km'],
                $form_data['estimated_duration_minutes'],
                $form_data['fare'],
                $form_data['status']
            ]);
            
            $route_id = $conn->lastInsertId();
            
            logAdminActivity('Create Route', "Route: {$form_data['route_name']} ({$form_data['origin']} → {$form_data['destination']})");
            
            
            $message = 'Route created successfully!';
            $message_type = 'success';
            
            // Clear form data
            $form_data = [];
            
        } catch (PDOException $e) {
            $message = 'Error creating route: ' . $e->getMessage();
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
    <title>Add Route - <?php echo SITE_NAME; ?></title>
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
            margin-bottom: 2rem;
        }
        
        .page-title h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .back-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5eb;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .map-container {
            grid-column: 1 / -1;
            height: 400px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #e1e5eb;
        }
        
        #map {
            width: 100%;
            height: 100%;
        }
        
        .location-suggestions {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            width: calc(100% - 1.5rem);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: none;
        }
        
        .location-suggestion {
            padding: 0.5rem 1rem;
            cursor: pointer;
        }
        
        .location-suggestion:hover {
            background: #f0f0f0;
        }
        
        .form-note {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .form-container {
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
            </ul>
            <div class="nav-user">
                <span>Welcome, <?php echo getAdminUsername(); ?></span>
                <a href="admin_logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <a href="manage_routes.php" class="back-link">← Back to Routes</a>
        
        <div class="page-header">
            <h2>Add New Route</h2>
            <p>Create a new bus route with origin and destination</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form id="routeForm" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="route_name">Route Name *</label>
                        <input type="text" id="route_name" name="route_name" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['route_name'] ?? ''); ?>" 
                               required placeholder="e.g., San Mateo - Cauayan">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="active" <?php echo ($form_data['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($form_data['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="origin">Origin *</label>
                        <input type="text" id="origin" name="origin" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['origin'] ?? ''); ?>" 
                               required placeholder="e.g., San Mateo, Isabela">
                        <div id="originSuggestions" class="location-suggestions"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="destination">Destination *</label>
                        <input type="text" id="destination" name="destination" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['destination'] ?? ''); ?>" 
                               required placeholder="e.g., Cauayan City, Isabela">
                        <div id="destinationSuggestions" class="location-suggestions"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="distance_km">Distance (km)</label>
                        <input type="number" id="distance_km" name="distance_km" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['distance_km'] ?? ''); ?>" 
                               step="0.1" min="0" placeholder="Leave empty to auto-calculate">
                        <div class="form-note">Leave empty to auto-calculate based on map route</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="estimated_duration_minutes">Estimated Duration (minutes)</label>
                        <input type="number" id="estimated_duration_minutes" name="estimated_duration_minutes" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['estimated_duration_minutes'] ?? ''); ?>" 
                               min="0" placeholder="Leave empty to auto-calculate">
                        <div class="form-note">Leave empty to auto-calculate based on distance</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="fare">Fare (₱) *</label>
                        <input type="number" id="fare" name="fare" class="form-control" 
                               value="<?php echo htmlspecialchars($form_data['fare'] ?? ''); ?>" 
                               step="0.01" min="0" required placeholder="e.g., 65.00">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Route Map</label>
                        <div class="map-container">
                            <div id="map"></div>
                        </div>
                        <div class="form-note">Drag markers to adjust origin and destination locations</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="manage_routes.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Route</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Leaflet and Routing Machine JS -->
    <script src="https://unpkg.com/leaflet@1.2.0/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>

<script>
// Initialize map and routing control
let map, routingControl;
let originMarker, destinationMarker;
let originLatLng = null;
let destinationLatLng = null;

// Initialize map
function initMap() {
    // Default center (San Mateo, Isabela)
    const defaultCenter = [16.9167, 121.5833];
    
    map = L.map('map').setView(defaultCenter, 12);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Initialize markers with default locations
    const defaultOrigin = [16.9167, 121.5833]; // San Mateo
    const defaultDestination = [16.9272, 121.7708]; // Cauayan
    
    originMarker = L.marker(defaultOrigin, {draggable: true})
        .addTo(map)
        .bindPopup('Origin: San Mateo, Isabela')
        .on('dragend', updateOriginFromMarker);
    
    destinationMarker = L.marker(defaultDestination, {draggable: true})
        .addTo(map)
        .bindPopup('Destination: Cauayan City, Isabela')
        .on('dragend', updateDestinationFromMarker);
    
    // Initialize routing control
    routingControl = L.Routing.control({
        waypoints: [
            L.latLng(defaultOrigin[0], defaultOrigin[1]),
            L.latLng(defaultDestination[0], defaultDestination[1])
        ],
        routeWhileDragging: true,
        lineOptions: {
            styles: [{color: '#667eea', opacity: 0.7, weight: 5}]
        },
        createMarker: function() { return null; } // Disable default markers
    }).addTo(map);
    
    // Store initial coordinates
    originLatLng = defaultOrigin;
    destinationLatLng = defaultDestination;
    
    // Update route when markers are dragged
    originMarker.on('dragend', updateRoute);
    destinationMarker.on('dragend', updateRoute);
}

// Update route based on marker positions
function updateRoute() {
    const originLatLng = originMarker.getLatLng();
    const destinationLatLng = destinationMarker.getLatLng();
    
    routingControl.setWaypoints([
        L.latLng(originLatLng.lat, originLatLng.lng),
        L.latLng(destinationLatLng.lat, destinationLatLng.lng)
    ]);
    
    // Update distance and duration when route changes
    routingControl.on('routesfound', function(e) {
        const routes = e.routes;
        if (routes && routes.length > 0) {
            const route = routes[0];
            const distanceKm = (route.summary.totalDistance / 1000).toFixed(1);
            const durationMinutes = Math.round(route.summary.totalTime / 60);
            
            document.getElementById('distance_km').value = distanceKm;
            document.getElementById('estimated_duration_minutes').value = durationMinutes;
        }
    });
}

// Update origin input when marker is dragged
function updateOriginFromMarker() {
    const latLng = originMarker.getLatLng();
    originLatLng = [latLng.lat, latLng.lng];
    
    // Reverse geocode to get location name
    reverseGeocode(latLng.lat, latLng.lng).then(locationName => {
        if (locationName) {
            document.getElementById('origin').value = locationName;
        }
    });
    
    updateRoute();
}

// Update destination input when marker is dragged
function updateDestinationFromMarker() {
    const latLng = destinationMarker.getLatLng();
    destinationLatLng = [latLng.lat, latLng.lng];
    
    // Reverse geocode to get location name
    reverseGeocode(latLng.lat, latLng.lng).then(locationName => {
        if (locationName) {
            document.getElementById('destination').value = locationName;
        }
    });
    
    updateRoute();
}

// Reverse geocode coordinates to location name
async function reverseGeocode(lat, lng) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
        const data = await response.json();
        return data.display_name || '';
    } catch (error) {
        console.error('Reverse geocoding error:', error);
        return '';
    }
}

// Initialize map when page loads
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    
    // Setup location suggestions
    setupLocationSuggestions('origin');
    setupLocationSuggestions('destination');
    
    // Form submission handler
    document.getElementById('routeForm').addEventListener('submit', function(e) {
        // Add coordinates to form as hidden inputs
        if (originLatLng) {
            const originCoordsInput = document.createElement('input');
            originCoordsInput.type = 'hidden';
            originCoordsInput.name = 'origin_coords';
            originCoordsInput.value = originLatLng.join(',');
            this.appendChild(originCoordsInput);
        }
        
        if (destinationLatLng) {
            const destCoordsInput = document.createElement('input');
            destCoordsInput.type = 'hidden';
            destCoordsInput.name = 'destination_coords';
            destCoordsInput.value = destinationLatLng.join(',');
            this.appendChild(destCoordsInput);
        }
    });
});

// Setup location suggestions for input
function setupLocationSuggestions(inputId) {
    const input = document.getElementById(inputId);
    const suggestions = document.getElementById(inputId + 'Suggestions');
    
    input.addEventListener('input', debounce(function() {
        const query = input.value.trim();
        if (query.length < 3) {
            suggestions.style.display = 'none';
            return;
        }
        
        fetchLocationSuggestions(query).then(results => {
            suggestions.innerHTML = '';
            if (results.length === 0) {
                suggestions.style.display = 'none';
                return;
            }
            
            results.forEach(result => {
                const div = document.createElement('div');
                div.className = 'location-suggestion';
                div.textContent = result.display_name;
                div.addEventListener('click', function() {
                    input.value = result.display_name;
                    suggestions.style.display = 'none';
                    
                    // Update marker position
                    const latLng = [parseFloat(result.lat), parseFloat(result.lon)];
                    if (inputId === 'origin') {
                        originMarker.setLatLng(latLng);
                        originLatLng = latLng;
                    } else {
                        destinationMarker.setLatLng(latLng);
                        destinationLatLng = latLng;
                    }
                    
                    map.setView(latLng, 13);
                    updateRoute();
                });
                
                suggestions.appendChild(div);
            });
            
            suggestions.style.display = 'block';
        });
    }, 300));
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !suggestions.contains(e.target)) {
            suggestions.style.display = 'none';
        }
    });
}

// Fetch location suggestions from Nominatim
async function fetchLocationSuggestions(query) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`);
        return await response.json();
    } catch (error) {
        console.error('Geocoding error:', error);
        return [];
    }
}

// Debounce function to limit API calls
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>

</body>
</html>