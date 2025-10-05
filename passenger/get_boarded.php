<?php
require_once '../admin/config.php';

if (isset($_POST['bus_number']) && isset($_POST['booking_id'])) {
    $busNumber = $_POST['bus_number'];
    $bookingId = $_POST['booking_id'];

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    $stmt = $conn->prepare("SELECT * FROM buses WHERE bus_number = ?");
    $stmt->bind_param("s", $busNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $bus = $result->fetch_assoc();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Bus not found.']);
        exit;
    }


    $stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ? AND bus_id = ?");
    $stmt->bind_param("ii", $bookingId, $bus['bus_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $result = $result->fetch_assoc();

    if ($result['boarded'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Passenger already marked as boarded.']);
        exit;
    }

    // Update the booking status to 'boarded'
    $stmt = $conn->prepare("UPDATE bookings SET boarded = 1 WHERE bus_id = ? AND booking_id = ?");
    $stmt->bind_param("ii", $result['bus_id'], $bookingId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Passenger marked as boarded.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
