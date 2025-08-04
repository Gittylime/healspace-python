<?php
session_start();
include 'includes/db_connection.php';
include 'includes/header.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = $_POST['user_type'];

    // --- CHANGE: Capturing the new multi-select issues and client summary field. ---
    $mental_issue = isset($_POST['issues']) ? implode(',', $_POST['issues']) : NULL; // Now an array from multi-select
    $summary = isset($_POST['summary']) ? $_POST['summary'] : NULL; // NEW field for clients

    // For therapists
    $specialties = isset($_POST['specialties']) ? $_POST['specialties'] : [];
    $bio = isset($_POST['bio']) ? $_POST['bio'] : NULL;

    if ($password !== $confirm_password) {
        $message = '<div class="alert alert-danger">Passwords do not match!</div>';
    } else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if email already exists
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $message = '<div class="alert alert-warning">Email already registered! Please use a different email or login.</div>';
        } else {
            // --- CHANGE: The SQL query and bind_param now include the new 'summary' field. ---
            // NOTE: You must add a 'summary' column to your 'users' table.
            // Example SQL: ALTER TABLE users ADD summary TEXT;
            $sql = "INSERT INTO users (username, email, password, user_type, mental_issue, bio, summary) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $username, $email, $hashed_password, $user_type, $mental_issue, $bio, $summary);

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id; // Get the newly inserted user's ID

                // If therapist, insert specialties
                if ($user_type === 'therapist' && !empty($specialties)) {
                    $stmt_specialty = $conn->prepare("INSERT INTO therapist_specialties_junction (therapist_id, specialty_id) VALUES (?, ?)");
                    foreach ($specialties as $specialty_id) {
                        $stmt_specialty->bind_param("ii", $user_id, $specialty_id);
                        $stmt_specialty->execute();
                    }
                    $stmt_specialty->close();
                }

                $message = '<div class="alert alert-success">Registration successful! You can now <a href="login.php">login</a>.</div>';
            } else {
                $message = '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}

// Fetch specialties for therapist signup
$specialties_query = $conn->query("SELECT specialty_id, specialty_name FROM therapist_specialties ORDER BY specialty_name");
$all_specialties = [];
while ($row = $specialties_query->fetch_assoc()) {
    $all_specialties[] = $row;
}

$conn->close();
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <h2 class="text-center mb-4">Sign Up for HealSpace</h2>
        <?php echo $message; ?>
        <form action="signup.php" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <div class="mb-3">
                <label for="user_type" class="form-label">I am a:</label>
                <select class="form-select" id="user_type" name="user_type" required onchange="toggleUserTypeFields()">
                    <option value="">Select...</option>
                    <option value="client">Client</option>
                    <option value="therapist">Therapist</option>
                </select>
            </div>

            <div id="clientFields" style="display: none;">
                <!-- --- CHANGE: Replaced the single input field with a multi-select dropdown. --- -->
                <div class="mb-3">
                    <label for="issues" class="form-label">Select your primary mental issues / interests:</label>
                    <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple options.</small>
                    <select multiple class="form-select" id="issues" name="issues[]" size="5">
                        <option value="Anxiety">Anxiety</option>
                        <option value="Depression">Depression</option>
                        <option value="Stress">Stress</option>
                        <option value="Relationship Issues">Relationship Issues</option>
                        <option value="Grief and Loss">Grief and Loss</option>
                        <option value="Trauma">Trauma</option>
                        <option value="Self-Esteem">Self-Esteem</option>
                        <option value="Work/Career Issues">Work/Career Issues</option>
                    </select>
                </div>
                <!-- --- CHANGE: Added a new textarea for the client's summary. --- -->
                <div class="mb-3">
                    <label for="summary" class="form-label">Summary of your concerns (optional)</label>
                    <textarea class="form-control" id="summary" name="summary" rows="4" placeholder="Briefly describe what you are looking for..."></textarea>
                </div>
            </div>

            <div id="therapistFields" style="display: none;">
                <div class="mb-3">
                    <label for="bio" class="form-label">Biography / Professional Summary</label>
                    <textarea class="form-control" id="bio" name="bio" rows="4"></textarea>
                </div>
                <div class="mb-3">
                    <label for="specialties" class="form-label">Specialties (select all that apply)</label>
                    <select class="form-select" id="specialties" name="specialties[]" multiple>
                        <?php foreach ($all_specialties as $specialty): ?>
                            <option value="<?php echo htmlspecialchars($specialty['specialty_id']); ?>">
                                <?php echo htmlspecialchars($specialty['specialty_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple.</small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Sign Up</button>
        </form>
        <p class="text-center mt-3">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</div>

<script>
    function toggleUserTypeFields() {
        const userType = document.getElementById('user_type').value;
        const clientFields = document.getElementById('clientFields');
        const therapistFields = document.getElementById('therapistFields');

        if (userType === 'client') {
            clientFields.style.display = 'block';
            therapistFields.style.display = 'none';
        } else if (userType === 'therapist') {
            clientFields.style.display = 'none';
            therapistFields.style.display = 'block';
        } else {
            clientFields.style.display = 'none';
            therapistFields.style.display = 'none';
        }
    }
    // Call on page load to set initial state
    toggleUserTypeFields();
</script>

<?php include 'includes/footer.php'; ?>
