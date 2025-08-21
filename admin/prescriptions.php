<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    redirect('../login.php');
}

// Handle prescription status update
if (isset($_POST['update_status']) && isset($_POST['prescription_id']) && isset($_POST['status'])) {
    $prescription_id = (int)$_POST['prescription_id'];
    $status = sanitize_input($_POST['status']);
    if (in_array($status, ['active', 'completed', 'cancelled'])) {
        $sql = "UPDATE prescriptions SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $status, $prescription_id);
        mysqli_stmt_execute($stmt);
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$doctor_filter = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$patient_filter = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

// Build query conditions
$conditions = [];
$params = [];
$types = '';

if ($status_filter) {
    $conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($doctor_filter) {
    $conditions[] = "p.doctor_id = ?";
    $params[] = $doctor_filter;
    $types .= 'i';
}

if ($patient_filter) {
    $conditions[] = "p.patient_id = ?";
    $params[] = $patient_filter;
    $types .= 'i';
}

if ($date_from) {
    $conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get total number of prescriptions
$sql = "SELECT COUNT(*) as count 
        FROM prescriptions p 
        JOIN users d ON p.doctor_id = d.id 
        JOIN users pt ON p.patient_id = pt.id 
        $where_clause";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

$row = mysqli_fetch_assoc($result);
$total_records = $row['count'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$total_pages = ceil($total_records / $records_per_page);
$offset = ($page - 1) * $records_per_page;

// Get prescriptions for current page
$sql = "SELECT p.*, 
               d.name as doctor_name, 
               pt.name as patient_name,
               (SELECT COUNT(*) FROM prescription_medications WHERE prescription_id = p.id) as medication_count
        FROM prescriptions p 
        JOIN users d ON p.doctor_id = d.id 
        JOIN users pt ON p.patient_id = pt.id 
        $where_clause
        ORDER BY p.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    $types .= 'ii';
    $params[] = $records_per_page;
    $params[] = $offset;
    mysqli_stmt_bind_param($stmt, $types, ...$params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $records_per_page, $offset);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get all doctors for filter
$doctors = mysqli_query($conn, "SELECT id, name FROM users WHERE role = 'doctor' ORDER BY name");

// Get all patients for filter
$patients = mysqli_query($conn, "SELECT id, name FROM users WHERE role = 'patient' ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
        }
        .sidebar a:hover {
            color: #f8f9fa;
        }
        .main-content {
            padding: 20px;
        }
        .status-badge {
            width: 100px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <h3 class="mb-4">Admin Panel</h3>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="users.php">
                            <i class="fas fa-users me-2"></i> Users
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="prescriptions.php" class="active">
                            <i class="fas fa-prescription me-2"></i> Prescriptions
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="settings.php">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Prescription Management</h2>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Doctor</label>
                                <select name="doctor_id" class="form-select">
                                    <option value="">All Doctors</option>
                                    <?php while ($doctor = mysqli_fetch_assoc($doctors)): ?>
                                        <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_filter === $doctor['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($doctor['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Patient</label>
                                <select name="patient_id" class="form-select">
                                    <option value="">All Patients</option>
                                    <?php while ($patient = mysqli_fetch_assoc($patients)): ?>
                                        <option value="<?php echo $patient['id']; ?>" <?php echo $patient_filter === $patient['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($patient['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="prescriptions.php" class="btn btn-secondary">Clear Filters</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Prescriptions Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Doctor</th>
                                        <th>Patient</th>
                                        <th>Diagnosis</th>
                                        <th>Medications</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($prescription = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><?php echo $prescription['id']; ?></td>
                                            <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                                            <td><?php echo htmlspecialchars($prescription['patient_name']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($prescription['diagnosis'], 0, 50)) . (strlen($prescription['diagnosis']) > 50 ? '...' : ''); ?></td>
                                            <td><?php echo $prescription['medication_count']; ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="prescription_id" value="<?php echo $prescription['id']; ?>">
                                                    <select name="status" class="form-select form-select-sm status-badge" onchange="this.form.submit()">
                                                        <option value="active" <?php echo $prescription['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="completed" <?php echo $prescription['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                        <option value="cancelled" <?php echo $prescription['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewPrescriptionModal<?php echo $prescription['id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- View Prescription Modal -->
                                        <div class="modal fade" id="viewPrescriptionModal<?php echo $prescription['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Prescription Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <strong>Doctor:</strong> <?php echo htmlspecialchars($prescription['doctor_name']); ?>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <strong>Patient:</strong> <?php echo htmlspecialchars($prescription['patient_name']); ?>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <strong>Diagnosis:</strong>
                                                            <p><?php echo nl2br(htmlspecialchars($prescription['diagnosis'])); ?></p>
                                                        </div>
                                                        <?php if ($prescription['notes']): ?>
                                                            <div class="mb-3">
                                                                <strong>Notes:</strong>
                                                                <p><?php echo nl2br(htmlspecialchars($prescription['notes'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Medications -->
                                                        <h6 class="mt-4">Medications</h6>
                                                        <table class="table table-sm">
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
                                                                <?php
                                                                $med_sql = "SELECT * FROM prescription_medications WHERE prescription_id = ?";
                                                                $med_stmt = mysqli_prepare($conn, $med_sql);
                                                                mysqli_stmt_bind_param($med_stmt, "i", $prescription['id']);
                                                                mysqli_stmt_execute($med_stmt);
                                                                $med_result = mysqli_stmt_get_result($med_stmt);
                                                                while ($medication = mysqli_fetch_assoc($med_result)):
                                                                ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($medication['drug_name']); ?></td>
                                                                        <td><?php echo htmlspecialchars($medication['generic_name']); ?></td>
                                                                        <td><?php echo htmlspecialchars($medication['dosage']); ?></td>
                                                                        <td><?php echo htmlspecialchars($medication['duration']); ?></td>
                                                                        <td><?php echo htmlspecialchars($medication['instructions']); ?></td>
                                                                    </tr>
                                                                <?php endwhile; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&doctor_id=<?php echo $doctor_filter; ?>&patient_id=<?php echo $patient_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 