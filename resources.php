<?php
session_start();
include 'includes/db_connection.php';

// Redirect if not logged in (resources are generally for all users)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$category = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : '';
$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

$sql = "SELECT title, description, link, category FROM mental_health_resources WHERE 1=1";
$params = [];
$types = '';

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= 's';
}
if (!empty($search_query)) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%" . $search_query . "%";
    $params[] = "%" . $search_query . "%";
    $types .= 'ss';
}

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $resources = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $resources = [];
    error_log("Error preparing SQL statement for resources: " . $conn->error);
}

// Fetch all distinct categories for filtering
$categories_query = $conn->query("SELECT DISTINCT category FROM mental_health_resources WHERE category IS NOT NULL ORDER BY category");
$all_categories = [];
while ($row = $categories_query->fetch_assoc()) {
    $all_categories[] = $row['category'];
}

$conn->close();
include 'includes/header.php';
?>

<h2 class="mb-4">Mental Health Resources</h2>
<p class="lead">Explore articles, links, and educational materials to support your well-being.</p>

<form action="resources.php" method="GET" class="mb-4">
    <div class="row g-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search resources..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <div class="col-md-4">
            <select name="category" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($all_categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary">Filter / Search</button>
            <a href="resources.php" class="btn btn-secondary ms-2">Reset</a>
        </div>
    </div>
</form>

<?php if (empty($resources)): ?>
    <div class="alert alert-info" role="alert">
        No resources found matching your criteria.
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($resources as $res): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($res['title']); ?></h5>
                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($res['category']); ?></h6>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($res['description'])); ?></p>
                        <a href="<?php echo htmlspecialchars($res['link']); ?>" class="card-link" target="_blank" rel="noopener noreferrer">Read More <i class="fas fa-external-link-alt"></i></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>