<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$success = false;
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitize_input($_POST['email']);

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!validate_email($email)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email exists
        $sql = "SELECT id, name FROM users WHERE email = ? AND is_verified = 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store reset token
            $sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $reset_token, $token_expiry, $user['id']);

            if (mysqli_stmt_execute($stmt)) {
                // Send reset email
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $reset_token;
                $email_subject = "Password Reset Request";
                $email_message = "Hello " . $user['name'] . ",<br><br>";
                $email_message .= "We received a request to reset your password. Click the link below to reset your password:<br><br>";
                $email_message .= "<a href='$reset_link'>$reset_link</a><br><br>";
                $email_message .= "This link will expire in 1 hour.<br><br>";
                $email_message .= "If you did not request a password reset, please ignore this email.<br><br>";
                $email_message .= "Best regards,<br>Prescription Checker Team";

                if (send_email($email, $email_subject, $email_message)) {
                    $success = true;
                } else {
                    $errors[] = "Failed to send reset email. Please try again.";
                }
            } else {
                $errors[] = "Failed to process request. Please try again.";
            }
        } else {
            $errors[] = "Email not found or account not verified.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Prescription Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .forgot-password-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="forgot-password-container">
            <h2 class="text-center mb-4">Forgot Password</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    Password reset instructions have been sent to your email address.
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div class="error-message"><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                </div>

                <div class="text-center">
                    Remember your password? <a href="login.php">Login here</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 