<?php
session_start();
require_once 'config.php';
// Get predefined items for treatments/medicines
$predefined_raw = ['Medicine' => [], 'Item' => []];
try {
    $predefined_raw = getPredefinedItems();
} catch (Exception $e) {
    $predefined_raw = ['Medicine' => [], 'Item' => []];
}

// Convert to arrays for JavaScript
$predefined_items = ['Medicine' => [], 'Item' => []];
foreach (($predefined_raw['Medicine'] ?? []) as $r) {
    $predefined_items['Medicine'][] = $r['name'];
}
foreach (($predefined_raw['Item'] ?? []) as $r) {
    $predefined_items['Item'][] = $r['name'];
}


// ✅ Only allow admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php?role=admin");
    exit;
}

// 🔥 Auto cleanup (remove visits older than 1 year)
$cleanupQuery = "DELETE FROM visits WHERE visit_date < DATE_SUB(NOW(), INTERVAL 1 YEAR)";
$pdo->exec($cleanupQuery);

// ✅ CORRECTED QUERY: Fetch students with latest visit info (fixed medicine display)
$query = "
    SELECT 
        s.student_id,
        s.first_name,
        s.last_name,
        s.student_number,
        s.gender,
        s.course,
        v.reason,
        v.medicine_given
    FROM students s
    LEFT JOIN (
        SELECT v1.* 
        FROM visits v1
        INNER JOIN (
            SELECT student_id, MAX(visit_date) as latest_date
            FROM visits
            GROUP BY student_id
        ) v2 ON v1.student_id = v2.student_id AND v1.visit_date = v2.latest_date
    ) v ON s.student_id = v.student_id
    ORDER BY s.student_id DESC
";
$stmt = $pdo->query($query);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter pending students (for dropdown)
$pending_students = array_filter($students, fn($s) => empty($s['reason']));

