<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a doctor
if (!is_logged_in() || !check_role('doctor')) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get doctor's current settings
$sql = "SELECT u.*, dp.phone, dp.specialization, dp.qualification, us.* 
        FROM users u 
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id 
        LEFT JOIN user_settings us ON u.id = us.user_id 
        WHERE u.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$doctor = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $specialization = trim($_POST['specialization']);
    $qualification = trim($_POST['qualification']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Notification settings
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $prescription_updates = isset($_POST['prescription_updates']) ? 1 : 0;
    $patient_messages = isset($_POST['patient_messages']) ? 1 : 0;
    
    // Validate input
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required fields.";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Check if email is already taken by another user
            if ($email !== $doctor['email']) {
                $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
                mysqli_stmt_execute($stmt);
                if (mysqli_stmt_get_result($stmt)->num_rows > 0) {
                    throw new Exception("Email is already taken by another user.");
                }
            }
            
            // Update user information
            $sql = "UPDATE users SET name = ?, email = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssi", $name, $email, $user_id);
            mysqli_stmt_execute($stmt);
            
            // Update doctor profile
            if ($doctor['phone'] === null) {
                $sql = "INSERT INTO doctor_profiles (user_id, phone, specialization, qualification) 
                        VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "isss", $user_id, $phone, $specialization, $qualification);
            } else {
                $sql = "UPDATE doctor_profiles SET phone = ?, specialization = ?, qualification = ? 
                        WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssi", $phone, $specialization, $qualification, $user_id);
            }
            mysqli_stmt_execute($stmt);
            
            // Update user settings
            if ($doctor['user_id'] === null) {
                $sql = "INSERT INTO user_settings (user_id, email_notifications, sms_notifications, 
                        prescription_updates, patient_messages) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iiiii", $user_id, $email_notifications, $sms_notifications, 
                                     $prescription_updates, $patient_messages);
            } else {
                $sql = "UPDATE user_settings SET email_notifications = ?, sms_notifications = ?, 
                        prescription_updates = ?, patient_messages = ? 
                        WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iiiii", $email_notifications, $sms_notifications, 
                                     $prescription_updates, $patient_messages, $user_id);
            }
            mysqli_stmt_execute($stmt);
            
            // Update password if provided
            if (!empty($current_password)) {
                if (empty($new_password) || empty($confirm_password)) {
                    throw new Exception("New password and confirmation are required.");
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match.");
                }
                
                // Verify current password
                if (!password_verify($current_password, $doctor['password'])) {
                    throw new Exception("Current password is incorrect.");
                }
                
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
                mysqli_stmt_execute($stmt);
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $success_message = "Settings updated successfully!";
            
            // Refresh doctor data
            $sql = "SELECT u.*, dp.phone, dp.specialization, dp.qualification, us.* 
                    FROM users u 
                    LEFT JOIN doctor_profiles dp ON u.id = dp.user_id 
                    LEFT JOIN user_settings us ON u.id = us.user_id 
                    WHERE u.id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $doctor = mysqli_fetch_assoc($result);
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Settings</h1>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="POST" id="settingsForm">
                        <!-- Profile Information -->
                        <h5 class="card-title mb-4">Profile Information</h5>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($doctor['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($doctor['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization" 
                                       value="<?php echo htmlspecialchars($doctor['specialization'] ?? ''); ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" id="qualification" name="qualification" 
                                       value="<?php echo htmlspecialchars($doctor['qualification'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Change Password -->
                        <h5 class="card-title mb-4">Change Password</h5>
                        <div class="row mb-4">
                            <div class="col-md-12 mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>

                        <!-- Notification Settings -->
                        <h5 class="card-title mb-4">Notification Settings</h5>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" 
                                           name="email_notifications" <?php echo ($doctor['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">Email Notifications</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sms_notifications" 
                                           name="sms_notifications" <?php echo ($doctor['sms_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sms_notifications">SMS Notifications</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="prescription_updates" 
                                           name="prescription_updates" <?php echo ($doctor['prescription_updates'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="prescription_updates">Prescription Updates</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="patient_messages" 
                                           name="patient_messages" <?php echo ($doctor['patient_messages'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="patient_messages">Patient Messages</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Account Information -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">Account Information</h5>
                    <div class="mb-3">
                        <label class="form-label text-muted">Account Type</label>
                        <p class="mb-0">Doctor</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Member Since</label>
                        <p class="mb-0"><?php echo date('F d, Y', strtotime($doctor['created_at'])); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Last Updated</label>
                        <p class="mb-0"><?php echo date('F d, Y', strtotime($doctor['updated_at'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Help & Support -->
            <div class="card shadow">
                <div class="card-body">
                    <h5 class="card-title mb-4">Help & Support</h5>
                    <p class="text-muted mb-3">Need help with your account settings?</p>
                    <a href="../contact.php" class="btn btn-outline-primary">
                        <i class="fas fa-envelope"></i> Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password validation
    const form = document.getElementById('settingsForm');
    form.addEventListener('submit', function(e) {
        const currentPassword = document.getElementById('current_password').value;
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (currentPassword || newPassword || confirmPassword) {
            if (!currentPassword || !newPassword || !confirmPassword) {
                e.preventDefault();
                alert('Please fill in all password fields to change your password.');
            } else if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?> 