<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a doctor
if (!is_logged_in() || !check_role('doctor')) {
	redirect('../login.php');
}

$doctor_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch patients (exclude those already assigned to this doctor to reduce clutter, but allow reassign)
$patients_sql = "SELECT id, name, email FROM users WHERE role = 'patient' ORDER BY name";
$patients_res = mysqli_query($conn, $patients_sql);
$patients = mysqli_fetch_all($patients_res, MYSQLI_ASSOC);

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patient_id'])) {
	$patient_id = (int)$_POST['patient_id'];
	if ($patient_id > 0) {
		// Upsert into patient_doctor
		$assign_sql = "INSERT INTO patient_doctor (patient_id, doctor_id) VALUES (?, ?) 
						ON DUPLICATE KEY UPDATE assigned_at = assigned_at";
		$assign_stmt = mysqli_prepare($conn, $assign_sql);
		mysqli_stmt_bind_param($assign_stmt, "ii", $patient_id, $doctor_id);
		if (mysqli_stmt_execute($assign_stmt)) {
			$success_message = 'Patient assigned successfully.';
		} else {
			$error_message = 'Failed to assign patient. Please try again.';
		}
		mysqli_stmt_close($assign_stmt);
	}
}

include '../includes/header.php';
?>

<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h4 mb-0"><i class="fas fa-user-plus me-2"></i>Assign Patient</h1>
		<a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
	</div>

	<?php if ($success_message): ?>
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			<?php echo htmlspecialchars($success_message); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<?php if ($error_message): ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<?php echo htmlspecialchars($error_message); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<div class="card shadow">
		<div class="card-body">
			<form method="POST">
				<div class="mb-3">
					<label for="patient_id" class="form-label">Select Patient</label>
					<select class="form-select" id="patient_id" name="patient_id" required>
						<option value="">-- Choose a patient --</option>
						<?php foreach ($patients as $patient): ?>
							<option value="<?php echo $patient['id']; ?>">
								<?php echo htmlspecialchars($patient['name'] . ' (' . $patient['email'] . ')'); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<button type="submit" class="btn btn-success">
					<i class="fas fa-user-check me-2"></i>Assign
				</button>
			</form>
		</div>
	</div>
</div>

<?php include '../includes/footer.php'; ?>
