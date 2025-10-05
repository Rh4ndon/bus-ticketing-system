<?php
require_once 'driver_auth.php'; // Auth for drivers
$db = new Database();
$conn = $db->getConnection();

// Get driver info
$driver_id = getDriverId();
$driver_name = getDriverName();

// Fetch assigned route
$route_stmt = $conn->prepare("
    SELECT r.route_id, r.route_name, r.origin, r.destination, r.distance_km, r.estimated_duration_minutes
    FROM routes r
    INNER JOIN buses b ON b.route_id = r.route_id
    INNER JOIN drivers d ON d.bus_id = b.bus_id
    WHERE d.driver_id = ?
");
$route_stmt->execute([$driver_id]);
$route = $route_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch passengers on this route
$passengers = [];
$coordinates = [];
if($route){
    $passenger_stmt = $conn->prepare("
        SELECT u.name, u.phone, b.pickup_stop, b.destination_stop, b.status
        FROM bookings b
        INNER JOIN users u ON u.id = b.user_id
        WHERE b.route_id = ?
        ORDER BY b.pickup_stop ASC
    ");
    $passenger_stmt->execute([$route['route_id']]);
    $passengers = $passenger_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map coordinates for demo purposes (replace with real route data)
    $coordinates = [
        $route['origin'] => ['lat' => 16.9167, 'lng' => 121.5833],
        $route['destination'] => ['lat' => 16.9272, 'lng' => 121.7708]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Driver Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f5f6fa; }
.navbar { background:#667eea; color:white; padding:1rem; display:flex; justify-content:space-between; align-items:center; }
.container { max-width:1200px; margin:2rem auto; padding:0 2rem; }
h2 { color:#333; }
.dashboard-grid { display:grid; grid-template-columns:2fr 1fr; gap:2rem; }
.card { background:white; padding:1.5rem; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
table { width:100%; border-collapse:collapse; margin-top:1rem; }
th, td { padding:0.5rem; text-align:left; border-bottom:1px solid #ddd; }
th { background:#f0f0f0; }
#driverMap { height:500px; border-radius:10px; }
.status-pending { color:#ffc107; font-weight:bold; }
.status-checked { color:#28a745; font-weight:bold; }
@media (max-width: 768px) { .dashboard-grid { grid-template-columns:1fr; } #driverMap { height:300px; } }
</style>
</head>
<body>
<nav class="navbar">
    <div>Welcome, <?php echo htmlspecialchars($driver_name); ?></div>
    <a href="driver_logout.php" style="color:white; text-decoration:none;">Logout</a>
</nav>

<div class="container">
    <h2>Current Route: <?php echo htmlspecialchars($route['route_name'] ?? 'No Route Assigned'); ?></h2>
    <div class="dashboard-grid">
        <div class="card">
            <div id="driverMap"></div>
        </div>

        <div class="card">
            <h3>Passengers</h3>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Pickup</th>
                        <th>Destination</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($passengers): ?>
                        <?php foreach($passengers as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo htmlspecialchars($p['pickup_stop']); ?></td>
                                <td><?php echo htmlspecialchars($p['destination_stop']); ?></td>
                                <td class="status-<?php echo strtolower($p['status']); ?>"><?php echo ucfirst($p['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No passengers yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
const driverMap = L.map('driverMap').setView([16.92195, 121.67705], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(driverMap);

<?php if($coordinates): ?>
const coordinates = <?php echo json_encode($coordinates); ?>;
const routeCoords = Object.values(coordinates).map(c => [c.lat, c.lng]);

// Draw route line
L.polyline(routeCoords, {color:'#28a745', weight:4, opacity:0.8}).addTo(driverMap);

// Add markers
for (const [name, coord] of Object.entries(coordinates)) {
    L.marker([coord.lat, coord.lng]).addTo(driverMap).bindPopup(name);
}

// Fit map to route
driverMap.fitBounds(routeCoords, {padding:[20,20]});
<?php else: ?>
// No route assigned
driverMap.setView([16.92195, 121.67705], 12);
<?php endif; ?>
</script>
</body>
</html>
