<?php
session_start();
// Use require_once for essential files to ensure they are included only once
// and that the script stops if they are missing.
require_once __DIR__ . '/includes/db_connection.php';
// We will move the header include to just before the HTML output
// require_once __DIR__ . '/includes/header.php'; // REMOVE this line from here

// Redirect if user is not logged in or not a therapist
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'therapist') {
    header("Location: login.php");
    exit();
}

$therapist_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
    $action = $_POST['action'];

    if ($session_id) {
        $new_status = '';
        if ($action === 'accept') {
            $new_status = 'accepted';
        } elseif ($action === 'decline') {
            $new_status = 'declined';
        }

        if (!empty($new_status)) {
            // Start transaction for atomicity
            $conn->begin_transaction();
            try {
                // Update session status
                $stmt_update_session = $conn->prepare("UPDATE sessions SET status = ? WHERE session_id = ? AND therapist_id = ? AND status = 'pending'");
                if (!$stmt_update_session) {
                    throw new Exception("Prepare statement failed: " . $conn->error);
                }
                $stmt_update_session->bind_param("sii", $new_status, $session_id, $therapist_id);
                $stmt_update_session->execute();

                if ($stmt_update_session->affected_rows > 0) {
                    // If declined, make the availability slot available again
                    if ($new_status === 'declined') {
                        // Find the corresponding availability slot
                        // This query logic looks a bit complex and could potentially miss edge cases
                        // It assumes start_time in therapist_availability is exactly 'YYYY-MM-DD HH:MM:SS'
                        // and matches CONCAT(s.session_date, ' ', s.session_time).
                        // Consider storing availability_id directly in the sessions table when booking
                        // for a more robust lookup.
                        $stmt_get_slot = $conn->prepare("SELECT ta.availability_id FROM therapist_availability ta JOIN sessions s ON ta.therapist_id = s.therapist_id AND ta.start_time = CONCAT(s.session_date, ' ', s.session_time) WHERE s.session_id = ?");
                        if (!$stmt_get_slot) {
                            throw new Exception("Prepare statement for getting slot failed: " . $conn->error);
                        }
                        $stmt_get_slot->bind_param("i", $session_id);
                        $stmt_get_slot->execute();
                        $result_slot = $stmt_get_slot->get_result();
                        $slot_row = $result_slot->fetch_assoc();
                        $stmt_get_slot->close();

                        if ($slot_row) {
                            $availability_id_to_unbook = $slot_row['availability_id'];
                            $stmt_unbook_slot = $conn->prepare("UPDATE therapist_availability SET is_booked = FALSE WHERE availability_id = ?");
                            if (!$stmt_unbook_slot) {
                                throw new Exception("Prepare statement for unbooking slot failed: " . $conn->error);
                            }
                            $stmt_unbook_slot->bind_param("i", $availability_id_to_unbook);
                            $stmt_unbook_slot->execute();
                            $stmt_unbook_slot->close();
                        }
                    }
                    $conn->commit();
                    $message = '<div class="alert alert-success">Session ' . $new_status . ' successfully!</div>';
                } else {
                    // If no rows affected, it means session was not found, not pending, or therapist ID mismatch
                    throw new Exception("Session not found, not pending, or already processed.");
                }
                $stmt_update_session->close();
            } catch (Exception $e) {
                $conn->rollback();
                $message = '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>'; // Sanitize error message
                error_log("Session management error: " . $e->getMessage());
            }
        }
    } else {
        $message = '<div class="alert alert-danger">Invalid session ID.</div>';
    }
}

