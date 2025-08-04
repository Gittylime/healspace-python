<?php
session_start();
include 'includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$message = '';
$current_chat_partner_id = null;
$chat_partner_name = '';
$conversations = []; // To store a list of users the current user has chatted with

// Determine potential chat partners
$partner_type = ($user_type === 'client') ? 'therapist' : 'client';

// Fetch users for the current user to chat with
if ($user_type === 'client') {
    // Clients can chat with therapists they have sessions with, or perhaps search for new ones
    $sql_partners = "SELECT DISTINCT u.user_id, u.username, u.user_type
                     FROM users u
                     JOIN sessions s ON (s.therapist_id = u.user_id AND s.client_id = ?) OR (s.client_id = u.user_id AND s.therapist_id = ?)
                     WHERE u.user_id != ?
                     ORDER BY u.username";
} else { // Therapist
    // Therapists can chat with clients they have sessions with
    $sql_partners = "SELECT DISTINCT u.user_id, u.username, u.user_type
                     FROM users u
                     JOIN sessions s ON (s.client_id = u.user_id AND s.therapist_id = ?) OR (s.therapist_id = u.user_id AND s.client_id = ?)
                     WHERE u.user_id != ?
                     ORDER BY u.username";
}

$stmt_partners = $conn->prepare($sql_partners);
if ($stmt_partners) {
    $stmt_partners->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt_partners->execute();
    $result_partners = $stmt_partners->get_result();
    while ($row = $result_partners->fetch_assoc()) {
        $conversations[] = $row;
    }
    $stmt_partners->close();
} else {
    error_log("Error fetching chat partners: " . $conn->error);
}


// Handle selecting a chat partner (from GET parameter or first in list)
if (isset($_GET['partner_id']) && filter_input(INPUT_GET, 'partner_id', FILTER_VALIDATE_INT)) {
    $current_chat_partner_id = $_GET['partner_id'];
    // Verify if this partner exists and is a valid chat partner
    $stmt_partner_name = $conn->prepare("SELECT username FROM users WHERE user_id = ? AND user_type = ?");
    if ($stmt_partner_name) {
        $stmt_partner_name->bind_param("is", $current_chat_partner_id, $partner_type);
        $stmt_partner_name->execute();
        $result_partner_name = $stmt_partner_name->get_result();
        if ($row = $result_partner_name->fetch_assoc()) {
            $chat_partner_name = htmlspecialchars($row['username']);
        } else {
            $current_chat_partner_id = null; // Partner not found or invalid type
        }
        $stmt_partner_name->close();
    }
} else if (!empty($conversations)) {
    // If no partner selected, default to the first one in the list
    $current_chat_partner_id = $conversations[0]['user_id'];
    $chat_partner_name = htmlspecialchars($conversations[0]['username']);
}


// Handle sending a message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['message_content']) && $current_chat_partner_id) {
    $message_content = filter_input(INPUT_POST, 'message_content', FILTER_SANITIZE_STRING);

    if (!empty($message_content)) {
        $stmt_send_msg = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_content) VALUES (?, ?, ?)");
        if ($stmt_send_msg) {
            $stmt_send_msg->bind_param("iis", $user_id, $current_chat_partner_id, $message_content);
            if ($stmt_send_msg->execute()) {
                // Message sent, refresh to show it (or use AJAX later)
                header("Location: messages.php?partner_id=" . $current_chat_partner_id);
                exit();
            } else {
                $message = '<div class="alert alert-danger">Error sending message: ' . $stmt_send_msg->error . '</div>';
            }
            $stmt_send_msg->close();
        } else {
            $message = '<div class="alert alert-danger">Database error during message send preparation: ' . $conn->error . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Message cannot be empty.</div>';
    }
}

