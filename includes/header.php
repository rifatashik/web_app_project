<?php
if (!isset($_SESSION)) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

// Get user notifications
$notifications = get_user_notifications($_SESSION['user_id']);
$unread_notifications = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unread_notifications++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar {
            background-color: #2c3e50;
            padding: 1rem;
        }
        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
        }
        .nav-link:hover {
            color: white !important;
        }
        .dropdown-menu {
            background-color: #2c3e50;
        }
        .dropdown-item {
            color: rgba(255,255,255,0.8);
        }
        .dropdown-item:hover {
            background-color: #34495e;
            color: white;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1;
            border-radius: 0.25rem;
            background-color: #dc3545;
            color: white;
        }
        .notification-dropdown {
            min-width: 300px;
            padding: 0;
        }
        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item.unread {
            background-color: #f8f9fa;
        }
        .notification-item .time {
            font-size: 0.75rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Prescription Checker</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <?php if ($_SESSION['role'] === 'patient'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="prescriptions.php">
                                <i class="fas fa-prescription"></i> My Prescriptions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="choose-doctor.php">
                                <i class="fas fa-user-md"></i> Choose Doctor
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="upload-prescription.php">
                                <i class="fas fa-upload"></i> Upload Prescription
                            </a>
                        </li>
                    <?php elseif ($_SESSION['role'] === 'doctor'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="write-prescription.php">
                                <i class="fas fa-pen"></i> Write Prescription
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assigned-patients.php">
                                <i class="fas fa-user-md"></i> My Patients
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-prescriptions.php">
                                <i class="fas fa-list"></i> My Prescriptions
                            </a>
                        </li>
                    <?php elseif ($_SESSION['role'] === 'pharmacist'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="verify-prescriptions.php">
                                <i class="fas fa-check-circle"></i> Verify Prescriptions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="drug-inventory.php">
                                <i class="fas fa-pills"></i> Drug Inventory
                            </a>
                        </li>
                    <?php elseif ($_SESSION['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="drugs.php">
                                <i class="fas fa-pills"></i> Drugs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_notifications > 0): ?>
                                <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <h6 class="dropdown-header">Notifications</h6>
                            <?php if (empty($notifications)): ?>
                                <div class="notification-item">
                                    <p class="mb-0">No notifications</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                        <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small class="time">
                                            <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-circle"></i> Profile
                            </a>
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Content will be inserted here -->
    </div>
</body>
</html> 