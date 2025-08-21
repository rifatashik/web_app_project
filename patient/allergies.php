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

// Get user's allergies
$sql = "SELECT allergies FROM user_profiles WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profile = mysqli_fetch_assoc($result);

// Convert allergies string to array
$allergies = [];
if ($profile && !empty($profile['allergies'])) {
    $allergies = array_map('trim', explode(',', $profile['allergies']));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $new_allergy = sanitize_input($_POST['new_allergy']);
                if (!empty($new_allergy)) {
                    if (!in_array($new_allergy, $allergies)) {
                        $allergies[] = $new_allergy;
                        $success_message = "Allergy added successfully!";
                    } else {
                        $error_message = "This allergy is already in your list.";
                    }
                }
                break;

            case 'delete':
                $index = (int)$_POST['index'];
                if (isset($allergies[$index])) {
                    unset($allergies[$index]);
                    $allergies = array_values($allergies); // Reindex array
                    $success_message = "Allergy removed successfully!";
                }
                break;

            case 'update':
                $allergies = [];
                if (isset($_POST['allergies']) && is_array($_POST['allergies'])) {
                    foreach ($_POST['allergies'] as $allergy) {
                        $allergy = sanitize_input($allergy);
                        if (!empty($allergy)) {
                            $allergies[] = $allergy;
                        }
                    }
                }
                $success_message = "Allergies updated successfully!";
                break;
        }

        // Update allergies in database
        $allergies_str = implode(', ', $allergies);
        $sql = "UPDATE user_profiles SET allergies = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $allergies_str, $user_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            $error_message = "Error updating allergies: " . mysqli_error($conn);
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Manage Allergies</h4>
                    <a href="dashboard.php" class="btn btn-light btn-sm">Back to Dashboard</a>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <!-- Add New Allergy Form -->
                    <form method="POST" action="" class="mb-4">
                        <input type="hidden" name="action" value="add">
                        <div class="input-group">
                            <input type="text" class="form-control" name="new_allergy" 
                                   placeholder="Enter new allergy" required>
                            <button type="submit" class="btn btn-primary">Add Allergy</button>
                        </div>
                    </form>

                    <!-- List of Allergies -->
                    <form method="POST" action="" id="allergiesForm">
                        <input type="hidden" name="action" value="update">
                        <div class="list-group mb-3">
                            <?php if (empty($allergies)): ?>
                                <div class="text-center text-muted py-3">
                                    No allergies recorded. Add your first allergy above.
                                </div>
                            <?php else: ?>
                                <?php foreach ($allergies as $index => $allergy): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <input type="text" class="form-control me-2" 
                                               name="allergies[]" value="<?php echo htmlspecialchars($allergy); ?>">
                                        <button type="button" class="btn btn-danger btn-sm delete-allergy" 
                                                data-index="<?php echo $index; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($allergies)): ?>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Information Card -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Why Track Allergies?</h5>
                </div>
                <div class="card-body">
                    <p>Keeping track of your allergies is important for:</p>
                    <ul>
                        <li>Preventing adverse reactions to medications</li>
                        <li>Helping healthcare providers make informed decisions</li>
                        <li>Ensuring safe prescription of medications</li>
                        <li>Maintaining accurate medical records</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Allergy Form (Hidden) -->
<form method="POST" action="" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="index" id="deleteIndex">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete button clicks
    document.querySelectorAll('.delete-allergy').forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Are you sure you want to remove this allergy?')) {
                const index = this.dataset.index;
                document.getElementById('deleteIndex').value = index;
                document.getElementById('deleteForm').submit();
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?> 