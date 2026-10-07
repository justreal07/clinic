<?php
require_once 'config.php';

if (!getCurrentUser()) {
    redirect('login.php');
}

$errors = [];
$success = '';

// Get course information if provided
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$course_info = null;

if ($course_id > 0) {
    $stmt = $pdo->prepare("SELECT course_code, course_name, department FROM courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $course_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all courses for dropdown
$stmt = $pdo->query("SELECT course_id, course_code, course_name FROM courses ORDER BY course_name");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get year of study options
$yearOptions = ['', '1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_number = sanitizeInput($_POST['student_number']);
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $department = sanitizeInput($_POST['department']);
    $year_of_study = sanitizeInput($_POST['year_of_study']);
    $selected_course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
    
    // Validation
    if (empty($student_number)) $errors[] = "Student number is required";
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($department)) $errors[] = "Department is required";
    if (empty($year_of_study)) $errors[] = "Year of study is required";
    
    // Check if student number already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_number = ?");
        $stmt->execute([$student_number]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Student number already exists";
        }
    }
    
    if (empty($errors)) {
    try {
        // Start a transaction
        $pdo->beginTransaction();
        
        // Insert student
        $stmt = $pdo->prepare("INSERT INTO students (student_number, first_name, last_name, email, phone, department, year_of_study) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_number, $first_name, $last_name, $email, $phone, $department, $year_of_study]);
        $student_id = $pdo->lastInsertId();
        
        // Create a visit record for the new student
        $visit_date = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO visits (student_id, visit_date, visit_reason, status, created_by) 
            VALUES (?, ?, 'New Student Registration', 'Completed', ?)
        ");
        $stmt->execute([$student_id, $visit_date, $_SESSION['user_id'] ?? 1]);
        
        // Commit the transaction
        $pdo->commit();
        
        $success = "Student and initial visit record added successfully!";
        // Clear form after successful submission
        $_POST = [];
        
        // Optional: Redirect to student list with success message
        header("Location: students.php?message=Student+added+successfully");
        exit();
    } catch (PDOException $e) {
        // Rollback the transaction if something failed
        $pdo->rollBack();
        $errors[] = "Error adding student: " . $e->getMessage();
    }
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - Clinic Visit Monitoring</title>
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
        <h2 class="mb-4"><i class="fas fa-user-plus"></i> Add New Student</h2>
        
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
                    <?php if ($course_info): ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Adding student for course: <?php echo htmlspecialchars($course_info['course_code'] . ' - ' . $course_info['course_name']); ?></h6>
                            <p>Department: <?php echo htmlspecialchars($course_info['department']); ?></p>
                        </div>
                        
                        <!-- Hidden field to pass course_id -->
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        
                        <!-- Auto-fill department if course has one -->
                        <?php if (!empty($course_info['department']) && empty($_POST['department'])): ?>
                            <input type="hidden" name="department" value="<?php echo htmlspecialchars($course_info['department']); ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="student_number" class="form-label">Student Number *</label>
                            <input type="text" class="form-control" id="student_number" name="student_number" 
                                   value="<?php echo htmlspecialchars($_POST['student_number'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="year_of_study" class="form-label">Year of Study *</label>
                            <select class="form-select" id="year_of_study" name="year_of_study" required>
                                <option value="">Select Year</option>
                                <?php foreach ($yearOptions as $year): ?>
                                    <option value="<?php echo $year; ?>" 
                                        <?php echo ($_POST['year_of_study'] ?? '') == $year ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="department" class="form-label">Department *</label>
                        <input type="text" class="form-control" id="department" name="department" 
                               value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>" required>
                        <small class="form-text text-muted">This will be auto-filled if you selected a course above.</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="course_id" class="form-label">Course (Optional)</label>
                        <select class="form-select" id="course_id" name="course_id">
                            <option value="">-- Select a Course --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" 
                                    <?php echo $course_id == $course['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">You can also select a course here if not done from dashboard.</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Add Student</button>
                        <a href="students.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.