<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a patient
if (!is_logged_in() || !check_role('patient')) {
    redirect('../login.php');
}

// Check if prescription ID is provided
if (!isset($_GET['id'])) {
    redirect('dashboard.php');
}

$prescription_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get prescription details with enhanced information
$sql = "SELECT p.*, 
        d.name as doctor_name, 
        d.email as doctor_email,
        CASE 
            WHEN p.notes LIKE 'Uploaded prescription file:%' THEN 'uploaded'
            ELSE 'written'
        END as prescription_type
        FROM prescriptions p
        JOIN users d ON p.doctor_id = d.id
        WHERE p.id = ? AND p.patient_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $prescription_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$prescription = mysqli_fetch_assoc($result);

// If prescription not found or doesn't belong to this patient
if (!$prescription) {
    redirect('dashboard.php');
}

// Get prescription medications
$sql = "SELECT * FROM prescription_medications WHERE prescription_id = ? ORDER BY id";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $prescription_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$medications = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Extract uploaded file information if this is an uploaded prescription
$uploaded_file_info = null;
if ($prescription['prescription_type'] === 'uploaded' && strpos($prescription['notes'], 'Uploaded prescription file:') === 0) {
    $uploaded_file_info = str_replace('Uploaded prescription file: ', '', $prescription['notes']);
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-prescription me-2"></i>Prescription Details
                    </h5>
                    <div>
                        <a href="prescriptions.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Prescriptions
                        </a>
                        <button onclick="window.print()" class="btn btn-primary btn-sm">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Prescription Type Badge -->
                    <div class="mb-3">
                        <?php if ($prescription['prescription_type'] === 'uploaded'): ?>
                            <span class="badge bg-info fs-6">
                                <i class="fas fa-upload me-1"></i>Uploaded Prescription
                            </span>
                        <?php else: ?>
                            <span class="badge bg-success fs-6">
                                <i class="fas fa-pen me-1"></i>Written Prescription
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="mb-3">Doctor Information</h6>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($prescription['doctor_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($prescription['doctor_email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Prescription Information</h6>
                            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($prescription['created_at'])); ?></p>
                            <p><strong>Status:</strong> 
                                <?php
                                $status_class = '';
                                $status_text = '';
                                switch ($prescription['status']) {
                                    case 'active':
                                        $status_class = 'bg-success';
                                        $status_text = 'Active';
                                        break;
                                    case 'completed':
                                        $status_class = 'bg-primary';
                                        $status_text = 'Completed';
                                        break;
                                    case 'cancelled':
                                        $status_class = 'bg-danger';
                                        $status_text = 'Cancelled';
                                        break;
                                    case 'pending':
                                        $status_class = 'bg-warning';
                                        $status_text = 'Pending Review';
                                        break;
                                    default:
                                        $status_class = 'bg-secondary';
                                        $status_text = ucfirst($prescription['status']);
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Uploaded File Information -->
                    <?php if ($uploaded_file_info): ?>
                        <div class="mb-4">
                            <h6 class="mb-3">
                                <i class="fas fa-file me-2"></i>Uploaded File
                            </h6>
                            <div class="alert alert-info">
                                <strong>File Name:</strong> <?php echo htmlspecialchars($uploaded_file_info); ?>
                                <br>
                                <small class="text-muted">
                                    This prescription was uploaded as a file and is currently under review by medical staff.
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($prescription['diagnosis'])): ?>
                        <div class="mb-4">
                            <h6 class="mb-3">
                                <i class="fas fa-stethoscope me-2"></i>Diagnosis
                            </h6>
                            <p><?php echo nl2br(htmlspecialchars($prescription['diagnosis'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($prescription['notes']) && $prescription['prescription_type'] === 'written'): ?>
                        <div class="mb-4">
                            <h6 class="mb-3">
                                <i class="fas fa-notes-medical me-2"></i>Notes
                            </h6>
                            <p><?php echo nl2br(htmlspecialchars($prescription['notes'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($medications)): ?>
                        <div class="mb-4">
                            <h6 class="mb-3">
                                <i class="fas fa-pills me-2"></i>Medications
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Drug Name</th>
                                            <th>Generic Name</th>
                                            <th>Dosage</th>
                                            <th>Duration</th>
                                            <th>Instructions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($medications as $medication): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($medication['drug_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($medication['generic_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($medication['dosage']); ?></td>
                                            <td><?php echo htmlspecialchars($medication['duration']); ?></td>
                                            <td><?php echo htmlspecialchars($medication['instructions'] ?? 'As prescribed'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mb-4">
                            <h6 class="mb-3">
                                <i class="fas fa-pills me-2"></i>Medications
                            </h6>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No medications have been added to this prescription yet.
                                <?php if ($prescription['prescription_type'] === 'uploaded'): ?>
                                    Medications will be extracted from the uploaded file during review.
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="mt-4">
                        <?php if ($prescription['prescription_type'] === 'uploaded' && $prescription['status'] === 'pending'): ?>
                            <a href="edit-prescription.php?id=<?php echo $prescription['id']; ?>" 
                               class="btn btn-warning">
                                <i class="fas fa-edit me-2"></i>Edit Prescription
                            </a>
                        <?php endif; ?>
                        
                        <a href="prescriptions.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Prescriptions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 