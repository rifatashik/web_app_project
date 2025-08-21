<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if patient_id is provided
if (!isset($_GET['patient_id']) || !is_numeric($_GET['patient_id'])) {
    header('Location: assigned-patients.php');
    exit();
}

$patient_id = intval($_GET['patient_id']);

// Verify that this patient is assigned to the current doctor
$verify_sql = "SELECT u.id, u.name, u.email FROM patient_doctor pd 
                JOIN users u ON pd.patient_id = u.id 
                WHERE pd.doctor_id = ? AND pd.patient_id = ?";
$verify_stmt = mysqli_prepare($conn, $verify_sql);
mysqli_stmt_bind_param($verify_stmt, "ii", $user_id, $patient_id);
mysqli_stmt_execute($verify_stmt);
$patient_result = mysqli_stmt_get_result($verify_stmt);

if (mysqli_num_rows($patient_result) === 0) {
    header('Location: assigned-patients.php');
    exit();
}

$patient = mysqli_fetch_assoc($patient_result);
mysqli_stmt_close($verify_stmt);

// Get prescriptions for this patient
$prescriptions_sql = "SELECT p.*, 
                      COUNT(pi.id) as medication_count,
                      GROUP_CONCAT(DISTINCT pi.drug_name SEPARATOR ', ') as medications
                      FROM prescriptions p 
                      LEFT JOIN prescription_medications pi ON p.id = pi.prescription_id
                      WHERE p.patient_id = ? AND p.doctor_id = ?
                      GROUP BY p.id
                      ORDER BY p.created_at DESC";
$prescriptions_stmt = mysqli_prepare($conn, $prescriptions_sql);
mysqli_stmt_bind_param($prescriptions_stmt, "ii", $patient_id, $user_id);
mysqli_stmt_execute($prescriptions_stmt);
$prescriptions_result = mysqli_stmt_get_result($prescriptions_stmt);
$prescriptions = mysqli_fetch_all($prescriptions_result, MYSQLI_ASSOC);
mysqli_stmt_close($prescriptions_stmt);

// Get prescription statistics
$stats_sql = "SELECT 
                COUNT(*) as total_prescriptions,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_prescriptions,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_prescriptions,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_prescriptions
                FROM prescriptions 
                WHERE patient_id = ? AND doctor_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, "ii", $patient_id, $user_id);
mysqli_stmt_execute($stats_stmt);
$stats_result = mysqli_stmt_get_result($stats_stmt);
$stats = mysqli_fetch_assoc($stats_result);
mysqli_stmt_close($stats_stmt);

// Handle prescription status updates
if (isset($_POST['update_status'])) {
    $prescription_id = intval($_POST['prescription_id']);
    $new_status = sanitize_input($_POST['new_status']);
    
    // Verify the prescription belongs to this doctor and patient
    $verify_prescription_sql = "SELECT id FROM prescriptions WHERE id = ? AND doctor_id = ? AND patient_id = ?";
    $verify_prescription_stmt = mysqli_prepare($conn, $verify_prescription_sql);
    mysqli_stmt_bind_param($verify_prescription_stmt, "iii", $prescription_id, $user_id, $patient_id);
    mysqli_stmt_execute($verify_prescription_stmt);
    
    if (mysqli_stmt_get_result($verify_prescription_stmt)->num_rows > 0) {
        $update_sql = "UPDATE prescriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $new_status, $prescription_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success_message = "Prescription status updated successfully.";
        } else {
            $error_message = "Failed to update prescription status.";
        }
        mysqli_stmt_close($update_stmt);
    }
    mysqli_stmt_close($verify_prescription_stmt);
    
    // Redirect to refresh the page
    header("Location: view-patient-prescriptions.php?patient_id=$patient_id");
    exit();
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Prescriptions - Doctor Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .prescription-card {
            transition: all 0.3s ease;
        }
        .prescription-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
        }
        .medication-list {
            max-height: 100px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="assigned-patients.php">My Patients</a></li>
                <li class="breadcrumb-item active">Patient Prescriptions</li>
            </ol>
        </nav>

        <!-- Patient Information -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="fas fa-user me-2"></i>Patient: <?php echo htmlspecialchars($patient['name']); ?>
                            </h4>
                            <a href="write-prescription.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-plus me-2"></i>Write New Prescription
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Patient ID:</strong> #<?php echo $patient['id']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['total_prescriptions']; ?></h3>
                        <p class="mb-0">Total Prescriptions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['active_prescriptions']; ?></h3>
                        <p class="mb-0">Active</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['completed_prescriptions']; ?></h3>
                        <p class="mb-0">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h3><?php echo $stats['cancelled_prescriptions']; ?></h3>
                        <p class="mb-0">Cancelled</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prescriptions List -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-prescription me-2"></i>Prescription History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($prescriptions)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                                <h5>No Prescriptions Found</h5>
                                <p class="text-muted">This patient doesn't have any prescriptions yet.</p>
                                <a href="write-prescription.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Write First Prescription
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($prescriptions as $prescription): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card prescription-card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">Prescription #<?php echo $prescription['id']; ?></h6>
                                                <span class="badge status-badge bg-<?php 
                                                    echo $prescription['status'] === 'active' ? 'success' : 
                                                        ($prescription['status'] === 'completed' ? 'info' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst($prescription['status']); ?>
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo date('M d, Y', strtotime($prescription['created_at'])); ?>
                                                    </small>
                                                </div>
                                                
                                                <?php if (!empty($prescription['medications'])): ?>
                                                    <div class="mb-3">
                                                        <strong>Medications:</strong>
                                                        <div class="medication-list mt-2">
                                                            <?php 
                                                            $medications = explode(', ', $prescription['medications']);
                                                            foreach ($medications as $med): ?>
                                                                <span class="badge bg-light text-dark me-1 mb-1">
                                                                    <?php echo htmlspecialchars(trim($med)); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($prescription['notes'])): ?>
                                                    <div class="mb-3">
                                                        <strong>Notes:</strong>
                                                        <p class="text-muted small mb-0">
                                                            <?php echo htmlspecialchars(substr($prescription['notes'], 0, 100)); ?>
                                                            <?php if (strlen($prescription['notes']) > 100): ?>...<?php endif; ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="d-grid gap-2">
                                                    <a href="view-prescription.php?id=<?php echo $prescription['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye me-2"></i>View Details
                                                    </a>
                                                    <a href="edit-prescription.php?id=<?php echo $prescription['id']; ?>" 
                                                       class="btn btn-outline-secondary btn-sm">
                                                        <i class="fas fa-edit me-2"></i>Edit
                                                    </a>
                                                    
                                                    <!-- Status Update Form -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="prescription_id" value="<?php echo $prescription['id']; ?>">
                                                        <div class="input-group input-group-sm">
                                                            <select name="new_status" class="form-select form-select-sm">
                                                                <option value="active" <?php echo $prescription['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="completed" <?php echo $prescription['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                <option value="cancelled" <?php echo $prescription['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                            </select>
                                                            <button type="submit" name="update_status" class="btn btn-outline-warning btn-sm">
                                                                <i class="fas fa-save"></i>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Back Button -->
        <div class="row mt-4">
            <div class="col-12">
                <a href="assigned-patients.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to My Patients
                </a>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html> 