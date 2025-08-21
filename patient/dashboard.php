<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a patient
if (!is_logged_in() || !check_role('patient')) {
    redirect('../login.php');
}

$user_id = $_SESSION['user_id'];

// Handle doctor selection form submission
if (isset($_POST['choose_doctor'])) {
    $doctor_id = intval($_POST['doctor_id']);
    
    // Validate that the selected user is actually a doctor
    $validate_sql = "SELECT id, name FROM users WHERE id = ? AND role = 'doctor'";
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
            $success_message = "Doctor selected successfully!";
        } else {
            $error_message = "Failed to select doctor. Please try again.";
        }
        mysqli_stmt_close($insert_stmt);
    } else {
        $error_message = "Invalid doctor selected.";
    }
    mysqli_stmt_close($validate_stmt);
}

// Fetch all doctors for the selection form
$doctor_query = "SELECT id, name FROM users WHERE role = 'doctor' ORDER BY name";
$doctor_result = mysqli_query($conn, $doctor_query);

// Get the currently assigned doctor for this patient
$current_doctor = null;
$current_doctor_sql = "SELECT d.id, d.name FROM patient_doctor pd 
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

// Get patient's prescriptions
$sql = "SELECT p.*, u.name as doctor_name, 
        (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = p.id) as medication_count
        FROM prescriptions p 
        JOIN users u ON p.doctor_id = u.id 
        WHERE p.patient_id = ? 
        ORDER BY p.created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$prescriptions = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Get patient's profile
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$patient = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Get unread notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$notifications = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Patient Profile Card -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-body">
                    <h5 class="card-title">Profile Information</h5>
                    <div class="mb-3">
                        <strong>Name:</strong> <?php echo htmlspecialchars($patient['name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?>
                    </div>
                    <a href="profile.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="choose-doctor.php" class="btn btn-outline-success btn-sm ms-2">
                        <i class="fas fa-user-md"></i> Choose Doctor
                    </a>
                </div>
            </div>
        </div>

        <!-- Doctor Selection Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-body">
                    <h5 class="card-title">Choose Your Doctor</h5>
                    <p class="text-muted">Select a doctor to manage your prescriptions. Only your chosen doctor will be able to view and manage your prescriptions.</p>
                    
                    <?php if ($current_doctor): ?>
                        <div class="alert alert-info mb-3">
                            <strong>Current Doctor:</strong> <?php echo htmlspecialchars($current_doctor['name']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="dashboard.php" method="post">
                        <div class="mb-3">
                            <label for="doctor_id" class="form-label">Select Doctor:</label>
                            <select name="doctor_id" id="doctor_id" class="form-select" required>
                                <option value="">-- Select Doctor --</option>
                                <?php 
                                mysqli_data_seek($doctor_result, 0);
                                while($doc = mysqli_fetch_assoc($doctor_result)): 
                                ?>
                                    <option value="<?php echo $doc['id']; ?>" 
                                            <?php echo ($current_doctor && $current_doctor['id'] == $doc['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($doc['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" name="choose_doctor" class="btn btn-primary">
                            <i class="fas fa-user-md me-2"></i>Choose Doctor
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="col-md-8 mb-4">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Prescriptions</h6>
                            <h2 class="mb-0"><?php echo mysqli_num_rows($prescriptions); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Active Prescriptions</h6>
                            <h2 class="mb-0">
                                <?php 
                                $active_count = 0;
                                mysqli_data_seek($prescriptions, 0);
                                while ($prescription = mysqli_fetch_assoc($prescriptions)) {
                                    if ($prescription['status'] === 'approved') {
                                        $active_count++;
                                    }
                                }
                                echo $active_count;
                                ?>
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Unread Notifications</h6>
                            <h2 class="mb-0"><?php echo mysqli_num_rows($notifications); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Prescriptions -->
    <div class="card shadow mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Prescriptions</h5>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($prescriptions) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th>Medications</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($prescriptions, 0);
                            while ($prescription = mysqli_fetch_assoc($prescriptions)): 
                            ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($prescription['diagnosis']); ?></td>
                                    <td><?php echo $prescription['medication_count']; ?> medications</td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($prescription['status']) {
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                'completed' => 'info',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($prescription['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-prescription.php?id=<?php echo $prescription['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No prescriptions found.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Notifications -->
    <?php if (mysqli_num_rows($notifications) > 0): ?>
    <div class="card shadow">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Notifications</h5>
        </div>
        <div class="card-body">
            <div class="list-group">
                <?php while ($notification = mysqli_fetch_assoc($notifications)): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></h6>
                            <small><?php echo time_elapsed_string($notification['created_at']); ?></small>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?> 