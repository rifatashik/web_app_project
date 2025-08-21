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

// Get patients assigned to this doctor
$sql = "SELECT p.id, p.name, p.email, p.created_at, 
        (SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id) as prescription_count
        FROM users p 
        JOIN patient_doctor pd ON p.id = pd.patient_id 
        WHERE pd.doctor_id = ? AND p.role = 'patient'
        ORDER BY p.name";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$patients = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Patients</h1>
        <a href="new-prescription.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Prescription
        </a>
    </div>

    <?php if (empty($patients)): ?>
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5>No Patients Yet</h5>
                <p class="text-muted">You haven't treated any patients yet.</p>
                <a href="new-prescription.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus"></i> Create Your First Prescription
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Email</th>
                                <th>Total Prescriptions</th>
                                <th>Last Prescription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td>
                                        <a href="view-patient.php?id=<?php echo $patient['id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($patient['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $patient['prescription_count']; ?> prescriptions
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($patient['last_prescription_date']): ?>
                                            <?php echo date('M d, Y', strtotime($patient['last_prescription_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view-patient.php?id=<?php echo $patient['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="new-prescription.php?patient_id=<?php echo $patient['id']; ?>" 
                                               class="btn btn-sm btn-success">
                                                <i class="fas fa-prescription"></i> New Rx
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?> 