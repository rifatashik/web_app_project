<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a doctor
if (!is_logged_in() || !check_role('doctor')) {
    redirect('../login.php');
}

// Check if prescription ID is provided
if (!isset($_GET['id'])) {
    redirect('dashboard.php');
}

$prescription_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get prescription details
$sql = "SELECT p.*, 
        d.name as doctor_name, 
        pt.name as patient_name, 
        pt.email as patient_email
        FROM prescriptions p
        JOIN users d ON p.doctor_id = d.id
        JOIN users pt ON p.patient_id = pt.id
        WHERE p.id = ? AND p.doctor_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $prescription_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$prescription = mysqli_fetch_assoc($result);

// If prescription not found or doesn't belong to this doctor
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

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Prescription Details</h5>
                    <div>
                        <a href="edit-prescription.php?id=<?php echo $prescription_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="mb-3">Patient Information</h6>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($prescription['patient_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($prescription['patient_email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3">Prescription Information</h6>
                            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($prescription['created_at'])); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php 
                                    echo $prescription['status'] === 'active' ? 'success' : 
                                        ($prescription['status'] === 'completed' ? 'primary' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($prescription['status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-3">Diagnosis</h6>
                        <p><?php echo nl2br(htmlspecialchars($prescription['diagnosis'])); ?></p>
                    </div>

                    <?php if (!empty($prescription['notes'])): ?>
                    <div class="mb-4">
                        <h6 class="mb-3">Notes</h6>
                        <p><?php echo nl2br(htmlspecialchars($prescription['notes'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <h6 class="mb-3">Medications</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
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
                                        <td><?php echo htmlspecialchars($medication['drug_name']); ?></td>
                                        <td><?php echo htmlspecialchars($medication['generic_name']); ?></td>
                                        <td><?php echo htmlspecialchars($medication['dosage']); ?></td>
                                        <td><?php echo htmlspecialchars($medication['duration']); ?></td>
                                        <td><?php echo htmlspecialchars($medication['instructions']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="text-end">
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print"></i> Print Prescription
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .card-header .btn,
    .text-end {
        display: none;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .container {
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?> 