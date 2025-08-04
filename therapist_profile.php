<?php
session_start();
include 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    header("Location: login.php");
    exit();
}

$therapist_id = isset($_GET['id']) ? filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) : 0;

if ($therapist_id === 0) {
    header("Location: book_session.php"); // Redirect if no therapist ID
    exit();
}

$therapist = null;
$message = '';

// Fetch therapist details
$stmt = $conn->prepare("SELECT u.username, u.email, u.bio, u.profile_picture,
                               GROUP_CONCAT(ts.specialty_name ORDER BY ts.specialty_name SEPARATOR ', ') AS specialties
                        FROM users u
                        JOIN therapist_specialties_junction tsj ON u.user_id = tsj.therapist_id
                        JOIN therapist_specialties ts ON tsj.specialty_id = ts.specialty_id
                        WHERE u.user_id = ? AND u.user_type = 'therapist'
                        GROUP BY u.user_id");
if ($stmt) {
    $stmt->bind_param("i", $therapist_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $therapist = $result->fetch_assoc();
    $stmt->close();
} else {
    error_log("Error fetching therapist profile: " . $conn->error);
    $message = '<div class="alert alert-danger">Error fetching therapist details.</div>';
}

if (!$therapist) {
    $message = '<div class="alert alert-warning">Therapist not found or invalid ID.</div>';
}

$conn->close();
include 'includes/header.php';
?>

<h2 class="mb-4">Therapist Profile: Dr. <?php echo htmlspecialchars($therapist['username'] ?? 'N/A'); ?></h2>

<?php echo $message; ?>

<?php if ($therapist): ?>
    <div class="row">
        <div class="col-md-4 text-center">
            <?php if ($therapist['profile_picture']): ?>
                <img src="uploads/profiles/<?php echo htmlspecialchars($therapist['profile_picture']); ?>" class="img-fluid rounded-circle mb-3" alt="Profile Picture" style="width: 150px; height: 150px; object-fit: cover;">
            <?php else: ?>
                <img src="images/default_profile.png" class="img-fluid rounded-circle mb-3" alt="Default Profile Picture" style="width: 150px; height: 150px; object-fit: cover;">
            <?php endif; ?>
            <h3>Dr. <?php echo htmlspecialchars($therapist['username']); ?></h3>
            <p class="text-muted"><?php echo htmlspecialchars($therapist['specialties']); ?></p>
        </div>
        <div class="col-md-8">
            <h4>About Me</h4>
            <p><?php echo nl2br(htmlspecialchars($therapist['bio'])); ?></p>

            <h4 class="mt-4">Book a Session</h4>
            <form id="bookingForm" class="mb-4">
                <input type="hidden" name="therapist_id" value="<?php echo $therapist_id; ?>">
                <div class="mb-3">
                    <label for="session_date" class="form-label">Select Date:</label>
                    <input type="date" class="form-control" id="session_date" name="session_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label for="session_type" class="form-label">Session Type:</label>
                    <select class="form-select" id="session_type" name="session_type" required>
                        <option value="chat">Chat Session</option>
                        <option value="video">Video Session</option>
                    </select>
                </div>
                <button type="button" class="btn btn-info" id="checkAvailabilityBtn">Check Availability</button>
            </form>

            <div id="availabilityResults" class="mt-3">
                <div class="alert alert-info">Select a date and click "Check Availability" to see available slots.</div>
            </div>
            <div id="bookingMessage" class="mt-3"></div>

        </div>
    </div>
<?php endif; ?>

<script>
document.getElementById('checkAvailabilityBtn').addEventListener('click', function() {
    const therapistId = document.querySelector('input[name="therapist_id"]').value;
    const sessionDate = document.getElementById('session_date').value;
    const availabilityResultsDiv = document.getElementById('availabilityResults');

    if (!sessionDate) {
        availabilityResultsDiv.innerHTML = '<div class="alert alert-warning">Please select a date.</div>';
        return;
    }

    // AJAX request to fetch availability
    fetch('ajax/get_therapist_availability.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `therapist_id=${therapistId}&session_date=${sessionDate}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.slots.length > 0) {
                let html = '<p class="fw-bold">Available Slots for ' + sessionDate + ':</p>';
                html += '<div class="list-group">';
                data.slots.forEach(slot => {
                    html += `
                        <button type="button" class="list-group-item list-group-item-action"
                                onclick="bookSlot(${therapistId}, '${sessionDate}', '${slot.start_time}', '${slot.end_time}')">
                            ${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)}
                        </button>
                    `;
                });
                html += '</div>';
                availabilityResultsDiv.innerHTML = html;
            } else {
                availabilityResultsDiv.innerHTML = '<div class="alert alert-info">No available slots for this date.</div>';
            }
        } else {
            availabilityResultsDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error fetching availability:', error);
        availabilityResultsDiv.innerHTML = '<div class="alert alert-danger">An error occurred while checking availability.</div>';
    });
});

function bookSlot(therapistId, sessionDate, startTime, endTime) {
    const sessionType = document.getElementById('session_type').value;
    const bookingMessageDiv = document.getElementById('bookingMessage');

    if (!sessionType) {
        bookingMessageDiv.innerHTML = '<div class="alert alert-warning">Please select a session type.</div>';
        return;
    }

    if (confirm(`Confirm booking a ${sessionType} session with Dr. <?php echo htmlspecialchars($therapist['username'] ?? ''); ?> on ${sessionDate} from ${startTime.substring(0,5)} to ${endTime.substring(0,5)}?`)) {
        fetch('ajax/book_session_process.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `therapist_id=${therapistId}&session_date=${sessionDate}&session_time=${startTime}&session_type=${sessionType}&availability_start_time=${startTime}&availability_end_time=${endTime}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bookingMessageDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                // Optionally refresh availability or redirect
                document.getElementById('checkAvailabilityBtn').click(); // Re-check availability
            } else {
                bookingMessageDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Error booking session:', error);
            bookingMessageDiv.innerHTML = '<div class="alert alert-danger">An error occurred during booking.</div>';
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>