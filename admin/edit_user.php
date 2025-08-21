<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('../login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $role = sanitize_input($_POST['role']);

    $errors = [];

    // Validate name
    if (empty($name)) {
        $errors[] = "Name is required";
    }

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists for other users
        $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            $errors[] = "Email already exists";
        }
    }

    // Validate role
    if (empty($role) || !in_array($role, ['patient', 'doctor'])) {
        $errors[] = "Invalid role selected";
    }

    // Check if user exists and is not an admin
    $sql = "SELECT role FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user) {
        $errors[] = "User not found";
    } elseif ($user['role'] === 'admin') {
        $errors[] = "Cannot modify admin user";
    }

    if (empty($errors)) {
        // Update user
        $sql = "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $name, $email, $role, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "User updated successfully";
        } else {
            $_SESSION['error_message'] = "Error updating user: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
}

redirect('users.php'); 