<?php
session_start();
require_once 'config.php';

// Only admin can manage inventory
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php?role=admin");
    exit;
}

// Ensure inventory table exists (safe to run multiple times)
$createTable = "CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$pdo->exec($createTable);
// Create inventory_log table for audit/history
$createLog = "CREATE TABLE IF NOT EXISTS inventory_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT DEFAULT NULL,
    change_amount INT NOT NULL,
    before_qty INT NOT NULL,
    after_qty INT NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    visit_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (inventory_id),
    INDEX (user_id),
    INDEX (visit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$pdo->exec($createLog);

// Create predefined_inventory table to persist canonical names used in staff forms
$createPre = "CREATE TABLE IF NOT EXISTS predefined_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_name_type (name(191), type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$pdo->exec($createPre);

// Load predefined items using shared helpers (defined in config.php)
// Load early so POST handlers can validate against the canonical list
$predefined_raw = ['Medicine' => [], 'Item' => []];
try {
    $tmp = getPredefinedItems();
    if (is_array($tmp)) { $predefined_raw = $tmp; }
} catch (Exception $e) {
    // fallback to empty lists if helper is not available or errors
    $predefined_raw = ['Medicine' => [], 'Item' => []];
}
// convert to arrays for the client-side PREDEFINED structure
$predefined_items = ['Medicine' => [], 'Item' => []];
foreach (($predefined_raw['Medicine'] ?? []) as $r) { $predefined_items['Medicine'][] = $r['name']; }
foreach (($predefined_raw['Item'] ?? []) as $r) { $predefined_items['Item'][] = $r['name']; }

// Handle POST actions: add, edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $type = $_POST['type'] ?? 'Item';
        $quantity = intval($_POST['quantity'] ?? 0);
        $allow_custom = isset($_POST['allow_custom']) && $_POST['allow_custom'] == '1';

        // choose name from predefined select unless custom is allowed and provided
        $predefined_name = trim($_POST['predefined_name'] ?? '');
        $custom_name = trim($_POST['name'] ?? '');

        $valid = false;
        $final_name = '';
        // Determine which type to use for this addition. Default to the top-level 'type',
        // but if the user provided a custom_type while adding custom name, prefer that.
        $selected_type = $type;
        $posted_custom_type = trim($_POST['custom_type'] ?? '');
        if ($allow_custom && $custom_name !== '') {
            // validate posted custom_type
            if ($posted_custom_type === 'Medicine' || $posted_custom_type === 'Item') {
                $selected_type = $posted_custom_type;
            }
            $valid = true;
            $final_name = $custom_name;
        } else {
            // validate against predefined lists
            $allPre = array_merge($predefined_items['Medicine'], $predefined_items['Item']);
            if (in_array($predefined_name, $allPre, true)) {
                $valid = true;
                $final_name = $predefined_name;
            }
        }

        if ($valid && $final_name !== '') {
            $stmt = $pdo->prepare("INSERT INTO inventory (name, type, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$final_name, $selected_type, $quantity]);
            // log creation in inventory_log (before 0 -> after quantity)
            try {
                $inventoryId = (int)$pdo->lastInsertId();
                $userId = $_SESSION['user']['id'] ?? null;
                $logStmt = $pdo->prepare("INSERT INTO inventory_log (inventory_id, change_amount, before_qty, after_qty, note, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                $logStmt->execute([$inventoryId, (int)$quantity, 0, (int)$quantity, 'created via inventory add', $userId]);
            } catch (Exception $e) {
                error_log('Failed to log inventory add: ' . $e->getMessage());
            }
            // Optionally save custom to predefined_inventory if checkbox asked
                if ($allow_custom && !empty($custom_name) && isset($_POST['save_predefined']) && $_POST['save_predefined'] == '1') {
                try {
                        $ins = $pdo->prepare("INSERT IGNORE INTO predefined_inventory (name, type) VALUES (?, ?)");
                        $ins->execute([$custom_name, $selected_type]);
                } catch (Exception $e) { /* ignore */ }
            }
        }
        header('Location: inventory.php?success=1');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'Item';
        $quantity = intval($_POST['quantity'] ?? 0);
        if ($id && $name !== '') {
            // fetch before quantity (safe prepared statement)
            $beforeQty = 0;
            try {
                $selQ = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ? LIMIT 1");
                $selQ->execute([$id]);
                $beforeQty = (int)$selQ->fetchColumn();
            } catch (Exception $e) { $beforeQty = 0; }
            // update
            $stmt = $pdo->prepare("UPDATE inventory SET name = ?, type = ?, quantity = ? WHERE id = ?");
            $stmt->execute([$name, $type, $quantity, $id]);
            // log edit
            try {
                $userId = $_SESSION['user']['id'] ?? null;
                $change = $quantity - $beforeQty;
                $logStmt = $pdo->prepare("INSERT INTO inventory_log (inventory_id, change_amount, before_qty, after_qty, note, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                $logStmt->execute([$id, (int)$change, (int)$beforeQty, (int)$quantity, 'edited via inventory admin', $userId]);
            } catch (Exception $e) {
                error_log('Failed to log inventory edit: ' . $e->getMessage());
            }
        }
        header('Location: inventory.php?success=1');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
                // Before deleting, get the item's name and type so we can cleanup predefined_inventory if needed
                $sel = $pdo->prepare("SELECT name, type FROM inventory WHERE id = ?");
                $sel->execute([$id]);
                $row = $sel->fetch(PDO::FETCH_ASSOC);

                // Perform delete
                try {
                    $before = (int)($row['quantity'] ?? 0);
                    // log deletion
                    $userId = $_SESSION['user']['id'] ?? null;
                    $logStmt = $pdo->prepare("INSERT INTO inventory_log (inventory_id, change_amount, before_qty, after_qty, note, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $logStmt->execute([$id, -$before, $before, 0, 'deleted via inventory admin', $userId]);
                } catch (Exception $e) {
                    error_log('Failed to log inventory delete: ' . $e->getMessage());
                }
                $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
                $stmt->execute([$id]);

                // If we removed the last inventory row using that name, remove it from predefined_inventory too
                if ($row && !empty($row['name'])) {
                    try {
                        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory WHERE name = ? AND type = ?");
                        $countStmt->execute([$row['name'], $row['type']]);
                        $remaining = (int)$countStmt->fetchColumn();
                        if ($remaining === 0) {
                            $delPre = $pdo->prepare("DELETE FROM predefined_inventory WHERE name = ? AND type = ?");
                            $delPre->execute([$row['name'], $row['type']]);
                        }
                    } catch (Exception $e) {
                        // don't block delete on failure to cleanup; log for debugging
                        error_log('Failed cleaning up predefined_inventory after delete: ' . $e->getMessage());
                    }
                }
        }
        header('Location: inventory.php?deleted=1');
        exit;
    }
}

// Fetch inventory items
$items = $pdo->query("SELECT * FROM inventory ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalItems = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$totalStock = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM inventory")->fetchColumn();
$medCountVal = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM inventory WHERE LOWER(TRIM(type)) = 'medicine'")->fetchColumn();
$itemCountVal = $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM inventory WHERE LOWER(TRIM(type)) = 'item'")->fetchColumn();
$lowStockThreshold = 5;
$lowStockStmt = $pdo->prepare("SELECT * FROM inventory WHERE quantity <= ? ORDER BY quantity ASC"); $lowStockStmt->execute([$lowStockThreshold]); $lowStock = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

// Recent inventory log (join to show item name and user)
$logsStmt = $pdo->prepare("SELECT l.*, i.name AS item_name, CONCAT(COALESCE(s.first_name,''), ' ', COALESCE(s.last_name,'')) AS user_name FROM inventory_log l LEFT JOIN inventory i ON l.inventory_id = i.id LEFT JOIN clinic_staff s ON l.user_id = s.staff_id ORDER BY l.created_at DESC LIMIT 50");
$logsStmt->execute();
$inventory_logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Stock distribution visualization removed - keep only numeric totals below

// (predefined items were initialized earlier to ensure POST handlers can access them)
// simple helper for escaping
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Inventory - ECClinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="a_dashboard.css">
    <!-- Stock distribution chart removed -->
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f5f5f5; }
        .container { max-width:1100px; margin:30px auto; }
        .stat-card { background:#fff; padding:18px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.05); }
        .inventory-table td, .inventory-table th { vertical-align: middle; }
        /* Larger stock distribution styling */
        .stock-distribution { padding: 18px; }
        .stock-distribution .card-body { padding: 26px; }
        .stock-distribution h4 { font-size: 1.25rem; font-weight:600; }
        .stock-badge { font-size: 1.05rem; padding: 10px 14px; border-radius: 8px; }
    </style>
</head>
<body>
<div class="sidebar">
    <h3><i class="fas fa-hospital"></i> ECClinic</h3>
    <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
    <a href="manage_staff.php"><i class="fas fa-user-nurse"></i> Manage Staff</a>
    <a href="inventory.php" class="active"><i class="fas fa-boxes"></i> Inventory</a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="fas fa-boxes"></i> Inventory</h3>
            <a href="admin_dashboard.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h4><?= (int)$totalItems ?></h4>
                    <p class="mb-0">Unique Items</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h4><?= (int)$totalStock ?></h4>
                    <p class="mb-0">Total Stock</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h4><?= (int)$medCountVal ?></h4>
                    <p class="mb-0">Medicines</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h4><?= (int)$itemCountVal ?></h4>
                    <p class="mb-0">Supplies / Items</p>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5>Add New Item</h5>
                <form method="POST" class="row g-2 align-items-end" id="addItemForm">
                    <input type="hidden" name="action" value="add">
                    <div class="col-md-4">
                        <label class="form-label">Type</label>
                        <select name="type" id="add_type" class="form-select">
                            <option value="Medicine">Medicine</option>
                            <option value="Item">Item</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Predefined Name</label>
                        <select name="predefined_name" id="predefined_name" class="form-select">
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" min="0" value="0" required>
                    </div>
                    <div class="col-md-2 text-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="custom_toggle" name="allow_custom">
                            <label class="form-check-label" for="custom_toggle">Add custom</label>
                        </div>
                    </div>

                    <div class="col-12 mt-2" id="custom_block" style="display:none;">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="form-label">Custom Name</label>
                                <input type="text" name="name" id="custom_name" class="form-control" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Custom Type</label>
                                <select name="custom_type" id="custom_type" class="form-select">
                                    <option value="Medicine">Medicine</option>
                                    <option value="Item">Item</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="save_predefined" name="save_predefined" value="1">
                            <label class="form-check-label" for="save_predefined">Save to predefined list</label>
                        </div>
                    </div>
                    <div class="col-12 text-end mt-2">
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5>Inventory List</h5>
                <?php if (!empty($items)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered inventory-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Updated</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                            <tr>
                                <td><?= (int)$it['id'] ?></td>
                                <td><?= h($it['name']) ?></td>
                                <td><?= h($it['type']) ?></td>
                                <td><?= (int)$it['quantity'] ?></td>
                                <td><?= h($it['updated_at']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary btn-edit" 
                                        data-id="<?= (int)$it['id'] ?>" 
                                        data-name="<?= h($it['name']) ?>" 
                                        data-type="<?= h($it['type']) ?>" 
                                        data-quantity="<?= (int)$it['quantity'] ?>">Edit</button>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this item?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                        <button class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-muted">No items in inventory yet.</p>
                <?php endif; ?>
                <?php
                    // quick unmapped check
                    $unmapped = [];
                    try { $unmapped = findUnmappedInventoryNames(); } catch (Exception $e) { }
                ?>
                <div class="mt-2">
                    <a href="predefined_inventory.php" class="btn btn-sm btn-outline-secondary">Manage Predefined Items</a>
                    <?php if (!empty($unmapped)): ?>
                        <a href="predefined_inventory.php" class="btn btn-sm btn-warning ms-2">Normalize (<?php echo count($unmapped); ?> unmapped)</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                    <div class="card stock-distribution">
                        <div class="card-body">
                            <h4 class="mb-3">Stock Distribution</h4>
                            <div class="d-flex gap-3 align-items-center">
                                <div class="badge bg-danger stock-badge">Medicines: <?= (int)$medCountVal ?></div>
                                <div class="badge bg-primary stock-badge">Supplies / Items: <?= (int)$itemCountVal ?></div>
                                <div class="badge bg-secondary stock-badge">Total: <?= (int)$totalStock ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Low Stock &lt;= <?= $lowStockThreshold ?></h5>
                        <?php if (!empty($lowStock)): ?>
                            <ul class="list-group">
                                <?php foreach ($lowStock as $l): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= h($l['name']) ?> <span class="badge bg-danger"><?= (int)$l['quantity'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted">No low-stock items.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

    <div class="container mt-3">
        <div class="card mb-4">
            <div class="card-body">
                <h5>Inventory Change Log (recent)</h5>
                <?php if (!empty($inventory_logs)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Item</th>
                                    <th>Change</th>
                                    <th>Before</th>
                                    <th>After</th>
                                    <th>User</th>
                                    <th>Visit ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory_logs as $log): ?>
                                <tr>
                                    <td><?= h($log['created_at']) ?></td>
                                    <td><?= h($log['item_name'] ?? 'N/A') ?></td>
                                    <td><?= (int)$log['change_amount'] ?></td>
                                    <td><?= (int)$log['before_qty'] ?></td>
                                    <td><?= (int)$log['after_qty'] ?></td>
                                    <td><?= h($log['user_name'] ?? $log['user_id']) ?></td>
                                    <td><?= h($log['visit_id'] ?? '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No inventory activity recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="editForm">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-header">
        <h5 class="modal-title">Edit Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-md-8">
            <label class="form-label">Name</label>
            <input type="text" name="name" id="edit_name" class="form-control" required maxlength="255">
        </div>
        <div class="col-md-4">
            <label class="form-label">Type</label>
            <select name="type" id="edit_type" class="form-select">
                <option value="Medicine">Medicine</option>
                <option value="Item">Item</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" id="edit_quantity" class="form-control" min="0" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </div>
      </form>
    </div>
  </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Edit button handler
    document.querySelectorAll('.btn-edit').forEach(function(b){
        b.addEventListener('click', function(){
            var id = this.getAttribute('data-id');
            var name = this.getAttribute('data-name');
            var type = this.getAttribute('data-type');
            var qty = this.getAttribute('data-quantity');
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_type').value = type;
            document.getElementById('edit_quantity').value = qty;
            var modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        });
    });

    // Predefined items data from server
    const PREDEFINED = {
        Medicine: <?= json_encode($predefined_items['Medicine']) ?>,
        Item: <?= json_encode($predefined_items['Item']) ?>
    };

    const typeSelect = document.getElementById('add_type');
    const predefinedSelect = document.getElementById('predefined_name');
    const customToggle = document.getElementById('custom_toggle');
    const customBlock = document.getElementById('custom_block');
    const customName = document.getElementById('custom_name');
    const customType = document.getElementById('custom_type');
    const addForm = document.getElementById('addItemForm');

    function populatePredefined() {
        const t = typeSelect.value || 'Medicine';
        predefinedSelect.innerHTML = '';
        (PREDEFINED[t] || []).forEach(function(name){
            const o = document.createElement('option'); o.value = name; o.textContent = name;
            predefinedSelect.appendChild(o);
        });
    }

    typeSelect.addEventListener('change', populatePredefined);
    customToggle.addEventListener('change', function(){
        if (this.checked) {
            customBlock.style.display = 'block';
            // default the custom type to the currently selected top-level type for convenience
            try { customType.value = typeSelect.value || 'Medicine'; } catch(e){ /* ignore */ }
        } else { customBlock.style.display = 'none'; }
    });

    // initial populate
    populatePredefined();

    // Validate before submit: either predefined selected or custom name when allowed
    addForm.addEventListener('submit', function(e){
        const allowCustom = customToggle.checked;
        const sel = predefinedSelect.value || '';
        const customVal = customName.value.trim();
        if (!allowCustom && !sel) {
            e.preventDefault(); alert('Please select a predefined name or enable Add custom.');
        }
        if (allowCustom && customVal === '') {
            e.preventDefault(); alert('Please enter a custom name or uncheck Add custom.');
        }
        // when using custom, ensure custom_type is present and valid
        if (allowCustom) {
            const ct = (customType && customType.value) ? customType.value : '';
            if (!ct || (ct !== 'Medicine' && ct !== 'Item')) {
                e.preventDefault(); alert('Please select whether the custom entry is a Medicine or an Item.');
            }
        }
    });
</script>
</body>
</html>
