<?php
session_start();
include 'includes/db_connection.php';
include 'includes/header.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT user_id, username, password, user_type FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($user_id, $username, $hashed_password, $user_type);
    $stmt->fetch();

    if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) {
        // Login successful
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['user_type'] = $user_type;

        // Redirect based on user type
        if ($user_type === 'client') {
            header("Location: client_dashboard.php");
        } elseif ($user_type === 'therapist') {
            header("Location: therapist_dashboard.php");
        } else {
            // Admin or other types could have their own dashboard
            header("Location: index.php"); // Default to homepage
        }
        exit();
    } else {
        $message = '<div class="alert alert-danger">Invalid email or password.</div>';
    }
    $stmt->close();
}
$conn->close();
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <h2 class="text-center mb-4">Login to HealSpace</h2>
        <?php echo $message; ?>
        <form action="login.php" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <p class="text-center mt-3">Don't have an account? <a href="signup.php">Sign Up here</a></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>