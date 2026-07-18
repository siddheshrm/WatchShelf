<?php
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "watchshelf";
$port = 3377;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    echo '<script>alert("Connection failed");</script>';
    echo '<script>window.location = "index.php";</script>';
    exit;
} else {
    $conn->set_charset("utf8mb4");
    // echo "Connected successfully";
}
