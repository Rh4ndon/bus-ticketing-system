<?php
// bus_assignment_functions.php - Bus assignment related functions
require_once 'config.php';

/**
 * Assign a bus to a route
 * @param int $route_id - The route ID to assign bus to
 * @param int $bus_id - The bus ID to assign
 * @return array - Result array with success status and message
 */
// Alternative version if you want to allow multiple route assignments
function assignBusToRoute($route_id, $bus_id) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $conn->beginTransaction();
        
        // Validate route exists and is active
        $route_check = $conn->prepare("SELECT route_name, status FROM routes WHERE route_id = ?");
        $route_check->execute([$route_id]);
        $route = $route_check->fetch();
        
        if (!$route) {
            throw new Exception('Route not found.');
        }
        
        if ($route['status'] !== 'active') {
            throw new Exception('Cannot assign bus to inactive route.');
        }
        
        // Validate bus exists
        $bus_check = $conn->prepare("SELECT bus_number, plate_number, status FROM buses WHERE bus_id = ?");
        $bus_check->execute([$bus_id]);
        $bus = $bus_check->fetch();
        
        if (!$bus) {
            throw new Exception('Bus not found.');
        }
        
        // Check if bus is already assigned to this specific route
        $existing_assignment = $conn->prepare("SELECT * FROM bus_route_assignments WHERE bus_id = ? AND route_id = ?");
        $existing_assignment->execute([$bus_id, $route_id]);
        
        if ($existing_assignment->rowCount() > 0) {
            throw new Exception('Bus is already assigned to this route.');
        }
        
        // Assign the bus to the route
        $assign_bus = $conn->prepare("
            INSERT INTO bus_route_assignments (route_id, bus_id) 
            VALUES (?, ?)
        ");
        $assign_bus->execute([$route_id, $bus_id]);
        
        // Update bus status to assigned (but don't set assigned_route_id since it can have multiple)
        $update_bus = $conn->prepare("UPDATE buses SET status = 'assigned' WHERE bus_id = ?");
        $update_bus->execute([$bus_id]);
        
        // Verify the assignment was successful
        if ($assign_bus->rowCount() === 0) {
            throw new Exception('Failed to assign bus to route.');
        }
        
        $conn->commit();
        
        // Log the successful assignment
        logAdminActivity('Assign Bus to Route', 
            "Bus #{$bus['bus_number']} ({$bus['plate_number']}) assigned to Route ID: {$route_id} ({$route['route_name']})");
        
        return [
            'success' => true,
            'message' => "Bus #{$bus['bus_number']} successfully assigned to route: {$route['route_name']}"
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        
        // Log the failed attempt
        logAdminActivity('Failed Bus Assignment', 
            "Attempted to assign Bus ID: {$bus_id} to Route ID: {$route_id}. Error: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Assignment failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Unassign a bus from its current route
 * @param int $assignment_id - The assignment ID to remove
 * @return array - Result array with success status and message
 */
function unassignBusFromRoute($assignment_id) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $conn->beginTransaction();
        
        // Get assignment info before removal
        $assignment_check = $conn->prepare("
            SELECT bra.*, b.bus_number, b.plate_number, r.route_name 
            FROM bus_route_assignments bra
            JOIN buses b ON bra.bus_id = b.bus_id
            JOIN routes r ON bra.route_id = r.route_id
            WHERE bra.id = ?
        ");
        $assignment_check->execute([$assignment_id]);
        $assignment = $assignment_check->fetch();
        
        if (!$assignment) {
            throw new Exception('Assignment not found.');
        }
        
        // Remove the assignment
        $unassign = $conn->prepare("DELETE FROM bus_route_assignments WHERE id = ?");
        $unassign->execute([$assignment_id]);
        
        if ($unassign->rowCount() === 0) {
            throw new Exception('Failed to remove bus assignment.');
        }
        
        // Update bus status
        $update_bus = $conn->prepare("UPDATE buses SET status = 'available' WHERE bus_id = ?");
        $update_bus->execute([$assignment['bus_id']]);
        
        $conn->commit();
        
        // Log the unassignment
        logAdminActivity('Unassign Bus from Route', 
            "Bus #{$assignment['bus_number']} ({$assignment['plate_number']}) unassigned from route: {$assignment['route_name']}");
        
        return [
            'success' => true,
            'message' => "Bus #{$assignment['bus_number']} successfully unassigned from route: {$assignment['route_name']}"
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        
        logAdminActivity('Failed Bus Unassignment', 
            "Attempted to remove assignment ID: {$assignment_id}. Error: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Unassignment failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Get all buses assigned to a route
 * @param int $route_id - The route ID
 * @return array - Array of assigned buses
 */
function getBusesAssignedToRoute($route_id) {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT b.*, bra.id as assignment_id, bra.assigned_at
            FROM bus_route_assignments bra
            JOIN buses b ON bra.bus_id = b.bus_id
            WHERE bra.route_id = ?
            ORDER BY b.bus_number ASC
        ");
        $stmt->execute([$route_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error fetching buses for route: " . $e->getMessage());
        return [];
    }
}

/**
 * Get available buses for assignment
 * @return array - Array of available buses
 */
function getAvailableBuses() {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        $stmt = $conn->query("
            SELECT bus_id, bus_number, plate_number, capacity 
            FROM buses 
            WHERE status = 'available' 
            ORDER BY bus_number ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error fetching available buses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all routes with their assigned buses
 * @return array - Array of routes with bus assignment info
 */
function getRoutesWithBusAssignments() {
    $db = new Database();
    $conn = $db->getConnection();
    
    try {
        // Get all routes
        $routes_stmt = $conn->query("SELECT * FROM routes ORDER BY created_at DESC");
        $routes = $routes_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get assigned buses for each route
        foreach ($routes as &$route) {
            $route['assigned_buses'] = getBusesAssignedToRoute($route['route_id']);
        }
        unset($route); // Break the reference
        
        return $routes;
        
    } catch (Exception $e) {
        error_log("Error fetching routes with assignments: " . $e->getMessage());
        return [];
    }
}
?>