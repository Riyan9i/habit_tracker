<?php
// Database configuration
define('DB_HOST', 'sql12.freesqldatabase.com');  // Your public DB host
define('DB_USER', 'sql12816822');               // Your DB username
define('DB_PASS', 'K5bS27p3tr');               // Your DB password
define('DB_NAME', 'sql12816822');              // Your DB name
define('DB_PORT', 3306);                        // Optional, default is 3306

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Timezone
date_default_timezone_set('Asia/Dhaka');
?>
