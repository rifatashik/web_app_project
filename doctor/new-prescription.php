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

// Get list of patients
$sql = "SELECT id, name, email FROM users WHERE role = 'patient' ORDER BY name";
$result = mysqli_query($conn, $sql);
$patients = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get list of drugs
$sql = "SELECT id, name, generic_name FROM drugs ORDER BY name";
$result = mysqli_query($conn, $sql);
$drugs = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'];
    $diagnosis = trim($_POST['diagnosis']);
    $notes = trim($_POST['notes']);
    $medications = $_POST['medications'];
    $generic_names = $_POST['generic_names'];
    $dosages = $_POST['dosages'];
    $durations = $_POST['durations'];
    $instructions = $_POST['instructions'];

    // Validate input
    if (empty($patient_id) || empty($diagnosis)) {
        $error_message = "Patient and diagnosis are required fields.";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert prescription
            $sql = "INSERT INTO prescriptions (doctor_id, patient_id, diagnosis, notes, status) 
                    VALUES (?, ?, ?, ?, 'active')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiss", $user_id, $patient_id, $diagnosis, $notes);
            mysqli_stmt_execute($stmt);
            
            $prescription_id = mysqli_insert_id($conn);

            // Ensure patient-doctor assignment exists
            $assign_sql = "INSERT INTO patient_doctor (patient_id, doctor_id) VALUES (?, ?) 
                            ON DUPLICATE KEY UPDATE assigned_at = assigned_at";
            $assign_stmt = mysqli_prepare($conn, $assign_sql);
            mysqli_stmt_bind_param($assign_stmt, "ii", $patient_id, $user_id);
            mysqli_stmt_execute($assign_stmt);
            mysqli_stmt_close($assign_stmt);

            // Insert medications
            $sql = "INSERT INTO prescription_medications (prescription_id, drug_name, generic_name, dosage, duration, instructions) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);

            foreach ($medications as $index => $drug_name) {
                if (!empty($drug_name)) {
                    mysqli_stmt_bind_param($stmt, "isssss", 
                        $prescription_id, 
                        $drug_name,
                        $generic_names[$index],
                        $dosages[$index], 
                        $durations[$index], 
                        $instructions[$index]
                    );
                    mysqli_stmt_execute($stmt);
                }
            }

            // Commit transaction
            mysqli_commit($conn);
            
            $success_message = "Prescription created successfully!";
            
            // Clear form data
            $_POST = array();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = "Error creating prescription: " . $e->getMessage();
        }
    }
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Write New Prescription</h5>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="" id="prescriptionForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="patient_id" class="form-label">Patient</label>
                                <select class="form-select" id="patient_id" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>" 
                                                <?php echo isset($_POST['patient_id']) && $_POST['patient_id'] == $patient['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($patient['name']); ?> 
                                            (<?php echo htmlspecialchars($patient['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">Diagnosis</label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="2" required><?php echo isset($_POST['diagnosis']) ? htmlspecialchars($_POST['diagnosis']) : ''; ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>

                        <h6 class="mb-3">Medications</h6>
                        <div id="medications">
                            <div class="medication-row mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Drug Name</label>
                                        <input type="text" class="form-control" name="medications[]" placeholder="e.g., Napa" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Generic Name</label>
                                        <input type="text" class="form-control" name="generic_names[]" placeholder="e.g., Paracetamol">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Dosage</label>
                                        <input type="text" class="form-control" name="dosages[]" placeholder="e.g., 500mg" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Duration</label>
                                        <input type="text" class="form-control" name="durations[]" placeholder="e.g., 7 days" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Instructions</label>
                                        <input type="text" class="form-control" name="instructions[]" placeholder="e.g., After meals">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-secondary" id="addMedication">
                                <i class="fas fa-plus"></i> Add Another Medication
                            </button>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Prescription
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('addMedication').addEventListener('click', function() {
    const medicationsDiv = document.getElementById('medications');
    const newRow = document.querySelector('.medication-row').cloneNode(true);
    
    // Clear values in the new row
    newRow.querySelectorAll('select, input').forEach(input => {
        input.value = '';
    });
    
    medicationsDiv.appendChild(newRow);
});
</script>

<?php include '../includes/footer.php'; ?> 