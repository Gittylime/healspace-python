<?php
session_start();
include 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mood_level = filter_input(INPUT_POST, 'mood_level', FILTER_VALIDATE_INT);
    $mood_notes = filter_input(INPUT_POST, 'mood_notes', FILTER_SANITIZE_STRING);
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    if ($mood_level === false || $mood_level < 1 || $mood_level > 5) {
        $message = '<div class="alert alert-danger">Please select a valid mood level (1-5).</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO mood_tracker (user_id, mood_level, mood_notes, is_public) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iisi", $user_id, $mood_level, $mood_notes, $is_public);
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Mood entry saved successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error saving mood: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Database error during preparation: ' . $conn->error . '</div>';
        }
    }
}

// Fetch mood history for the current user
$mood_history = [];
$stmt_history = $conn->prepare("SELECT mood_level, mood_notes, entry_date, is_public FROM mood_tracker WHERE user_id = ? ORDER BY entry_date DESC");
if ($stmt_history) {
    $stmt_history->bind_param("i", $user_id);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    while ($row = $result_history->fetch_assoc()) {
        $mood_history[] = $row;
    }
    $stmt_history->close();
} else {
    error_log("Error preparing mood history query: " . $conn->error);
}

$conn->close();
include 'includes/header.php';
?>

<h2 class="mb-4">Mood Tracker</h2>
<p class="lead">Record your mood daily to observe patterns and progress.</p>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3>Log Your Mood Today</h3>
            </div>
            <div class="card-body">
                <form action="mood_tracker.php" method="POST">
                    <div class="mb-3">
                        <label for="mood_level" class="form-label">How are you feeling today? (1-5, 5 being great)</label>
                        <input type="range" class="form-range" id="mood_level" name="mood_level" min="1" max="5" step="1" value="3" oninput="document.getElementById('moodValue').innerText = this.value;">
                        <p class="text-center mt-2">Mood Level: <span id="moodValue">3</span></p>
                    </div>
                    <div class="mb-3">
                        <label for="mood_notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="mood_notes" name="mood_notes" rows="3" placeholder="Describe your day, what influenced your mood, etc."></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_public" name="is_public">
                        <label class="form-check-label" for="is_public">
                            Make this entry public to your therapists (if you have any and grant permission)
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Mood</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3>Your Mood History</h3>
            </div>
            <div class="card-body">
                <?php if (empty($mood_history)): ?>
                    <div class="alert alert-info">No mood entries yet. Start tracking your mood!</div>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($mood_history as $entry): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Date:</strong> <?php echo date("Y-m-d H:i", strtotime($entry['entry_date'])); ?><br>
                                    <strong>Mood:</strong> <?php echo htmlspecialchars($entry['mood_level']); ?> / 5
                                    <?php if (!empty($entry['mood_notes'])): ?>
                                        <p class="text-muted small mb-0 mt-1">Notes: <?php echo nl2br(htmlspecialchars($entry['mood_notes'])); ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-<?php echo $entry['is_public'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $entry['is_public'] ? 'Public' : 'Private'; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>