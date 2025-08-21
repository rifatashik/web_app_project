<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a doctor
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    redirect('../login.php');
}

// Get prescription ID from URL
$prescription_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get prescription details
$sql = "SELECT p.*, pt.name as patient_name 
        FROM prescriptions p 
        JOIN users pt ON p.patient_id = pt.id 
        WHERE p.id = ? AND p.doctor_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $prescription_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$prescription = mysqli_fetch_assoc($result);

// If prescription doesn't exist or doesn't belong to this doctor
if (!$prescription) {
    redirect('prescriptions.php');
}

// Get prescription medications
$sql = "SELECT * FROM prescription_medications WHERE prescription_id = ? ORDER BY id";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $prescription_id);
mysqli_stmt_execute($stmt);
$medications_result = mysqli_stmt_get_result($stmt);
$medications = [];
while ($row = mysqli_fetch_assoc($medications_result)) {
    $medications[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnosis = sanitize_input($_POST['diagnosis']);
    $notes = sanitize_input($_POST['notes']);
    $status = sanitize_input($_POST['status']);
    
    // Update prescription
    $sql = "UPDATE prescriptions SET diagnosis = ?, notes = ?, status = ? WHERE id = ? AND doctor_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssii", $diagnosis, $notes, $status, $prescription_id, $_SESSION['user_id']);
    
    if (mysqli_stmt_execute($stmt)) {
        // Delete existing medications
        $sql = "DELETE FROM prescription_medications WHERE prescription_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $prescription_id);
        mysqli_stmt_execute($stmt);
        
        // Insert new medications
        if (isset($_POST['medications']) && is_array($_POST['medications'])) {
            $sql = "INSERT INTO prescription_medications (prescription_id, drug_name, generic_name, dosage, duration, instructions) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            
            foreach ($_POST['medications'] as $med) {
                $drug_name = sanitize_input($med['drug_name']);
                $generic_name = sanitize_input($med['generic_name']);
                $dosage = sanitize_input($med['dosage']);
                $duration = sanitize_input($med['duration']);
                $instructions = sanitize_input($med['instructions']);
                
                mysqli_stmt_bind_param($stmt, "isssss", $prescription_id, $drug_name, $generic_name, $dosage, $duration, $instructions);
                mysqli_stmt_execute($stmt);
            }
        }
        
        $success_message = "Prescription updated successfully!";
        
        // Refresh prescription data
        $sql = "SELECT p.*, pt.name as patient_name 
                FROM prescriptions p 
                JOIN users pt ON p.patient_id = pt.id 
                WHERE p.id = ? AND p.doctor_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $prescription_id, $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $prescription = mysqli_fetch_assoc($result);
        
        // Refresh medications
        $sql = "SELECT * FROM prescription_medications WHERE prescription_id = ? ORDER BY id";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $prescription_id);
        mysqli_stmt_execute($stmt);
        $medications_result = mysqli_stmt_get_result($stmt);
        $medications = [];
        while ($row = mysqli_fetch_assoc($medications_result)) {
            $medications[] = $row;
        }
    } else {
        $error_message = "Error updating prescription. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Prescription - Doctor Panel</title>
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
        .medication-row {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <h3 class="mb-4">Doctor Panel</h3>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="prescriptions.php" class="active">
                            <i class="fas fa-prescription me-2"></i> Prescriptions
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="patients.php">
                            <i class="fas fa-users me-2"></i> Patients
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a href="profile.php">
                            <i class="fas fa-user me-2"></i> Profile
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Edit Prescription</h2>
                    <a href="prescriptions.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Prescriptions
                    </a>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="" id="prescriptionForm">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Patient</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($prescription['patient_name']); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active" <?php echo $prescription['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="completed" <?php echo $prescription['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $prescription['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Diagnosis</label>
                                <textarea name="diagnosis" class="form-control" rows="4" required><?php echo htmlspecialchars($prescription['diagnosis']); ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($prescription['notes']); ?></textarea>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4>Medications</h4>
                                    <button type="button" class="btn btn-primary" onclick="addMedication()">
                                        <i class="fas fa-plus me-2"></i>Add Medication
                                    </button>
                                </div>
                                <div id="medications">
                                    <?php foreach ($medications as $index => $medication): ?>
                                        <div class="medication-row">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">Drug Name</label>
                                                    <input type="text" name="medications[<?php echo $index; ?>][drug_name]" class="form-control" value="<?php echo htmlspecialchars($medication['drug_name']); ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Generic Name</label>
                                                    <input type="text" name="medications[<?php echo $index; ?>][generic_name]" class="form-control" value="<?php echo htmlspecialchars($medication['generic_name']); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Dosage</label>
                                                    <input type="text" name="medications[<?php echo $index; ?>][dosage]" class="form-control" value="<?php echo htmlspecialchars($medication['dosage']); ?>" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Duration</label>
                                                    <input type="text" name="medications[<?php echo $index; ?>][duration]" class="form-control" value="<?php echo htmlspecialchars($medication['duration']); ?>" required>
                                                </div>
                                                <div class="col-md-7">
                                                    <label class="form-label">Instructions</label>
                                                    <input type="text" name="medications[<?php echo $index; ?>][instructions]" class="form-control" value="<?php echo htmlspecialchars($medication['instructions']); ?>" required>
                                                </div>
                                                <div class="col-md-1 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger" onclick="removeMedication(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                                <a href="prescriptions.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let medicationCount = <?php echo count($medications); ?>;
        
        function addMedication() {
            const template = `
                <div class="medication-row">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Drug Name</label>
                            <input type="text" name="medications[${medicationCount}][drug_name]" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Generic Name</label>
                            <input type="text" name="medications[${medicationCount}][generic_name]" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dosage</label>
                            <input type="text" name="medications[${medicationCount}][dosage]" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duration</label>
                            <input type="text" name="medications[${medicationCount}][duration]" class="form-control" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Instructions</label>
                            <input type="text" name="medications[${medicationCount}][instructions]" class="form-control" required>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-danger" onclick="removeMedication(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('medications').insertAdjacentHTML('beforeend', template);
            medicationCount++;
        }
        
        function removeMedication(button) {
            button.closest('.medication-row').remove();
        }
    </script>
</body>
</html> 