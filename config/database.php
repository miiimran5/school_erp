<?php
// Database Configuration

$host = "localhost";
$username = "root";
$password = "";
$database = "school_erp";

// Create Connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check Connection
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Set Character Encoding
mysqli_set_charset($conn, "utf8mb4");
?>