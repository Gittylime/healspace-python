<?php
session_start();
include '../includes/db_connection.php';

header('Content-Type: application/json'); // Respond with JSON

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

$client_id = $_SESSION['user_id'];
$therapist_id = filter_input(INPUT_POST, 'therapist_id', FILTER_VALIDATE_INT);
$session_date_str = filter_input(INPUT_POST, 'session_date', FILTER_SANITIZE_STRING);
$session_time_str = filter_input(INPUT_POST, 'session_time', FILTER_SANITIZE_STRING); // This is the start_time from availability
$session_type = filter_input(INPUT_POST, 'session_type', FILTER_SANITIZE_STRING);
$availability_start_time_str = filter_input(INPUT_POST, 'availability_start_time', FILTER_SANITIZE_STRING);
$availability_end_time_str = filter_input(INPUT_POST, 'availability_end_time', FILTER_SANITIZE_STRING);

// Validate inputs
if (!$therapist_id || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $session_date_str) ||
    !preg_match("/^\d{2}:\d{2}:\d{2}$/", $session_time_str) ||
    !in_array($session_type, ['chat', 'video']) ||
    !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $availability_start_time_str) ||
    !preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $availability_end_time_str)
) {
    $response['message'] = 'Invalid booking details.';
    echo json_encode($response);
    exit();
}

// Start a transaction for atomicity
$conn->begin_transaction();

try {
    // 1. Check if the slot is still available and belongs to the therapist
    $stmt_check = $conn->prepare("SELECT availability_id FROM therapist_availability WHERE therapist_id = ? AND start_time = ? AND end_time = ? AND is_booked = FALSE");
    if (!$stmt_check) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $stmt_check->bind_param("iss", $therapist_id, $availability_start_time_str, $availability_end_time_str);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $availability_row = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$availability_row) {
        throw new Exception("Selected slot is no longer available or invalid.");
    }
    $availability_id = $availability_row['availability_id'];

    // 2. Mark the slot as booked
    $stmt_update = $conn->prepare("UPDATE therapist_availability SET is_booked = TRUE WHERE availability_id = ?");
    if (!$stmt_update) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $stmt_update->bind_param("i", $availability_id);
    if (!$stmt_update->execute()) {
        throw new Exception("Failed to update availability: " . $stmt_update->error);
    }
    $stmt_update->close();

    // 3. Insert the session into the sessions table
    // For video sessions, you'd generate a link here (e.g., using Twilio API or Jitsi link)
    $video_link = ($session_type === 'video') ? 'https://meet.jit.si/' . uniqid('healspace_') : NULL; // Placeholder
    
    $stmt_insert = $conn->prepare("INSERT INTO sessions (client_id, therapist_id, session_date, session_time, session_type, video_link, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    if (!$stmt_insert) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    $stmt_insert->bind_param("iissss", $client_id, $therapist_id, $session_date_str, $session_time_str, $session_type, $video_link);
    if (!$stmt_insert->execute()) {
        throw new Exception("Failed to insert session: " . $stmt_insert->error);
    }
    $stmt_insert->close();

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Session booked successfully! Your therapist will confirm soon.';
    if ($session_type === 'video') {
        $response['video_link'] = $video_link; // Send link back if successful
    }

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
    error_log("Session booking failed: " . $e->getMessage());
}

$conn->close();
echo json_encode($response);
?>