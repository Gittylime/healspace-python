<?php
session_start();
include 'includes/db_connection.php';

// Redirect if not logged in or not a therapist
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'therapist') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch therapist-specific data (e.g., pending sessions)
$pending_sessions_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM sessions WHERE therapist_id = ? AND status = 'pending'");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($pending_sessions_count);
    $stmt->fetch();
    $stmt->close();
}

include 'includes/header.php';
?>

<h2 class="mb-4">Welcome, Dr. <?php echo htmlspecialchars($username); ?>!</h2>
<p class="lead">This is your HealSpace Therapist Dashboard. Manage your practice here.</p>

<div class="row mt-4">
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">Manage Availability</h5>
                <p class="card-text">Set and edit your available time slots for sessions.</p>
                <a href="manage_availability.php" class="btn btn-primary">Set Availability</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">Booked Sessions
                    <?php if ($pending_sessions_count > 0): ?>
                        <span class="badge bg-danger ms-2"><?php echo $pending_sessions_count; ?> Pending</span>
                    <?php endif; ?>
                </h5>
                <p class="card-text">View and manage your upcoming and pending client sessions.</p>
                <a href="manage_sessions.php" class="btn btn-primary">View Sessions</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">Access Client Journals & Moods</h5>
                <p class="card-text">View your clients' progress with their permission.</p>
                <a href="view_client_data.php" class="btn btn-primary">Access Data</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">Messages</h5>
                <p class="card-text">Communicate with your clients via secure messaging.</p>
                <a href="messages.php" class="btn btn-primary">Go to Messages</a>
            </div>
        </div>
    </div>
    </div>

<?php
$conn->close();
include 'includes/footer.php';
?>