<?php
require_once 'admin_auth.php';

$db = new Database();
$conn = $db->getConnection();

$message = '';
$message_type = 'info';

// Handle QR code generation
if (isset($_GET['action']) && $_GET['action'] === 'generate_qr' && isset($_GET['bus_id'])) {
    $bus_id = (int)$_GET['bus_id'];
    
    // Get bus details
    $stmt = $conn->prepare("SELECT bus_number, plate_number FROM buses WHERE bus_id = ?");
    $stmt->execute([$bus_id]);
    $bus = $stmt->fetch();
    
    if ($bus) {
        // Create QR code data (you can customize what data to include)
        $qr_data = "Bus Number: " . $bus['bus_number'] . "\nPlate Number: " . $bus['plate_number'] . "\nBus ID: " . $bus_id;
        
        // Generate QR code using Google Charts API
        $qr_url = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($qr_data);
        
        // Set headers for download
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="bus_' . $bus['bus_number'] . '_qr.png"');
        
        // Get the image and output it
        $image_data = file_get_contents($qr_url);
        echo $image_data;
        exit;
    }
}

// Handle actions (activate/deactivate/delete/maintenance bus)
if (isset($_GET['action']) && isset($_GET['bus_id'])) {
    $action = $_GET['action'];
    $bus_id = (int)$_GET['bus_id'];
    
    switch ($action) {
        case 'activate':
            $stmt = $conn->prepare("UPDATE buses SET status = 'available' WHERE bus_id = ?");
            $stmt->execute([$bus_id]);
            $message = "Bus activated successfully!";
            $message_type = 'success';
            logAdminActivity('Activate Bus', "Bus ID: $bus_id");
            break;
            
        case 'deactivate':
            $stmt = $conn->prepare("UPDATE buses SET status = 'inactive' WHERE bus_id = ?");
            $stmt->execute([$bus_id]);
            $message = "Bus deactivated successfully!";
            $message_type = 'success';
            logAdminActivity('Deactivate Bus', "Bus ID: $bus_id");
            break;
            
        case 'maintenance':
            $stmt = $conn->prepare("UPDATE buses SET status = 'maintenance' WHERE bus_id = ?");
            $stmt->execute([$bus_id]);
            $message = "Bus marked for maintenance!";
            $message_type = 'success';
            logAdminActivity('Bus Maintenance', "Bus ID: $bus_id");
            break;
            
        case 'delete':
            // Check if bus has any active drivers or bookings
            $stmt = $conn->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM drivers WHERE assigned_bus_id = ? AND status = 'active') as driver_count,
                    (SELECT COUNT(*) FROM bookings WHERE bus_id = ? AND booking_status = 'confirmed') as booking_count
            ");
            $stmt->execute([$bus_id, $bus_id]);
            $result = $stmt->fetch();
            
            if ($result['driver_count'] > 0) {
                $message = "Cannot delete bus: Bus has assigned active drivers. Please reassign or deactivate drivers first.";
                $message_type = 'error';
            } elseif ($result['booking_count'] > 0) {
                $message = "Cannot delete bus: Bus has active bookings. Please wait for bookings to complete.";
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("DELETE FROM buses WHERE bus_id = ?");
                $stmt->execute([$bus_id]);
                $message = "Bus deleted successfully!";
                $message_type = 'success';
                logAdminActivity('Delete Bus', "Bus ID: $bus_id");
            }
            break;
    }
}

