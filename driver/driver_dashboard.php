<?php
require_once '../admin/config.php';
session_start();

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header("Location: driver_login.php");
    exit();
}

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header("Location: driver_login.php?timeout=1");
    exit(); 
}


$_SESSION['last_activity'] = time();

// Database connection
$db = new Database();
$conn = $db->getConnection();

$driver_id = $_SESSION['driver_id'];
$driver_name = $_SESSION['driver_name'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_trip_status':
            $trip_id = $_POST['trip_id'];
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE active_trips SET status = ?, updated_at = NOW() WHERE trip_id = ? AND driver_id = ?");
            $result = $stmt->execute([$status, $trip_id, $driver_id]);
            
            if ($status === 'departed') {
                $stmt = $conn->prepare("UPDATE active_trips SET actual_departure = NOW() WHERE trip_id = ? AND driver_id = ?");
                $stmt->execute([$trip_id, $driver_id]);
            } elseif ($status === 'arrived') {
                $stmt = $conn->prepare("UPDATE active_trips SET actual_arrival = NOW() WHERE trip_id = ? AND driver_id = ?");
                $stmt->execute([$trip_id, $driver_id]);
            } elseif ($status === 'completed') {
                // UPDATE BUS STATUS TO 'available' WHEN TRIP IS COMPLETED
                $stmt = $conn->prepare("
                    UPDATE buses 
                    SET status = 'available' 
                    WHERE bus_id = (
                        SELECT bus_id FROM active_trips WHERE trip_id = ?
                    )
                ");
                $stmt->execute([$trip_id]);
                
                // Also update the driver's status if needed
                $stmt = $conn->prepare("
                    UPDATE drivers 
                    SET status = 'active' 
                    WHERE driver_id = ?
                ");
                $stmt->execute([$driver_id]);
            }
            
            echo json_encode(['success' => $result]);
            exit;

case 'accept_booking':
    $booking_id = $_POST['booking_id'];
    
    // Get booking details
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }
    
    // Check if bus has available seats (only count confirmed bookings)
    $seat_check = $conn->prepare("
        SELECT b.capacity - COALESCE((
            SELECT COUNT(*) 
            FROM bookings bk 
            WHERE bk.bus_id = b.bus_id 
            AND bk.travel_date = ? 
            AND bk.booking_status IN ('confirmed')
        ), 0) as available_seats
        FROM buses b
        WHERE b.bus_id = ?
    ");
    $seat_check->execute([$booking['travel_date'], $booking['bus_id']]);
    $available_seats = $seat_check->fetchColumn();

    if ($available_seats <= 0) {
        echo json_encode(['success' => false, 'error' => 'Bus is now full. Cannot accept booking.']);
        exit;
    }
    
    // Accept the booking
    $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'confirmed', driver_approved = 1, driver_id = ?, updated_at = NOW() WHERE booking_id = ?");
    $result = $stmt->execute([$driver_id, $booking_id]);
    
    echo json_encode(['success' => $result]);
    exit;

case 'reject_booking':
    $booking_id = $_POST['booking_id'];
    $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', driver_approved = 0, driver_id = ?, updated_at = NOW() WHERE booking_id = ?");
    $result = $stmt->execute([$driver_id, $booking_id]);
    echo json_encode(['success' => $result]);
    exit;
            
        case 'mark_passenger_boarded':
            $booking_id = $_POST['booking_id'];
            $stmt = $conn->prepare("UPDATE bookings SET boarded = 1 WHERE booking_id = ?");
            $result = $stmt->execute([$booking_id]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_passenger_count':
            $trip_id = $_POST['trip_id'];
            $count = $_POST['count'];
            $bus_id = $_POST['bus_id'];
            
            // Update trip passenger count
            $stmt = $conn->prepare("UPDATE active_trips SET passenger_count = ? WHERE trip_id = ? AND driver_id = ?");
            $result = $stmt->execute([$count, $trip_id, $driver_id]);
            
            // Update bus available seats (capacity minus passenger count)
            if ($result) {
                $stmt = $conn->prepare("
                    UPDATE buses 
                    SET status = CASE 
                        WHEN (capacity - ?) <= 0 THEN 'full' 
                        ELSE 'on_trip' 
                    END 
                    WHERE bus_id = ?
                ");
                $stmt->execute([$count, $bus_id]);
            }
            
            echo json_encode(['success' => $result]);
            exit;
            
        case 'cancel_booking':
            $booking_id = $_POST['booking_id'];
            $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE booking_id = ?");
            $result = $stmt->execute([$booking_id]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'update_location':
            $latitude = $_POST['latitude'];
            $longitude = $_POST['longitude'];
            $speed = $_POST['speed'] ?? 0;
            $heading = $_POST['heading'] ?? 0;
            $accuracy = $_POST['accuracy'] ?? 0;
            
            // Get driver's assigned bus
            $stmt = $conn->prepare("SELECT assigned_bus_id FROM drivers WHERE driver_id = ?");
            $stmt->execute([$driver_id]);
            $bus_id = $stmt->fetchColumn();
            
            if ($bus_id) {
                $stmt = $conn->prepare("INSERT INTO bus_locations (bus_id, latitude, longitude, speed, heading, accuracy) 
                                      VALUES (?, ?, ?, ?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE 
                                      latitude = VALUES(latitude), longitude = VALUES(longitude), 
                                      speed = VALUES(speed), heading = VALUES(heading), 
                                      accuracy = VALUES(accuracy), updated_at = NOW()");
                $result = $stmt->execute([$bus_id, $latitude, $longitude, $speed, $heading, $accuracy]);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No assigned bus found']);
            }
            exit;
            
        case 'start_trip':
            $route_id = $_POST['route_id'];
            
            // Check driver and bus status first
            $stmt = $conn->prepare("
                SELECT d.status as driver_status, b.status as bus_status
                FROM drivers d
                LEFT JOIN buses b ON d.assigned_bus_id = b.bus_id
                WHERE d.driver_id = ?
            ");
            $stmt->execute([$driver_id]);
            $status_check = $stmt->fetch();
            
            if ($status_check['driver_status'] !== 'active') {
                echo json_encode(['success' => false, 'error' => 'Your driver account is deactivated.']);
                exit;
            }
            
            if ($status_check['bus_status'] === 'maintenance') {
                echo json_encode(['success' => false, 'error' => 'Your assigned bus is under maintenance.']);
                exit;
            }
            
            if ($status_check['bus_status'] === 'inactive') {
                echo json_encode(['success' => false, 'error' => 'Your assigned bus is deactivated.']);
                exit;
            }
            
            // Check if driver already has an active trip
            $stmt = $conn->prepare("
                SELECT COUNT(*) 
                FROM active_trips 
                WHERE driver_id = ? AND status NOT IN ('completed', 'cancelled')
            ");
            $stmt->execute([$driver_id]);
            $active_trip_count = $stmt->fetchColumn();
            
            if ($active_trip_count > 0) {
                echo json_encode(['success' => false, 'error' => 'You already have an active trip. Please complete it before starting a new one.']);
                exit;
            }
            
            // Get driver's assigned bus
            $stmt = $conn->prepare("SELECT assigned_bus_id FROM drivers WHERE driver_id = ?");
            $stmt->execute([$driver_id]);
            $bus_id = $stmt->fetchColumn();
            
            if (!$bus_id) {
                echo json_encode(['success' => false, 'error' => 'No bus assigned to driver']);
                exit;
            }
            
            // Get route details
            $stmt = $conn->prepare("SELECT * FROM routes WHERE route_id = ?");
            $stmt->execute([$route_id]);
            $route = $stmt->fetch();
            
            if (!$route) {
                echo json_encode(['success' => false, 'error' => 'Route not found']);
                exit;
            }
            
            // Calculate departure and arrival times
            $departure_time = date('Y-m-d H:i:s');
            $arrival_time = date('Y-m-d H:i:s', strtotime("+{$route['estimated_duration_minutes']} minutes"));
            
            // Create new active trip
            $stmt = $conn->prepare("
                INSERT INTO active_trips (route_id, bus_id, driver_id, trip_date, scheduled_departure, estimated_arrival, status)
                VALUES (?, ?, ?, CURDATE(), ?, ?, 'scheduled')
            ");
            $result = $stmt->execute([$route_id, $bus_id, $driver_id, $departure_time, $arrival_time]);
            
            if ($result) {
                $trip_id = $conn->lastInsertId();
                
                // Update bus status
                $stmt = $conn->prepare("UPDATE buses SET status = 'on_trip' WHERE bus_id = ?");
                $stmt->execute([$bus_id]);
                
                echo json_encode(['success' => true, 'trip_id' => $trip_id]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create trip']);
            }
            exit;
    }
}

// Fetch driver details with assigned bus
$stmt = $conn->prepare("
    SELECT d.*, b.bus_id, b.bus_number, b.plate_number, b.capacity, b.status as bus_status
    FROM drivers d
    LEFT JOIN buses b ON d.assigned_bus_id = b.bus_id
    WHERE d.driver_id = ?
");
$stmt->execute([$driver_id]);
$driver_data = $stmt->fetch();

// Check if driver or bus is inactive/maintenance
$can_start_trip = true;
$trip_error = '';

if ($driver_data['status'] !== 'active') {
    $can_start_trip = false;
    $trip_error = 'Your driver account is deactivated. Please contact administrator.';
} elseif ($driver_data['bus_status'] === 'maintenance') {
    $can_start_trip = false;
    $trip_error = 'Your assigned bus is under maintenance. Cannot start trip.';
} elseif ($driver_data['bus_status'] === 'inactive') {
    $can_start_trip = false;
    $trip_error = 'Your assigned bus is deactivated. Cannot start trip.';
} elseif (!$driver_data['bus_id']) {
    $can_start_trip = false;
    $trip_error = 'No bus assigned to you. Please contact administrator.';
}

// Fetch assigned routes for this driver's bus
$assigned_routes = [];
if ($driver_data['bus_id']) {
    $stmt = $conn->prepare("
        SELECT r.*, bra.assigned_at
        FROM routes r
        JOIN bus_route_assignments bra ON r.route_id = bra.route_id
        WHERE bra.bus_id = ? AND r.status = 'active'
        ORDER BY bra.assigned_at DESC
    ");
    $stmt->execute([$driver_data['bus_id']]);
    $assigned_routes = $stmt->fetchAll();
}

// Fetch active trip for this driver with enhanced route data
$stmt = $conn->prepare("
    SELECT at.*, r.route_name, r.origin, r.destination, r.distance_km, 
           r.estimated_duration_minutes, r.fare, b.bus_number, b.plate_number, b.capacity,
           r.origin_lat, r.origin_lng, r.dest_lat, r.dest_lng
    FROM active_trips at
    JOIN routes r ON at.route_id = r.route_id
    JOIN buses b ON at.bus_id = b.bus_id
    WHERE at.driver_id = ? AND at.status NOT IN ('completed', 'cancelled')
    ORDER BY at.created_at DESC
    LIMIT 1
");
$stmt->execute([$driver_id]);
$active_trip = $stmt->fetch();

// Enhanced geocoding with Philippines locations
if ($active_trip && (empty($active_trip['origin_lat']) || empty($active_trip['dest_lat']))) {
    function enhancedGeocode($location) {
        $common_locations = [
            'san mateo, isabela' => ['lat' => 16.8767, 'lng' => 121.5941],
            'cauayan, isabela' => ['lat' => 16.9333, 'lng' => 121.7667],
            'sm city cauayan' => ['lat' => 16.9325, 'lng' => 121.7689],
            'tuguegarao' => ['lat' => 17.6132, 'lng' => 121.7270],
            'ilagan, isabela' => ['lat' => 17.1378, 'lng' => 121.8889],
            'santiago, isabela' => ['lat' => 16.6877, 'lng' => 121.5465],
            'roxas, isabela' => ['lat' => 16.6203, 'lng' => 121.5341],
            'cabagan, isabela' => ['lat' => 17.4397, 'lng' => 121.7719],
        ];
        
        $location_lower = strtolower($location);
        foreach ($common_locations as $key => $coords) {
            if (strpos($location_lower, $key) !== false) {
                return $coords;
            }
        }
        
        return ['lat' => 16.8750, 'lng' => 121.6000];
    }
    
    if (empty($active_trip['origin_lat'])) {
        $origin_coords = enhancedGeocode($active_trip['origin']);
        $active_trip['origin_lat'] = $origin_coords['lat'];
        $active_trip['origin_lng'] = $origin_coords['lng'];
    }
    
    if (empty($active_trip['dest_lat'])) {
        $dest_coords = enhancedGeocode($active_trip['destination']);
        $active_trip['dest_lat'] = $dest_coords['lat'];
        $active_trip['dest_lng'] = $dest_coords['lng'];
    }
}

// Fetch bookings for active trip
$bookings = [];
if ($active_trip) {
    $stmt = $conn->prepare("
        SELECT b.*, u.full_name, u.mobile_number
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        WHERE b.trip_id = ? AND b.booking_status = 'confirmed'
        ORDER BY b.seat_number
    ");
    $stmt->execute([$active_trip['trip_id']]);
    $bookings = $stmt->fetchAll();
    
    // Fetch passenger locations for active trip
    $stmt = $conn->prepare("
        SELECT pl.*, u.full_name, u.mobile_number, b.booking_reference
        FROM passenger_locations pl
        JOIN users u ON pl.user_id = u.user_id
        JOIN bookings b ON pl.booking_id = b.booking_id
        WHERE pl.bus_id = ? AND b.trip_id = ? AND b.booking_status = 'confirmed' AND b.boarded = 0
        ORDER BY pl.updated_at DESC
    ");
    $stmt->execute([$active_trip['bus_id'], $active_trip['trip_id']]);
    $passenger_locations = $stmt->fetchAll();
} else {
    $passenger_locations = [];
}

// Fetch pending bookings for the driver's active trip
$pending_bookings = [];
if ($active_trip) {
$stmt = $conn->prepare("
    SELECT b.*, u.full_name, u.mobile_number, r.route_name, r.origin, r.destination
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN routes r ON b.route_id = r.route_id
    WHERE b.trip_id = ? AND b.booking_status = 'pending'
    ORDER BY b.created_at DESC
");
    $stmt->execute([$active_trip['trip_id']]);
    $pending_bookings = $stmt->fetchAll();
} else {
    $pending_bookings = [];
}

// Fetch booking history for the driver
$history_stmt = $conn->prepare("
    SELECT b.*, r.route_name, r.origin, r.destination, u.full_name, u.mobile_number,
           at.trip_date, at.scheduled_departure, at.actual_departure, at.actual_arrival
    FROM bookings b
    JOIN routes r ON b.route_id = r.route_id
    JOIN users u ON b.user_id = u.user_id
    LEFT JOIN active_trips at ON b.trip_id = at.trip_id
    WHERE at.driver_id = ? OR (b.driver_id = ? AND b.trip_id IS NULL)
    ORDER BY b.created_at DESC
    LIMIT 20
");
$history_stmt->execute([$driver_id, $driver_id]);
$booking_history = $history_stmt->fetchAll();

// Count statistics
$total_bookings = count($bookings);
$boarded_passengers = array_filter($bookings, function($b) { return $b['boarded']; });
$boarded_count = count($boarded_passengers);
$available_seats = ($active_trip ? $active_trip['capacity'] : 0) - $total_bookings - ($active_trip ? $active_trip['passenger_count'] : 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BusTrack - Driver</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#007bff">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="header">
    <h1><i class="fas fa-bus"></i> Driver Dashboard</h1>
</div>

<!-- HOME SECTION -->
<div class="content-section active" id="home">
    <div class="section">
        <h3><i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($driver_name); ?>!</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value"><?php echo $active_trip ? 1 : 0; ?></div>
                <div class="stat-label">Active Trips</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $total_bookings; ?></div>
                <div class="stat-label">Bookings</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $boarded_count; ?></div>
                <div class="stat-label">Boarded</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="available-seats-display"><?php echo $available_seats; ?></div>
                <div class="stat-label">Available</div>
            </div>
        </div>
    </div>

    <?php if ($active_trip): ?>
    <div class="trip-info">
        <div class="bus-header">
            <div class="bus-info">
                <h4><?php echo htmlspecialchars($active_trip['route_name']); ?></h4>
                <p class="bus-route">
                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($active_trip['origin']); ?>
                    <i class="fas fa-arrow-right" style="margin: 0 8px;"></i>
                    <i class="fas fa-flag-checkered"></i> <?php echo htmlspecialchars($active_trip['destination']); ?>
                </p>
            </div>
            <span class="status-badge status-<?php echo $active_trip['status']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $active_trip['status'])); ?>
            </span>
        </div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin: 16px 0;">
            <div style="font-size: 14px; opacity: 0.9;">
                <i class="fas fa-bus"></i> <?php echo htmlspecialchars($active_trip['bus_number']); ?> (<?php echo htmlspecialchars($active_trip['plate_number']); ?>)
            </div>
        </div>
        
        <div class="bus-details">
            <div class="detail-item">
                <div class="detail-value"><?php echo $active_trip['distance_km']; ?> km</div>
                <div class="detail-label">Distance</div>
            </div>
            <div class="detail-item">
                <div class="detail-value"><?php echo $active_trip['estimated_duration_minutes']; ?> min</div>
                <div class="detail-label">Duration</div>
            </div>
            <div class="detail-item">
                <div class="detail-value"><?php echo $active_trip['capacity']; ?> seats</div>
                <div class="detail-label">Capacity</div>
            </div>
            <div class="detail-item">
                <div class="detail-value">₱<?php echo number_format($active_trip['fare'], 2); ?></div>
                <div class="detail-label">Fare</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <h3><i class="fas fa-users"></i> Passenger Management</h3>
        <div class="passenger-count-section">
            <div style="text-align: center; margin-bottom: 8px; font-weight: 600;">Walk-in Passengers</div>
            <div style="text-align: center; margin-bottom: 12px; font-size: 13px; color: #6c757d;">
                Booked: <?php echo $total_bookings; ?> + Walk-ins: <span id="walkinin-count"><?php echo $active_trip['passenger_count']; ?></span> = 
                <strong><span id="total-passenger-count"><?php echo $total_bookings + $active_trip['passenger_count']; ?></span></strong> / <?php echo $active_trip['capacity']; ?>
            </div>
            
            <div class="passenger-count-controls">
                <button class="count-btn btn-danger" onclick="updatePassengerCount(-1)">
                    <i class="fas fa-minus"></i>
                </button>
                <div class="count-display" id="current-count"><?php echo $active_trip['passenger_count']; ?></div>
                <button class="count-btn btn-success" onclick="updatePassengerCount(1)">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            
            <div style="text-align: center; margin-top: 12px; font-size: 14px; color: #007bff; font-weight: 600;">
                <i class="fas fa-chair"></i> Available Seats: <span id="available-seats-counter"><?php echo $available_seats; ?></span>
            </div>
            <div style="text-align: center; margin-top: 4px; font-size: 12px; color: #6c757d;">
                Max capacity: <?php echo $active_trip['capacity']; ?> passengers
            </div>
        </div>
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap;" id="trip-controls">
            <?php
            switch ($active_trip['status']) {
                case 'scheduled':
                case 'boarding':
                    echo '<button class="btn btn-success" onclick="updateTripStatus(\'departed\', ' . $active_trip['trip_id'] . ')">
                            <i class="fas fa-play"></i> Start Trip
                          </button>';
                    break;
                case 'departed':
                    echo '<button class="btn btn-primary" onclick="updateTripStatus(\'in_transit\', ' . $active_trip['trip_id'] . ')">
                            <i class="fas fa-road"></i> In Transit
                          </button>';
                    break;
                case 'in_transit':
                    echo '<button class="btn btn-warning" onclick="updateTripStatus(\'arrived\', ' . $active_trip['trip_id'] . ')">
                            <i class="fas fa-flag-checkered"></i> Arrived
                          </button>';
                    break;
                case 'arrived':
                    echo '<button class="btn btn-success" onclick="updateTripStatus(\'completed\', ' . $active_trip['trip_id'] . ')">
                            <i class="fas fa-check"></i> Complete
                          </button>';
                    break;
                case 'completed':
                    echo '<div style="padding: 10px; background: #e8f5e8; color: #388e3c; border-radius: 8px; text-align: center;">
                            <i class="fas fa-check-circle"></i> Trip completed successfully!
                          </div>';
                    break;
            }
            ?>
            <button class="btn btn-info" onclick="refreshDashboard()">
                <i class="fas fa-refresh"></i> Refresh
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <h3><i class="fas fa-route"></i> Available Routes</h3>
        <?php if (!$can_start_trip && !empty($trip_error)): ?>
        <div style="padding: 10px; background: #f8d7da; color: #721c24; border-radius: 8px; margin-bottom: 15px;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $trip_error; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($assigned_routes)): ?>
        <div class="bus-list">
            <?php foreach ($assigned_routes as $route): ?>
            <div class="bus-item">
                <div class="bus-header">
                    <div class="bus-info">
                        <h4><?php echo htmlspecialchars($route['route_name']); ?></h4>
                        <p class="bus-route">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($route['origin']); ?>
                            <i class="fas fa-arrow-right"></i>
                            <i class="fas fa-flag-checkered"></i> <?php echo htmlspecialchars($route['destination']); ?>
                        </p>
                    </div>
                </div>
                
                <div class="bus-details">
                    <div class="detail-item">
                        <div class="detail-value"><?php echo $route['distance_km']; ?> km</div>
                        <div class="detail-label">Distance</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-value"><?php echo $route['estimated_duration_minutes']; ?> min</div>
                        <div class="detail-label">Duration</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-value">₱<?php echo number_format($route['fare'], 2); ?></div>
                        <div class="detail-label">Fare</div>
                    </div>
                    <div class="detail-item">
                        <?php if ($can_start_trip): ?>
                        <button class="btn btn-primary" onclick="startTrip(<?php echo $route['route_id']; ?>)">
                            <i class="fas fa-play"></i> Start
                        </button>
                        <?php else: ?>
                        <button class="btn btn-primary" disabled>
                            <i class="fas fa-ban"></i> Not Available
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-route"></i>
            <p>No routes assigned to your bus</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- MAP SECTION -->
<div class="content-section" id="map-view">
    <div class="section">
        <h3><i class="fas fa-map-marked-alt"></i> Live Tracking</h3>
        <div id="map"></div>
        
        <?php if ($active_trip): ?>
        <div class="map-info">
            <div class="map-info-item">
                <span class="map-info-label">Current Status:</span>
                <span class="status-badge status-<?php echo $active_trip['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $active_trip['status'])); ?>
                </span>
            </div>
            <div class="map-info-item">
                <span class="map-info-label">Passengers:</span>
                <span><?php echo $total_bookings + $active_trip['passenger_count']; ?> / <?php echo $active_trip['capacity']; ?></span>
            </div>
            <div class="map-info-item">
                <span class="map-info-label">Route:</span>
                <span><?php echo htmlspecialchars($active_trip['route_name']); ?></span>
            </div>
            <div class="map-info-item">
                <span class="map-info-label">Bus:</span>
                <span><?php echo htmlspecialchars($active_trip['bus_number']); ?> (<?php echo htmlspecialchars($active_trip['plate_number']); ?>)</span>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-map-marked-alt"></i>
            <p>No active trip to track</p>
            <p>Start a trip to enable live tracking</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- BOOKINGS SECTION -->
<div class="content-section" id="bookings">
    <div class="section">
        <h3><i class="fas fa-ticket-alt"></i> Current Passengers</h3>
        
        <!-- Pending Approval Section -->
        <h4 style="margin: 15px 0 10px 0; color: #ff9800;"><i class="fas fa-clock"></i> Pending Approval</h4>
        <?php if (!empty($pending_bookings)): ?>
        <div class="bus-list">
            <?php foreach ($pending_bookings as $booking): ?>
            <div class="booking-item" style="border-left-color: #ff9800;">
                <div class="booking-info">
                    <h4><?php echo htmlspecialchars($booking['full_name']); ?></h4>
                    <p><i class="fas fa-ticket-alt"></i> <?php echo htmlspecialchars($booking['booking_reference']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['mobile_number']); ?></p>
                    <p><i class="fas fa-route"></i> <?php echo htmlspecialchars($booking['route_name']); ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($booking['origin']); ?> → <?php echo htmlspecialchars($booking['destination']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($booking['travel_date'])); ?> at <?php echo date('g:i A', strtotime($booking['departure_time'])); ?></p>
                </div>
                
                <div class="booking-actions">
                    <button class="btn btn-success" onclick="acceptBooking(<?php echo $booking['booking_id']; ?>)">
                        <i class="fas fa-check"></i> Accept
                    </button>
                    <button class="btn btn-danger" onclick="rejectBooking(<?php echo $booking['booking_id']; ?>)">
                        <i class="fas fa-times"></i> Reject
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="padding: 10px; background: #f9f9f9; border-radius: 8px; text-align: center; margin-bottom: 20px;">
            <i class="fas fa-check-circle" style="color: #4caf50;"></i> No pending booking requests
        </div>
        <?php endif; ?>
        
        <!-- Confirmed Passengers Section -->
        <h4 style="margin: 20px 0 10px 0; color: #4caf50;"><i class="fas fa-check-circle"></i> Confirmed Passengers</h4>
        <?php if (!empty($bookings)): ?>
        <div class="bus-list">
            <?php foreach ($bookings as $booking): ?>
            <div class="booking-item">
                <div class="booking-info">
                    <h4><?php echo htmlspecialchars($booking['full_name']); ?></h4>
                    <p><i class="fas fa-ticket-alt"></i> <?php echo htmlspecialchars($booking['booking_reference']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($booking['mobile_number']); ?></p>
                    <p><i class="fas fa-chair"></i> Seat <?php echo htmlspecialchars($booking['seat_number']); ?></p>
                </div>
                
                <div class="booking-actions">
                    <?php if (!$booking['boarded']): ?>
                    <button class="btn btn-success" onclick="markBoarded(<?php echo $booking['booking_id']; ?>)">
                        <i class="fas fa-check"></i> Mark Boarded
                    </button>
                     <button class="btn btn-danger" onclick="cancelBooking(<?php echo $booking['booking_id']; ?>)">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <?php else: ?>
                    <button class="btn btn-success" disabled>
                        <i class="fas fa-check-circle"></i> Boarded
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>No confirmed passengers for current trip</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- HISTORY SECTION -->
<div class="content-section" id="history">
    <div class="section">
        <h3><i class="fas fa-history"></i> Booking History</h3>
        <?php if (!empty($booking_history)): ?>
        <div class="bus-list">
            <?php foreach ($booking_history as $booking): ?>
            <div class="booking-item">
                <div class="booking-info">
                    <h4><?php echo htmlspecialchars($booking['full_name']); ?></h4>
                    <p><i class="fas fa-route"></i> <?php echo htmlspecialchars($booking['route_name']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($booking['trip_date'])); ?></p>
                    <p><i class="fas fa-ticket-alt"></i> <?php echo htmlspecialchars($booking['booking_reference']); ?></p>
                    <p><i class="fas fa-chair"></i> Seat <?php echo htmlspecialchars($booking['seat_number']); ?></p>
                </div>
                
                <div class="booking-actions">
                    <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                        <?php echo ucfirst($booking['booking_status']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <p>No booking history found</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!--PROFILE SECTION -->
<div class="content-section" id="profile">
    <div class="section">
        <h3><i class="fas fa-user"></i> Driver Profile</h3>
        
        <!-- Driver Information Card -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="profile-name">
                    <h2><?php echo htmlspecialchars($driver_data['full_name'] ?? $driver_name); ?></h2>
                    <p class="profile-role">Bus Driver</p>
                    <span class="status-badge status-<?php echo $driver_data['status']; ?>">
                        <?php echo ucfirst($driver_data['status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Driver Details -->
        <div class="profile-details">
            
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?php echo htmlspecialchars($driver_data['email'] ?? 'Not provided'); ?></div>
                </div>
            </div>
            
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Phone Number</div>
                    <div class="detail-value"><?php echo htmlspecialchars($driver_data['phone'] ?? 'Not provided'); ?></div>
                </div>
            </div>
            
            <div class="detail-row">
                <div class="detail-icon">
                    <i class="fas fa-id-badge"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">License Number</div>
                    <div class="detail-value"><?php echo htmlspecialchars($driver_data['license_number'] ?? 'Not provided'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Assigned Bus Information -->
        <?php if ($driver_data['bus_id']): ?>
        <div class="section-divider">
            <h4><i class="fas fa-bus"></i> Assigned Bus</h4>
        </div>
        
        <div class="bus-assignment-card">
            <div class="bus-assignment-info">
                <div class="bus-number"><?php echo htmlspecialchars($driver_data['bus_number']); ?></div>
                <div class="bus-details-grid">
                    <div class="bus-detail">
                        <span class="label">Plate Number:</span>
                        <span class="value"><?php echo htmlspecialchars($driver_data['plate_number']); ?></span>
                    </div>
                    <div class="bus-detail">
                        <span class="label">Capacity:</span>
                        <span class="value"><?php echo $driver_data['capacity']; ?> seats</span>
                    </div>
                    <div class="bus-detail">
                        <span class="label">Status:</span>
                        <span class="status-badge status-<?php echo $driver_data['bus_status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $driver_data['bus_status'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="section-divider">
            <h4><i class="fas fa-bus"></i> Assigned Bus</h4>
        </div>
        <div class="no-bus-assigned">
            <i class="fas fa-exclamation-triangle"></i>
            <p>No bus currently assigned</p>
            <p class="sub-text">Please contact your administrator</p>
        </div>
        <?php endif; ?>
        
        <!-- Account Actions -->
        <div class="profile-actions">
            <a class="btn btn-danger logout-btn" href="driver_logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

    </div>
</div>

<!-- Bottom Navigation -->
<div class="nav-bottom">
    <button class="active" onclick="showSection('home')">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </button>
    <button onclick="showSection('map-view')">
        <i class="fas fa-map"></i>
        <span>Map</span>
    </button>
    <button onclick="showSection('bookings')">
        <i class="fas fa-ticket-alt"></i>
        <span>Passengers</span>
    </button>
    <button onclick="showSection('history')">
        <i class="fas fa-history"></i>
        <span>History</span>
    </button>
       <button onclick="showSection('profile')">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </button>
</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

<script>
// Global variables
let map, routingControl;
let watchId = null;
let currentLocation = null;

// Constants for calculations
const CAPACITY = <?php echo $active_trip ? $active_trip['capacity'] : 0; ?>;
const TOTAL_BOOKINGS = <?php echo $total_bookings; ?>;

// Accept booking request
function acceptBooking(bookingId) {
    const formData = new FormData();
    formData.append('action', 'accept_booking');
    formData.append('booking_id', bookingId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Booking accepted successfully!');
            window.location.reload();
        } else {
            alert(data.error || 'Failed to accept booking');
        }
    })
    .catch(error => {
        console.error('Error accepting booking:', error);
        alert('Error accepting booking');
    });
}

// Reject booking request
function rejectBooking(bookingId) {
    if (!confirm('Are you sure you want to reject this booking request?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reject_booking');
    formData.append('booking_id', bookingId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Booking rejected successfully!');
            window.location.reload();
        } else {
            alert('Failed to reject booking');
        }
    })
    .catch(error => {
        console.error('Error rejecting booking:', error);
        alert('Error rejecting booking');
    });
}

// Show section function
function showSection(sectionId) {
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    document.getElementById(sectionId).classList.add('active');
    
    document.querySelectorAll('.nav-bottom button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.nav-bottom button:nth-child(${getNavIndex(sectionId)})`).classList.add('active');
    
    if (sectionId === 'map-view') {
        setTimeout(initMap, 100);
    }
}

function getNavIndex(sectionId) {
    switch(sectionId) {
        case 'home': return 1;
        case 'map-view': return 2;
        case 'bookings': return 3;
        case 'history': return 4;
         case 'profile': return 5;
        default: return 1;
    }
}

// Initialize map
function initMap() {
    if (map) {
        map.remove();
        map = null;
    }
    
    map = L.map('map').setView([16.8750, 121.6000], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    <?php if ($active_trip): ?>

        <?php if (!empty($passenger_locations)): ?>
                const customers = {
                <?php 
                $count = count($passenger_locations);
                $i = 0;
                foreach ($passenger_locations as $passenger): ?>
                    <?php echo $passenger['booking_id'];?>: {
                        name: "<?php echo htmlspecialchars($passenger['full_name']); ?>",
                        reference: "<?php echo htmlspecialchars($passenger['booking_reference']); ?>",
                        coords: [<?php echo $passenger['latitude']; ?>, <?php echo $passenger['longitude']; ?>]
                    }<?php if (++$i < $count) echo ','; ?>
                <?php endforeach; ?>
                };

            console.log(customers);
                
            let customerMarkers = {},routeControl;

            const passengerIcon = L.icon({
                    iconUrl: 'passenger.png',
                     iconColor: '#ffffff', // Icon color
                markerColor: 'blue', // Background color of the marker
                outlineColor: '#000000', // Outline color
                outlineWidth: 1,
                iconSize: [34, 34]
                });
          for (const id in customers) {
                const customer = customers[id];
                customerMarkers[id] = L.marker(customer.coords, {icon: passengerIcon})
                    .addTo(map)
                    .bindPopup(`<b>${customer.name}</b><br>${customer.reference}`);
            }
        <?php endif; ?>
    // Add origin and destination markers
    const origin = L.marker([<?php echo $active_trip['origin_lat']; ?>, <?php echo $active_trip['origin_lng']; ?>])
        .addTo(map)
        .bindPopup('<?php echo addslashes($active_trip['origin']); ?>');
    
    const destination = L.marker([<?php echo $active_trip['dest_lat']; ?>, <?php echo $active_trip['dest_lng']; ?>])
        .addTo(map)
        .bindPopup('<?php echo addslashes($active_trip['destination']); ?>');
    
    // Add route
    routingControl = L.Routing.control({
        waypoints: [
            L.latLng(<?php echo $active_trip['origin_lat']; ?>, <?php echo $active_trip['origin_lng']; ?>),
            L.latLng(<?php echo $active_trip['dest_lat']; ?>, <?php echo $active_trip['dest_lng']; ?>)
        ],
        draggableWaypoints: false,
                    routeWhileDragging: false,
                    showAlternatives: false,
                    addWaypoints: false,
                    show: false,
        lineOptions: {
            styles: [{color: '#2196f3', opacity: 0.7, weight: 5}]
        }
    }).addTo(map);
    
    // Fit map to show the entire route
    const bounds = L.latLngBounds([
        [<?php echo $active_trip['origin_lat']; ?>, <?php echo $active_trip['origin_lng']; ?>],
        [<?php echo $active_trip['dest_lat']; ?>, <?php echo $active_trip['dest_lng']; ?>]
    ]);
    map.fitBounds(bounds, {padding: [50, 50]});
    
    // Start location tracking if trip is active
    if (['scheduled', 'boarding', 'departed', 'in_transit', 'arrived'].includes('<?php echo $active_trip['status']; ?>')) {
        startLocationTracking();
    }
    <?php else: ?>
    // Default view for Isabela, Philippines
    map.setView([16.8750, 121.6000], 13);
    <?php endif; ?>
}

// Start location tracking
function startLocationTracking() {
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
    }
    
    if (!navigator.geolocation) {
        console.error("Geolocation is not supported by this browser.");
        return;
    }
    
    const options = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
    };
    
    watchId = navigator.geolocation.watchPosition(
        (position) => {
            const { latitude, longitude, accuracy, speed, heading } = position.coords;
            currentLocation = { latitude, longitude, accuracy, speed, heading };
            
            // Update location on server
            updateLocation(latitude, longitude, speed, heading, accuracy);
            
            // Update map with current location
            if (map) {
                // Remove existing location marker if any
                map.eachLayer(layer => {
                    if (layer instanceof L.Marker && layer.options && layer.options.isCurrentLocation) {
                        map.removeLayer(layer);
                    }
                });
                
                // Add new location marker
               const busIcon = L.icon({
                iconUrl: '../passenger/bus.png',
                iconColor: '#ffffff', // Icon color
                markerColor: 'blue', // Background color of the marker
                outlineColor: '#000000', // Outline color
                outlineWidth: 1,
                iconSize: [24, 24]
              
                });
                const locationMarker = L.marker([latitude, longitude],{icon:busIcon}, {isCurrentLocation: true})
                    .addTo(map)
                    .bindPopup('Your current location')
                    .openPopup();
            }
        },
        (error) => {
            console.error("Geolocation error:", error);
        },
        options
    );
}

// Update location on server
function updateLocation(latitude, longitude, speed, heading, accuracy) {
    const formData = new FormData();
    formData.append('action', 'update_location');
    formData.append('latitude', latitude);
    formData.append('longitude', longitude);
    formData.append('speed', speed || 0);
    formData.append('heading', heading || 0);
    formData.append('accuracy', accuracy || 0);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('Failed to update location:', data.error);
        }
    })
    .catch(error => {
        console.error('Error updating location:', error);
    });
}

// Update trip status
function updateTripStatus(status, tripId) {
    if (!confirm(`Are you sure you want to mark this trip as '${status}'?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_trip_status');
    formData.append('trip_id', tripId);
    formData.append('status', status);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the page to update the UI
            window.location.reload();
        } else {
            alert('Failed to update trip status');
        }
    })
    .catch(error => {
        console.error('Error updating trip status:', error);
        alert('Error updating trip status');
    });
}

// Mark passenger as boarded
function markBoarded(bookingId) {
    const formData = new FormData();
    formData.append('action', 'mark_passenger_boarded');
    formData.append('booking_id', bookingId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the page to update the UI
            window.location.reload();
        } else {
            alert('Failed to mark passenger as boarded');
        }
    })
    .catch(error => {
        console.error('Error marking passenger as boarded:', error);
        alert('Error marking passenger as boarded');
    });
}

// Cancel booking
function cancelBooking(bookingId) {
    if (!confirm('Are you sure you want to cancel this booking?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'cancel_booking');
    formData.append('booking_id', bookingId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the page to update the UI
            window.location.reload();
        } else {
            alert('Failed to cancel booking');
        }
    })
    .catch(error => {
        console.error('Error canceling booking:', error);
        alert('Error canceling booking');
    });
}

// Update passenger count with proper available seats calculation
function updatePassengerCount(change) {
    const currentCount = parseInt(document.getElementById('current-count').textContent);
    const newCount = Math.max(0, currentCount + change);
    
    // Calculate new available seats
    const newAvailableSeats = CAPACITY - TOTAL_BOOKINGS - newCount;
    
    if (newAvailableSeats < 0) {
        alert(`Cannot exceed bus capacity of ${CAPACITY} passengers. Current bookings: ${TOTAL_BOOKINGS}`);
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_passenger_count');
    formData.append('trip_id', <?php echo $active_trip ? $active_trip['trip_id'] : 0; ?>);
    formData.append('bus_id', <?php echo $active_trip ? $active_trip['bus_id'] : 0; ?>);
    formData.append('count', newCount);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update all the counters in real-time
            document.getElementById('current-count').textContent = newCount;
            document.getElementById('walkinin-count').textContent = newCount;
            document.getElementById('total-passenger-count').textContent = TOTAL_BOOKINGS + newCount;
            document.getElementById('available-seats-counter').textContent = newAvailableSeats;
            document.getElementById('available-seats-display').textContent = newAvailableSeats;
        } else {
            alert('Failed to update passenger count');
        }
    })
    .catch(error => {
        console.error('Error updating passenger count:', error);
        alert('Error updating passenger count');
    });
}

// Start a new trip
function startTrip(routeId) {
    if (!confirm('Are you sure you want to start this trip?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'start_trip');
    formData.append('route_id', routeId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the page to update the UI
            window.location.reload();
        } else {
            alert(data.error || 'Failed to start trip');
        }
    })
    .catch(error => {
        console.error('Error starting trip:', error);
        alert('Error starting trip');
    });
}

// Refresh dashboard
function refreshDashboard() {
    window.location.reload();
}

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map if on map view
    if (document.getElementById('map-view').classList.contains('active')) {
        setInterval(initMap, 1000);
    }
    
    // Check if we need to start location tracking
    <?php if ($active_trip && in_array($active_trip['status'], ['scheduled', 'boarding', 'departed', 'in_transit', 'arrived'])): ?>
    startLocationTracking();
    <?php endif; ?>
});

// Handle page visibility change
/*
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        // Refresh data when page becomes visible again
        refreshDashboard();
    }
});
*/
</script>

</body>
</html>