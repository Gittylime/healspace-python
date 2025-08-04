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
    $entry_title = filter_input(INPUT_POST, 'entry_title', FILTER_SANITIZE_STRING);
    $entry_content = filter_input(INPUT_POST, 'entry_content', FILTER_SANITIZE_STRING);
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    if (empty($entry_title) || empty($entry_content)) {
        $message = '<div class="alert alert-danger">Title and content cannot be empty.</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO journal_entries (user_id, entry_title, entry_content, is_public) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issi", $user_id, $entry_title, $entry_content, $is_public);
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Journal entry saved successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error saving journal entry: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Database error during preparation: ' . $conn->error . '</div>';
        }
    }
}

// Fetch journal entries for the current user
$journal_entries = [];
$stmt_entries = $conn->prepare("SELECT journal_id, entry_title, entry_content, entry_date, is_public FROM journal_entries WHERE user_id = ? ORDER BY entry_date DESC");
if ($stmt_entries) {
    $stmt_entries->bind_param("i", $user_id);
    $stmt_entries->execute();
    $result_entries = $stmt_entries->get_result();
    while ($row = $result_entries->fetch_assoc()) {
        $journal_entries[] = $row;
    }
    $stmt_entries->close();
} else {
    error_log("Error preparing journal entries query: " . $conn->error);
}

$conn->close();
include 'includes/header.php';
?>

<h2 class="mb-4">Your Private Journal</h2>
<p class="lead">Write down your thoughts and reflections. Your entries are private by default.</p>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3>New Journal Entry</h3>
            </div>
            <div class="card-body">
                <form action="journal.php" method="POST">
                    <div class="mb-3">
                        <label for="entry_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="entry_title" name="entry_title" required>
                    </div>
                    <div class="mb-3">
                        <label for="entry_content" class="form-label">Content</label>
                        <textarea class="form-control" id="entry_content" name="entry_content" rows="8" required></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_public" name="is_public">
                        <label class="form-check-label" for="is_public">
                            Make this entry public to your therapists (if you have any and grant permission)
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Entry</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h3>Your Past Entries</h3>
            </div>
            <div class="card-body">
                <?php if (empty($journal_entries)): ?>
                    <div class="alert alert-info">No journal entries yet. Start writing!</div>
                <?php else: ?>
                    <div class="accordion" id="journalAccordion">
                        <?php foreach ($journal_entries as $index => $entry): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                                        <?php echo htmlspecialchars($entry['entry_title']); ?>
                                        <span class="badge bg-<?php echo $entry['is_public'] ? 'success' : 'secondary'; ?> ms-2">
                                            <?php echo $entry['is_public'] ? 'Public' : 'Private'; ?>
                                        </span>
                                        <small class="text-muted ms-auto"><?php echo date("Y-m-d H:i", strtotime($entry['entry_date'])); ?></small>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#journalAccordion">
                                    <div class="accordion-body">
                                        <p><?php echo nl2br(htmlspecialchars($entry['entry_content'])); ?></p>
                                        </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>