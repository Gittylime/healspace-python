<?php
session_start();
include 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'therapist') {
    header("Location: login.php");
    exit();
}

$therapist_id = $_SESSION['user_id'];
$message = '';
$selected_client_id = isset($_GET['client_id']) ? filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_INT) : null;
$selected_client_name = '';

// Get clients associated with this therapist (from accepted sessions)
$clients_query = $conn->prepare("
    SELECT DISTINCT u.user_id, u.username
    FROM users u
    JOIN sessions s ON u.user_id = s.client_id
    WHERE s.therapist_id = ? AND s.status = 'accepted'
    ORDER BY u.username
");

$associated_clients = [];
if ($clients_query) {
    $clients_query->bind_param("i", $therapist_id);
    $clients_query->execute();
    $result_clients = $clients_query->get_result();
    while ($row = $result_clients->fetch_assoc()) {
        $associated_clients[] = $row;
    }
    $clients_query->close();
} else {
    error_log("Error fetching associated clients: " . $conn->error);
}

// If a client is selected, fetch their public mood and journal entries
$client_moods = [];
$client_journals = [];

if ($selected_client_id) {
    // Verify that the selected client is actually associated with this therapist
    $is_associated = false;
    foreach ($associated_clients as $client) {
        if ($client['user_id'] == $selected_client_id) {
            $is_associated = true;
            $selected_client_name = $client['username'];
            break;
        }
    }

    if ($is_associated) {
        // Fetch public mood entries
        $stmt_moods = $conn->prepare("SELECT mood_level, mood_notes, entry_date FROM mood_tracker WHERE user_id = ? AND is_public = TRUE ORDER BY entry_date DESC");
        if ($stmt_moods) {
            $stmt_moods->bind_param("i", $selected_client_id);
            $stmt_moods->execute();
            $result_moods = $stmt_moods->get_result();
            while ($row = $result_moods->fetch_assoc()) {
                $client_moods[] = $row;
            }
            $stmt_moods->close();
        } else {
            error_log("Error fetching client moods: " . $conn->error);
        }

        // Fetch public journal entries
        $stmt_journals = $conn->prepare("SELECT entry_title, entry_content, entry_date FROM journal_entries WHERE user_id = ? AND is_public = TRUE ORDER BY entry_date DESC");
        if ($stmt_journals) {
            $stmt_journals->bind_param("i", $selected_client_id);
            $stmt_journals->execute();
            $result_journals = $stmt_journals->get_result();
            while ($row = $result_journals->fetch_assoc()) {
                $client_journals[] = $row;
            }
            $stmt_journals->close();
        } else {
            error_log("Error fetching client journals: " . $conn->error);
        }
    } else {
        $message = '<div class="alert alert-danger">You do not have permission to view data for this client.</div>';
        $selected_client_id = null; // Clear invalid selection
    }
}

$conn->close();
include 'includes/header.php';
?>

<h2 class="mb-4">Client Data Access</h2>
<p class="lead">View public mood entries and journal entries of your clients who have granted permission.</p>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5>Your Clients</h5>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($associated_clients)): ?>
                    <li class="list-group-item text-muted">No clients associated with you yet.</li>
                <?php else: ?>
                    <?php foreach ($associated_clients as $client): ?>
                        <a href="view_client_data.php?client_id=<?php echo htmlspecialchars($client['user_id']); ?>"
                           class="list-group-item list-group-item-action <?php echo ($selected_client_id == $client['user_id']) ? 'active' : ''; ?>">
                           <?php echo htmlspecialchars($client['username']); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <?php echo $selected_client_id ? 'Data for ' . htmlspecialchars($selected_client_name) : 'Select a client to view their data'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!$selected_client_id): ?>
                    <div class="alert alert-info text-center">Please select a client from the left panel.</div>
                <?php else: ?>
                    <ul class="nav nav-tabs mb-3" id="clientDataTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="mood-tab" data-bs-toggle="tab" data-bs-target="#mood" type="button" role="tab" aria-controls="mood" aria-selected="true">Mood Tracker</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="journal-tab" data-bs-toggle="tab" data-bs-target="#journal" type="button" role="tab" aria-controls="journal" aria-selected="false">Journal</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="clientDataTabContent">
                        <div class="tab-pane fade show active" id="mood" role="tabpanel" aria-labelledby="mood-tab">
                            <?php if (empty($client_moods)): ?>
                                <div class="alert alert-info">No public mood entries for this client.</div>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($client_moods as $mood): ?>
                                        <li class="list-group-item">
                                            <strong>Date:</strong> <?php echo date("Y-m-d H:i", strtotime($mood['entry_date'])); ?><br>
                                            <strong>Mood:</strong> <?php echo htmlspecialchars($mood['mood_level']); ?> / 5
                                            <?php if (!empty($mood['mood_notes'])): ?>
                                                <p class="text-muted small mb-0 mt-1">Notes: <?php echo nl2br(htmlspecialchars($mood['mood_notes'])); ?></p>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="journal" role="tabpanel" aria-labelledby="journal-tab">
                            <?php if (empty($client_journals)): ?>
                                <div class="alert alert-info">No public journal entries for this client.</div>
                            <?php else: ?>
                                <div class="accordion" id="journalClientAccordion">
                                    <?php foreach ($client_journals as $index => $journal): ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="clientJournalHeading<?php echo $index; ?>">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#clientJournalCollapse<?php echo $index; ?>" aria-expanded="false" aria-controls="clientJournalCollapse<?php echo $index; ?>">
                                                    <?php echo htmlspecialchars($journal['entry_title']); ?>
                                                    <small class="text-muted ms-auto"><?php echo date("Y-m-d H:i", strtotime($journal['entry_date'])); ?></small>
                                                </button>
                                            </h2>
                                            <div id="clientJournalCollapse<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="clientJournalHeading<?php echo $index; ?>" data-bs-parent="#journalClientAccordion">
                                                <div class="accordion-body">
                                                    <p><?php echo nl2br(htmlspecialchars($journal['entry_content'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>