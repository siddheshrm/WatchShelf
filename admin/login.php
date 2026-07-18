<?php
session_start();
require_once '../config/connection.php';
require_once '../config/app.php';

function redirectWithAlert(string $message, string $location): void
{
    echo "<script>";
    echo "alert(" . json_encode($message) . ");";
    echo "window.location.href = " . json_encode($location) . ";";
    echo "</script>";

    exit();
}

// If already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Basic validation
    if (empty($email) || empty($password)) {
        redirectWithAlert("Please enter both email and password.", "login.php");
    }

    // Query the database to check if the user exists
    $sql = "SELECT id, email, password FROM admin_users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Verify the hashed password
        if (password_verify($password, $row['password'])) {
            // Regenerate the session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];

            header("Location: dashboard.php");
            exit();
        } else {
            redirectWithAlert("Invalid email or password.", "login.php");
        }
    } else {
        redirectWithAlert("Invalid email or password.", "login.php");
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | WatchShelf</title>
    <link rel="stylesheet" href="<?= CSS_URL ?>/admin.css">
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <h1>WatchShelf</h1>
            <p>Admin Login</p>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit">Login</button>
            </form>
        </div>
    </div>
</body>

</html>