<?php
session_start();
include '../includes/db_connection.php';

header('Content-Type: application/json'); // Respond with JSON

$response = ['success' => false, 'message' => '', 'slots' => []];

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

$therapist_id = filter_input(INPUT_POST, 'therapist_id', FILTER_VALIDATE_INT);
$session_date_str = filter_input(INPUT_POST, 'session_date', FILTER_SANITIZE_STRING);

if (!$therapist_id || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $session_date_str)) {
    $response['message'] = 'Invalid therapist ID or date.';
    echo json_encode($response);
    exit();
}

// Convert session_date_str to a DateTime object for safer comparison
$session_date_obj = new DateTime($session_date_str);
$start_of_day = $session_date_obj->format('Y-m-d 00:00:00');
$end_of_day = $session_date_obj->format('Y-m-d 23:59:59');

// Fetch available slots for the given therapist and date that are not yet booked
$sql = "SELECT start_time, end_time
        FROM therapist_availability
        WHERE therapist_id = ?
        AND start_time >= ? AND end_time <= ?
        AND is_booked = FALSE
        ORDER BY start_time";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("iss", $therapist_id, $start_of_day, $end_of_day);
    $stmt->execute();
    $result = $stmt->get_result();
    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $slots[] = $row;
    }
    $response['success'] = true;
    $response['slots'] = $slots;
    $stmt->close();
} else {
    $response['message'] = 'Database error fetching availability: ' . $conn->error;
    error_log("Error fetching availability: " . $conn->error);
}

$conn->close();
echo json_encode($response);
?>