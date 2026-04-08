<?php
session_start();
include "Server.php";

function loginUser($conn, $username, $password) {

    // Prepare statement
    $stmt = $conn->prepare("SELECT USERS_ID, USERNAME, PASSWORD, TYPE FROM USERS WHERE USERNAME = ?");
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        // Verify hashed password
        if (password_verify($password, $user['PASSWORD'])) {

            $_SESSION['user_id'] = $user['USERS_ID'];
            $_SESSION['username'] = $user['USERNAME'];
            $_SESSION['type'] = $user['TYPE'];

            if ($user['TYPE'] === "merchant") {
                header("Location: merchant.php");
            } else {
                header("Location: userHome.php");
            }
            exit;

        } else {
            echo "<script>alert('Invalid Password'); window.location.href='../userLogIn.html';</script>";
        }

    } else {
        echo "<script>alert('User not found'); window.location.href='../userLogIn.html';</script>";
    }

    $stmt->close();
}

// Call function when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    loginUser($conn, $username, $password);
}

$conn->close();
?>