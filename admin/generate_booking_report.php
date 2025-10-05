<?php
require_once 'config.php';

// Check if user is logged in (add your authentication logic here)
// session_start();
// if (!isset($_SESSION['admin_logged_in'])) {
//     header('Location: login.php');
//     exit();
// }

$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default to first day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default to today
$route_id = $_GET['route_id'] ?? '';
$status = $_GET['status'] ?? '';

// Build the query with filters
$query = "
    SELECT 
        b.booking_reference,
        b.created_at as booking_date,
        u.full_name as passenger_name,
        u.mobile_number as passenger_phone,
        u.email as passenger_email,
        r.route_name,
        r.origin,
        r.destination,
        bus.bus_number,
        bus.plate_number,
        d.full_name as driver_name,
        b.seat_number,
        b.travel_date,
        b.departure_time,
        b.fare,
        b.booking_status,
        b.pickup_stop,
        b.destination_stop,
        CASE WHEN b.boarded = 1 THEN 'Yes' ELSE 'No' END as boarded,
        CASE WHEN b.driver_approved = 1 THEN 'Yes' ELSE 'No' END as driver_approved,
        at.actual_departure,
        at.actual_arrival,
        at.status as trip_status
    FROM bookings b
    INNER JOIN users u ON b.user_id = u.user_id
    INNER JOIN routes r ON b.route_id = r.route_id
    INNER JOIN buses bus ON b.bus_id = bus.bus_id
    LEFT JOIN drivers d ON b.driver_id = d.driver_id
    LEFT JOIN active_trips at ON b.trip_id = at.trip_id
    WHERE DATE(b.created_at) BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

if (!empty($route_id)) {
    $query .= " AND b.route_id = ?";
    $params[] = $route_id;
}

