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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Visit - Clinic Visit Monitoring</title>
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
        <h2 class="mb-4"><i class="fas fa-notes-medical"></i> Visit Details</h2>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Visit #<?php echo $visit['visit_id']; ?></h5>
                        <span class="badge bg-<?php echo $visit['status'] == 'completed' ? 'success' : ($visit['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                            <?php echo ucfirst($visit['status']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Student Information</h6>
                                <p><strong>Student ID:</strong> <?php echo htmlspecialchars($visit['student_number']); ?></p>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($visit['student_first_name'] . ' ' . $visit['student_last_name']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($visit['department']); ?></p>
                                <p><strong>Year:</strong> <?php echo htmlspecialchars($visit['year_of_study']); ?></p>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Visit Information</h6>
                                <p><strong>Date & Time:</strong> <?php echo date('M j, Y g:i A', strtotime($visit['visit_date'])); ?></p>
                                <p><strong>Attending Staff:</strong> 
                                    <?php if (!empty($visit['staff_first_name'])): ?>
                                        <?php echo htmlspecialchars($visit['staff_first_name'] . ' ' . $visit['staff_last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <h6>Visit Reason</h6>
                            <p class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($visit['visit_reason'])); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Diagnosis</h6>
                            <p class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($visit['diagnosis'])); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Treatment</h6>
                            <p class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($visit['treatment'])); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Additional Notes</h6>
                            <p class="bg-light p-3 rounded"><?php echo nl2br(htmlspecialchars($visit['notes'])); ?></p>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="edit_visit.php?id=<?php echo $visit['visit_id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit Visit
                            </a>
                            <a href="visits.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Visits
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>