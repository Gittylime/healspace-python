<?php
session_start();
include 'includes/db_connection.php'; // Include DB connection for fetching user info

// Redirect if not logged in or not a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch client-specific data (optional, e.g., for profile display)
// You might fetch their mental_issue from the 'users' table if you want to display it
// For simplicity, we'll just use the session username for now.

include 'includes/header.php'; // Include the header
?>

<h2 class="mb-4">Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
<p class="lead">This is your HealSpace Client Dashboard. Manage your mental health journey here.</p>

<div class="row mt-4">
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">Mood Tracker</h5>
                <p class="card-text">Log your daily mood and track your emotional progress.</p>
                <a href="mood_tracker.php" class="btn btn-primary">Go to Mood Tracker</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">Journaling</h5>
                <p class="card-text">Document your thoughts, feelings, and experiences privately.</p>
                <a href="journal.php" class="btn btn-primary">Go to Journal</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">Mental Health Resources</h5>
                <p class="card-text">Access educational materials and helpful links.</p>
                <a href="resources.php" class="btn btn-primary">View Resources</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">Book a Session</h5>
                <p class="card-text">Find and schedule sessions with specialized therapists.</p>
                <a href="book_session.php" class="btn btn-primary">Book Now</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">My Sessions</h5>
                <p class="card-text">View your upcoming and past therapy sessions.</p>
                <a href="my_sessions.php" class="btn btn-primary">View Sessions</a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">Messages</h5>
                <p class="card-text">Communicate with your therapists via secure messaging.</p>
                <a href="messages.php" class="btn btn-primary">Go to Messages</a>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include 'includes/footer.php';
?>