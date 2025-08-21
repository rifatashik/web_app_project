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

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $prescription_ids = isset($_POST['prescription_ids']) ? $_POST['prescription_ids'] : [];
    $action = $_POST['bulk_action'];
    
    if (!empty($prescription_ids)) {
        $ids = implode(',', array_map('intval', $prescription_ids));
        
        switch ($action) {
            case 'complete':
                $sql = "UPDATE prescriptions SET status = 'completed' WHERE id IN ($ids) AND doctor_id = ?";
                break;
            case 'cancel':
                $sql = "UPDATE prescriptions SET status = 'cancelled' WHERE id IN ($ids) AND doctor_id = ?";
                break;
            case 'delete':
                $sql = "DELETE FROM prescriptions WHERE id IN ($ids) AND doctor_id = ?";
                break;
            default:
                $error_message = "Invalid action selected.";
                break;
        }
        
        if (isset($sql)) {
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Selected prescriptions have been updated successfully.";
            } else {
                $error_message = "Error updating prescriptions.";
            }
        }
    } else {
        $error_message = "Please select at least one prescription.";
    }
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'created_at';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Get prescriptions for patients assigned to this doctor
$sql = "SELECT p.*, u.name as patient_name, u.email as patient_email
        FROM prescriptions p 
        JOIN users u ON p.patient_id = u.id 
        JOIN patient_doctor pd ON u.id = pd.patient_id 
        WHERE pd.doctor_id = ? 
        ORDER BY p.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$prescriptions = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Prescriptions</h1>
        <div>
            <a href="new-prescription.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Prescription
            </a>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

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
                    <a href="prescriptions.php" class="btn btn-secondary">
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
                <form method="POST" id="prescriptionsForm">
                    <div class="mb-3">
                        <select class="form-select w-auto" name="bulk_action">
                            <option value="">Bulk Actions</option>
                            <option value="complete">Mark as Completed</option>
                            <option value="cancel">Mark as Cancelled</option>
                            <option value="delete">Delete Selected</option>
                        </select>
                        <button type="submit" class="btn btn-secondary ms-2">Apply</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" class="form-check-input" id="selectAll">
                                    </th>
                                    <th>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'created_at', 'sort_order' => $sort_by === 'created_at' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                                           class="text-decoration-none text-dark">
                                            Date
                                            <?php if ($sort_by === 'created_at'): ?>
                                                <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'patient_name', 'sort_order' => $sort_by === 'patient_name' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                                           class="text-decoration-none text-dark">
                                            Patient
                                            <?php if ($sort_by === 'patient_name'): ?>
                                                <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Diagnosis</th>
                                    <th>Medications</th>
                                    <th>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by' => 'status', 'sort_order' => $sort_by === 'status' && $sort_order === 'ASC' ? 'DESC' : 'ASC'])); ?>" 
                                           class="text-decoration-none text-dark">
                                            Status
                                            <?php if ($sort_by === 'status'): ?>
                                                <i class="fas fa-sort-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($prescription = mysqli_fetch_assoc($prescriptions)): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input prescription-checkbox" 
                                                   name="prescription_ids[]" value="<?php echo $prescription['id']; ?>">
                                        </td>
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
                                            <div class="btn-group">
                                                <a href="view-prescription.php?id=<?php echo $prescription['id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit-prescription.php?id=<?php echo $prescription['id']; ?>" 
                                                   class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="deletePrescription(<?php echo $prescription['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox functionality
    const selectAll = document.getElementById('selectAll');
    const prescriptionCheckboxes = document.querySelectorAll('.prescription-checkbox');
    
    selectAll.addEventListener('change', function() {
        prescriptionCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
    
    // Bulk action confirmation
    const form = document.getElementById('prescriptionsForm');
    form.addEventListener('submit', function(e) {
        const action = this.querySelector('select[name="bulk_action"]').value;
        const selectedCount = document.querySelectorAll('.prescription-checkbox:checked').length;
        
        if (!action) {
            e.preventDefault();
            alert('Please select an action to perform.');
            return;
        }
        
        if (selectedCount === 0) {
            e.preventDefault();
            alert('Please select at least one prescription.');
            return;
        }
        
        if (!confirm(`Are you sure you want to ${action} ${selectedCount} prescription(s)?`)) {
            e.preventDefault();
        }
    });
});

function deletePrescription(id) {
    if (confirm('Are you sure you want to delete this prescription? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="bulk_action" value="delete">
            <input type="hidden" name="prescription_ids[]" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?> 