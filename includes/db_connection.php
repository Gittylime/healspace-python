<?php
$servername = "localhost";
$username = "root"; // Default XAMPP username

// IMPORTANT: This is the line you need to adjust
$password = "Bcd123@#"; // Case 1: If your MySQL 'root' user truly has NO password.

// OR, if you have set a password for your MySQL 'root' user:
// $password = "YOUR_ACTUAL_MYSQL_ROOT_PASSWORD"; // Case 2: Replace with the password you set

// Also, double-check your database name. The previous error message showed 'healspace_thera_db',
// but your code here uses 'healspace_therapy'. Ensure this matches your actual database name in phpMyAdmin.
$dbname = "healspace_therapy"; // Or "healspace_thera_db" if that's the correct one

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // For debugging, it's good to see the exact connection error
    die("Connection failed: " . $conn->connect_error);
}

// echo "Connected successfully"; // Uncomment this line temporarily to confirm successful connection
?>