// Fetch sessions
$sessions_query = $conn->prepare("
    SELECT s.session_id, s.session_date, s.session_time, s.session_type, s.status, s.video_link,
           s.booking_date, -- Added booking_date for display in pending tab
           u.username AS client_username, u.user_id AS client_id
    FROM sessions s
    JOIN users u ON s.client_id = u.user_id
    WHERE s.therapist_id = ?
    ORDER BY s.session_date DESC, s.session_time DESC
");

$sessions = [];
if ($sessions_query) {
    $sessions_query->bind_param("i", $therapist_id);
    $sessions_query->execute();
    $result_sessions = $sessions_query->get_result();
    while ($row = $result_sessions->fetch_assoc()) {
        $sessions[] = $row;
    }
    $sessions_query->close();
} else {
    error_log("Error fetching therapist sessions: " . $conn->error);
    $message = '<div class="alert alert-danger">Error fetching sessions. Please try again.</div>'; // User-friendly error
}

// Close connection at the very end, after all DB operations.
// Only close if $conn was successfully established.
if ($conn) {
    $conn->close();
}

// Now include the header, right before the HTML content starts.
require_once __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
    <h2 class="mb-4">Manage Your Sessions</h2>
    <p class="lead">View pending requests, and manage your accepted and past sessions.</p>

    <?php echo $message; ?>

    <?php if (empty($sessions)): ?>
        <div class="alert alert-info">You have no sessions yet.</div>
    <?php else: ?>
        <ul class="nav nav-tabs mb-3" id="sessionTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">Pending</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="false">Upcoming</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab" aria-controls="past" aria-selected="false">Past & Declined</button>
            </li>
        </ul>
        <div class="tab-content" id="sessionTabContent">
            <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                <?php $pending_sessions = array_filter($sessions, function($s){ return $s['status'] === 'pending'; }); ?>
                <?php if (empty($pending_sessions)): ?>
                    <div class="alert alert-info">No pending session requests.</div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($pending_sessions as $session): ?>
                            <div class="list-group-item list-group-item-action mb-2 shadow-sm">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1">Client: <?php echo htmlspecialchars($session['client_username']); ?></h5>
                                    <small class="text-muted">Requested: <?php echo date("Y-m-d H:i", strtotime($session['booking_date'])); ?></small>
                                </div>
                                <p class="mb-1">Date: <?php echo htmlspecialchars($session['session_date']); ?> at <?php echo htmlspecialchars($session['session_time']); ?></p>
                                <p class="mb-1">Type: <span class="badge bg-info"><?php echo ucfirst(htmlspecialchars($session['session_type'])); ?> Session</span></p>
                                <div class="mt-2">
                                    <form action="manage_sessions.php" method="POST" class="d-inline">
                                        <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-success btn-sm">Accept</button>
                                    </form>
                                    <form action="manage_sessions.php" method="POST" class="d-inline ms-2">
                                        <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                        <input type="hidden" name="action" value="decline">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to decline this session? This will make the slot available again.');">Decline</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                <?php
                // Filter for upcoming sessions (accepted AND in the future)
                $upcoming_sessions = array_filter($sessions, function($s) {
                    $session_datetime_str = $s['session_date'] . ' ' . $s['session_time'];
                    $session_datetime = new DateTime($session_datetime_str);
                    $now = new DateTime();
                    return $s['status'] === 'accepted' && $session_datetime > $now;
                });
                ?>
                <?php if (empty($upcoming_sessions)): ?>
                    <div class="alert alert-info">No upcoming sessions.</div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($upcoming_sessions as $session): ?>
                            <div class="list-group-item list-group-item-action mb-2 shadow-sm">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1">Client: <?php echo htmlspecialchars($session['client_username']); ?></h5>
                                    <small class="text-muted">Status: <span class="badge bg-success">Accepted</span></small>
                                </div>
                                <p class="mb-1">Date: <?php echo htmlspecialchars($session['session_date']); ?> at <?php echo htmlspecialchars($session['session_time']); ?></p>
                                <p class="mb-1">Type: <span class="badge bg-info"><?php echo ucfirst(htmlspecialchars($session['session_type'])); ?> Session</span></p>
                                <?php if ($session['session_type'] === 'video' && !empty($session['video_link'])): ?>
                                    <p class="mb-1">Video Link: <a href="<?php echo htmlspecialchars($session['video_link']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">Join Video Call</a></p>
                                <?php endif; ?>
                                <a href="messages.php?partner_id=<?php echo $session['client_id']; ?>" class="btn btn-sm btn-secondary mt-2">Message Client</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="past" role="tabpanel" aria-labelledby="past-tab">
                <?php
                // Filter for past/declined sessions
                $past_sessions = array_filter($sessions, function($s) {
                    $session_datetime_str = $s['session_date'] . ' ' . $s['session_time'];
                    $session_datetime = new DateTime($session_datetime_str);
                    $now = new DateTime();
                    // Past if declined OR (accepted AND current/past time)
                    return $s['status'] === 'declined' || ($s['status'] === 'accepted' && $session_datetime <= $now);
                });
                ?>
                <?php if (empty($past_sessions)): ?>
                    <div class="alert alert-info">No past or declined sessions.</div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($past_sessions as $session): ?>
                            <div class="list-group-item list-group-item-action mb-2 shadow-sm">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1">Client: <?php echo htmlspecialchars($session['client_username']); ?></h5>
                                    <small class="text-muted">Status:
                                        <span class="badge bg-<?php
                                            if ($session['status'] === 'declined') echo 'danger';
                                            // Assume 'completed' if accepted and in the past, or add a 'completed' status to DB
                                            else if ($session['status'] === 'accepted' && (new DateTime($session['session_date'] . ' ' . $session['session_time'])) <= (new DateTime())) echo 'primary'; // Use primary for implicitly 'completed' if no actual 'completed' status
                                            else echo 'secondary';
                                        ?>">
                                            <?php
                                                // Display "Completed" if accepted and in the past
                                                if ($session['status'] === 'accepted' && (new DateTime($session['session_date'] . ' ' . $session['session_time'])) <= (new DateTime())) {
                                                    echo 'Completed';
                                                } else {
                                                    echo ucfirst(htmlspecialchars($session['status']));
                                                }
                                            ?>
                                        </span>
                                    </small>
                                </div>
                                <p class="mb-1">Date: <?php echo htmlspecialchars($session['session_date']); ?> at <?php echo htmlspecialchars($session['session_time']); ?></p>
                                <p class="mb-1">Type: <span class="badge bg-info"><?php echo ucfirst(htmlspecialchars($session['session_type'])); ?> Session</span></p>
                                <a href="messages.php?partner_id=<?php echo $session['client_id']; ?>" class="btn btn-sm btn-secondary mt-2">Message Client</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>