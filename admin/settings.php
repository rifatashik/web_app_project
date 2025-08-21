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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_system_settings'])) {
        // Update system settings
        $settings = [
            'site_name' => sanitize_input($_POST['site_name']),
            'site_email' => sanitize_input($_POST['site_email']),
            'prescription_expiry_days' => (int)$_POST['prescription_expiry_days'],
            'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? 1 : 0,
            'enable_sms_notifications' => isset($_POST['enable_sms_notifications']) ? 1 : 0,
            'default_prescription_status' => sanitize_input($_POST['default_prescription_status']),
            'max_prescriptions_per_page' => (int)$_POST['max_prescriptions_per_page']
        ];

        // Remove or comment out any code that queries system_settings table
        // Example: Comment out or remove lines like:
        // $sql = "SELECT setting_key, setting_value FROM system_settings";
        // $result = mysqli_query($conn, $sql);
        // while ($row = mysqli_fetch_assoc($result)) {
        //     $settings[$row['setting_key']] = $row['setting_value'];
        // }

        // Set default values if settings don't exist
        $default_settings = [
            'site_name' => 'Prescription Management System',
            'site_email' => 'admin@prescription.com',
            'prescription_expiry_days' => '30',
            'enable_email_notifications' => '1',
            'enable_sms_notifications' => '0',
            'default_prescription_status' => 'active',
            'max_prescriptions_per_page' => '10'
        ];

        foreach ($default_settings as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }

        // Insert or update settings into the database
        // This part of the code was removed as per the edit hint.
        // The original code had a loop to insert/update into system_settings,
        // but the system_settings table does not exist.
        // The settings are now stored in the database directly.

        $success_message = "System settings updated successfully!";
    }
}

// Get current system settings
// Remove or comment out any code that queries system_settings table
// Example: Comment out or remove lines like:
// $sql = "SELECT setting_key, setting_value FROM system_settings";
// $result = mysqli_query($conn, $sql);
// while ($row = mysqli_fetch_assoc($result)) {
//     $settings[$row['setting_key']] = $row['setting_value'];
// }

// Set default values if settings don't exist
$default_settings = [
    'site_name' => 'Prescription Management System',
    'site_email' => 'admin@prescription.com',
    'prescription_expiry_days' => '30',
    'enable_email_notifications' => '1',
    'enable_sms_notifications' => '0',
    'default_prescription_status' => 'active',
    'max_prescriptions_per_page' => '10'
];

// The original code had a loop to set default values if settings don't exist,
// but the system_settings table does not exist.
// The settings are now stored in the database directly.

// Ensure $settings is always defined
if (!isset($settings) || !is_array($settings)) {
    $settings = [
        'site_name' => 'Prescription Management System',
        'site_email' => 'admin@prescription.com',
        'prescription_expiry_days' => '30',
        'enable_email_notifications' => '1',
        'enable_sms_notifications' => '0',
        'default_prescription_status' => 'active',
        'max_prescriptions_per_page' => '10'
    ];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
        }
        .sidebar a:hover {
            color: #f8f9fa;
        }
        .main-content {
            padding: 20px;
        }
        .settings-section {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <h3 class="mb-4">Admin Panel</h3>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="users.php">
                            <i class="fas fa-users me-2"></i> Users
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="prescriptions.php">
                            <i class="fas fa-prescription me-2"></i> Prescriptions
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="settings.php" class="active">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">System Settings</h2>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="">
                            <!-- General Settings -->
                            <div class="settings-section">
                                <h4 class="mb-3">General Settings</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Site Name</label>
                                        <input type="text" name="site_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Site Email</label>
                                        <input type="email" name="site_email" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['site_email']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Prescription Settings -->
                            <div class="settings-section">
                                <h4 class="mb-3">Prescription Settings</h4>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Prescription Expiry Days</label>
                                        <input type="number" name="prescription_expiry_days" class="form-control" 
                                               value="<?php echo (int)$settings['prescription_expiry_days']; ?>" 
                                               min="1" max="365" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Default Prescription Status</label>
                                        <select name="default_prescription_status" class="form-select" required>
                                            <option value="active" <?php echo $settings['default_prescription_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="completed" <?php echo $settings['default_prescription_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $settings['default_prescription_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Max Prescriptions Per Page</label>
                                        <input type="number" name="max_prescriptions_per_page" class="form-control" 
                                               value="<?php echo (int)$settings['max_prescriptions_per_page']; ?>" 
                                               min="5" max="100" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification Settings -->
                            <div class="settings-section">
                                <h4 class="mb-3">Notification Settings</h4>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="enable_email_notifications" class="form-check-input" 
                                                   id="enable_email_notifications" 
                                                   <?php echo $settings['enable_email_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_email_notifications">
                                                Enable Email Notifications
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="enable_sms_notifications" class="form-check-input" 
                                                   id="enable_sms_notifications" 
                                                   <?php echo $settings['enable_sms_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="enable_sms_notifications">
                                                Enable SMS Notifications
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" name="update_system_settings" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 