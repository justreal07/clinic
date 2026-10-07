<?php
session_start();
require_once 'config.php';

// Ensure only staff can access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'staff') {
    header("Location: login.php");
    exit;
}

// 🔥 AUTOMATIC CLEANUP: Delete visit records older than 1 year
$cleanupQuery = "DELETE FROM visits WHERE visit_date < DATE_SUB(NOW(), INTERVAL 1 YEAR)";
$pdo->exec($cleanupQuery);

// ✅ CORRECTED QUERY: Fetch students with latest visit info
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

// Filter students who have no recorded visit OR have no medicine given
$pending_students = array_filter($students, function($student) {
    // Only show students with no medicine given
    return empty($student['reason']) || empty($student['medicine_given']);
});

// Load predefined items so staff form can use the same canonical list (keeps parity with admin)
$predefined_raw = ['Medicine' => [], 'Item' => []];
try {
    $tmp = getPredefinedItems();
    if (is_array($tmp)) { $predefined_raw = $tmp; }
} catch (Exception $e) {
    $predefined_raw = ['Medicine' => [], 'Item' => []];
}
// convert to arrays for JS
$predefined_items = ['Medicine' => [], 'Item' => []];
foreach (($predefined_raw['Medicine'] ?? []) as $r) { $predefined_items['Medicine'][] = $r['name']; }
foreach (($predefined_raw['Item'] ?? []) as $r) { $predefined_items['Item'][] = $r['name']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECClinic - Staff Dashboard</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="landing.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            max-width: 1100px;
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
        .dashboard-content {
            padding: 30px;
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
        /* Confirmation modal custom styles */
        .confirm-modal .modal-content {
            border-radius: 12px;
            padding: 20px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .confirm-modal .modal-header {
            border-bottom: none;
            padding-bottom: 0;
        }
        .confirm-modal .modal-title {
            font-weight: 700;
            color: var(--dark);
        }
        .confirm-modal .modal-body {
            color: #333;
            font-size: 15px;
            margin-top: 8px;
        }
        .confirm-modal .btn-yes {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
        }
        .confirm-modal .btn-notyet {
            background: transparent;
            color: var(--dark);
            border: 1px solid #ddd;
            padding: 8px 14px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2><i class="fas fa-user-tie"></i> Staff Dashboard</h2>
            <div>
                <span>Welcome, <strong><?= htmlspecialchars($_SESSION['user']['name']) ?></strong></span>
                <a href="logout.php" class="btn btn-light btn-sm ms-3"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="dashboard-content">
            <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="records-tab" data-bs-toggle="tab" href="#records" role="tab">Student Records</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="visit-tab" data-bs-toggle="tab" href="#visit" role="tab">Record New Visit</a>
                </li>
            </ul>

            <div class="tab-content mt-4">
                <!-- Student Records -->
                <div class="tab-pane fade show active" id="records" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle text-center">
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
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= $student['student_id'] ?></td>
                                    <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                    <td><?= htmlspecialchars($student['student_number']) ?></td>
                                    <td><?= htmlspecialchars($student['gender']) ?></td>
                                    <td><?= htmlspecialchars($student['course'] ?? 'N/A') ?></td>
                                    <td>
                                        <?php if (!empty($student['reason'])): ?>
                                            <span class="status-recorded"><?= htmlspecialchars($student['reason']) ?></span>
                                        <?php else: ?>
                                            <span class="status-pending">No Visit Recorded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($student['medicine_given'])): ?>
                                            <span class="medicine-given"><?= htmlspecialchars($student['medicine_given']) ?></span>
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

                <!-- Record New Visit -->
                <div class="tab-pane fade" id="visit" role="tabpanel">
                    <h4>Record a New Visit</h4>
                    <form method="POST" action="record_visit.php" class="mt-3">
                        <div class="mb-3">
                            <label for="student_id" class="form-label">Select Student</label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">-- Choose Student --</option>
                                <?php foreach ($pending_students as $student): ?>
                                    <option value="<?= $student['student_id'] ?>" 
                                        data-current-reason="<?= htmlspecialchars($student['reason']) ?>" 
                                        data-current-medicine="<?= htmlspecialchars($student['medicine_given']) ?>">
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> 
                                        (<?= htmlspecialchars($student['student_number']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">Visit Reason</label>
                            <select class="form-select" id="reason" name="reason" required>
                                <option value="">-- Select Common Reason --</option>
                                <option value="Diarrhea">Diarrhea</option>
                                <option value="Headache">Headache</option>
                                <option value="Fever">Fever</option>
                                <option value="Toothache">Toothache</option>
                                <option value="Allergic reaction">Allergic reaction</option>
                                <option value="Cold">Cold</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3" id="otherReasonContainer" style="display:none;">
                            <label for="other_reason" class="form-label">Other Reason (please type)</label>
                            <input type="text" class="form-control" id="other_reason" name="other_reason" placeholder="Type custom reason (e.g., sprain, insect bite)">
                        </div>

                        <div class="mb-3">
                            <label for="treatment" class="form-label">Treatment / Item Given</label>
                            <!-- Disabled by default; enabled when staff selects a reason/student -->
                            <select class="form-select" id="treatment" name="treatment" disabled>
                                <option value="">-- Select Treatment / Item --</option>
                                <optgroup label="Medications">
                                    <option value="Paracetamol">Paracetamol</option>
                                    <option value="Syndex">Syndex</option>
                                    <option value="Diatabs">Diatabs</option>
                                    <option value="Cetirizine">Cetirizine</option>
                                </optgroup>
                                <optgroup label="Supplies / Items">
                                    <option value="Bandaid">Bandaid</option>
                                    <option value="First-aid">First-aid</option>
                                </optgroup>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3 d-flex align-items-start" style="gap:12px;">
                            <div style="flex:1;">
                                <div id="treatmentWrapper">
                                    <label for="treatment" class="form-label visually-hidden">Treatment / Item Given</label>
                                    <!-- the select is above -->
                                </div>
                            </div>
                            <div style="width:140px;">
                                <label for="quantity_used" class="form-label">Qty Used</label>
                                <input type="number" min="1" class="form-control" id="quantity_used" name="quantity_used" value="1" required>
                            </div>
                        </div>

                        <div class="mb-3" id="otherTreatmentContainer" style="display:none;">
                            <label for="other_treatment" class="form-label">Other Treatment / Item (please type)</label>
                            <input type="text" class="form-control" id="other_treatment" name="other_treatment" placeholder="Type custom treatment or item">
                        </div>

                        <div id="inventoryWarning" style="display:none;" class="alert alert-warning">Low stock</div>

                        <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Record Visit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Confirmation Modal -->
    <!-- Modal removed: Medicine is enabled immediately when a reason/student is selected -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Modal control + reason->medicine mapping
    document.addEventListener('DOMContentLoaded', function(){
    var reason = document.getElementById('reason');
    var treatment = document.getElementById('treatment');
    var studentSelect = document.getElementById('student_id');

        // Map reasons to related medicines
        var reasonToMeds = {
            'Diarrhea': ['Diatabs', 'ORS', 'First-aid'],
            'Headache': ['Paracetamol', 'Ibuprofen'],
            'Fever': ['Paracetamol', 'Ibuprofen', 'Cough Syrup'],
            'Toothache': ['Paracetamol', 'Antibiotic', 'First-aid', 'Bandaid'],
            'Allergic reaction': ['Cetirizine', 'Antihistamine'],
            'Cold': ['Cough Syrup', 'Paracetamol', 'Antihistamine'],
            'Stomach Ache': ['Antacid','Diatabs','ORS'],
            'Injury': ['First-aid','Bandaid','Gauze']
        };

        // Server-provided predefined items (keeps staff treatments in sync with admin)
        var PREDEFINED = {
            Medicine: <?= json_encode($predefined_items['Medicine']) ?>,
            Item: <?= json_encode($predefined_items['Item']) ?>
        };

        function populateTreatmentOptions(selectedReason){
            // clear current options
            treatment.innerHTML = '';
            var defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = '-- Select Treatment / Item --';
            treatment.appendChild(defaultOpt);

            var meds = reasonToMeds[selectedReason] || [];
            // create optgroups for visual separation
            var medGroup = document.createElement('optgroup');
            medGroup.label = 'Medications';
            var itemGroup = document.createElement('optgroup');
            itemGroup.label = 'Supplies / Items';

            // itemList seeded from server predefined items (plus a couple of legacy names)
            var predefinedMeds = PREDEFINED.Medicine || [];
            var predefinedItems = PREDEFINED.Item || [];
            var itemList = predefinedItems.concat(['Bandaid','First-aid']);

            // add related meds/items first into appropriate groups
            meds.forEach(function(m){
                var opt = document.createElement('option');
                opt.value = m;
                opt.textContent = m;
                if(itemList.indexOf(m) !== -1) itemGroup.appendChild(opt);
                else medGroup.appendChild(opt);
            });

            // ensure full list also available (to allow other choices)
            // ensure all predefined meds/items are also available
            predefinedMeds.forEach(function(m){ if(meds.indexOf(m) === -1){ var opt = document.createElement('option'); opt.value = m; opt.textContent = m; medGroup.appendChild(opt); }});
            predefinedItems.forEach(function(m){ if(meds.indexOf(m) === -1){ var opt = document.createElement('option'); opt.value = m; opt.textContent = m; itemGroup.appendChild(opt); }});

            // append groups only if they have options
            if(medGroup.children.length > 0) treatment.appendChild(medGroup);
            if(itemGroup.children.length > 0) treatment.appendChild(itemGroup);
            // always include an Other option at the end for custom entries
            var otherOpt = document.createElement('option');
            otherOpt.value = 'Other';
            otherOpt.textContent = 'Other';
            treatment.appendChild(otherOpt);
        }

        // When reason changes and medicine is still disabled, ask to continue
    var otherReasonContainer = document.getElementById('otherReasonContainer');
    var otherReasonInput = document.getElementById('other_reason');
    var otherTreatmentContainer = document.getElementById('otherTreatmentContainer');
    var otherTreatmentInput = document.getElementById('other_treatment');

        reason.addEventListener('change', function(){
            var val = reason.value;
                if(val === 'Other'){
                // show textbox for custom reason
                otherReasonContainer.style.display = '';
                otherReasonInput.required = true;
                otherReasonInput.focus();
                // prepare medicine options for custom reason if any (empty by default)
                    populateTreatmentOptions(otherReasonInput.value || '');
                    if(treatment.disabled){
                        // enable treatment directly (no popup)
                        treatment.disabled = false;
                        treatment.required = true;
                    }
            } else {
                // hide other reason input
                otherReasonContainer.style.display = 'none';
                otherReasonInput.required = false;
                // prepare medicine options related to reason
                if(val){
                    populateTreatmentOptions(val);
                    if(treatment.disabled){
                        // enable treatment directly (no popup)
                        treatment.disabled = false;
                        treatment.required = true;
                    }
                } else {
                    populateTreatmentOptions('');
                }
            }
        });

        // If the user types a custom reason, update medicine options live
        otherReasonInput.addEventListener('input', function(){
            populateTreatmentOptions(this.value);
        });

        // When treatment changes and user selects 'Other', show textbox for custom treatment
        treatment.addEventListener('change', function(){
            var val = treatment.value;
            var qtyInput = document.getElementById('quantity_used');
            var warn = document.getElementById('inventoryWarning');
            warn.style.display = 'none';
            
            // If switching to 'Other', clear any previous warning
            if(val === 'Other') {
                warn.style.display = 'none';
            }
            if(val === 'Other'){
                otherTreatmentContainer.style.display = '';
                otherTreatmentInput.required = true;
                otherTreatmentInput.focus();
            } else {
                otherTreatmentContainer.style.display = 'none';
                otherTreatmentInput.required = false;
            }

            // If a concrete treatment is selected (not empty and not 'Other'), check inventory via the API
            if(val && val !== 'Other'){
                fetch('inventory_api.php?name=' + encodeURIComponent(val))
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if(!data.ok){ return; }
                        var q = data.quantity || 0;
                        var threshold = data.threshold || 5;
                        var used = parseInt(document.getElementById('quantity_used').value || '1', 10) || 1;
                        
                        if(!data.exists){
                            // Not found in inventory -> show available amount (0) and a neutral hint
                            warn.textContent = 'Available: ' + q + ' — no inventory record found';
                            warn.className = 'alert alert-secondary';
                            warn.style.display = '';
                            // Disable form submission for non-existent items
                            warn.setAttribute('data-out-of-stock', 'true');
                        } else {
                            // Show exact availability and severity
                            if(q <= 0){
                                warn.textContent = 'Available: 0 — Out of stock';
                                warn.className = 'alert alert-danger';
                                warn.style.display = '';
                                warn.setAttribute('data-out-of-stock', 'true');
                            } else if(q - used < 0){
                                warn.textContent = 'Available: ' + q + ' — Insufficient for requested (' + used + ')';
                                warn.className = 'alert alert-danger';
                                warn.style.display = '';
                                warn.setAttribute('data-out-of-stock', 'true');
                            } else if(q <= threshold){
                                warn.textContent = 'Available: ' + q + ' — Low stock';
                                warn.className = 'alert alert-warning';
                                warn.style.display = '';
                                warn.setAttribute('data-out-of-stock', 'false');
                            } else {
                                warn.textContent = 'Available: ' + q + ' — Stock sufficient';
                                warn.className = 'alert alert-success';
                                warn.style.display = '';
                                warn.setAttribute('data-out-of-stock', 'false');
                            }
                        }
                    }).catch(function(err){ 
                        console.error('Error checking inventory:', err);
                    });
                }
            });

        // Function to handle custom treatment input
        function handleCustomTreatment() {
            var typedT = (otherTreatmentInput.value || '').trim();
            if (!typedT) {
                alert('Please enter the treatment/item in the "Other Treatment / Item" field.');
                otherTreatmentInput.focus();
                return false;
            }
            
            var existsT = false;
            for (var j = 0; j < treatment.options.length; j++) {
                if (treatment.options[j].value === typedT) {
                    treatment.options[j].selected = true;
                    existsT = true;
                    break;
                }
            }
            
            if (!existsT) {
                var optT = document.createElement('option');
                optT.value = typedT;
                optT.text = typedT;
                optT.selected = true;
                treatment.appendChild(optT);
            }
            return true;
        }
                // Add form submission handler
        var visitForm = document.querySelector('form[action="record_visit.php"]');
        if (visitForm) {
            visitForm.addEventListener('submit', function(e) {
                var treatment = document.getElementById('treatment');
                var warn = document.getElementById('inventoryWarning');
                var quantityUsed = parseInt(document.getElementById('quantity_used').value) || 1;
                
                // Check if a treatment is selected and it's not 'Other'
                if (treatment.value && treatment.value !== 'Other') {
                    // If warning exists and indicates out of stock or insufficient quantity
                    if (warn && warn.getAttribute('data-out-of-stock') === 'true') {
                        e.preventDefault();
                        alert('Cannot proceed: The selected item is out of stock or has insufficient quantity available.');
                        treatment.focus();
                        return false;
                    }
                    
                    // Additional check to ensure the stock is still valid
                    fetch('inventory_api.php?name=' + encodeURIComponent(treatment.value))
                        .then(function(response) { 
                            return response.json(); 
                        })
                        .then(function(data) {
                            if (data.exists && (data.quantity < quantityUsed)) {
                                e.preventDefault();
                                alert('Cannot proceed: The selected item does not have enough stock available. Current stock: ' + data.quantity);
                                treatment.focus();
                                return false;
                            }
                        })
                        .catch(function(error) {
                            console.error('Error checking stock:', error);
                        });
                }
                
                // Handle custom treatment if selected
                if (treatment.value === 'Other') {
                    var otherTreatment = document.getElementById('other_treatment').value.trim();
                    if (!otherTreatment) {
                        e.preventDefault();
                        alert('Please enter a treatment/item in the "Other Treatment / Item" field.');
                        document.getElementById('other_treatment').focus();
                        return false;
                    }
                }
                
                // Handle custom reason if selected
                var reason = document.getElementById('reason');
                if (reason.value === 'Other') {
                    var otherReason = document.getElementById('other_reason').value.trim();
                    if (!otherReason) {
                        e.preventDefault();
                        alert('Please enter a reason in the "Other Reason" field.');
                        document.getElementById('other_reason').focus();
                        return false;
                    }
                }
            });
        }
        // When selecting a student, prefill reason if present and prepare medicines
        studentSelect.addEventListener('change', function(){
            var sel = studentSelect.options[studentSelect.selectedIndex];
            if (!sel) return;
            
            var curReason = sel.getAttribute('data-current-reason') || '';
            var curMedicine = sel.getAttribute('data-current-medicine') || '';

            if (curReason) {
                reason.value = curReason;
                populateTreatmentOptions(curReason);
                // If student already has a treatment/medicine, pre-select it and enable (optional)
                if (curMedicine) {
                    treatment.disabled = false;
                    treatment.required = true;
                    setTimeout(function() { 
                        treatment.value = curMedicine; 
                    }, 10);
                } else {
                    // no treatment yet -> enable treatment select
                    if (treatment.disabled) {
                        treatment.disabled = false;
                        treatment.required = true;
                    }
                }
            } else {
                // no existing reason: reset reason & treatment
                reason.value = '';
                treatment.disabled = true;
                treatment.required = false;
                populateTreatmentOptions('');
            }
        });

        // No modal: clicking reason/student will enable medicine directly. Keep simple enable/disable behavior.

        // initialize treatment options empty
        populateTreatmentOptions('');
    });
    </script>
</body>
</html>