<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session if not already started
if (!isset($_SESSION)) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if notification ID is provided
if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit;
}

$notification_id = (int)$_POST['notification_id'];

// Verify that the notification belongs to the current user
$sql = "SELECT id FROM notifications WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $notification_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_fetch_assoc($result)) {
    // Mark notification as read
    $sql = "UPDATE notifications SET is_read = TRUE WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $notification_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Notification not found or unauthorized']);
}
?> 