<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a patient
if (!is_logged_in() || !check_role('patient')) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get user settings
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = "All password fields are required.";
                } elseif (!password_verify($current_password, $user['password'])) {
                    $error_message = "Current password is incorrect.";
                } elseif ($new_password !== $confirm_password) {
                    $error_message = "New passwords do not match.";
                } elseif (!validate_password($new_password)) {
                    $error_message = "New password must be at least 8 characters long and contain uppercase, lowercase, and numbers.";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_message = "Password updated successfully!";
                    } else {
                        $error_message = "Error updating password: " . mysqli_error($conn);
                    }
                }
                break;

            case 'update_notifications':
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
                $prescription_reminders = isset($_POST['prescription_reminders']) ? 1 : 0;
                $status_updates = isset($_POST['status_updates']) ? 1 : 0;

                $sql = "UPDATE user_settings SET 
                        email_notifications = ?,
                        sms_notifications = ?,
                        prescription_reminders = ?,
                        status_updates = ?
                        WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iiiii", 
                    $email_notifications, 
                    $sms_notifications, 
                    $prescription_reminders, 
                    $status_updates, 
                    $user_id
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Notification preferences updated successfully!";
                } else {
                    $error_message = "Error updating notification preferences: " . mysqli_error($conn);
                }
                break;

            case 'update_privacy':
                $share_medical_history = isset($_POST['share_medical_history']) ? 1 : 0;
                $share_prescriptions = isset($_POST['share_prescriptions']) ? 1 : 0;
                $share_allergies = isset($_POST['share_allergies']) ? 1 : 0;

                $sql = "UPDATE user_settings SET 
                        share_medical_history = ?,
                        share_prescriptions = ?,
                        share_allergies = ?
                        WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iiii", 
                    $share_medical_history, 
                    $share_prescriptions, 
                    $share_allergies, 
                    $user_id
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_message = "Privacy settings updated successfully!";
                } else {
                    $error_message = "Error updating privacy settings: " . mysqli_error($conn);
                }
                break;
        }
    }
}

// Get user settings
$sql = "SELECT * FROM user_settings WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$settings = mysqli_fetch_assoc($result);

// If no settings exist, create default settings
if (!$settings) {
    $sql = "INSERT INTO user_settings (
        user_id, 
        email_notifications, 
        sms_notifications, 
        prescription_updates, 
        patient_messages,
        prescription_reminders,
        status_updates,
        share_medical_history,
        share_prescriptions,
        share_allergies
    ) VALUES (?, 1, 1, 1, 1, 1, 1, 1, 1, 1)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    
    // Fetch the newly created settings
    $sql = "SELECT * FROM user_settings WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $settings = mysqli_fetch_assoc($result);
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Password Change Card -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text">Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>

            <!-- Notification Preferences Card -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Notification Preferences</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_notifications">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications"
                                       <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="email_notifications">Email Notifications</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications"
                                       <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="sms_notifications">SMS Notifications</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="prescription_reminders" name="prescription_reminders"
                                       <?php echo $settings['prescription_reminders'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="prescription_reminders">Prescription Reminders</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="status_updates" name="status_updates"
                                       <?php echo $settings['status_updates'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status_updates">Status Updates</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Preferences</button>
                    </form>
                </div>
            </div>

            <!-- Privacy Settings Card -->
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Privacy Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_privacy">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="share_medical_history" name="share_medical_history"
                                       <?php echo $settings['share_medical_history'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="share_medical_history">Share Medical History with Healthcare Providers</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="share_prescriptions" name="share_prescriptions"
                                       <?php echo $settings['share_prescriptions'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="share_prescriptions">Share Prescription History</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="share_allergies" name="share_allergies"
                                       <?php echo $settings['share_allergies'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="share_allergies">Share Allergy Information</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Privacy Settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 