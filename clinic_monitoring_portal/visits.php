<?php
require_once 'config.php';

if (!getCurrentUser()) {
    redirect('login.php');
}

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$student_filter = isset($_GET['student']) ? $_GET['student'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$whereClause = [];
$params = [];

if (!empty($status_filter)) {
    $whereClause[] = "cv.status = ?";
    $params[] = $status_filter;
}

if (!empty($student_filter)) {
    $whereClause[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_number LIKE ?)";
    $params[] = "%{$student_filter}%";
    $params[] = "%{$student_filter}%";
    $params[] = "%{$student_filter}%";
}

if (!empty($date_from)) {
    $whereClause[] = "cv.visit_date >= ?";
    $params[] = "$date_from 00:00:00";
}

if (!empty($date_to)) {
    $whereClause[] = "cv.visit_date <= ?";
    $params[] = "$date_to 23:59:59";
}

$whereString = !empty($whereClause) ? "WHERE " . implode(" AND ", $whereClause) : "";

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Count total records
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM clinic_visits cv JOIN students s ON cv.student_id = s.student_id $whereString");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch visits
$stmt = $pdo->prepare("
    SELECT cv.visit_id, cv.visit_date, cv.visit_reason, cv.diagnosis, cv.treatment, cv.status, 
           s.student_number, s.first_name, s.last_name, st.first_name as staff_first_name, st.last_name as staff_last_name
    FROM clinic_visits cv 
    JOIN students s ON cv.student_id = s.student_id 
    LEFT JOIN clinic_staff st ON cv.staff_id = st.staff_id 
    $whereString
    ORDER BY cv.visit_date DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all students for filter dropdown
$stmt = $pdo->query("SELECT student_id, student_number, first_name, last_name FROM students ORDER BY last_name, first_name");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statuses for filter
$statuses = ['all' => 'All Statuses', 'pending' => 'Pending', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visits - Clinic Visit Monitoring</title>
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
                        <a class="nav-link active" href="visits.php"><i class="fas fa-notes-medical"></i> Visits</a>
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
        <h2 class="mb-4"><i class="fas fa-notes-medical"></i> Clinic Visits</h2>
        
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Filter Visits</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach ($statuses as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo $status_filter == $key ? 'selected' : ''; ?>>
                                    <?php echo $value; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="student" class="form-label">Student</label>
                        <input type="text" class="form-control" id="student" name="student" 
                               value="<?php echo htmlspecialchars($student_filter); ?>" placeholder="Search by name or ID">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                        <a href="visits.php" class="btn btn-outline-secondary ms-2">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5>Total Records: <?php echo number_format($totalRecords); ?></h5>
            <a href="add_visit.php" class="btn btn-primary"><i class="fas fa-plus"></i> Record New Visit</a>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Date & Time</th>
                        <th>Reason</th>
                        <th>Diagnosis</th>
                        <th>Treatment</th>
                        <th>Status</th>
                        <th>Staff</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visits)): ?>
                        <tr>
                            <td colspan="9" class="text-center">No visits found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($visits as $visit): ?>
                            <tr>
                                <td>#<?php echo $visit['visit_id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($visit['student_number'] . ' - ' . $visit['first_name'] . ' ' . $visit['last_name']); ?>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($visit['visit_date'])); ?></td>
                                <td><?php echo htmlspecialchars(substr($visit['visit_reason'], 0, 40)) . (strlen($visit['visit_reason']) > 40 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars(substr($visit['diagnosis'], 0, 40)) . (strlen($visit['diagnosis']) > 40 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars(substr($visit['treatment'], 0, 40)) . (strlen($visit['treatment']) > 40 ? '...' : ''); ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                        switch($visit['status']) {
                                            case 'completed': echo 'bg-success'; break;
                                            case 'pending': echo 'bg-warning'; break;
                                            case 'cancelled': echo 'bg-danger'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php echo ucfirst($visit['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($visit['staff_first_name'])): ?>
                                        <?php echo htmlspecialchars($visit['staff_first_name'] . ' ' . $visit['staff_last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_visit.php?id=<?php echo $visit['visit_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_visit.php?id=<?php echo $visit['visit_id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&student=<?php echo urlencode($student_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&student=<?php echo urlencode($student_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&student=<?php echo urlencode($student_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>