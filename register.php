<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize_input($_POST['role']);
    $medical_id = sanitize_input($_POST['medical_id']);

    // Validate name
    if (empty($name)) {
        $errors[] = "Name is required";
    }

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!validate_email($email)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Email already exists";
        }
    }

    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (!validate_password($password)) {
        $errors[] = "Password must be at least 8 characters long and contain uppercase, lowercase, and numbers";
    }

    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // Validate role
    if (!in_array($role, ['patient', 'doctor', 'pharmacist'])) {
        $errors[] = "Invalid role selected";
    }

    // Validate medical ID for doctors and pharmacists
    if (in_array($role, ['doctor', 'pharmacist']) && empty($medical_id)) {
        $errors[] = "Medical ID is required for doctors and pharmacists";
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user into database
        $sql = "INSERT INTO users (name, email, password, role, medical_id) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $hashed_password, $role, $medical_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $user_id = mysqli_insert_id($conn);
            
            // Create user profile
            $sql = "INSERT INTO user_profiles (user_id) VALUES (?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            
            $success = true;
            
            // Send welcome email
            $email_subject = "Welcome to Prescription Checker";
            $email_message = "Hello $name,<br><br>";
            $email_message .= "Welcome to Prescription Checker! Your account has been created successfully.<br><br>";
            $email_message .= "You can now login to your account and start using our services.<br><br>";
            $email_message .= "Best regards,<br>Prescription Checker Team";
            
            send_email($email, $email_subject, $email_message);
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Prescription Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .register-container {
            max-width: 600px;
            margin: 50px auto;
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
        .success-message {
            color: #28a745;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <h2 class="text-center mb-4">Register</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    Registration successful! You can now login to your account.
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
                    <label for="name">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" required 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <small class="form-text text-muted">
                        Password must be at least 8 characters long and contain uppercase, lowercase, and numbers
                    </small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="patient" <?php echo (isset($_POST['role']) && $_POST['role'] == 'patient') ? 'selected' : ''; ?>>Patient</option>
                        <option value="doctor" <?php echo (isset($_POST['role']) && $_POST['role'] == 'doctor') ? 'selected' : ''; ?>>Doctor</option>
                        <option value="pharmacist" <?php echo (isset($_POST['role']) && $_POST['role'] == 'pharmacist') ? 'selected' : ''; ?>>Pharmacist</option>
                    </select>
                </div>

                <div class="form-group" id="medical_id_group" style="display: none;">
                    <label for="medical_id">Medical ID</label>
                    <input type="text" class="form-control" id="medical_id" name="medical_id"
                           value="<?php echo isset($_POST['medical_id']) ? htmlspecialchars($_POST['medical_id']) : ''; ?>">
                    <small class="form-text text-muted">Required for doctors and pharmacists</small>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </div>

                <div class="text-center">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#role').change(function() {
                if ($(this).val() === 'doctor' || $(this).val() === 'pharmacist') {
                    $('#medical_id_group').show();
                    $('#medical_id').prop('required', true);
                } else {
                    $('#medical_id_group').hide();
                    $('#medical_id').prop('required', false);
                }
            });
        });
    </script>
</body>
</html> 