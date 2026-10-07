<?php
require_once 'config.php';

if (!getCurrentUser()) {
    redirect('login.php');
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: visits.php");
    exit();
}

$visit_id = intval($_GET['id']);

// Get visit details
$stmt = $pdo->prepare("
    SELECT cv.*, s.student_number, s.first_name as student_first_name, s.last_name as student_last_name, 
           s.department, s.year_of_study, st.first_name as staff_first_name, st.last_name as staff_last_name
    FROM clinic_visits cv 
    JOIN students s ON cv.student_id = s.student_id 
    LEFT JOIN clinic_staff st ON cv.staff_id = st.staff_id 
    WHERE cv.visit_id = ?
");
$stmt->execute([$visit_id]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visit) {
    header("Location: visits.php?message=Visit+not+found");
    exit();
}

// Get students for dropdown
$stmt = $pdo->query("SELECT student_id, student_number, first_name, last_name FROM students ORDER BY last_name, first_name");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff for dropdown
$stmt = $pdo->query("SELECT staff_id, staff_number, first_name, last_name FROM clinic_staff ORDER BY last_name, first_name");
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get visit types
$stmt = $pdo->query("SELECT type_id, type_name FROM visit_types ORDER BY type_name");
$visitTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = intval($_POST['student_id']);
    $staff_id = !empty($_POST['staff_id']) ? intval($_POST['staff_id']) : null;
    $visit_date = $_POST['visit_date'];
    $visit_type_id = !empty($_POST['visit_type_id']) ? intval($_POST['visit_type_id']) : null;
    $visit_reason = sanitizeInput($_POST['visit_reason']);
    $diagnosis = sanitizeInput($_POST['diagnosis']);
    $treatment = sanitizeInput($_POST['treatment']);
    $notes = sanitizeInput($_POST['notes']);
    $status = $_POST['status'];
    
    // Validation
    if (empty($student_id)) $errors[] = "Student is required";
    if (empty($visit_date)) $errors[] = "Visit date is required";
    if (empty($visit_reason)) $errors[] = "Visit reason is required";
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE clinic_visits SET student_id = ?, staff_id = ?, visit_date = ?, visit_reason = ?, diagnosis = ?, treatment = ?, notes = ?, status = ? WHERE visit_id = ?");
            $stmt->execute([$student_id, $staff_id, $visit_date, $visit_reason, $diagnosis, $treatment, $notes, $status, $visit_id]);
            $success = "Visit updated successfully!";
            header("Location: view_visit.php?id=" . $visit_id);
            exit();
        } catch (PDOException $e) {
            $errors[] = "Error updating visit: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Visit - Clinic Visit Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-heartbeat"></i> Clinic Visit Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="students.php"><i class="fas fa-user-graduate"></i> Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="visits.php"><i class="fas fa-notes-medical"></i> Visits</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="staff.php"><i class="fas fa-user-md"></i> Staff</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <h2 class="mb-4"><i class="fas fa-edit"></i> Edit Visit #<?php echo $visit_id; ?></h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_id" class="form-label">Student *</label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">Select a student...</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['student_id']; ?>" 
                                        <?php echo $visit['student_id'] == $student['student_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['student_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <a href="add_student.php" target="_blank" class="btn btn-sm btn-outline-primary mt-1">Add New Student</a>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="visit_date" class="form-label">Visit Date & Time *</label>
                            <input type="datetime-local" class="form-control" id="visit_date" name="visit_date" 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($visit['visit_date'])); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="visit_type_id" class="form-label">Visit Reason</label>
                            <select class="form-select" id="visit_type_id" name="visit_type_id">
                                <option value="">Select reason...</option>
                                <?php foreach ($visitTypes as $type): ?>
                                    <option value="<?php echo $type['type_id']; ?>" 
                                        <?php echo (!empty($visit['visit_reason']) && strpos($visit['visit_reason'], $type['type_name']) !== false) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="other" <?php echo (!empty($visit['visit_reason']) && !in_array($visit['visit_reason'], array_column($visitTypes, 'type_name'))) ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="staff_id" class="form-label">Attending Staff</label>
                            <select class="form-select" id="staff_id" name="staff_id">
                                <option value="">Select staff...</option>
                                <?php foreach ($staff as $member): ?>
                                    <option value="<?php echo $member['staff_id']; ?>" 
                                        <?php echo $visit['staff_id'] == $member['staff_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($member['staff_number'] . ' - ' . $member['first_name'] . ' ' . $member['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="visit_reason" class="form-label">Visit Reason Details *</label>
                        <textarea class="form-control" id="visit_reason" name="visit_reason" rows="3" 
                                  placeholder="Describe the reason for the visit..."><?php echo htmlspecialchars($visit['visit_reason']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="diagnosis" class="form-label">Diagnosis</label>
                        <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" 
                                  placeholder="Enter diagnosis..."><?php echo htmlspecialchars($visit['diagnosis']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="treatment" class="form-label">Treatment</label>
                        <textarea class="form-control" id="treatment" name="treatment" rows="3" 
                                  placeholder="Enter treatment plan..."><?php echo htmlspecialchars($visit['treatment']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Any additional observations..."><?php echo htmlspecialchars($visit['notes']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="pending" <?php echo $visit['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $visit['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $visit['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Visit</button>
                        <a href="view_visit.php?id=<?php echo $visit_id; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>