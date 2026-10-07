<?php
session_start();
require_once 'config.php';

// admin-only
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php?role=admin'); exit;
}

// Handle actions: add, edit, delete, normalize
$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] === 'Item' ? 'Item' : 'Medicine';
            if ($name !== '') { addPredefinedItem($name, $type); }
            header('Location: predefined_inventory.php?ok=1'); exit;
        }
        // edits are handled in Inventory (we keep predefined names canonical); skip edit action here
        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) { deletePredefinedItem($id); }
            header('Location: predefined_inventory.php?ok=1'); exit;
        }
        if ($action === 'map') {
            // map an unmapped inventory name to a predefined id
            $invName = trim($_POST['inv_name'] ?? '');
            $preId = intval($_POST['pre_id'] ?? 0);
            if ($invName !== '' && $preId > 0) {
                // fetch predefined name and type
                $stmt = $pdo->prepare('SELECT name, type FROM predefined_inventory WHERE id = ?');
                $stmt->execute([$preId]);
                $p = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($p) {
                    // update all inventory rows with that name to the canonical name/type
                    $upd = $pdo->prepare('UPDATE inventory SET name = ?, type = ? WHERE name = ?');
                    $upd->execute([$p['name'], $p['type'], $invName]);
                }
            }
            header('Location: predefined_inventory.php?ok=1'); exit;
        }
    } catch (Exception $e) {
        header('Location: predefined_inventory.php?error=1'); exit;
    }
}

$items = getPredefinedItems();
$unmapped = findUnmappedInventoryNames();

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Predefined Inventory - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="a_dashboard.css">
</head>
<body>
<div class="sidebar">
    <h3><i class="fas fa-hospital"></i> ECClinic</h3>
    <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
    <a href="manage_staff.php"><i class="fas fa-user-nurse"></i> Manage Staff</a>
    <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
    <a href="predefined_inventory.php" class="active"><i class="fas fa-list"></i> Predefined Items</a>
    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div class="main-content">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center my-3">
            <h3>Predefined Inventory</h3>
            <a href="inventory.php" class="btn btn-light">Back to Inventory</a>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5>Add New Predefined Item</h5>
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="mb-2">
                                <select name="type" class="form-select">
                                    <option value="Medicine">Medicine</option>
                                    <option value="Item">Item</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <input name="name" class="form-control" placeholder="Name" required>
                            </div>
                            <button class="btn btn-primary">Add</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5>Existing Predefined Items</h5>
                        <?php foreach ($items as $type => $list): ?>
                            <h6 class="mt-3"><?php echo h($type); ?></h6>
                            <table class="table table-sm">
                                <thead><tr><th>Name</th><th class="text-end">Actions</th></tr></thead>
                                <tbody>
                                <?php foreach ($list as $row): ?>
                                    <tr>
                                        <td><?php echo h($row['name']); ?></td>
                                        <td class="text-end">
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5>Unmapped Inventory Names</h5>
                        <?php if (empty($unmapped)): ?>
                            <p class="text-muted">All inventory names are mapped to predefined list.</p>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="map">
                                <div class="mb-2">
                                    <label class="form-label">Unmapped Name</label>
                                    <select name="inv_name" class="form-select">
                                        <?php foreach ($unmapped as $u): ?>
                                            <option value="<?php echo h($u); ?>"><?php echo h($u); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Map to Predefined</label>
                                    <select name="pre_id" class="form-select">
                                        <?php foreach ($items as $type => $list): foreach ($list as $r): ?>
                                            <option value="<?php echo (int)$r['id']; ?>"><?php echo h($type . ' - ' . $r['name']); ?></option>
                                        <?php endforeach; endforeach; ?>
                                    </select>
                                </div>
                                <button class="btn btn-primary">Map</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5>Quick Actions</h5>
                        <p class="text-muted">Use this area to normalize and manage predefined items.</p>
                        <button class="btn btn-outline-primary" onclick="location.reload();">Refresh</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit removed: changes to predefined canonical names are handled via Inventory UI now -->
</body>
</html>
