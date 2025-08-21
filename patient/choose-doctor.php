<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a patient
if (!is_logged_in() || !check_role('patient')) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle doctor selection form submission
if (isset($_POST['choose_doctor'])) {
    $doctor_id = intval($_POST['doctor_id']);
    
    // Validate that the selected user is actually a doctor
    $validate_sql = "SELECT id, name, email FROM users WHERE id = ? AND role = 'doctor' AND status = 'active'";
    $validate_stmt = mysqli_prepare($conn, $validate_sql);
    mysqli_stmt_bind_param($validate_stmt, "i", $doctor_id);
    mysqli_stmt_execute($validate_stmt);
    $validate_result = mysqli_stmt_get_result($validate_stmt);
    
    if (mysqli_num_rows($validate_result) > 0) {
        // First, remove any existing doctor assignment for this patient
        $delete_sql = "DELETE FROM patient_doctor WHERE patient_id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $user_id);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);
        
        // Then, assign the new doctor
        $insert_sql = "INSERT INTO patient_doctor (patient_id, doctor_id) VALUES (?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "ii", $user_id, $doctor_id);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $success_message = "Doctor selected successfully! You can now request prescriptions from your chosen doctor.";
        } else {
            $error_message = "Failed to select doctor. Please try again.";
        }
        mysqli_stmt_close($insert_stmt);
    } else {
        $error_message = "Invalid doctor selected.";
    }
    mysqli_stmt_close($validate_stmt);
}

// Fetch all active doctors for the selection form
$doctor_query = "SELECT id, name, email, created_at FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY name";
$doctor_result = mysqli_query($conn, $doctor_query);

// Get the currently assigned doctor for this patient
$current_doctor = null;
$current_doctor_sql = "SELECT d.id, d.name, d.email FROM patient_doctor pd 
                       JOIN users d ON pd.doctor_id = d.id 
                       WHERE pd.patient_id = ?";
$current_doctor_stmt = mysqli_prepare($conn, $current_doctor_sql);
mysqli_stmt_bind_param($current_doctor_stmt, "i", $user_id);
mysqli_stmt_execute($current_doctor_stmt);
$current_doctor_result = mysqli_stmt_get_result($current_doctor_stmt);
if ($current_doctor = mysqli_fetch_assoc($current_doctor_result)) {
    // Store the current doctor info for later use
    $current_doctor = $current_doctor;
}
mysqli_stmt_close($current_doctor_stmt);

// Get patient's profile
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$patient = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Choose Doctor</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Current Doctor Info -->
        <?php if ($current_doctor): ?>
        <div class="col-12 mb-4">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-md me-2"></i>Your Current Doctor
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="card-subtitle mb-2 text-muted">Dr. <?php echo htmlspecialchars($current_doctor['name']); ?></h6>
                            <p class="card-text">
                                <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($current_doctor['email']); ?>
                            </p>
                            <p class="text-success">
                                <i class="fas fa-check-circle me-2"></i>You are currently assigned to this doctor.
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="dashboard.php" class="btn btn-outline-success">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Doctor Selection -->
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-md me-2"></i>Choose Your Doctor
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Select a doctor to manage your prescriptions. Only your chosen doctor will be able to view and manage your prescriptions. 
                        You can change your doctor at any time.
                    </p>

                    <?php if (mysqli_num_rows($doctor_result) > 0): ?>
                        <form action="choose-doctor.php" method="post">
                            <div class="row">
                                <?php while($doctor = mysqli_fetch_assoc($doctor_result)): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card h-100 border-<?php echo ($current_doctor && $current_doctor['id'] == $doctor['id']) ? 'success' : 'light'; ?>">
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="doctor_id" 
                                                       id="doctor_<?php echo $doctor['id']; ?>" 
                                                       value="<?php echo $doctor['id']; ?>"
                                                       <?php echo ($current_doctor && $current_doctor['id'] == $doctor['id']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="doctor_<?php echo $doctor['id']; ?>">
                                                    <h6 class="card-title">Dr. <?php echo htmlspecialchars($doctor['name']); ?></h6>
                                                    <p class="card-text text-muted">
                                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($doctor['email']); ?>
                                                    </p>
                                                    <?php if ($current_doctor && $current_doctor['id'] == $doctor['id']): ?>
                                                        <span class="badge bg-success">Current Doctor</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" name="choose_doctor" class="btn btn-primary">
                                    <i class="fas fa-user-md me-2"></i>Select Doctor
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No doctors are currently available. Please contact the administrator.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-submit form when a doctor is selected
document.addEventListener('DOMContentLoaded', function() {
    const radioButtons = document.querySelectorAll('input[name="doctor_id"]');
    const form = document.querySelector('form');
    
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                // Add a small delay to show the selection
                setTimeout(() => {
                    form.submit();
                }, 500);
            }
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?> 