<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a doctor
if (!is_logged_in() || !check_role('doctor')) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];

// Get assigned patients for this doctor
$assigned_patients_sql = "SELECT u.id, u.name, u.email, u.created_at, pd.assigned_at,
                         (SELECT COUNT(*) FROM prescriptions WHERE patient_id = u.id AND doctor_id = ?) as prescription_count,
                         (SELECT COUNT(*) FROM prescriptions WHERE patient_id = u.id AND doctor_id = ? AND status = 'active') as active_prescriptions
                         FROM patient_doctor pd
                         JOIN users u ON pd.patient_id = u.id
                         WHERE pd.doctor_id = ?
                         ORDER BY pd.assigned_at DESC";
$assigned_stmt = mysqli_prepare($conn, $assigned_patients_sql);
mysqli_stmt_bind_param($assigned_stmt, "iii", $user_id, $user_id, $user_id);
mysqli_stmt_execute($assigned_stmt);
$assigned_patients = mysqli_fetch_all($assigned_stmt->get_result(), MYSQLI_ASSOC);
mysqli_stmt_close($assigned_stmt);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Assigned Patients</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2>
                    <i class="fas fa-user-md me-2"></i>My Assigned Patients
                </h2>
                <span class="badge bg-success fs-6"><?php echo count($assigned_patients); ?> patients</span>
            </div>
        </div>
    </div>

    <?php if (empty($assigned_patients)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-user-md fa-4x text-muted mb-4"></i>
                        <h3>No Assigned Patients</h3>
                        <p class="text-muted mb-4">
                            Patients will appear here once they choose you as their doctor through the patient portal.
                        </p>
                        <a href="dashboard.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($assigned_patients as $patient): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card shadow h-100">
                        <div class="card-header bg-primary text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($patient['name']); ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <p class="mb-1">
                                    <i class="fas fa-envelope me-2 text-muted"></i>
                                    <?php echo htmlspecialchars($patient['email']); ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-calendar me-2 text-muted"></i>
                                    Patient since: <?php echo date('M d, Y', strtotime($patient['created_at'])); ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-user-md me-2 text-muted"></i>
                                    Assigned since: <?php echo date('M d, Y', strtotime($patient['assigned_at'])); ?>
                                </p>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="text-center">
                                        <h5 class="text-primary mb-0"><?php echo $patient['prescription_count']; ?></h5>
                                        <small class="text-muted">Total Prescriptions</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <h5 class="text-success mb-0"><?php echo $patient['active_prescriptions']; ?></h5>
                                        <small class="text-muted">Active Prescriptions</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="write-prescription.php?patient_id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-pen me-2"></i>Write Prescription
                                </a>
                                <a href="view-patient-prescriptions.php?patient_id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-history me-2"></i>View History
                                </a>
                                <a href="patient-details.php?patient_id=<?php echo $patient['id']; ?>" 
                                   class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-user me-2"></i>Patient Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Summary Statistics -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Summary Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h4 class="text-primary"><?php echo count($assigned_patients); ?></h4>
                                <p class="text-muted">Total Assigned Patients</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h4 class="text-success">
                                    <?php 
                                    $total_prescriptions = array_sum(array_column($assigned_patients, 'prescription_count'));
                                    echo $total_prescriptions;
                                    ?>
                                </h4>
                                <p class="text-muted">Total Prescriptions</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h4 class="text-warning">
                                    <?php 
                                    $total_active = array_sum(array_column($assigned_patients, 'active_prescriptions'));
                                    echo $total_active;
                                    ?>
                                </h4>
                                <p class="text-muted">Active Prescriptions</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h4 class="text-info">
                                    <?php 
                                    $avg_prescriptions = count($assigned_patients) > 0 ? round($total_prescriptions / count($assigned_patients), 1) : 0;
                                    echo $avg_prescriptions;
                                    ?>
                                </h4>
                                <p class="text-muted">Avg Prescriptions/Patient</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?> 