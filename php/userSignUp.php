<?php
include "Server.php";

function isDuplicate($conn, $username, $email){
    $sql = "SELECT USERS_ID FROM USERS WHERE USERNAME = ? OR EMAIL = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function registerUser($conn, $username, $password, $email, $account_type){

    if (isDuplicate($conn, $username, $email)) {
        echo "<script>alert('Username or Email already exists!'); window.location.href='../userSignUp.html';</script>";
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO USERS (USERNAME, PASSWORD, EMAIL, TYPE) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssss", $username, $hashedPassword, $email, $account_type);

    if ($stmt->execute()) {
        echo "<script>alert('Created Account Successfully'); window.location.href='../userLogIn.html';</script>";
        exit;
    } else {
        echo "<script>alert('Error creating account'); window.location.href='../userSignUp.html';</script>";
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username     = $_POST['username'];
    $password     = $_POST['password']; // hash inside function
    $email        = $_POST['email'];
    $account_type = $_POST['account_type'];

    registerUser($conn, $username, $password, $email, $account_type);
}

$conn->close();
?>