// Fetch messages for the current conversation
$current_conversation_messages = [];
if ($current_chat_partner_id) {
    $stmt_messages = $conn->prepare(
        "SELECT m.message_content, m.timestamp, u.username AS sender_username, m.sender_id
         FROM messages m
         JOIN users u ON m.sender_id = u.user_id
         WHERE (m.sender_id = ? AND m.receiver_id = ?)
            OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.timestamp ASC"
    );
    if ($stmt_messages) {
        $stmt_messages->bind_param("iiii", $user_id, $current_chat_partner_id, $current_chat_partner_id, $user_id);
        $stmt_messages->execute();
        $result_messages = $stmt_messages->get_result();
        while ($row = $result_messages->fetch_assoc()) {
            $current_conversation_messages[] = $row;
        }
        $stmt_messages->close();
    } else {
        error_log("Error fetching messages: " . $conn->error);
    }
}

$conn->close();
include 'includes/header.php';
?>

<h2 class="mb-4">Messages</h2>
<p class="lead">Communicate securely with your <?php echo ($user_type === 'client') ? 'therapists' : 'clients'; ?>.</p>

<?php echo $message; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Conversations</h5>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (empty($conversations)): ?>
                    <li class="list-group-item text-muted">No <?php echo ($user_type === 'client') ? 'therapists' : 'clients'; ?> to chat with yet.</li>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <a href="messages.php?partner_id=<?php echo htmlspecialchars($conv['user_id']); ?>"
                           class="list-group-item list-group-item-action <?php echo ($current_chat_partner_id == $conv['user_id']) ? 'active' : ''; ?>">
                           <?php echo htmlspecialchars($conv['username']); ?>
                           <?php if ($conv['user_type'] === 'therapist') echo ' (Dr.)'; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm h-100 d-flex flex-column">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <?php echo $current_chat_partner_id ? 'Chat with ' . $chat_partner_name : 'Select a conversation'; ?>
                </h5>
            </div>
            <div class="card-body overflow-auto" style="height: 400px; flex-grow: 1;" id="chat-box">
                <?php if ($current_chat_partner_id): ?>
                    <?php if (empty($current_conversation_messages)): ?>
                        <div class="alert alert-info text-center">Start a new conversation!</div>
                    <?php else: ?>
                        <?php foreach ($current_conversation_messages as $msg): ?>
                            <div class="d-flex <?php echo ($msg['sender_id'] == $user_id) ? 'justify-content-end' : 'justify-content-start'; ?> mb-2">
                                <div class="card p-2 <?php echo ($msg['sender_id'] == $user_id) ? 'bg-primary text-white' : 'bg-light'; ?>" style="max-width: 75%;">
                                    <div class="small text-muted mb-1 <?php echo ($msg['sender_id'] == $user_id) ? 'text-end text-white-50' : 'text-start'; ?>">
                                        <?php echo htmlspecialchars($msg['sender_username']); ?> at <?php echo date("H:i", strtotime($msg['timestamp'])); ?>
                                    </div>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($msg['message_content'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-warning text-center">Please select a conversation from the left to view messages.</div>
                <?php endif; ?>
            </div>
            <?php if ($current_chat_partner_id): ?>
                <div class="card-footer">
                    <form action="messages.php?partner_id=<?php echo htmlspecialchars($current_chat_partner_id); ?>" method="POST" class="d-flex">
                        <textarea name="message_content" class="form-control me-2" rows="1" placeholder="Type your message..." required></textarea>
                        <button type="submit" class="btn btn-primary">Send</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Scroll chat to bottom on load
    const chatBox = document.getElementById('chat-box');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    // Optional: Auto-refresh chat (simple polling, not true real-time)
    // You would typically use WebSockets for better real-time experience
    // if (<?php echo json_encode($current_chat_partner_id !== null); ?>) {
    //     setInterval(function() {
    //         // Make an AJAX call to fetch new messages and update chat-box div
    //         // This is a basic example, full implementation would be more complex
    //         // Example: fetch('ajax/get_new_messages.php?partner_id=...');
    //     }, 5000); // Refresh every 5 seconds
    // }
</script>

<?php include 'includes/footer.php'; ?>