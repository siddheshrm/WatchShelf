<?php
require_once '../../includes/auth.php';
require_once '../../config/connection.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: list.php");
    exit();
}

$watchId = (int) $_GET['id'];

// Get image folder
$stmt = $conn->prepare("SELECT image_folder FROM watches WHERE id = ?");
if (!$stmt) {
    die($conn->error);
}

$stmt->bind_param("i", $watchId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: list.php");
    exit();
}

$watch = $result->fetch_assoc();
$imageFolder = WATCH_IMAGE_DIR . '/' . $watch['image_folder'];

$stmt->close();

/* Delete watch
   Retailers are automatically deleted because of ON DELETE CASCADE */
$stmt = $conn->prepare("DELETE FROM watches WHERE id = ?");
if (!$stmt) {
    die($conn->error);
}

$stmt->bind_param("i", $watchId);

if ($stmt->execute()) {
    // Delete image folder if it exists
    if (is_dir($imageFolder)) {

        foreach (glob($imageFolder . "/*") as $file) {
            unlink($file);
        }

        rmdir($imageFolder);
    }
}

$stmt->close();
$conn->close();

header("Location: list.php");
exit();