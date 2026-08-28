<?php

require_once __DIR__ . '/config/db.php';

echo "<h2>School ERP Database Test</h2>";

if ($conn && !$conn->connect_error) {
    echo "<p style='color:green;font-weight:bold;'>";
    echo "Database Connected Successfully!";
    echo "</p>";

    echo "Database: school_erp<br>";
    echo "MySQL Host: localhost";
} else {
    echo "<p style='color:red;font-weight:bold;'>";
    echo "Database Connection Failed.";
    echo "</p>";
}