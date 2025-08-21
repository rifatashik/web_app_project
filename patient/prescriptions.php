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

// Get all prescriptions for the patient with enhanced information
$sql = "SELECT p.*, u.name as doctor_name, 
        (SELECT COUNT(*) FROM prescription_medications WHERE prescription_id = p.id) as drug_count,
        CASE 
            WHEN p.notes LIKE 'Uploaded prescription file:%' THEN 'uploaded'
            ELSE 'written'
        END as prescription_type
        FROM prescriptions p
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE p.patient_id = ?
        ORDER BY p.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$prescriptions = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-prescription me-2"></i>My Prescriptions
                    </h4>
                    <a href="upload-prescription.php" class="btn btn-light btn-sm">
                        <i class="fas fa-upload"></i> Upload New Prescription
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <?php if (empty($prescriptions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-prescription fa-3x text-muted mb-3"></i>
                            <h5>No Prescriptions Found</h5>
                            <p class="text-muted">You haven't uploaded any prescriptions yet.</p>
                            <a href="upload-prescription.php" class="btn btn-primary">
                                Upload Your First Prescription
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Doctor</th>
                                        <th>Medications</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prescriptions as $prescription): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></td>
                                            <td>
                                                <?php if ($prescription['prescription_type'] === 'uploaded'): ?>
                                                    <span class="badge bg-info">
                                                        <i class="fas fa-upload me-1"></i>Uploaded
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-pen me-1"></i>Written
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                                            <td>
                                                <?php if ($prescription['drug_count'] > 0): ?>
                                                    <span class="badge bg-info">
                                                        <?php echo $prescription['drug_count']; ?> medications
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No medications</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
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
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="view-prescription.php?id=<?php echo $prescription['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <?php if ($prescription['prescription_type'] === 'uploaded' && $prescription['status'] === 'pending'): ?>
                                                        <a href="edit-prescription.php?id=<?php echo $prescription['id']; ?>" 
                                                           class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h5>Total Prescriptions</h5>
                            <h3><?php echo count($prescriptions); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h5>Active Prescriptions</h5>
                            <h3><?php echo count(array_filter($prescriptions, function($p) { return $p['status'] === 'active'; })); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Information Card -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>About Your Prescriptions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Prescription Types</h6>
                            <ul class="list-unstyled">
                                <li><span class="badge bg-info">Uploaded</span> - Prescriptions you've uploaded as files</li>
                                <li><span class="badge bg-success">Written</span> - Prescriptions written by your doctor</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Prescription Status</h6>
                            <ul class="list-unstyled">
                                <li><span class="badge bg-warning">Pending Review</span> - Under review by medical staff</li>
                                <li><span class="badge bg-success">Active</span> - Approved and valid</li>
                                <li><span class="badge bg-primary">Completed</span> - Treatment completed</li>
                                <li><span class="badge bg-danger">Cancelled</span> - No longer valid</li>
                            </ul>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <h6>What You Can Do</h6>
                            <ul>
                                <li>Upload new prescription files (JPG, PNG, PDF)</li>
                                <li>View detailed prescription information</li>
                                <li>Edit pending uploaded prescriptions</li>
                                <li>Track prescription status and medications</li>
                                <li>Print prescriptions for pharmacy use</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 