<?php
session_start();
include 'server.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $sql = "SELECT ADMIN_ID, password FROM admin WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {

        $stmt->bind_result($id, $dbPassword);
        $stmt->fetch();

        // Plain text password comparison
        if ($password === $dbPassword) {

            $_SESSION['admin_id'] = $id;
            $_SESSION['admin_username'] = $username;

            
            header("Location: ../admin.html");
        } else {
            echo "Invalid password!";
        }

    } else {
        echo "Username not found!";
    }

    $stmt->close();
    $conn->close();
}
?>