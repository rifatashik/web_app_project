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

// Get recent prescriptions
$sql = "SELECT p.*, u.name as patient_name, 
        (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as drug_count
        FROM prescriptions p
        LEFT JOIN users u ON p.patient_id = u.id
        WHERE p.doctor_id = ?
        ORDER BY p.created_at DESC
        LIMIT 5";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_prescriptions = mysqli_fetch_all($stmt->get_result(), MYSQLI_ASSOC);

// Get prescription statistics
$sql = "SELECT 
        COUNT(*) as total_prescriptions,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_prescriptions,
        SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified_prescriptions,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_prescriptions
        FROM prescriptions 
        WHERE doctor_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$stats = mysqli_fetch_assoc($stmt->get_result());

// Get recent patients
$sql = "SELECT DISTINCT u.id, u.name, u.email
        FROM users u
        INNER JOIN prescriptions p ON u.id = p.patient_id
        WHERE p.doctor_id = ?
        ORDER BY p.created_at DESC
        LIMIT 5";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_patients = mysqli_fetch_all($stmt->get_result(), MYSQLI_ASSOC);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Statistics Cards -->
        <div class="col-md-3 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Prescriptions</h5>
                    <h2 class="mb-0"><?php echo $stats['total_prescriptions']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Pending</h5>
                    <h2 class="mb-0"><?php echo $stats['pending_prescriptions']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Verified</h5>
                    <h2 class="mb-0"><?php echo $stats['verified_prescriptions']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Rejected</h5>
                    <h2 class="mb-0"><?php echo $stats['rejected_prescriptions']; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Patients Section -->
    <?php
    // Get assigned patients for this doctor
    $assigned_patients_sql = "SELECT u.id, u.name, u.email, pd.assigned_at
                              FROM patient_doctor pd
                              JOIN users u ON pd.patient_id = u.id
                              WHERE pd.doctor_id = ?
                              ORDER BY pd.assigned_at DESC";
    $assigned_stmt = mysqli_prepare($conn, $assigned_patients_sql);
    mysqli_stmt_bind_param($assigned_stmt, "i", $user_id);
    mysqli_stmt_execute($assigned_stmt);
    $assigned_patients = mysqli_fetch_all($assigned_stmt->get_result(), MYSQLI_ASSOC);
    mysqli_stmt_close($assigned_stmt);
    ?>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-user-md me-2"></i>My Assigned Patients
                    </h5>
                    <span class="badge bg-light text-dark"><?php echo count($assigned_patients); ?> patients</span>
                </div>
                <div class="card-body">
                    <?php if (empty($assigned_patients)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                            <h5>No Assigned Patients</h5>
                            <p class="text-muted">Patients will appear here once they choose you as their doctor.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Patient Name</th>
                                        <th>Email</th>
                                        <th>Assigned Since</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_patients as $patient): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($patient['name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($patient['assigned_at'])); ?></td>
                                            <td>
                                                <a href="write-prescription.php?patient_id=<?php echo $patient['id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-pen"></i> Write Prescription
                                                </a>
                                                <a href="view-patient-prescriptions.php?patient_id=<?php echo $patient['id']; ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i> View History
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Prescriptions -->
        <div class="col-md-8 mb-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Prescriptions</h5>
                    <a href="prescriptions.php" class="btn btn-light btn-sm">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_prescriptions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-prescription fa-3x text-muted mb-3"></i>
                            <h5>No Prescriptions Yet</h5>
                            <p class="text-muted">You haven't written any prescriptions yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>Medications</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_prescriptions as $prescription): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($prescription['patient_name']); ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $prescription['drug_count']; ?> medications
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch ($prescription['status']) {
                                                    case 'pending':
                                                        $status_class = 'bg-warning';
                                                        break;
                                                    case 'verified':
                                                        $status_class = 'bg-success';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'bg-danger';
                                                        break;
                                                    default:
                                                        $status_class = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($prescription['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view-prescription.php?id=<?php echo $prescription['id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Patients -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Recent Patients</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_patients)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No Patients Yet</h5>
                            <p class="text-muted">You haven't treated any patients yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($recent_patients as $patient): ?>
                                <a href="view-patient.php?id=<?php echo $patient['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($patient['name']); ?></h6>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($patient['email']); ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="new-prescription.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Prescription
                        </a>
                        <a href="assign-patient.php" class="btn btn-outline-success">
                            <i class="fas fa-user-plus"></i> Assign Patient
                        </a>
                        <a href="assigned-patients.php" class="btn btn-outline-primary">
                            <i class="fas fa-user-md"></i> My Assigned Patients
                        </a>
                        <a href="profile.php" class="btn btn-outline-primary">
                            <i class="fas fa-user"></i> Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 