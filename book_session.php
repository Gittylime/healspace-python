<?php
session_start();
include 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'client') {
    header("Location: login.php");
    exit();
}

$message = '';
$search_specialty_id = isset($_GET['specialty']) ? filter_input(INPUT_GET, 'specialty', FILTER_VALIDATE_INT) : '';
$search_therapist_name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '';

// Fetch all specialties for the filter dropdown
$all_specialties_query = $conn->query("SELECT specialty_id, specialty_name FROM therapist_specialties ORDER BY specialty_name");
$all_specialties = [];
while ($row = $all_specialties_query->fetch_assoc()) {
    $all_specialties[] = $row;
}

// Prepare the query for therapists
$sql = "SELECT DISTINCT u.user_id, u.username, u.email, u.bio, u.profile_picture,
               GROUP_CONCAT(ts.specialty_name ORDER BY ts.specialty_name SEPARATOR ', ') AS specialties
        FROM users u
        JOIN therapist_specialties_junction tsj ON u.user_id = tsj.therapist_id
        JOIN therapist_specialties ts ON tsj.specialty_id = ts.specialty_id
        WHERE u.user_type = 'therapist'";

$params = [];
$types = '';

if (!empty($search_specialty_id)) {
    $sql .= " AND ts.specialty_id = ?";
    $params[] = $search_specialty_id;
    $types .= 'i';
}

if (!empty($search_therapist_name)) {
    $sql .= " AND u.username LIKE ?";
    $params[] = "%" . $search_therapist_name . "%";
    $types .= 's';
}

$sql .= " GROUP BY u.user_id ORDER BY u.username";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $therapists = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $therapists = [];
    error_log("Error preparing therapist search query: " . $conn->error);
    $message = '<div class="alert alert-danger">An error occurred while fetching therapists. Please try again.</div>';
}

$conn->close();
include 'includes/header.php';
?>

<h2 class="mb-4">Book a Session</h2>
<p class="lead">Find a therapist that matches your needs and book an online session.</p>

<?php echo $message; ?>

<form action="book_session.php" method="GET" class="mb-4">
    <div class="row g-3">
        <div class="col-md-4">
            <input type="text" name="name" class="form-control" placeholder="Search by therapist name..." value="<?php echo htmlspecialchars($search_therapist_name); ?>">
        </div>
        <div class="col-md-4">
            <select name="specialty" class="form-select">
                <option value="">All Specialties</option>
                <?php foreach ($all_specialties as $specialty): ?>
                    <option value="<?php echo htmlspecialchars($specialty['specialty_id']); ?>" <?php echo ($search_specialty_id == $specialty['specialty_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($specialty['specialty_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary">Find Therapists</button>
            <a href="book_session.php" class="btn btn-secondary ms-2">Reset</a>
        </div>
    </div>
</form>

<?php if (empty($therapists)): ?>
    <div class="alert alert-info" role="alert">
        No therapists found matching your criteria.
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($therapists as $therapist): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body text-center">
                        <?php if ($therapist['profile_picture']): ?>
                            <img src="uploads/profiles/<?php echo htmlspecialchars($therapist['profile_picture']); ?>" class="rounded-circle mb-3" alt="Profile Picture" style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <img src="images/default_profile.png" class="rounded-circle mb-3" alt="Default Profile Picture" style="width: 100px; height: 100px; object-fit: cover;">
                        <?php endif; ?>
                        <h5 class="card-title">Dr. <?php echo htmlspecialchars($therapist['username']); ?></h5>
                        <p class="card-text text-muted small"><?php echo htmlspecialchars($therapist['specialties']); ?></p>
                        <p class="card-text description-truncate"><?php echo nl2br(htmlspecialchars(substr($therapist['bio'], 0, 100) . (strlen($therapist['bio']) > 100 ? '...' : ''))); ?></p>
                        <a href="therapist_profile.php?id=<?php echo $therapist['user_id']; ?>" class="btn btn-sm btn-outline-info">View Profile & Book</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>