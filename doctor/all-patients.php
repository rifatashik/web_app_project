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

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $where_clause = "WHERE u.name LIKE ? OR u.email LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param];
    $types = "ss";
}

// Get all patients with their prescription counts
$sql = "SELECT 
        u.id,
        u.name,
        u.email,
        COUNT(DISTINCT p.id) as prescription_count,
        MAX(p.created_at) as last_prescription_date,
        GROUP_CONCAT(DISTINCT d.name) as doctors
        FROM users u
        LEFT JOIN prescriptions p ON u.id = p.patient_id
        LEFT JOIN users d ON p.doctor_id = d.id
        $where_clause
        GROUP BY u.id, u.name, u.email
        ORDER BY u.name ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$patients = mysqli_fetch_all($stmt->get_result(), MYSQLI_ASSOC);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">All Patients</h1>
        <a href="new-prescription.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Prescription
        </a>
    </div>

    <!-- Search Form -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by patient name or email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
                <?php if (!empty($search)): ?>
                    <div class="col-md-4">
                        <a href="all-patients.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Search
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (empty($patients)): ?>
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5>No Patients Found</h5>
                <p class="text-muted">
                    <?php if (!empty($search)): ?>
                        No patients match your search criteria.
                    <?php else: ?>
                        There are no patients in the system yet.
                    <?php endif; ?>
                </p>
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
                                <th>Doctors</th>
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
                                        <?php if ($patient['doctors']): ?>
                                            <?php 
                                            $doctors = explode(',', $patient['doctors']);
                                            foreach ($doctors as $doctor): ?>
                                                <span class="badge bg-info me-1">
                                                    <?php echo htmlspecialchars($doctor); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">None</span>
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