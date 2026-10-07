<?php
session_start();
require_once 'config.php';

// ✅ Check if admin or staff is logged in
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'staff'])) {
    header("Location: login.php");
    exit;
}

$adminName = $_SESSION['user']['name'] ?? 'Admin';

// ✅ ACCURATE QUERIES:

// Total unique students who visited
$totalStudentsQuery = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM visits");

$totalStudents = $totalStudentsQuery->fetchColumn();

// Total visits count
$totalVisitsQuery = $pdo->query("SELECT COUNT(*) FROM students");
$totalVisits = $totalVisitsQuery->fetchColumn();

// ▼▼▼ ADD THESE LINES ▼▼▼
// Debug: Check visits count
$debugCount = $pdo->query("SELECT COUNT(*) FROM visits")->fetchColumn();
error_log("Total visits in database: " . $debugCount);

// Debug: Check recent visits
$recentVisits = $pdo->query("SELECT visit_id, student_id, visit_date FROM visits ORDER BY visit_date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
error_log("Recent visits: " . print_r($recentVisits, true));
// ▲▲▲ UP TO HERE ▲▲▲


// Most common visit reasons (accurate)
$reasonQuery = $pdo->query("
    SELECT reason, COUNT(*) AS count 
    FROM visits 
    WHERE reason IS NOT NULL AND reason != ''
    GROUP BY reason 
    ORDER BY count DESC
    LIMIT 10
");
$visit_reasons = $reasonQuery->fetchAll(PDO::FETCH_KEY_PAIR);


// Custom / Other reasons: capture typed reasons that are not in the common list
$commonReasons = [
    'Diarrhea','Headache','Fever','Toothache','Allergic reaction','Cold'
];
$placeholders = implode(',', array_fill(0, count($commonReasons), '?'));
$otherStmt = $pdo->prepare("SELECT reason, COUNT(*) AS count FROM visits WHERE reason IS NOT NULL AND reason != '' AND reason NOT IN ($placeholders) GROUP BY reason ORDER BY count DESC LIMIT 20");
$otherStmt->execute($commonReasons);
$other_reasons = $otherStmt->fetchAll(PDO::FETCH_KEY_PAIR);
// Most visited courses (accurate - count actual visits per course)
$courseQuery = $pdo->query("
    SELECT s.course, COUNT(*) AS count
    FROM visits v
    INNER JOIN students s ON v.student_id = s.student_id
    WHERE s.course IS NOT NULL AND s.course != ''
    GROUP BY s.course
    ORDER BY count DESC
    LIMIT 8
");
$courses = $courseQuery->fetchAll(PDO::FETCH_KEY_PAIR);

// Most common treatment/item given (accurate)
$treatmentQuery = $pdo->query("
    SELECT medicine_given as treatment, COUNT(*) AS count
    FROM visits
    WHERE medicine_given IS NOT NULL AND medicine_given != ''
    GROUP BY medicine_given
    ORDER BY count DESC
    LIMIT 8
");
$treatments = $treatmentQuery->fetchAll(PDO::FETCH_KEY_PAIR);
// After the $treatments query, add:
$visitsWithTreatmentQuery = $pdo->query("
    SELECT COUNT(*) 
    FROM visits 
    WHERE medicine_given IS NOT NULL AND medicine_given != ''
");
$visitsWithTreatment = $visitsWithTreatmentQuery->fetchColumn();

// Custom / Other treatments/items: capture typed treatments/items that are not in the common list
$commonTreatments = ['Paracetamol','Syndex','Diatabs','Cetirizine','Bandaid','First-aid'];
$placeholdersTreat = implode(',', array_fill(0, count($commonTreatments), '?'));
$otherTreatStmt = $pdo->prepare("SELECT medicine_given AS treatment, COUNT(*) AS count FROM visits WHERE medicine_given IS NOT NULL AND medicine_given != '' AND medicine_given NOT IN ($placeholdersTreat) GROUP BY medicine_given ORDER BY count DESC LIMIT 20");
$otherTreatStmt->execute($commonTreatments);
$other_treatments = $otherTreatStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Recent activity (last 7 days)
$recentVisitsQuery = $pdo->query("
    SELECT COUNT(*) 
    FROM visits 
    WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$recentVisits = $recentVisitsQuery->fetchColumn();

// Monthly trend
$monthlyTrendQuery = $pdo->query("
    SELECT 
        DATE_FORMAT(visit_date, '%Y-%m') as month,
        COUNT(*) as count
    FROM visits 
    WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$monthlyTrend = $monthlyTrendQuery->fetchAll(PDO::FETCH_KEY_PAIR);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ECClinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Use admin dashboard sidebar styles -->
    <link rel="stylesheet" href="a_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #bb0505;
            --primary-light: #ffe5e5;
            --gray: #6c757d;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
            font-size: 0.875rem;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .dashboard-header {
            background: white;
            padding: 15px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dashboard-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .nav-tabs {
            background: var(--light);
            padding: 0 25px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav-tabs .nav-link {
            font-size: 0.8rem;
            padding: 8px 16px;
            color: var(--gray);
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            background: var(--primary);
            color: white;
            border: none;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            padding: 25px;
        }
        
        .chart-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .chart-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .chart-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-header p {
            font-size: 0.75rem;
            color: var(--gray);
            margin: 2px 0 0 0;
        }
        
        .chart-container {
            height: 250px;
            position: relative;
        }
        
        .btn-print {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-print:hover {
            background: #9a0404;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 0 25px 15px 25px;
        }
        
        .stat-item {
            background: var(--light);
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            border-left: 3px solid var(--primary);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            margin: 0;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--gray);
            margin: 0;
        }
        
        .data-table {
            font-size: 0.8rem;
            margin-top: 10px;
        }
        
        .data-table table {
            width: 100%;
        }
        
        .data-table th {
            background: var(--light);
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
                padding: 15px;
            }
            
            .dashboard-container {
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar (shared with manage_staff.php) -->
    <div class="sidebar">
        <h3><i class="fas fa-hospital"></i> ECClinic</h3>
        <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="manage_staff.php"><i class="fas fa-user-nurse"></i> Manage Staff</a>
        <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
        <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports & Analytics</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-chart-bar"></i> Clinic Analytics</h1>
                <p style="font-size: 0.8rem; color: #666; margin: 2px 0 0 0;">Accurate visit statistics and reports</p>
            </div>
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>

        <!-- Navigation -->
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" href="reports.php"><i class="fas fa-chart-pie"></i> Analytics</a>
            </li>
        </ul>

 <!-- Accurate Quick Stats -->
<div class="stats-summary">
    <div class="stat-item">
        <p class="stat-number"><?= $totalVisits ?></p>
        <p class="stat-label">Total Visits</p>
    </div>
    <div class="stat-item">
        <p class="stat-number"><?= $visitsWithTreatment ?></p>
        <p class="stat-label">Visits with Treatment</p>
    </div>
    <div class="stat-item">
        <p class="stat-number"><?= count($treatments) ?></p>
        <p class="stat-label">Unique Treatments/Items</p>
        <?php if (!empty($treatments)): ?>
        <div style="font-size: 0.7rem; margin-top: 5px; color: #666;">
            <?= implode(', ', array_keys($treatments)) ?>
        </div>
        <?php endif; ?>
    </div>
</div>

        <!-- Accurate Charts Grid -->
        <div class="charts-grid">
            <!-- Course Visits -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-book"></i> Visits by Course</h3>
                    <p>Top courses with most clinic visits</p>
                </div>
                <div class="chart-container">
                    <canvas id="courseChart"></canvas>
                </div>
                <?php if (!empty($courses)): ?>
                <div class="data-table">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Visits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course => $count): ?>
                            <tr>
                                <td><?= htmlspecialchars($course) ?></td>
                                <td><?= $count ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Treatments/Items Given -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-pills"></i> Treatments/Items Distribution</h3>
                    <p>Most frequently given treatments and items</p>
                </div>
                <div class="chart-container">
                    <canvas id="treatmentChart"></canvas>
                </div>
                <?php if (!empty($treatments)): ?>
                <div class="data-table">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Treatment/Item</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($treatments as $treatment => $count): ?>
                            <tr>
                                <td><?= htmlspecialchars($treatment) ?></td>
                                <td><?= $count ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Visit Reasons -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-notes-medical"></i> Visit Reasons</h3>
                    <p>Most common reasons for clinic visits</p>
                </div>
                <div class="chart-container">
                    <canvas id="reasonChart"></canvas>
                </div>
                <?php if (!empty($visit_reasons)): ?>
                <div class="data-table">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Reason</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visit_reasons as $reason => $count): ?>
                            <tr>
                                <td><?= htmlspecialchars($reason) ?></td>
                                <td><?= $count ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Custom / Other Reasons -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-ellipsis-h"></i> Custom / Other Reasons</h3>
                    <p>Typed reasons not in the common list</p>
                </div>
                <div class="chart-container" style="height:160px; display:flex; align-items:center; justify-content:center;">
                    <?php if (!empty($other_reasons)): ?>
                        <div class="data-table" style="width:100%;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Reason</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($other_reasons as $r => $c): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r) ?></td>
                                        <td><?= $c ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No custom reasons recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Custom / Other Treatments -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-capsules"></i> Custom / Other Treatments</h3>
                    <p>Typed treatments/items not in the common list</p>
                </div>
                <div class="chart-container" style="height:160px; display:flex; align-items:center; justify-content:center;">
                    <?php if (!empty($other_treatments)): ?>
                        <div class="data-table" style="width:100%;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Treatment / Item</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($other_treatments as $t => $c): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($t) ?></td>
                                        <td><?= $c ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No custom treatments/items recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div> <!-- .main-content -->

    <script>
        const courseData = <?= json_encode($courses) ?>;
        const treatmentData = <?= json_encode($treatments) ?>;
        const reasonData = <?= json_encode($visit_reasons) ?>;
        const monthlyData = <?= json_encode($monthlyTrend) ?>;

        // Compact chart options
        const compactOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: {
                            size: 10
                        }
                    }
                },
                tooltip: {
                    enabled: true
                }
            },
            scales: {
                x: {
                    ticks: {
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: {
                            size: 10
                        }
                    }
                }
            }
        };

        // Course Chart - Accurate data
        if (Object.keys(courseData).length > 0) {
            new Chart(document.getElementById('courseChart'), {
                type: 'bar',
                data: {
                    labels: Object.keys(courseData),
                    datasets: [{
                        label: 'Number of Visits',
                        data: Object.values(courseData),
                        backgroundColor: '#bb0505',
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: compactOptions
            });
        } else {
            document.getElementById('courseChart').innerHTML = '<p class="text-muted text-center">No course visit data available</p>';
        }

        // Treatment/Item Chart - Accurate data
        if (Object.keys(treatmentData).length > 0) {
            new Chart(document.getElementById('treatmentChart'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(treatmentData),
                    datasets: [{
                        data: Object.values(treatmentData),
                        backgroundColor: [
                            '#bb0505', '#36A2EB', '#FFCE56', '#4BC0C0', 
                            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    ...compactOptions,
                    cutout: '60%'
                }
            });
        } else {
            document.getElementById('treatmentChart').innerHTML = '<p class="text-muted text-center">No treatment/item data available</p>';
        }

        // Reason Chart - Accurate data
        if (Object.keys(reasonData).length > 0) {
            new Chart(document.getElementById('reasonChart'), {
                type: 'bar',
                data: {
                    labels: Object.keys(reasonData),
                    datasets: [{
                        label: 'Number of Visits',
                        data: Object.values(reasonData),
                        backgroundColor: '#36A2EB',
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    ...compactOptions,
                    indexAxis: 'y'
                }
            });
        } else {
            document.getElementById('reasonChart').innerHTML = '<p class="text-muted text-center">No visit reason data available</p>';
        }
    </script>
</body>
</html>
*** End Patch