// Get search and filter parameters
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Build query with filters
$query = "
    SELECT b.*, 
           d.full_name as driver_name,
           d.driver_code,
           (SELECT COUNT(*) FROM bookings bk WHERE bk.bus_id = b.bus_id AND bk.booking_status = 'confirmed' AND bk.travel_date >= CURDATE()) as active_bookings,
           (SELECT status FROM active_trips WHERE bus_id = b.bus_id AND status NOT IN ('completed', 'cancelled') ORDER BY created_at DESC LIMIT 1) as current_trip_status
    FROM buses b 
    LEFT JOIN drivers d ON b.bus_id = d.assigned_bus_id AND d.status = 'active'
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (b.bus_number LIKE ? OR b.plate_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if ($status_filter) {
    $query .= " AND b.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$buses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Buses - <?php echo SITE_NAME; ?></title>
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
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            flex: 1;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .filter-btn {
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            height: fit-content;
        }
        
        .clear-btn {
            background: #6c757d;
            color: white;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            height: fit-content;
        }
        
        .buses-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-available {
            background: #d4edda;
            color: #155724;
        }
        
        .status-on_trip {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-maintenance {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .capacity-info {
            text-align: center;
            font-weight: 600;
        }
        
        .booking-count {
            background: #e9ecef;
            color: #495057;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
            text-decoration: none;
            color: white;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .btn-edit {
            background: #17a2b8;
        }
        
        .btn-activate {
            background: #28a745;
        }
        
        .btn-deactivate {
            background: #6c757d;
        }
        
        .btn-maintenance {
            background: #ffc107;
            color: #333;
        }
        
        .btn-delete {
            background: #dc3545;
        }
        
        .btn-qr {
            background: #6f42c1;
        }
        
        .btn-qr:hover {
            background: #5936a6;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #666;
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
            
            .filters-row {
                flex-direction: column;
            }
            
            .buses-table {
                overflow-x: auto;
            }
            
            .actions {
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
                <li><a href="manage_buses.php" class="active">Manage Buses</a></li>
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
        <div class="page-header">
            <div class="page-title">
                <h2>Manage Buses</h2>
                <p>Add, edit, and manage bus fleet</p>
            </div>
            <a href="add_bus.php" class="add-btn">+ Add New Bus</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" 
                               placeholder="Bus number or plate number..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Status</option>
                            <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="on_trip" <?php echo $status_filter === 'on_trip' ? 'selected' : ''; ?>>On Trip</option>
                            <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn">Filter</button>
                    <a href="manage_buses.php" class="clear-btn">Clear</a>
                </div>
            </form>
        </div>
        
        <div class="buses-table">
            <?php if (empty($buses)): ?>
                <div class="no-data">
                    <h3>No buses found</h3>
                    <p>No buses match your search criteria or no buses have been added yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Bus Number</th>
                            <th>Plate Number</th>
                            <th>Capacity</th>
                            <th>Assigned Driver</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buses as $bus): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($bus['bus_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($bus['plate_number']); ?></td>
                            <td class="capacity-info"><?php echo $bus['capacity']; ?> seats</td>
                            <td>
                                <?php if ($bus['driver_name']): ?>
                                    <?php echo htmlspecialchars($bus['driver_name']); ?><br>
                                    <small>(<?php echo htmlspecialchars($bus['driver_code']); ?>)</small>
                                <?php else: ?>
                                    <em>No driver assigned</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $bus['status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $bus['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($bus['created_at'])); ?></td>
                            <td>
                                <div class="actions">
                                    <a href="edit_bus.php?id=<?php echo $bus['bus_id']; ?>" 
                                       class="action-btn btn-edit">Edit</a>

                                    <div style="display: none;" id="qrcode-<?php echo $bus['bus_id']; ?>"></div>
                                    
                                    <button href="#" 
                                        onclick="downloadQRCode('<?php echo $bus['bus_id']; ?>', '<?php echo htmlspecialchars($bus['bus_number']); ?>')"
                                       class="action-btn btn-qr" 
                                      >Download QR Code</button>
                                    
                                    <?php if ($bus['status'] === 'available'): ?>
                                        <a href="?action=maintenance&bus_id=<?php echo $bus['bus_id']; ?>" 
                                           class="action-btn btn-maintenance"
                                           onclick="return confirm('Mark this bus for maintenance?')">Maintenance</a>
                                        <a href="?action=deactivate&bus_id=<?php echo $bus['bus_id']; ?>" 
                                           class="action-btn btn-deactivate"
                                           onclick="return confirm('Deactivate this bus?')">Deactivate</a>
                                    <?php elseif ($bus['status'] === 'maintenance'): ?>
                                        <a href="?action=activate&bus_id=<?php echo $bus['bus_id']; ?>" 
                                           class="action-btn btn-activate"
                                           onclick="return confirm('Mark this bus as available?')">Available</a>
                                        <a href="?action=deactivate&bus_id=<?php echo $bus['bus_id']; ?>" 
                                           class="action-btn btn-deactivate"
                                           onclick="return confirm('Deactivate this bus?')">Deactivate</a>
                                    <?php elseif ($bus['status'] === 'inactive'): ?>
                                        <a href="?action=activate&bus_id=<?php echo $bus['bus_id']; ?>" 
                                           class="action-btn btn-activate"
                                           onclick="return confirm('Activate this bus?')">Activate</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($bus['status'] !== 'on_trip'): ?>
                                        <a href="?action=delete&bus_id=<?php echo $bus['bus_id']; ?>" 
                                           class="action-btn btn-delete"
                                           onclick="return confirm('Are you sure you want to delete this bus? This action cannot be undone.')">Delete</a>
                                    <?php endif; ?>
                                </div>
                                <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                if (typeof QRCode === 'undefined') {
                                    var script = document.createElement('script');
                                    script.src = "https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js";
                                    script.onload = function() {
                                        generateTableQRCode('<?php echo $bus['bus_id']; ?>', '<?php echo addslashes($bus['bus_number']); ?>');
                                    };
                                    document.head.appendChild(script);
                                } else {
                                    generateTableQRCode('<?php echo $bus['bus_id']; ?>', '<?php echo addslashes($bus['bus_number']); ?>');
                                }
                            });

                            function generateTableQRCode(busId, qrData) {
                                var qrDiv = document.getElementById('qrcode-' + busId);
                                if (qrDiv && !qrDiv.hasChildNodes()) {
                                    new QRCode(qrDiv, {
                                        text: qrData,
                                        width: 200,
                                        height: 200
                                    });
                                }
                            }

                            function downloadQRCode(busId, busNumber) {
                                var qrDiv = document.getElementById('qrcode-' + busId);
                                if (!qrDiv) {
                                    alert('QR Code not found');
                                    return;
                                }

                                var qrImg = qrDiv.querySelector('img');
                                var qrCanvas = qrDiv.querySelector('canvas');

                                if (qrImg) {
                                    // If QR code is an image
                                    downloadFromImage(qrImg, busNumber);
                                } else if (qrCanvas) {
                                    // If QR code is a canvas
                                    downloadFromCanvas(qrCanvas, busNumber);
                                } else {
                                    alert('QR Code image not found');
                                }
                            }

                            function downloadFromImage(img, busNumber) {
                                // Create a canvas to convert image to PNG
                                var canvas = document.createElement('canvas');
                                var ctx = canvas.getContext('2d');

                                canvas.width = img.width;
                                canvas.height = img.height;

                                ctx.drawImage(img, 0, 0);

                                // Convert canvas to blob and download
                                canvas.toBlob(function(blob) {
                                    downloadBlob(blob, busNumber + '.png');
                                }, 'image/png');
                            }

                            function downloadFromCanvas(canvas, busNumber) {
                                // Convert canvas to blob and download
                                canvas.toBlob(function(blob) {
                                    downloadBlob(blob, busNumber + '.png');
                                }, 'image/png');
                            }

                            function downloadBlob(blob, filename) {
                                // Create download link
                                var link = document.createElement('a');
                                link.href = URL.createObjectURL(blob);
                                link.download = filename;

                                // Trigger download
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);

                                // Clean up
                                URL.revokeObjectURL(link.href);
                            }
                        </script>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-submit form on status change
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>