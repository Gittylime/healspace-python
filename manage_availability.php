<?php
session_start();
include 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'therapist') {
    header("Location: login.php");
    exit();
}

$therapist_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] === 'add_slot') {
        $start_datetime_str = filter_input(INPUT_POST, 'start_datetime', FILTER_SANITIZE_STRING);
        $end_datetime_str = filter_input(INPUT_POST, 'end_datetime', FILTER_SANITIZE_STRING);

        // Validate dates
        try {
            $start_datetime = new DateTime($start_datetime_str);
            $end_datetime = new DateTime($end_datetime_str);

            if ($start_datetime >= $end_datetime) {
                $message = '<div class="alert alert-danger">End time must be after start time.</div>';
            } elseif ($start_datetime < new DateTime()) {
                $message = '<div class="alert alert-danger">Cannot add availability in the past.</div>';
            } else {
                $stmt = $conn->prepare("INSERT INTO therapist_availability (therapist_id, start_time, end_time, is_booked) VALUES (?, ?, ?, FALSE)");
                if ($stmt) {
                    $start_formatted = $start_datetime->format('Y-m-d H:i:s');
                    $end_formatted = $end_datetime->format('Y-m-d H:i:s');
                    $stmt->bind_param("iss", $therapist_id, $start_formatted, $end_formatted);
                    if ($stmt->execute()) {
                        $message = '<div class="alert alert-success">Availability slot added successfully!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Error adding slot: ' . $stmt->error . '</div>';
                    }
                    $stmt->close();
                } else {
                    $message = '<div class="alert alert-danger">Database error during slot addition: ' . $conn->error . '</div>';
                }
            }
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Invalid date/time format. Please use YYYY-MM-DD HH:MM.</div>';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_slot') {
        $availability_id = filter_input(INPUT_POST, 'availability_id', FILTER_VALIDATE_INT);

        if ($availability_id) {
            $stmt = $conn->prepare("DELETE FROM therapist_availability WHERE availability_id = ? AND therapist_id = ? AND is_booked = FALSE");
            if ($stmt) {
                $stmt->bind_param("ii", $availability_id, $therapist_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $message = '<div class="alert alert-success">Availability slot deleted.</div>';
                    } else {
                        $message = '<div class="alert alert-warning">Slot not found or already booked.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Error deleting slot: ' . $stmt->error . '</div>';
                }
                $stmt->close();
            } else {
                $message = '<div class="alert alert-danger">Database error during slot deletion: ' . $conn->error . '</div>';
            }
        }
    }
}

// Fetch current availability
$current_availability = [];
$stmt_availability = $conn->prepare("SELECT availability_id, start_time, end_time, is_booked FROM therapist_availability WHERE therapist_id = ? ORDER BY start_time ASC");
if ($stmt_availability) {
    $stmt_availability->bind_param("i", $therapist_id);
    $stmt_availability->execute();
    $result_availability = $stmt_availability->get_result();
    while ($row = $result_availability->fetch_assoc()) {
        $current_availability[] = $row;
    }
    $stmt_availability->close();
} else {
    error_log("Error fetching therapist availability: " . $conn->error);
}

$conn->close();
include 'includes/header.php';
?>

<h2 class="mb-4">Manage Your Availability</h2>
<p class="lead">Add new time slots when you are available for sessions.</p>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3>Add New Availability Slot</h3>
            </div>
            <div class="card-body">
                <form action="manage_availability.php" method="POST">
                    <input type="hidden" name="action" value="add_slot">
                    <div class="mb-3">
                        <label for="start_datetime" class="form-label">Start Date & Time</label>
                        <input type="datetime-local" class="form-control" id="start_datetime" name="start_datetime" required>
                    </div>
                    <div class="mb-3">
                        <label for="end_datetime" class="form-label">End Date & Time</label>
                        <input type="datetime-local" class="form-control" id="end_datetime" name="end_datetime" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Slot</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3>Current & Future Availability</h3>
            </div>
            <div class="card-body">
                <?php if (empty($current_availability)): ?>
                    <div class="alert alert-info">No availability slots set yet.</div>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($current_availability as $slot): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <?php echo date("Y-m-d H:i", strtotime($slot['start_time'])); ?> -
                                    <?php echo date("H:i", strtotime($slot['end_time'])); ?>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $slot['is_booked'] ? 'danger' : 'success'; ?> me-2">
                                        <?php echo $slot['is_booked'] ? 'Booked' : 'Available'; ?>
                                    </span>
                                    <?php if (!$slot['is_booked']): ?>
                                        <form action="manage_availability.php" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete_slot">
                                            <input type="hidden" name="availability_id" value="<?php echo $slot['availability_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this slot?');">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>