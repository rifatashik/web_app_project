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

// Get patient ID from URL if provided
$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;

// Get all patients for dropdown
$sql = "SELECT id, name, email FROM users WHERE role = 'patient' ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
$patients = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get all drugs for dropdown
$sql = "SELECT id, name, generic_name FROM drugs ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
$drugs = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)$_POST['patient_id'];
    $diagnosis = trim($_POST['diagnosis']);
    $notes = trim($_POST['notes']);
    $medications = $_POST['medications'];
    $medication_types = $_POST['medication_type'];
    
    // Validate input
    if (empty($patient_id) || empty($diagnosis)) {
        $error_message = "Please fill in all required fields.";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert prescription
            $sql = "INSERT INTO prescriptions (patient_id, doctor_id, diagnosis, notes, status) 
                    VALUES (?, ?, ?, ?, 'active')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiss", $patient_id, $user_id, $diagnosis, $notes);
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

            foreach ($medications as $index => $med) {
                if ($medication_types[$index] === 'select' && !empty($med['drug_id'])) {
                    // Get drug details from database
                    $drug_sql = "SELECT name, generic_name FROM drugs WHERE id = ?";
                    $drug_stmt = mysqli_prepare($conn, $drug_sql);
                    mysqli_stmt_bind_param($drug_stmt, "i", $med['drug_id']);
                    mysqli_stmt_execute($drug_stmt);
                    $drug_result = mysqli_stmt_get_result($drug_stmt);
                    if ($drug_row = mysqli_fetch_assoc($drug_result)) {
                        $drug_name = $drug_row['name'];
                        $generic_name = $drug_row['generic_name'];
                    }
                } else {
                    // Manual entry
                    $drug_name = trim($med['manual_drug_name']);
                    $generic_name = trim($med['manual_generic_name']);
                }

                if (!empty($drug_name)) {
                    mysqli_stmt_bind_param($stmt, "isssss", 
                        $prescription_id, 
                        $drug_name,
                        $generic_name,
                        $med['dosage'], 
                        $med['duration'], 
                        $med['instructions']
                    );
                    mysqli_stmt_execute($stmt);
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $success_message = "Prescription created successfully!";
            
            // Redirect to view the prescription
            redirect("view-prescription.php?id=" . $prescription_id);
            
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Write Prescription</h1>
        <a href="prescriptions.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left"></i> Back to Prescriptions
        </a>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <form method="POST" id="prescriptionForm">
                <!-- Patient Selection -->
                <div class="mb-4">
                    <label for="patient_id" class="form-label">Select Patient <span class="text-danger">*</span></label>
                    <select class="form-select" id="patient_id" name="patient_id" required>
                        <option value="">Choose a patient...</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" 
                                    <?php echo $selected_patient_id == $patient['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['name'] . ' (' . $patient['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Diagnosis -->
                <div class="mb-4">
                    <label for="diagnosis" class="form-label">Diagnosis <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="diagnosis" name="diagnosis" rows="2" required></textarea>
                </div>

                <!-- Medications -->
                <div class="mb-4">
                    <label class="form-label">Medications <span class="text-danger">*</span></label>
                    <div id="medications">
                        <div class="medication-item card mb-3">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="medication_type[0]" id="selectDrug0" value="select" checked>
                                            <label class="form-check-label" for="selectDrug0">Select from List</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="medication_type[0]" id="manualDrug0" value="manual">
                                            <label class="form-check-label" for="manualDrug0">Enter Manually</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3" id="selectDrugContainer0">
                                        <label class="form-label">Drug</label>
                                        <select class="form-select" name="medications[0][drug_id]" required>
                                            <option value="">Select a drug...</option>
                                            <?php foreach ($drugs as $drug): ?>
                                                <option value="<?php echo $drug['id']; ?>">
                                                    <?php echo htmlspecialchars($drug['name'] . ' (' . $drug['generic_name'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3" id="manualDrugContainer0" style="display: none;">
                                        <label class="form-label">Drug Name</label>
                                        <input type="text" class="form-control" name="medications[0][manual_drug_name]" 
                                               placeholder="Enter drug name">
                                        <label class="form-label mt-2">Generic Name</label>
                                        <input type="text" class="form-control" name="medications[0][manual_generic_name]" 
                                               placeholder="Enter generic name">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Dosage</label>
                                        <input type="text" class="form-control" name="medications[0][dosage]" 
                                               placeholder="e.g., 500mg" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Frequency</label>
                                        <input type="text" class="form-control" name="medications[0][frequency]" 
                                               placeholder="e.g., Twice daily" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Duration</label>
                                        <input type="text" class="form-control" name="medications[0][duration]" 
                                               placeholder="e.g., 7 days" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Instructions</label>
                                        <input type="text" class="form-control" name="medications[0][instructions]" 
                                               placeholder="e.g., After meals">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary" id="addMedication">
                        <i class="fas fa-plus"></i> Add Another Medication
                    </button>
                </div>

                <!-- Additional Notes -->
                <div class="mb-4">
                    <label for="notes" class="form-label">Additional Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                              placeholder="Any additional instructions or notes for the patient..."></textarea>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Prescription
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to handle medication type toggle
    function handleMedicationTypeToggle(index) {
        const selectRadio = document.getElementById(`selectDrug${index}`);
        const manualRadio = document.getElementById(`manualDrug${index}`);
        const selectContainer = document.getElementById(`selectDrugContainer${index}`);
        const manualContainer = document.getElementById(`manualDrugContainer${index}`);
        const selectInput = selectContainer.querySelector('select');
        const manualInputs = manualContainer.querySelectorAll('input');

        selectRadio.addEventListener('change', function() {
            if (this.checked) {
                selectContainer.style.display = 'block';
                manualContainer.style.display = 'none';
                selectInput.required = true;
                manualInputs.forEach(input => input.required = false);
            }
        });

        manualRadio.addEventListener('change', function() {
            if (this.checked) {
                selectContainer.style.display = 'none';
                manualContainer.style.display = 'block';
                selectInput.required = false;
                manualInputs.forEach(input => input.required = true);
            }
        });
    }

    // Initialize for first medication
    handleMedicationTypeToggle(0);

    // Add medication button handler
    let medicationCount = 1;
    document.getElementById('addMedication').addEventListener('click', function() {
        const medicationsContainer = document.getElementById('medications');
        const newMedication = document.querySelector('.medication-item').cloneNode(true);
        
        // Update IDs and names
        newMedication.querySelectorAll('[id]').forEach(el => {
            el.id = el.id.replace('0', medicationCount);
        });
        
        newMedication.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace('[0]', `[${medicationCount}]`);
        });
        
        // Clear values
        newMedication.querySelectorAll('input, select').forEach(el => {
            el.value = '';
        });
        
        // Add remove button
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn btn-outline-danger btn-sm position-absolute top-0 end-0 m-3';
        removeButton.innerHTML = '<i class="fas fa-times"></i>';
        removeButton.onclick = function() {
            newMedication.remove();
        };
        newMedication.style.position = 'relative';
        newMedication.appendChild(removeButton);
        
        medicationsContainer.appendChild(newMedication);
        handleMedicationTypeToggle(medicationCount);
        medicationCount++;
    });
});
</script>

<?php include '../includes/footer.php'; ?> 