// Statistics
$totalPatients = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM visits WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$totalAppointments = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM visits WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$totalDoctors = $pdo->query("SELECT COUNT(*) FROM clinic_staff WHERE staff_number IS NULL OR staff_number <> 'admin'")->fetchColumn();
$totalReports = $pdo->query("SELECT COUNT(*) FROM visits WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

// Admin name
$adminName = $_SESSION['user']['name'] ?? 'Admin';

// URL parameters
$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
$defaultTab = $_GET['tab'] ?? 'records';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECClinic - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- shared sidebar/dashboard styles -->
    <link rel="stylesheet" href="a_dashboard.css">
    <style>
        :root {
            --primary: #bb0505;
            --primary-dark: #9a0404;
            --primary-light: #ffe5e5;
            --dark: #2c2c2c;
        }

        body {
            background: linear-gradient(135deg, var(--primary-light) 0%, #ffffff 100%);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }

        .dashboard-container {
            max-width: 1300px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-header h2 {
            margin: 0;
            font-weight: 700;
        }

        .nav-tabs .nav-link.active {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }

        .nav-tabs .nav-link {
            color: var(--dark);
            font-weight: 500;
        }

        .table thead {
            background: var(--primary-light);
        }

        .btn-submit {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .status-pending {
            color: #dc3545;
            font-weight: 600;
        }

        .status-recorded {
            color: #28a745;
            font-weight: 600;
        }

        .medicine-given {
            background-color: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid var(--primary);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        .admin-nav {
            background: #f8f9fa;
            padding: 15px 30px;
            border-bottom: 1px solid #dee2e6;
        }

        .admin-nav .nav-link.active {
            background: var(--primary);
            color: #fff;
        }

        .dashboard-content {
            padding: 30px;
        }
    </style>
</head>
<body>
    <!-- Sidebar (shared with reports.php/manage_staff.php) -->
    <div class="sidebar">
        <h3><i class="fas fa-hospital"></i> ECClinic</h3>
        <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="manage_staff.php"><i class="fas fa-user-nurse"></i> Manage Staff</a>
        <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
    <div class="dashboard-container">
    <div class="dashboard-header">
        <h2><i class="fas fa-user-shield"></i> Admin Dashboard</h2>
        <div>
            <span>Welcome, <strong><?= htmlspecialchars($adminName) ?></strong></span>
            <a href="logout.php" class="btn btn-light btn-sm ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="admin-nav">
        <ul class="nav">
            <li class="nav-item">
                <a class="nav-link active" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
            </li>
            <!-- Manage Staff and Reports removed from admin nav per request -->
        </ul>
    </div>

    <!-- Statistics -->
    <div class="stats-grid" style="padding: 0 30px;">
        <div class="stat-card">
            <h3><?= $totalPatients ?></h3>
            <p><i class="fas fa-user-injured"></i> Weekly Student Visits</p>
        </div>
        <div class="stat-card">
            <h3><?= $totalAppointments ?></h3>
            <p><i class="fas fa-calendar-check"></i> Monthly Student Visits</p>
        </div>
        <div class="stat-card">
            <h3><?= $totalDoctors ?></h3>
            <p><i class="fas fa-user-md"></i> Medical Staff</p>
        </div>
        <div class="stat-card">
            <h3><?= $totalReports ?></h3>
            <p><i class="fas fa-file-medical"></i> Weekly Reports</p>
        </div>
    </div>

    <div class="dashboard-content">
        <ul class="nav nav-tabs" id="dashboardTabs">
            <li class="nav-item">
                <a class="nav-link active" id="records-tab" data-bs-toggle="tab" href="#records" role="tab">Student Records</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="visit-tab" data-bs-toggle="tab" href="#visit" role="tab">Record Visit</a>
            </li>
        </ul>

        <div class="tab-content mt-4">
            <!-- Student Records -->
            <div class="tab-pane fade show active" id="records" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover text-center align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student Name</th>
                                <th>Student Number</th>
                                <th>Gender</th>
                                <th>Course</th>
                                <th>Visit Reason</th>
                                <th>Medicine Given</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?= $s['student_id'] ?></td>
                                <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                                <td><?= htmlspecialchars($s['student_number']) ?></td>
                                <td><?= htmlspecialchars($s['gender']) ?></td>
                                <td><?= htmlspecialchars($s['course'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if (!empty($s['reason'])): ?>
                                        <span class="status-recorded"><?= htmlspecialchars($s['reason']) ?></span>
                                    <?php else: ?>
                                        <span class="status-pending">No Visit Recorded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($s['medicine_given'])): ?>
                                        <span class="medicine-given"><?= htmlspecialchars($s['medicine_given']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Record Visit -->
<div class="tab-pane fade" id="visit" role="tabpanel">
    <h4>Record a New Visit</h4>
    <form method="POST" action="record_visit.php" class="mt-3">
        <div class="mb-3">
            <label for="student_id" class="form-label">Select Student</label>
            <select class="form-select" id="student_id" name="student_id" required>
                <option value="">-- Choose Student --</option>
                <?php foreach ($pending_students as $student): ?>
                    <option value="<?= $student['student_id'] ?>"
                        data-current-reason="<?= htmlspecialchars($student['reason'] ?? '') ?>"
                        data-current-medicine="<?= htmlspecialchars($student['medicine_given'] ?? '') ?>">
                        <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' (' . $student['student_number'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="reason" class="form-label">Reason for Visit</label>
            <select class="form-select" id="reason" name="reason" required>
                <option value="">-- Select Reason --</option>
                <option value="Fever">Fever</option>
                <option value="Cough/Cold">Cough/Cold</option>
                <option value="Headache">Headache</option>
                <option value="Stomach Ache">Stomach Ache</option>
                <option value="Injury">Injury</option>
                <option value="Diarrhea">Diarrhea</option>
                <option value="Toothache">Toothache</option>
                <option value="Allergic Reaction">Allergic Reaction</option>
                <option value="Other">Other (Please Specify)</option>
            </select>
            <div id="other_reason_container" style="display:none;margin-top:10px;">
                <input type="text" class="form-control" id="other_reason" name="other_reason">
            </div>
        </div>

        <div class="mb-3">
            <label for="treatment" class="form-label">Treatment / Medicine</label>
            <select class="form-select" id="treatment" name="treatment" required disabled>
                <option value="">-- Select Treatment --</option>
            </select>
            <div id="other_treatment_container" style="display:none;margin-top:10px;">
                <input type="text" class="form-control" id="other_treatment" name="other_treatment">
            </div>
        </div>

        <div class="mb-3">
            <label for="quantity_used" class="form-label">Quantity</label>
            <input type="number" class="form-control" id="quantity_used" name="quantity_used" value="1" min="1" required>
        </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
        </div>

        <div id="inventoryWarning" class="alert" style="display:none;"></div>

        <button type="submit" id="submitVisit" class="btn btn-primary">
    <i class="fas fa-save"></i> Record Visit
</button>

    </form>
</div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const reason = document.getElementById('reason');
    const treatment = document.getElementById('treatment');
    const quantityInput = document.getElementById('quantity_used');
    const inventoryWarning = document.getElementById('inventoryWarning');

    const otherReasonContainer = document.getElementById('other_reason_container');
    const otherReasonInput = document.getElementById('other_reason');
    const otherTreatmentContainer = document.getElementById('other_treatment_container');
    const otherTreatmentInput = document.getElementById('other_treatment');

    const predefinedItems = <?= json_encode($predefined_items) ?>;

    const reasonToMedicine = {
        'Fever': 'Paracetamol',
        'Cough/Cold': 'Antihistamine',
        'Headache': 'Ibuprofen',
        'Stomach Ache': 'Antacid',
        'Injury': 'Paracetamol',
        'Diarrhea': 'Loperamide',
        'Toothache': 'Ibuprofen',
        'Allergic Reaction': 'Antihistamine'
    };

    function populateTreatmentOptions(reasonVal) {
        treatment.innerHTML = '<option value="">-- Select Treatment --</option>';

        if (predefinedItems.Medicine.length) {
            const g = document.createElement('optgroup');
            g.label = 'Medicines';
            predefinedItems.Medicine.forEach(i => g.append(new Option(i, i)));
            treatment.appendChild(g);
        }

        if (predefinedItems.Item.length) {
            const g = document.createElement('optgroup');
            g.label = 'Medical Supplies';
            predefinedItems.Item.forEach(i => g.append(new Option(i, i)));
            treatment.appendChild(g);
        }

        treatment.append(new Option('Other (Please Specify)', 'Other'));

        if (reasonToMedicine[reasonVal]) {
            [...treatment.options].forEach(o => {
                if (o.value === reasonToMedicine[reasonVal]) o.selected = true;
            });
            checkInventory();
        }
    }

    async function checkInventory() {
    const selected = treatment.value;
    const qty = parseInt(quantityInput.value) || 1;
    const submitBtn = document.getElementById('submitVisit');

    // Skip if nothing selected or "Other"
    if (!selected || selected === 'Other') {
        inventoryWarning.style.display = 'none';
        submitBtn.disabled = false;
        return true;
    }

    try {
        const response = await fetch(
            `inventory_api.php?name=${encodeURIComponent(selected)}`
        );
        const data = await response.json();

        if (!data.ok) {
            inventoryWarning.className = 'alert alert-danger';
            inventoryWarning.textContent = 'Unable to verify inventory.';
            inventoryWarning.style.display = '';
            submitBtn.disabled = true;
            return false;
        }

        const available = parseInt(data.quantity) || 0;
        const threshold = parseInt(data.threshold) || 0;

        // ❌ ZERO STOCK — HARD BLOCK
        if (available === 0) {
            inventoryWarning.className = 'alert alert-danger';
            inventoryWarning.textContent =
                '❌ Out of stock. This item cannot be used to record a visit.';
            inventoryWarning.style.display = '';
            submitBtn.disabled = true;
            return false;
        }

        // ❌ NOT ENOUGH STOCK
        if (qty > available) {
            inventoryWarning.className = 'alert alert-danger';
            inventoryWarning.textContent =
                `❌ Only ${available} left in stock. Reduce quantity or select another item.`;
            inventoryWarning.style.display = '';
            submitBtn.disabled = true;
            return false;
        }

        // ⚠ LOW STOCK (allowed but warned)
        if (available <= threshold) {
            inventoryWarning.className = 'alert alert-warning';
            inventoryWarning.textContent =
                `⚠ Low stock: ${available} remaining.`;
            inventoryWarning.style.display = '';
            submitBtn.disabled = false;
            return true;
        }

        // ✅ OK
        inventoryWarning.className = 'alert alert-success';
        inventoryWarning.textContent =
            `✔ In stock: ${available} available.`;
        inventoryWarning.style.display = '';
        submitBtn.disabled = false;
        return true;

    } catch (err) {
        console.error(err);
        inventoryWarning.className = 'alert alert-warning';
        inventoryWarning.textContent =
            'Inventory check failed. Please try again.';
        inventoryWarning.style.display = '';
        submitBtn.disabled = true;
        return false;
    }
}


    reason.addEventListener('change', () => {
        otherReasonContainer.style.display = reason.value === 'Other' ? '' : 'none';
        otherReasonInput.required = reason.value === 'Other';

        treatment.disabled = !reason.value;
        if (reason.value) populateTreatmentOptions(reason.value);
    });

    treatment.addEventListener('change', () => {
        otherTreatmentContainer.style.display = treatment.value === 'Other' ? '' : 'none';
        otherTreatmentInput.required = treatment.value === 'Other';
        checkInventory();
    });
    visitForm.addEventListener('submit', async function (e) {
    const ok = await checkInventory();
    if (!ok) {
        e.preventDefault();
        alert(
            'This record cannot be saved because the selected treatment is out of stock.'
        );
        treatment.focus();
        return false;
    }
});


    quantityInput.addEventListener('input', checkInventory);
});
</script>


</body>
</html>