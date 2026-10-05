<?php
$conn = new mysqli("localhost", "root", "", "alertiq");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$hashed_password = password_hash("12345", PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = 1");
$stmt->bind_param("s", $hashed_password);

if ($stmt->execute()) {
    echo "Password hashed and updated successfully! You can now delete this file (setup_login.php).<br>";
    echo "Login with: <b>test@example.com</b> / <b>12345</b>";
} else {
    echo "Error: " . $stmt->error;
}

$conn->close();
?>