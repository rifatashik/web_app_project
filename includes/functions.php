<?php
session_start();

/**
 * Start session if not already started
 */
function start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to generate JWT token
function generate_jwt($user_id, $role) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user_id,
        'role' => $role,
        'exp' => time() + (60 * 60 * 24) // 24 hours expiration
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, 'your-secret-key', true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

// Function to verify JWT token
function verify_jwt($token) {
    $parts = explode('.', $token);
    if (count($parts) != 3) {
        return false;
    }

    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
    if (!$payload || $payload['exp'] < time()) {
        return false;
    }

    return $payload;
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to check user role
function check_role($required_role) {
    if (!is_logged_in()) {
        return false;
    }
    return $_SESSION['role'] === $required_role;
}

// Function to redirect user
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function to generate random OTP
function generate_otp() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Function to send email
function send_email($to, $subject, $message) {
    // Set email headers
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Prescription Checker <noreply@prescriptionchecker.com>',
        'Reply-To: noreply@prescriptionchecker.com',
        'X-Mailer: PHP/' . phpversion()
    );

    // Try to send email
    $mail_sent = mail($to, $subject, $message, implode("\r\n", $headers));
    
    // Log email sending attempt
    if (!$mail_sent) {
        error_log("Failed to send email to: $to, Subject: $subject");
    }
    
    return $mail_sent;
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate password strength
function validate_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// Function to create notification
function create_notification($user_id, $message, $type = 'info') {
    global $conn;
    $message = sanitize_input($message);
    $sql = "INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $message, $type);
    return mysqli_stmt_execute($stmt);
}

// Function to get user notifications
function get_user_notifications($user_id) {
    global $conn;
    $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Function to mark notification as read
function mark_notification_read($notification_id) {
    global $conn;
    $sql = "UPDATE notifications SET is_read = TRUE WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $notification_id);
    return mysqli_stmt_execute($stmt);
}
?> 