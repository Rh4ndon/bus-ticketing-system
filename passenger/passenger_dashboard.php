<?php

require_once "../admin/config.php";
session_start();

// ----------------------
// Session & Authentication
// ----------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: passenger_login.php");
    exit();
}

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header("Location: passenger_login.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

  // Set timezone to Manila, Philippines
    date_default_timezone_set('Asia/Manila');

    // Get current date in Manila timezone
    $travelDate = date('Y-m-d');

// ----------------------
// Handle AJAX requests
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        // --------- Book Bus ----------
        case 'book_bus':
            $trip_id = intval($_POST['trip_id']);
            $bus_id = intval($_POST['bus_id']);
            $route_id = intval($_POST['route_id']);
            $travel_date = $travelDate;
            $departure_time = sanitizeInput($_POST['departure_time']);
            $fare = floatval($_POST['fare']);

            // Check if user already has an active booking for this date
            $existing_booking = $conn->prepare("
                SELECT booking_id FROM bookings 
                WHERE user_id = ? AND travel_date = ? AND booking_status IN ('pending', 'confirmed')
            ");
            $existing_booking->execute([$user_id, $travel_date]);
            
            if ($existing_booking->rowCount() > 0) {
                echo json_encode(['success' => false, 'error' => 'You already have an active booking for this date. You can only have one booking at a time.']);
                exit();
            }

            // Check if bus still has available seats (only count confirmed bookings)
            $seat_check = $conn->prepare("
                SELECT b.capacity - COALESCE((
                    SELECT COUNT(*) 
                    FROM bookings bk 
                    WHERE bk.bus_id = b.bus_id 
                    AND bk.travel_date = ? 
                    AND bk.booking_status = 'confirmed'
                ), 0) as available_seats
                FROM buses b
                WHERE b.bus_id = ?
            ");
            $seat_check->execute([$travel_date, $bus_id]);
            $available_seats = $seat_check->fetchColumn();

            if ($available_seats <= 0) {
                echo json_encode(['success' => false, 'error' => 'Bus is now full. Please try another bus.']);
                exit();
            }

            // Find the next available seat number (only count confirmed bookings for seat assignment)
            $seat_stmt = $conn->prepare("
                SELECT COALESCE(MAX(seat_number), 0) + 1 as next_seat
                FROM bookings 
                WHERE bus_id = ? AND travel_date = ? AND booking_status = 'confirmed'
            ");
            $seat_stmt->execute([$bus_id, $travel_date]);
            $seat_number = $seat_stmt->fetchColumn();

            // Create booking with pending status (waiting for driver acceptance)
            $booking_ref = generateBookingReference();
            $booking_stmt = $conn->prepare("
                INSERT INTO bookings (
                    booking_reference, user_id, bus_id, route_id, trip_id, 
                    seat_number, travel_date, departure_time, fare, 
                    payment_status, booking_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
            ");

            if ($booking_stmt->execute([$booking_ref, $user_id, $bus_id, $route_id, $trip_id, $seat_number, $travel_date, $departure_time, $fare])) {
                echo json_encode([
                    'success' => true, 
                    'booking_reference' => $booking_ref, 
                    'message' => 'Booking request sent! Waiting for driver acceptance.',
                    'status' => 'pending'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to create booking']);
            }
            exit();

        // --------- Cancel Booking ----------
        case 'cancel_booking':
            $booking_id = intval($_POST['booking_id']);

            $verify_stmt = $conn->prepare("
                SELECT b.*, at.status as trip_status 
                FROM bookings b
                LEFT JOIN active_trips at ON b.trip_id = at.trip_id
                WHERE b.booking_id = ? AND b.user_id = ?
            ");
            $verify_stmt->execute([$booking_id, $user_id]);
            $booking = $verify_stmt->fetch();

            if (!$booking) {
                echo json_encode(['success' => false, 'error' => 'Booking not found']);
                exit();
            }

            // Prevent cancellation if trip is in transit
            if ($booking['trip_status'] === 'in_transit') {
                echo json_encode(['success' => false, 'error' => 'Cannot cancel - trip is already in transit']);
                exit();
            }

            // Allow cancellation for all statuses except completed trips and in_transit
            if ($booking['booking_status'] === 'completed') {
                echo json_encode(['success' => false, 'error' => 'Cannot cancel - trip has already been completed']);
                exit();
            }

            $cancel_stmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', updated_at = NOW() WHERE booking_id = ?");
            echo json_encode($cancel_stmt->execute([$booking_id]) ? ['success' => true, 'message' => 'Booking cancelled successfully'] : ['success' => false, 'error' => 'Failed to cancel booking']);
            exit();

            // Allow cancellation for all statuses except completed trips
            if ($booking['booking_status'] === 'completed') {
                echo json_encode(['success' => false, 'error' => 'Cannot cancel - trip has already been completed']);
                exit();
            }

            $cancel_stmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled', updated_at = NOW() WHERE booking_id = ?");
            echo json_encode($cancel_stmt->execute([$booking_id]) ? ['success' => true, 'message' => 'Booking cancelled successfully'] : ['success' => false, 'error' => 'Failed to cancel booking']);
            exit();

                    // --------- Verify Bus QR Code ----------
        case 'verify_bus_qr':
            $scanned_bus_number = sanitizeInput($_POST['bus_number']);
            $booking_id = intval($_POST['booking_id']);
            
            // Get booking details
            $booking_stmt = $conn->prepare("
                SELECT b.*, bus.bus_number 
                FROM bookings b 
                JOIN buses bus ON b.bus_id = bus.bus_id 
                WHERE b.booking_id = ? AND b.user_id = ?
            ");
            $booking_stmt->execute([$booking_id, $user_id]);
            $booking = $booking_stmt->fetch();
            
            if (!$booking) {
                echo json_encode(['success' => false, 'error' => 'Booking not found']);
                exit();
            }
            
            // Check if booking is already confirmed
            if ($booking['booking_status'] === 'confirmed') {
                echo json_encode(['success' => true, 'message' => 'Booking already confirmed']);
                exit();
            }
            
            // Check if scanned bus number matches booking bus number
            if ($scanned_bus_number !== $booking['bus_number']) {
                echo json_encode(['success' => false, 'error' => 'Invalid bus. Please scan the correct bus QR code.']);
                exit();
            }
            
            // Update booking status to confirmed
            $update_stmt = $conn->prepare("UPDATE bookings SET booking_status = 'confirmed', updated_at = NOW() WHERE booking_id = ?");
            
            if ($update_stmt->execute([$booking_id])) {
                echo json_encode(['success' => true, 'message' => 'Boarding confirmed! Enjoy your ride.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to confirm booking']);
            }
            exit();

        // --------- Update Profile ----------
        case 'update_profile':
            $full_name = sanitizeInput($_POST['full_name']);
            $email = sanitizeInput($_POST['email']);
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];

            $verify_stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $verify_stmt->execute([$user_id]);
            $user_data = $verify_stmt->fetch();

            if (!verifyPassword($current_password, $user_data['password'])) {
                echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
                exit();
            }

            if (!empty($new_password)) {
                $hashed_password = hashPassword($new_password);
                $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ?, updated_at = NOW() WHERE user_id = ?");
                $update_stmt->execute([$full_name, $email, $hashed_password, $user_id]);
            } else {
                $update_stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE user_id = ?");
                $update_stmt->execute([$full_name, $email, $user_id]);
            }

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            exit();

        // --------- Track Bus ----------
        case 'track_bus':
            $bus_id = intval($_POST['bus_id']);
            
            $track_stmt = $conn->prepare("
                SELECT bl.*, b.bus_number, r.route_name, r.origin, r.destination
                FROM bus_locations bl
                JOIN buses b ON bl.bus_id = b.bus_id
                LEFT JOIN active_trips at ON b.bus_id = at.bus_id AND at.status NOT IN ('completed', 'cancelled')
                LEFT JOIN routes r ON at.route_id = r.route_id
                WHERE bl.bus_id = ?
                ORDER BY bl.updated_at DESC
                LIMIT 1
            ");
            $track_stmt->execute([$bus_id]);
            $bus_data = $track_stmt->fetch();
            
            if ($bus_data && $bus_data['latitude'] && $bus_data['longitude']) {
                echo json_encode([
                    'success' => true, 
                    'data' => [
                        'latitude' => (float)$bus_data['latitude'],
                        'longitude' => (float)$bus_data['longitude'],
                        'bus_number' => $bus_data['bus_number'],
                        'route_name' => $bus_data['route_name'],
                        'updated_at' => $bus_data['updated_at']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Bus location not available']);
            }
            exit();

        // --------- Update Passenger Location ----------
        case 'update_location':
            $lat = floatval($_POST['lat']);
            $lng = floatval($_POST['lng']);
            
            // Get user's active booking
            $booking_stmt = $conn->prepare("
                SELECT booking_id, bus_id, booking_reference
                FROM bookings 
                WHERE user_id = ? 
                AND travel_date = ? 
                AND booking_status = 'confirmed'
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $booking_stmt->execute([$user_id, $travelDate]);
            $booking = $booking_stmt->fetch();
            
            if ($booking) {
              $location_stmt = $conn->prepare("
    INSERT INTO passenger_locations (user_id, booking_id, bus_id, booking_reference, latitude, longitude, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE 
    user_id = VALUES(user_id),
    booking_id = VALUES(booking_id),
    bus_id = VALUES(bus_id),
    booking_reference = VALUES(booking_reference),
    latitude = VALUES(latitude), 
    longitude = VALUES(longitude),
    updated_at = NOW()
");
$location_stmt->execute([$user_id, $booking['booking_id'], $booking['bus_id'], $booking['booking_reference'], $lat, $lng]);
echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No active booking found']);
            }
            exit();
    }
}

// ----------------------
// Fetch Data for Dashboard
// ----------------------

// Get user info
$user_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

  

    
  
// Check if user has an active booking today
$active_booking_stmt = $conn->prepare("
    SELECT b.*, at.trip_id, at.bus_id, at.status as trip_status, bus.bus_number, r.route_name, r.origin, r.destination,
           r.origin_lat, r.origin_lng, r.dest_lat, r.dest_lng
    FROM bookings b
    LEFT JOIN active_trips at ON b.trip_id = at.trip_id
    JOIN buses bus ON b.bus_id = bus.bus_id
    JOIN routes r ON b.route_id = r.route_id
    WHERE b.user_id = ? 
    AND b.travel_date = ? 
    AND b.booking_status IN ('pending', 'confirmed')
    ORDER BY b.created_at DESC 
    LIMIT 1
");
$active_booking_stmt->execute([$user_id, $travelDate]);
$active_booking = $active_booking_stmt->fetch();

// Get the active trip details if exists
$active_trip = null;
if ($active_booking && isset($active_booking['trip_id'])) {
    $trip_stmt = $conn->prepare("
        SELECT * FROM active_trips 
        WHERE trip_id = ? AND status NOT IN ('completed', 'cancelled')
    ");
    $trip_stmt->execute([$active_booking['trip_id']]);
    $active_trip = $trip_stmt->fetch();
}

// Get user's bookings with updated trip status
$bookings_stmt = $conn->prepare("
    SELECT b.*, r.route_name, r.origin, r.destination, bus.bus_number,
           at.status as trip_status, at.scheduled_departure as trip_departure
    FROM bookings b
    JOIN routes r ON b.route_id = r.route_id
    JOIN buses bus ON b.bus_id = bus.bus_id
    LEFT JOIN active_trips at ON b.trip_id = at.trip_id
    WHERE b.user_id = ?
    ORDER BY b.travel_date DESC, b.departure_time DESC
    LIMIT 10
");
$bookings_stmt->execute([$user_id]);
$bookings = $bookings_stmt->fetchAll();

// Update booking status based on trip status
foreach ($bookings as $key => $booking) {
    if ($booking['trip_status'] === 'completed' && $booking['booking_status'] === 'confirmed') {
        $update_stmt = $conn->prepare("UPDATE bookings SET booking_status = 'completed' WHERE booking_id = ?");
        $update_stmt->execute([$booking['booking_id']]);
        $bookings[$key]['booking_status'] = 'completed';
    }
}

// Get booking stats
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as active_bookings,
        SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
        SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) as pending_bookings
    FROM bookings 
    WHERE user_id = ?
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch();

// Available buses today (only buses that are on trip)
$today = date('Y-m-d');
$today_buses_stmt = $conn->prepare("
    SELECT at.*, b.bus_number, b.capacity, r.route_name, r.origin, r.destination, r.fare, 
           r.origin_lat, r.origin_lng, r.dest_lat, r.dest_lng,
           (b.capacity - COALESCE((
                SELECT COUNT(*) 
                FROM bookings bk 
                WHERE bk.bus_id = b.bus_id 
                AND bk.travel_date = ? 
                AND bk.booking_status = 'confirmed'
            ), 0)) as available_seats,
           COALESCE((
                SELECT COUNT(*) 
                FROM bookings bk 
                WHERE bk.bus_id = b.bus_id 
                AND bk.travel_date = ? 
                AND bk.booking_status = 'pending'
            ), 0) as pending_bookings
    FROM active_trips at
    JOIN buses b ON at.bus_id = b.bus_id
    JOIN routes r ON at.route_id = r.route_id
    WHERE DATE(at.scheduled_departure) = ? 
    AND at.status IN ('scheduled', 'boarding', 'departed', 'in_transit')
    HAVING available_seats > 0
    ORDER BY at.scheduled_departure ASC
    LIMIT 5
");
$today_buses_stmt->execute([$today, $today, $today]);
$today_buses = $today_buses_stmt->fetchAll();

// Check if user already has a booking today
$has_active_booking_today = $active_booking ? true : false;
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo SITE_NAME; ?> - Passenger Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.2.0/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; 
            background: #f5f5f5;
            min-height: 100vh;
            padding-bottom: 80px;
            color: #333;
        }
        
        /* Header */
        .header {
            background: #fff;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logout-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }
        
        /* Content Sections */
        .content-section { 
            display: none; 
            padding: 15px;
        }
        .content-section.active { display: block; }
        
        /* Mobile App Style Elements */
        .section {
            background: #fff;
            margin: 10px 0;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .section h3 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        /* Bus List */
        .bus-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .bus-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #2196f3;
        }
        
        .bus-item.full {
            border-left-color: #f44336;
            opacity: 0.7;
        }
        
        .bus-item.active {
            border-left-color: #4caf50;
            background: #f0f9f0;
        }
        
        .bus-item.pending {
            border-left-color: #ff9800;
            background: #fffaf0;
        }
        
        .bus-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .bus-info h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .bus-route {
            color: #666;
            font-size: 14px;
        }
        
        .bus-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .detail-item {
            text-align: center;
            padding: 8px;
            background: rgba(255,255,255,0.7);
            border-radius: 8px;
            font-size: 14px;
        }
        
        .detail-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-confirmed { background: #e8f5e8; color: #388e3c; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        .status-completed { background: #e0f2f1; color: #00695c; }
        .status-on_trip { background: #e3f2fd; color: #1565c0; }
        .status-boarding { background: #fff8e1; color: #ff8f00; }
        .status-scheduled { background: #f3e5f5; color: #7b1fa2; }
        .status-departed { background: #e1f5fe; color: #0277bd; }
        .status-in_transit { background: #e8f5e8; color: #2e7d32; }
        .status-arrived { background: #fff3e0; color: #ef6c00; }
        
        /* Bookings List */
        .booking-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #2196f3;
        }
        
        .booking-item.pending {
            border-left-color: #ff9800;
            background: #fffaf0;
        }
        
        .booking-info h4 {
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .booking-info p {
            color: #666;
            font-size: 14px;
            margin: 3px 0;
        }
        
        .booking-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        /* Pending notice */
        .pending-notice {
            padding: 10px;
            text-align: center;
            background: #fff3cd;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* Map */
        #tracking-map { 
            height: 300px; 
            width: 100%; 
            border-radius: 12px; 
            margin-bottom: 15px;
        }
        
        .map-info {
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .map-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .map-info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .map-info-label {
            font-weight: bold;
            color: #333;
        }
        
        /* Bottom Navigation */
        .nav-bottom {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            display: flex;
            border-top: 1px solid #ddd;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .nav-bottom button {
            flex: 1;
            padding: 12px 10px;
            border: none;
            background: none;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            transition: all 0.3s ease;
            color: #666;
        }
        
        .nav-bottom button.active {
            color: #2196f3;
        }
        
        .nav-bottom button i {
            font-size: 20px;
        }
        
        /* Notifications */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 1001;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            max-width: 80%;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success { background: #4caf50; }
        .notification.error { background: #f44336; }
        .notification.info { background: #2196f3; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px 15px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            width: 100%;
            justify-content: center;
            margin: 5px 0;
        }
        
        .btn-primary { background: #2196f3; color: white; }
        .btn-success { background: #4caf50; color: white; }
        .btn-warning { background: #ff9800; color: white; }
        .btn-danger { background: #f44336; color: white; }
        
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        
        .btn:disabled { 
            opacity: 0.6; 
            cursor: not-allowed; 
            transform: none;
            pointer-events: none;
        }
        
        /* Loading animation */
        .fa-spin {
            animation: fa-spin 1s infinite linear;
        }
        
        @keyframes fa-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* History Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .stat-item {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2196f3;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        
        /* Responsive */
        @media (min-width: 768px) {
            body {
                max-width: 500px;
                margin: 0 auto;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            
            .bus-details {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .booking-actions {
                flex-wrap: nowrap;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <h1><i class="fas fa-bus"></i> <?php echo SITE_NAME; ?></h1>
</div>

<!-- HOME SECTION -->
<div class="content-section active" id="home">
    <?php
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
    if ($has_active_booking_today && $active_booking): ?>
    <div class="section">
        <h3><i class="fas fa-bus"></i> Your Booking Today</h3>
        <div class="bus-item <?php echo $active_booking['booking_status'] === 'confirmed' ? 'active' : 'pending'; ?>">
            <div class="bus-header">
                <div class="bus-info">
                    <h4><?php echo isset($active_booking['route_name']) ? htmlspecialchars($active_booking['route_name']) : 'Unknown Route'; ?></h4>
                    <p class="bus-route"><?php echo isset($active_booking['origin']) ? htmlspecialchars($active_booking['origin']) : 'Unknown'; ?> <?php echo isset($active_booking['destination']) ? htmlspecialchars($active_booking['destination']) : 'Unknown'; ?></p>
                </div>
                
            
            <?php if ($active_booking['booking_status'] === 'confirmed'): ?>
            <button class="btn btn-primary" onclick="switchTab('track')">
                <i class="fas fa-map"></i> Track Bus
            </button>
            <?php elseif ($active_booking['booking_status'] === 'pending'): ?>
            <div class="pending-notice">
                <i class="fas fa-clock"></i> Waiting for driver acceptance
            </div>
            <button class="btn btn-danger" onclick="cancelBooking(<?php echo $active_booking['booking_id']; ?>)">
                <i class="fas fa-times"></i> Cancel Request
            </button>
            <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="section">
        <h3><i class="fas fa-bus"></i> Available Buses Today <?php
                // Set timezone to Manila, Philippines
                date_default_timezone_set('Asia/Manila');

                // Get current date in Manila timezone
                $travelDate = date('Y-m-d');

                // Output the date
                echo $travelDate;
                ?></h3>
        <?php if ($today_buses): ?>
        <div class="bus-list">
            <?php foreach ($today_buses as $bus): 
                $isFull = $bus['available_seats'] <= 0;
                $hasBooking = $has_active_booking_today;
                $pendingCount = isset($bus['pending_bookings']) ? $bus['pending_bookings'] : 0;
            ?>
            <div class="bus-item <?php echo $isFull ? 'full' : ''; ?> <?php echo $hasBooking ? 'active' : ''; ?>">
                <div class="bus-header">
                    <div class="bus-info">
                        <h4><?php echo htmlspecialchars($bus['route_name']); ?></h4>
                        <p class="bus-route"><?php echo htmlspecialchars($bus['origin']); ?><?php echo htmlspecialchars($bus['destination']); ?></p>
                    </div>
                    <div class="status-badge status-<?php echo $bus['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $bus['status'])); ?>
                    </div>
                </div>
                <div class="bus-details">
                    <div class="detail-item">
                        <div class="detail-value"><?php echo date('g:i A', strtotime($bus['scheduled_departure'])); ?></div>
                        <div class="detail-label">Departure</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-value" id="seats-<?php echo $bus['bus_id']; ?>"><?php echo $bus['available_seats']; ?></div>
                        <div class="detail-label">Seats Left</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-value"><?php echo number_format($bus['fare'], 2); ?></div>
                        <div class="detail-label">Fare</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-value"><?php echo $bus['bus_number']; ?></div>
                        <div class="detail-label">Bus No.</div>
                    </div>
                </div>
                
                <?php if ($pendingCount > 0): ?>
                <div style="padding: 8px; text-align: center; background: #fff3cd; border-radius: 8px; margin: 10px 0; font-size: 14px; color: #856404;">
                    <i class="fas fa-clock"></i> <?php echo $pendingCount; ?> pending request(s)
                </div>
                <?php endif; ?>
                
                <?php if (!$isFull && !$hasBooking): ?>
                <button class="btn btn-success" onclick="bookBus(<?php echo $bus['trip_id']; ?>, <?php echo $bus['bus_id']; ?>, <?php echo $bus['route_id']; ?>, '<?php echo $bus['scheduled_departure']; ?>', <?php echo $bus['fare']; ?>)">
                    <i class="fas fa-ticket-alt"></i> Book Now
                </button>
                <?php elseif ($isFull): ?>
                <div style="padding:10px; text-align:center; color:#c62828;">
                    <i class="fas fa-times-circle"></i> FULLY BOOKED
                </div>
                <?php else: ?>
                <div style="padding:10px; text-align:center; color:#856404;">
                    <i class="fas fa-info-circle"></i> You have an active booking
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bus"></i>
            <p>No buses available for today.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- BOOKINGS SECTION -->
<div class="content-section" id="bookings">
    <div class="section">
        <h3><i class="fas fa-history"></i> Bookings</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value"><?php echo $stats['total_bookings']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $stats['active_bookings']; ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $stats['pending_bookings']; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3><i class="fas fa-ticket-alt"></i> My Bookings</h3>
        
        <?php if ($bookings): ?>
        <div class="booking-list">
            <?php foreach ($bookings as $booking): ?>
            <div class="booking-item <?php echo $booking['booking_status'] === 'pending' ? 'pending' : ''; ?>">
                <div class="booking-info">
                    <h4><?php echo htmlspecialchars($booking['route_name']); ?></h4>
                    <p><i class="fas fa-route"></i> <?php echo htmlspecialchars($booking['origin']); ?> <?php echo htmlspecialchars($booking['destination']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($booking['travel_date'])); ?> at <?php echo date('g:i A', strtotime($booking['departure_time'])); ?></p>
                    <p><i class="fas fa-bus"></i> Bus: <?php echo htmlspecialchars($booking['bus_number']); ?> | Seat: <?php echo $booking['seat_number']; ?></p>
                    <p><i class="fas fa-receipt"></i> Ref: <?php echo htmlspecialchars($booking['booking_reference']); ?></p>
                    <p><i class="fas fa-money-bill"></i> Fare:<?php echo number_format($booking['fare'], 2); ?></p>
                </div>
                <div class="booking-actions">
                    <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                        <?php echo ucfirst($booking['booking_status']); ?>
                    </span>
                <?php if (
                    $booking['booking_status'] === 'pending' || 
                    (
                        $booking['booking_status'] === 'confirmed' && 
                        (
                            empty($booking['trip_status']) || 
                            !in_array($booking['trip_status'], ['departed','in_transit','arrived','completed'])
                        )
                    )
                ): ?>
                <button class="btn btn-danger" onclick="cancelBooking(<?php echo $booking['booking_id']; ?>)">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-ticket-alt"></i>
            <p>You haven't made any bookings yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- TRACK SECTION -->
<div class="content-section" id="track">
    <div class="section">
        <h3><i class="fas fa-map-marker-alt"></i> Track Your Bus</h3>
        
        <?php if ($active_booking && $active_booking['booking_status'] === 'confirmed'): ?>
        <div id="tracking-map"></div>
        
        <div class="map-info">
            <div class="map-info-item">
                <span class="map-info-label">Bus Number:</span>
                <span><?php echo htmlspecialchars($active_booking['bus_number']); ?></span>
            </div>
            <div class="map-info-item">
                <span class="map-info-label">Route:</span>
                <span><?php echo htmlspecialchars($active_booking['route_name']); ?></span>
            </div>
            <div class="map-info-item">
                <span class="map-info-label">Status:</span>
                <span class="status-badge status-<?php echo $active_booking['trip_status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $active_booking['trip_status'])); ?>
                </span>
            </div>
            <div class="map-info-item">
                <span class="map-info-label">Last Updated:</span>
                <span id="last-updated">Just now</span>
            </div>
        </div>
        
        <button class="btn btn-primary" onclick="refreshLocation()">
            <i class="fas fa-sync-alt"></i> Refresh Location
        </button>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-map-marker-alt"></i>
            <p>No active booking to track.</p>
            <p>Book a bus first to track its location.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- PROFILE SECTION -->
<div class="content-section" id="profile">
    <div class="section">
        <h3><i class="fas fa-user"></i> My Profile</h3>
        
        <form id="profile-form">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Full Name</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;" required>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;" required>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Current Password</label>
                <input type="password" name="current_password" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;" required>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: bold;">New Password (leave blank to keep current)</label>
                <input type="password" name="new_password" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Profile
            </button>
        </form>
    </div>

        <a href="passenger_logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- QR SCANNER SECTION -->
<div class="content-section" id="qr-scanner">
    <div class="section">
        <h3><i class="fas fa-qrcode"></i> Scan Bus QR Code</h3>
        
        <?php if ($active_booking && in_array($active_booking['booking_status'], ['pending', 'confirmed'])): ?>
        <div style="text-align: center; margin-bottom: 20px;">
            <p>Scan QR code to confirm boarding</p>
        </div>
        
        <!-- QR Scanner Container -->
        <div id="qr-scanner-container">
            <div id="my-qr-reader" style="width: 100%;"></div>
            <div id="qr-scan-result" style="margin-top: 15px; text-align: center;"></div>
            
            <!-- Camera permission prompt (initially hidden) -->
            <div id="camera-permission-prompt" style="display: none; text-align: center; padding: 20px; background: #f8f9fa; border-radius: 8px; margin-top: 15px;">
                <i class="fas fa-camera" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;"></i>
                <h4>Camera Access Required</h4>
                <p>Please allow camera access to scan QR codes</p>
                <button class="btn btn-primary" onclick="initQRScanner()">
                    <i class="fas fa-camera"></i> Enable Camera
                </button>
            </div>
        </div>
        
        <div class="map-info" style="margin-top: 20px;">
            <div class="map-info-item">
                <span class="map-info-label">Your Booking:</span>
                <span><?php echo htmlspecialchars($active_booking['route_name']); ?></span>
            </div>
            <div class="map-info-item">
                <span class="map-info-label">Bus Number:</span>
                <span><?php echo htmlspecialchars($active_booking['bus_number']); ?></span>
            </div>
            <div class="map-info-item">
                <span class="map-info-label">Status:</span>
                <span id="bookingStatus" class="status-badge status-<?php echo $active_booking['booking_status']; ?>">
                    <?php echo ucfirst($active_booking['booking_status']); ?>
                </span>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-qrcode"></i>
            <p>No active booking to scan.</p>
            <p>You need a pending or confirmed booking to scan bus QR codes.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bottom Navigation -->
<div class="nav-bottom">
    <button class="active" onclick="switchTab('home')">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </button>
    <button onclick="switchTab('bookings')">
        <i class="fas fa-history"></i>
        <span>Bookings</span>
    </button>
    <button onclick="switchTab('track')">
        <i class="fas fa-map"></i>
        <span>Track</span>
    </button>
    <button onclick="switchTab('qr-scanner')">
        <i class="fas fa-qrcode"></i>
        <span>Scan</span>
    </button>
    <button onclick="switchTab('profile')">
        <i class="fas fa-user"></i>
        <span>Profile</span>
    </button>
</div>


<!-- Notification -->
<div id="notification" class="notification"></div>

<script src="https://unpkg.com/leaflet@1.2.0/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
// Global variables
let map, routingControl, locationInterval;
let userLocation = null;
let html5QrcodeScanner = null;

// DOM ready function from reference
       function domReady(fn) {
            if (
                document.readyState === "complete" ||
                document.readyState === "interactive"
            ) {
                setTimeout(fn, 1000);
            } else {
                document.addEventListener("DOMContentLoaded", fn);
            }
        }

        function onScanSuccess(decodeText, decodeResult) {
    // Get the booking ID from PHP session (available in your dashboard)
    const bookingId = <?php echo $active_booking ? $active_booking['booking_id'] : 'null'; ?>;
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'verify_bus_qr');
    formData.append('bus_number', decodeText);
    formData.append('booking_id', bookingId);

    fetch('get_boarded.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showNotification(result.message, 'success');
            // Update UI to show confirmed status
            if (document.getElementById('bookingStatus')) {
                document.getElementById('bookingStatus').textContent = 'confirmed';
                document.getElementById('bookingStatus').className = 'status-badge status-confirmed';
            }
            // Optionally reload the page after a delay to reflect changes
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else if (result.error) {
            showNotification(result.error, 'error');
        } else {
            console.error('Unexpected response:', result);
            showNotification('Invalid response from server', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Network error. Please try again.', 'error');
    });
}

// Initialize the scanner when the QR tab is active
function initQRScanner() {
    // Check if scanner is already initialized
    if (window.htmlScanner) return;
    
    // Check if user has an active booking
    const bookingId = <?php echo $active_booking ? $active_booking['booking_id'] : 'null'; ?>;
    if (!bookingId) {
        showNotification('No active booking found. Please make a booking first.', 'error');
        return;
    }
    
    // Initialize the scanner
    window.htmlScanner = new Html5QrcodeScanner(
        "my-qr-reader", {
            fps: 10,
            qrbox: 250
        }
    );
    window.htmlScanner.render(onScanSuccess);
}

// Tab switching
function switchTab(tabId) {
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });
    document.getElementById(tabId).classList.add('active');
    
    document.querySelectorAll('.nav-bottom button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.nav-bottom button:nth-child(${getTabIndex(tabId)})`).classList.add('active');
    
    // Initialize map when track tab is opened
    if (tabId === 'track' && document.getElementById('tracking-map')) {
        initMap();
    }

        // Initialize QR scanner when scan tab is opened
    if (tabId === 'qr-scanner') {
        // Small delay to ensure the tab is visible before initializing scanner
        setTimeout(initQRScanner, 300);
    } else {
        // Clean up scanner when leaving the QR tab
        if (window.htmlScanner) {
            try {
                window.htmlScanner.clear();
                delete window.htmlScanner;
            } catch (e) {
                console.error('Error clearing scanner:', e);
            }
        }
    }
}

function getTabIndex(tabId) {
    const tabs = ['home', 'bookings', 'track', 'qr-scanner', 'profile'];
    return tabs.indexOf(tabId) + 1;
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = `notification ${type}`;
    notification.classList.add('show');
    
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// Book bus function
function bookBus(tripId, busId, routeId, departureTime, fare) {
    if (!confirm(`Are you sure you want to book this bus?\nFare: â‚±${parseFloat(fare).toFixed(2)}`)) {
        return;
    }
    
    const travelDate = <?php
                // Set timezone to Manila, Philippines
                date_default_timezone_set('Asia/Manila');

                // Get current date in Manila timezone
                $travelDate = date('Y-m-d');

                // Output the date
                echo $travelDate;
                ?>;
   
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'book_bus',
            'trip_id': tripId,
            'bus_id': busId,
            'route_id': routeId,
            'travel_date': travelDate,
            'departure_time': departureTime,
            'fare': fare
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Update available seats count
            const seatsElement = document.getElementById(`seats-${busId}`);
            if (seatsElement) {
                seatsElement.textContent = parseInt(seatsElement.textContent) - 1;
            }
            // Reload page after 2 seconds to show updated booking status
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification(data.error || 'Failed to book bus', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while booking', 'error');
    });
}

// Cancel booking function
function cancelBooking(bookingId) {
    if (!confirm('Are you sure you want to cancel this booking?')) {
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'cancel_booking',
            'booking_id': bookingId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            // Reload page after 2 seconds to show updated booking status
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification(data.error || 'Failed to cancel booking', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while cancelling', 'error');
    });
}

// Initialize map for tracking
function initMap() {
    if (!document.getElementById('tracking-map')) return;
    
    // Clear existing map if any
    if (map) {
        map.remove();
        if (routingControl) {
            map.removeControl(routingControl);
        }
    }
    
    // Initialize map centered on a default location
    map = L.map('tracking-map').setView([14.5995, 120.9842], 13); // Default to Manila
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    <?php if ($active_booking && $active_booking['booking_status'] === 'confirmed'): ?>
    // Try to get user's current location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                userLocation = [position.coords.latitude, position.coords.longitude];
                updateBusLocation();
            },
            function(error) {
                console.error('Geolocation error:', error);
                updateBusLocation();
            }
        );
    } else {
        updateBusLocation();
    }
    <?php endif; ?>
}

// Update bus location on the map
function updateBusLocation() {
    <?php if ($active_booking && $active_booking['booking_status'] === 'confirmed'): ?>
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'action': 'track_bus',
            'bus_id': <?php echo $active_booking['bus_id']; ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const busLocation = [data.data.latitude, data.data.longitude];
            
            // Clear existing markers
            map.eachLayer(layer => {
                if (layer instanceof L.Marker) {
                    map.removeLayer(layer);
                }
            });
            
            // Add bus marker
            const busIcon = L.icon({
                iconUrl: 'bus.png',
                iconColor: '#ffffff', // Icon color
                markerColor: 'blue', // Background color of the marker
                outlineColor: '#000000', // Outline color
                outlineWidth: 1,
                iconSize: [24, 24]
              
            });
            
            const busMarker = L.marker(busLocation, {icon: busIcon})
                .addTo(map)
                .bindPopup(`<b>Bus ${data.data.bus_number}</b><br>${data.data.route_name}`)
                .openPopup();
            
            // Add user marker if location is available
            if (userLocation) {
                const userIcon = L.icon({
                    iconUrl: 'passenger.png',
                     iconColor: '#ffffff', // Icon color
                markerColor: 'blue', // Background color of the marker
                outlineColor: '#000000', // Outline color
                outlineWidth: 1,
                iconSize: [24, 24]
                });
                
                L.marker(userLocation, {icon: userIcon})
                    .addTo(map)
                    .bindPopup('<b>Your Location</b>')
                    .openPopup();
                
                // Add route between user and bus
                if (routingControl) {
                    map.removeControl(routingControl);
                }
                
                routingControl = L.Routing.control({
                    waypoints: [
                        L.latLng(userLocation[0], userLocation[1]),
                        L.latLng(busLocation[0], busLocation[1])
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
            }
            
            // Update last updated time
            document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
            
            // Fit map to show both markers
            if (userLocation) {
                const bounds = L.latLngBounds([busLocation, userLocation]);
                map.fitBounds(bounds, {padding: [50, 50]});
            } else {
                map.setView(busLocation, 15);
            }
        } else {
            showNotification(data.error || 'Unable to track bus location', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error tracking bus location', 'error');
    });
    <?php endif; ?>
}

// Refresh location manually
function refreshLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                userLocation = [position.coords.latitude, position.coords.longitude];
                
                // Send location to server
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'update_location',
                        'lat': position.coords.latitude,
                        'lng': position.coords.longitude
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateBusLocation();
                        showNotification('Location updated successfully', 'success');
                    } else {
                        showNotification(data.error || 'Failed to update location', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error updating location', 'error');
                });
            },
            function(error) {
                console.error('Geolocation error:', error);
                showNotification('Unable to get your location', 'error');
            }
        );
    } else {
        showNotification('Geolocation is not supported by your browser', 'error');
    }
}

// Profile form submission
document.getElementById('profile-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_profile');
    
    fetch('', {
        method: 'POST',
        body: new URLSearchParams(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
        } else {
            showNotification(data.error || 'Failed to update profile', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while updating profile', 'error');
    });
});

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    // Set up periodic location updates if on track tab
    if (document.getElementById('track').classList.contains('active')) {
        initMap();
        locationInterval = setInterval(updateBusLocation, 30000); // Update every 30 seconds
    }
    
    // Clear interval when leaving track tab
    document.querySelectorAll('.nav-bottom button').forEach(btn => {
        btn.addEventListener('click', function() {
            if (locationInterval) {
                clearInterval(locationInterval);
                locationInterval = null;
            }
        });
    });
});
</script>

</body>
</html>