if (!empty($status)) {
    $query .= " AND b.booking_status = ?";
    $params[] = $status;
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics based on filtered data
$total_bookings = count($bookings);
$total_revenue = 0;
$confirmed_bookings = 0;
$completed_bookings = 0;
$pending_bookings = 0;
$cancelled_bookings = 0;

foreach ($bookings as $booking) {
    $total_revenue += $booking['fare'];
    
    switch ($booking['booking_status']) {
        case 'confirmed':
            $confirmed_bookings++;
            break;
        case 'completed':
            $completed_bookings++;
            break;
        case 'pending':
            $pending_bookings++;
            break;
        case 'cancelled':
            $cancelled_bookings++;
            break;
    }
}

// If export is requested, generate Excel file
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Generate filename with current date
    $filename = 'booking_reports_' . date('Y-m-d') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // CSV headers
    $headers = [
        'Booking Reference',
        'Booking Date',
        'Passenger Name',
        'Phone Number',
        'Email',
        'Route Name',
        'Origin',
        'Destination',
        'Bus Number',
        'Plate Number',
        'Driver Name',
        'Seat Number',
        'Travel Date',
        'Departure Time',
        'Fare (PHP)',
        'Booking Status',
        'Pickup Stop',
        'Destination Stop',
        'Boarded',
        'Driver Approved',
        'Actual Departure',
        'Actual Arrival',
        'Trip Status'
    ];
    
    fputcsv($output, $headers);
    
    // Add data rows
    foreach ($bookings as $booking) {
        $row = [
            $booking['booking_reference'],
            date('Y-m-d H:i:s', strtotime($booking['booking_date'])),
            $booking['passenger_name'],
            $booking['passenger_phone'],
            $booking['passenger_email'] ?? 'N/A',
            $booking['route_name'],
            $booking['origin'],
            $booking['destination'],
            $booking['bus_number'],
            $booking['plate_number'],
            $booking['driver_name'] ?? 'N/A',
            $booking['seat_number'],
            $booking['travel_date'] !== '0000-00-00' ? $booking['travel_date'] : 'N/A',
            $booking['departure_time'],
            number_format($booking['fare'], 2),
            ucfirst($booking['booking_status']),
            $booking['pickup_stop'] ?? 'N/A',
            $booking['destination_stop'] ?? 'N/A',
            $booking['boarded'],
            $booking['driver_approved'],
            $booking['actual_departure'] ?? 'N/A',
            $booking['actual_arrival'] ?? 'N/A',
            ucfirst($booking['trip_status'] ?? 'N/A')
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

// Get routes for filter dropdown
$routes_stmt = $conn->prepare("SELECT route_id, route_name FROM routes WHERE status = 'active' ORDER BY route_name");
$routes_stmt->execute();
$routes = $routes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current filter parameters for URL building
$current_filters = [
    'start_date' => $start_date,
    'end_date' => $end_date,
    'route_id' => $route_id,
    'status' => $status
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Reports - Bus Reservation System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        h1 {
            color: #333;
            margin: 0;
        }
        
        .header-info {
            color: #666;
            font-size: 14px;
        }
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            border: 1px solid #dee2e6;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #495057;
            font-size: 13px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: end;
            grid-column: 1 / -1;
            justify-content: flex-start;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #1e7e34;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(40,167,69,0.3);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
            transform: translateY(-1px);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(102,126,234,0.3);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.revenue {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card.confirmed {
            background: linear-gradient(135deg, #FF6B6B 0%, #FFE66D 100%);
        }
        
        .stat-card.completed {
            background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
        }
        
        .stat-number {
            font-size: 2.2em;
            font-weight: bold;
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-label {
            opacity: 0.95;
            font-size: 14px;
            font-weight: 500;
        }
        
        .additional-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .mini-stat {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #e9ecef;
            transition: border-color 0.3s ease;
        }
        
        .mini-stat:hover {
            border-color: #007bff;
        }
        
        .mini-stat-number {
            font-size: 1.5em;
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .mini-stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
            color: #495057;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            font-size: 13px;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tr:hover {
            background-color: #e3f2fd;
        }
        
        .status {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-completed {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
            font-style: italic;
            font-size: 16px;
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        
        .filter-summary {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .filter-summary h4 {
            margin: 0 0 10px 0;
            color: #0056b3;
        }
        
        .filter-summary p {
            margin: 0;
            color: #495057;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .filters {
                grid-template-columns: 1fr;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .additional-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Booking Reports</h1>
                <div class="header-info">
                    Generated on <?php echo date('F j, Y g:i A'); ?>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <form method="GET" class="filters">
            <div class="filter-group">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            
            <div class="filter-group">
                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            
            <div class="filter-group">
                <label for="route_id">Route:</label>
                <select id="route_id" name="route_id">
                    <option value="">All Routes</option>
                    <?php foreach ($routes as $route): ?>
                        <option value="<?php echo $route['route_id']; ?>" 
                                <?php echo $route_id == $route['route_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($route['route_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="status">Booking Status:</label>
                <select id="status" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="?<?php echo http_build_query(array_merge($current_filters, ['export' => 'excel'])); ?>" 
                   class="btn btn-success">📊 Export to Excel</a>
                <a href="?" class="btn btn-secondary">Clear All</a>
            </div>
        </form>
        
        <!-- Filter Summary -->
        <?php if (!empty(array_filter($current_filters))): ?>
        <div class="filter-summary">
            <h4>Active Filters</h4>
            <p>
                <?php
                $filter_text = [];
                if ($start_date !== date('Y-m-01')) $filter_text[] = "From: " . date('M j, Y', strtotime($start_date));
                if ($end_date !== date('Y-m-d')) $filter_text[] = "To: " . date('M j, Y', strtotime($end_date));
                if ($route_id) {
                    $selected_route = array_filter($routes, function($r) use ($route_id) { return $r['route_id'] == $route_id; });
                    if ($selected_route) $filter_text[] = "Route: " . reset($selected_route)['route_name'];
                }
                if ($status) $filter_text[] = "Status: " . ucfirst($status);
                
                echo !empty($filter_text) ? implode(' | ', $filter_text) : 'Showing all records';
                ?>
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Main Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_bookings); ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card revenue">
                <div class="stat-number">₱<?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card confirmed">
                <div class="stat-number"><?php echo number_format($confirmed_bookings); ?></div>
                <div class="stat-label">Confirmed</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-number"><?php echo number_format($completed_bookings); ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        
        <!-- Additional Statistics -->
        <div class="additional-stats">
            <div class="mini-stat">
                <div class="mini-stat-number"><?php echo number_format($pending_bookings); ?></div>
                <div class="mini-stat-label">Pending</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-number"><?php echo number_format($cancelled_bookings); ?></div>
                <div class="mini-stat-label">Cancelled</div>
            </div>
            <div class="mini-stat">
                <div class="mini-stat-number"><?php echo $total_bookings > 0 ? number_format(($completed_bookings / $total_bookings) * 100, 1) . '%' : '0%'; ?></div>
                <div class="mini-stat-label">Success Rate</div>
            </div>
        </div>
        
        <!-- Data Table -->
        <div class="table-container">
            <div class="table-header">
                Booking Records (<?php echo number_format($total_bookings); ?> records found)
            </div>
            
            <?php if (!empty($bookings)): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking Ref</th>
                                <th>Date</th>
                                <th>Passenger</th>
                                <th>Phone</th>
                                <th>Route</th>
                                <th>Bus</th>
                                <th>Driver</th>
                                <th>Seat</th>
                                <th>Fare</th>
                                <th>Status</th>
                                <th>Boarded</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($booking['booking_reference']); ?></strong></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($booking['booking_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($booking['passenger_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['passenger_phone']); ?></td>
                                    <td title="<?php echo htmlspecialchars($booking['origin'] . ' → ' . $booking['destination']); ?>">
                                        <?php echo htmlspecialchars($booking['route_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['bus_number']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['driver_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo $booking['seat_number']; ?></td>
                                    <td><strong>₱<?php echo number_format($booking['fare'], 2); ?></strong></td>
                                    <td>
                                        <span class="status status-<?php echo $booking['booking_status']; ?>">
                                            <?php echo ucfirst($booking['booking_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: <?php echo $booking['boarded'] === 'Yes' ? '#28a745' : '#dc3545'; ?>">
                                            <?php echo $booking['boarded']; ?>
                                        </strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i>📊</i>
                    <strong>No booking data found</strong><br>
                    Try adjusting your filter criteria to see more results.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-submit form when filters change (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.filters form');
            const selects = form.querySelectorAll('select');
            
            selects.forEach(select => {
                select.addEventListener('change', function() {
                    // Optional: Auto-submit on change
                    // form.submit();
                });
            });
            
            // Ensure end date is not before start date
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            
            startDate.addEventListener('change', function() {
                endDate.min = this.value;
            });
            
            endDate.addEventListener('change', function() {
                startDate.max = this.value;
            });
        });
    </script>
</body>
</html>