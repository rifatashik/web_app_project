<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a patient
if (!is_logged_in() || !check_role('patient')) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success_message = '';
$error_message = '';

// Get prescription details
$sql = "SELECT p.*, 
        CASE 
            WHEN p.notes LIKE 'Uploaded prescription file:%' THEN 'uploaded'
            ELSE 'written'
        END as prescription_type
        FROM prescriptions p
        WHERE p.id = ? AND p.patient_id = ? AND p.status = 'pending'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $prescription_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$prescription = mysqli_fetch_assoc($result);

// If prescription not found or doesn't belong to this patient or is not pending
if (!$prescription || $prescription['prescription_type'] !== 'uploaded') {
    redirect('prescriptions.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis = sanitize_input($_POST['diagnosis']);
    $notes = sanitize_input($_POST['notes']);
    
    // Update prescription
    $update_sql = "UPDATE prescriptions SET diagnosis = ?, notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ssi", $diagnosis, $notes, $prescription_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        $success_message = "Prescription updated successfully!";
        // Refresh prescription data
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $prescription_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $prescription = mysqli_fetch_assoc($result);
    } else {
        $error_message = "Failed to update prescription. Please try again.";
    }
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-edit me-2"></i>Edit Uploaded Prescription
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> You can edit the details of your uploaded prescription before it's reviewed by medical staff.
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">
                                <i class="fas fa-stethoscope me-2"></i>Diagnosis/Condition
                            </label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" 
                                      placeholder="Describe your condition or diagnosis..."><?php echo htmlspecialchars($prescription['diagnosis'] ?? ''); ?></textarea>
                            <div class="form-text">Describe the medical condition or diagnosis for this prescription.</div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">
                                <i class="fas fa-notes-medical me-2"></i>Additional Notes
                            </label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" 
                                      placeholder="Add any additional notes or instructions..."><?php echo htmlspecialchars($prescription['notes'] ?? ''); ?></textarea>
                            <div class="form-text">Include any additional information that might help medical staff understand your prescription.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Prescription
                            </button>
                            <a href="view-prescription.php?id=<?php echo $prescription_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-eye me-2"></i>View Prescription
                            </a>
                            <a href="prescriptions.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Prescriptions
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Prescription Information -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>Prescription Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Prescription ID:</strong> #<?php echo $prescription['id']; ?></p>
                            <p><strong>Upload Date:</strong> <?php echo date('F j, Y', strtotime($prescription['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> 
                                <span class="badge bg-warning">Pending Review</span>
                            </p>
                            <p><strong>Type:</strong> 
                                <span class="badge bg-info">Uploaded Prescription</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 