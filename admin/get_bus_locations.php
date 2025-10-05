<?php
// get_bus_locations.php - API endpoint for fetching real-time bus locations
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once 'config.php';

// Only allow AJAX requests
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    // Allow direct access for development/testing
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request method']);
        exit;
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get active bus locations with driver and status information
    $stmt = $conn->prepare("
        SELECT 
            b.bus_id,
            b.bus_number,
            b.plate_number,
            b.status,
            bl.latitude,
            bl.longitude,
            bl.updated_at as last_update,
            d.full_name as driver_name,
            d.driver_code,
            COALESCE(bl.speed, 0) as speed,
            CASE 
                WHEN bl.updated_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'online'
                ELSE 'offline'
            END as tracking_status
        FROM buses b
        LEFT JOIN bus_locations bl ON b.bus_id = bl.bus_id
        LEFT JOIN drivers d ON b.bus_id = d.assigned_bus_id AND d.status = 'active'
        WHERE b.status IN ('available', 'on_trip')
        AND bl.latitude IS NOT NULL 
        AND bl.longitude IS NOT NULL
        ORDER BY bl.updated_at DESC
    ");
    
    $stmt->execute();
    $busLocations = $stmt->fetchAll();
    
    // Format the response
    $response = [];
    foreach ($busLocations as $bus) {
        $response[] = [
            'bus_id' => (int)$bus['bus_id'],
            'bus_number' => $bus['bus_number'],
            'plate_number' => $bus['plate_number'],
            'status' => $bus['status'],
            'latitude' => (float)$bus['latitude'],
            'longitude' => (float)$bus['longitude'],
            'last_update' => $bus['last_update'],
            'driver_name' => $bus['driver_name'],
            'driver_code' => $bus['driver_code'],
            'speed' => (float)$bus['speed'],
            'tracking_status' => $bus['tracking_status']
        ];
    }
    
    // Add metadata
    $metadata = [
        'total_buses' => count($response),
        'online_buses' => count(array_filter($response, function($bus) {
            return $bus['tracking_status'] === 'online';
        })),
        'last_updated' => date('Y-m-d H:i:s'),
        'route_bounds' => [
            'san_mateo' => ['lat' => 16.9167, 'lng' => 121.5833],
            'cauayan' => ['lat' => 16.9272, 'lng' => 121.7708]
        ]
    ];
    
    $finalResponse = [
        'success' => true,
        'data' => $response,
        'metadata' => $metadata
    ];
    
    echo json_encode($finalResponse);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch bus locations',
        'message' => $e->getMessage()
    ]);
}
?>