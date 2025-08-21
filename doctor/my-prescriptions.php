<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a doctor
if (!is_logged_in() || !check_role('doctor')) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$sql = "SELECT p.*, 
        pt.name as patient_name, 
        pt.email as patient_email,
        (SELECT COUNT(*) FROM prescription_medications WHERE prescription_id = p.id) as medication_count
        FROM prescriptions p
        JOIN users pt ON p.patient_id = pt.id
        WHERE p.doctor_id = ?";

$params = [$user_id];
$types = "i";

if ($status) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($search) {
    $sql .= " AND (pt.name LIKE ? OR pt.email LIKE ? OR p.diagnosis LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($date_from) {
    $sql .= " AND DATE(p.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to) {
    $sql .= " AND DATE(p.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$sql .= " ORDER BY p.created_at DESC";

// Prepare and execute query
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$prescriptions = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Prescriptions</h1>
        <a href="new-prescription.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Prescription
        </a>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Patient name, email, or diagnosis">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="my-prescriptions.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Prescriptions List -->
    <div class="card shadow">
        <div class="card-body">
            <?php if (empty($prescriptions)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-prescription fa-3x text-muted mb-3"></i>
                    <h5>No prescriptions found</h5>
                    <p class="text-muted">Start by creating a new prescription</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Diagnosis</th>
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
                                        <?php echo htmlspecialchars($prescription['patient_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($prescription['patient_email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($prescription['diagnosis'], 0, 50)) . '...'; ?></td>
                                    <td><?php echo $prescription['medication_count']; ?> medications</td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $prescription['status'] === 'active' ? 'success' : 
                                                ($prescription['status'] === 'completed' ? 'primary' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($prescription['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-prescription.php?id=<?php echo $prescription['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit-prescription.php?id=<?php echo $prescription['id']; ?>" 
                                           class="btn btn-sm btn-secondary">
                                            <i class="fas fa-edit"></i> Edit
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

<?php include '../includes/footer.php'; ?> 