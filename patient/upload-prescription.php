<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a patient
if (!is_logged_in() || !check_role('patient')) {
    redirect('../login.php');
}

$errors = [];
$success = false;
$ocr_results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['prescription_file']) && $_FILES['prescription_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['prescription_file'];
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only JPG, PNG, and PDF files are allowed.";
        } else {
            $upload_dir = '../uploads/prescriptions/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = uniqid() . '_' . basename($file['name']);
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Determine doctor to assign: patient's chosen doctor -> any active doctor -> admin
                $patient_id = $_SESSION['user_id'];
                $doctor_id = null;

                // 1) Patient's chosen doctor
                $chosen_sql = "SELECT doctor_id FROM patient_doctor WHERE patient_id = ? ORDER BY assigned_at DESC LIMIT 1";
                $chosen_stmt = mysqli_prepare($conn, $chosen_sql);
                mysqli_stmt_bind_param($chosen_stmt, "i", $patient_id);
                mysqli_stmt_execute($chosen_stmt);
                $chosen_res = mysqli_stmt_get_result($chosen_stmt);
                if ($row = mysqli_fetch_assoc($chosen_res)) {
                    $doctor_id = (int)$row['doctor_id'];
                }
                mysqli_stmt_close($chosen_stmt);

                // 2) Any active doctor
                if (!$doctor_id) {
                    $doc_res = mysqli_query($conn, "SELECT id FROM users WHERE role = 'doctor' AND (status IS NULL OR status = 'active') ORDER BY id LIMIT 1");
                    if ($doc_row = mysqli_fetch_assoc($doc_res)) {
                        $doctor_id = (int)$doc_row['id'];
                    }
                }

                // 3) Admin fallback
                if (!$doctor_id) {
                    $admin_res = mysqli_query($conn, "SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
                    if ($admin_row = mysqli_fetch_assoc($admin_res)) {
                        $doctor_id = (int)$admin_row['id'];
                    }
                }

                if ($doctor_id) {
                    // Create prescription record
                    $sql = "INSERT INTO prescriptions (patient_id, doctor_id, status, notes) VALUES (?, ?, 'pending', ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    $notes = "Uploaded prescription file: " . $file['name'];
                    mysqli_stmt_bind_param($stmt, "iis", $patient_id, $doctor_id, $notes);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $prescription_id = mysqli_insert_id($conn);

                        // Ensure patient-doctor assignment exists
                        $assign_sql = "INSERT INTO patient_doctor (patient_id, doctor_id) VALUES (?, ?) 
                                        ON DUPLICATE KEY UPDATE assigned_at = assigned_at";
                        $assign_stmt = mysqli_prepare($conn, $assign_sql);
                        mysqli_stmt_bind_param($assign_stmt, "ii", $patient_id, $doctor_id);
                        mysqli_stmt_execute($assign_stmt);
                        mysqli_stmt_close($assign_stmt);
                        
                        // If OCR results are available, add prescription items (optional)
                        if ($ocr_results && !empty($ocr_results['drugs'])) {
                            foreach ($ocr_results['drugs'] as $drug) {
                                // Use prescription_medications table consistent with the system
                                $sql = "INSERT INTO prescription_medications (prescription_id, drug_name, generic_name, dosage, duration, instructions) 
                                       VALUES (?, ?, ?, ?, ?, ?)";
                                $stmt = mysqli_prepare($conn, $sql);
                                $generic = isset($drug['generic_name']) ? $drug['generic_name'] : null;
                                $instructions = isset($drug['instructions']) ? $drug['instructions'] : null;
                                mysqli_stmt_bind_param($stmt, "isssss", 
                                    $prescription_id, 
                                    $drug['name'], 
                                    $generic,
                                    $drug['dosage'], 
                                    $drug['duration'], 
                                    $instructions
                                );
                                mysqli_stmt_execute($stmt);
                            }
                        }

                        $success = true;
                        
                        // Create notification for the assigned doctor/admin
                        create_notification($doctor_id, "New prescription uploaded by " . $_SESSION['name'], 'info');
                    } else {
                        $errors[] = "Failed to save prescription. Please try again.";
                    }
                } else {
                    $errors[] = "System error: No doctor/admin user found. Please contact support.";
                }
            } else {
                $errors[] = "Failed to upload file. Please try again.";
            }
        }
    } else {
        $errors[] = "Please select a file to upload.";
    }
}

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Upload Prescription</h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Prescription uploaded successfully! Our team will review it shortly.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="prescription_file" class="form-label">Select Prescription File</label>
                            <input type="file" class="form-control" id="prescription_file" name="prescription_file" accept=".jpg,.jpeg,.png,.pdf" required>
                            <div class="form-text">Supported formats: JPG, PNG, PDF</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Upload Prescription</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 