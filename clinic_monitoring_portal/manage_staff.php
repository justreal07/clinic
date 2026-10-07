<?php
session_start();
require_once 'config.php';

// ✅ Check if admin is logged in
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php?role=admin");
    exit;
}

$adminName = $_SESSION['user']['name'] ?? 'Admin';

// Handle adding a new staff member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_staff') {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $staff_no = trim($_POST['staff_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $position = trim($_POST['position'] ?? 'Staff');
    $password = trim($_POST['password'] ?? '');
    $has_badge = isset($_POST['has_badge']) ? 1 : 0;

    // basic validation
    if ($first === '' || $last === '' || $staff_no === '' || $email === '') {
        header('Location: manage_staff.php?status=error&message=' . urlencode('Please fill required fields'));
        exit;
    }

    try {
        // Hash password (or set a default temporary password if empty)
        if ($password === '') {
            $password = bin2hex(random_bytes(4)); // simple temp password
        }
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // use the existing 'password' column (other files expect `password`) and store the hashed password
        $stmt = $pdo->prepare("INSERT INTO clinic_staff (first_name, last_name, staff_number, email, position, password, has_badge, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$first, $last, $staff_no, $email, $position, $password_hash, $has_badge]);

        header('Location: manage_staff.php?status=success&message=' . urlencode('Staff added'));
        exit;
    } catch (Exception $e) {
        header('Location: manage_staff.php?status=error&message=' . urlencode('DB error: ' . $e->getMessage()));
        exit;
    }
}

// Handle editing a staff member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_staff') {
    $id = intval($_POST['staff_id'] ?? 0);
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $staff_no = trim($_POST['staff_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $position = trim($_POST['position'] ?? 'Staff');

    if ($id <= 0 || $first === '' || $last === '' || $staff_no === '' || $email === '') {
        header('Location: manage_staff.php?status=error&message=' . urlencode('Please fill required fields'));
        exit;
    }

    try {
        // prevent duplicate staff_number/email for other users
        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM clinic_staff WHERE (staff_number = ? OR email = ?) AND staff_id != ?");
        $dupStmt->execute([$staff_no, $email, $id]);
        if ($dupStmt->fetchColumn() > 0) {
            header('Location: manage_staff.php?status=error&message=' . urlencode('Staff number or email already used by another account'));
            exit;
        }

        $stmt = $pdo->prepare("UPDATE clinic_staff SET first_name = ?, last_name = ?, staff_number = ?, email = ?, position = ? WHERE staff_id = ?");
        $stmt->execute([$first, $last, $staff_no, $email, $position, $id]);
        header('Location: manage_staff.php?status=success&message=' . urlencode('Staff updated'));
        exit;
    } catch (Exception $e) {
        header('Location: manage_staff.php?status=error&message=' . urlencode('DB error: ' . $e->getMessage()));
        exit;
    }
}

// Handle deleting a staff member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_staff') {
    $id = intval($_POST['staff_id'] ?? 0);
    if ($id <= 0) {
        header('Location: manage_staff.php?status=error&message=' . urlencode('Invalid staff id'));
        exit;
    }
    try {
        // Prevent deleting the currently logged-in admin
        if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin' && $_SESSION['user']['id'] == $id) {
            header('Location: manage_staff.php?status=error&message=' . urlencode('Cannot delete the logged-in admin'));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM clinic_staff WHERE staff_id = ?");
        $stmt->execute([$id]);
        header('Location: manage_staff.php?status=success&message=' . urlencode('Staff removed'));
        exit;
    } catch (Exception $e) {
        header('Location: manage_staff.php?status=error&message=' . urlencode('DB error: ' . $e->getMessage()));
        exit;
    }
}

// Fetch staff members from DB (fallback to empty array if table missing)
try {
    $stmt = $pdo->query("SELECT staff_id, first_name, last_name, staff_number, email, position, has_badge FROM clinic_staff ORDER BY staff_id DESC");
    $staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // table might not exist yet; fall back to sample
    $staff_members = [
        ['staff_id' => 1, 'first_name' => 'Julius', 'last_name' => 'Rivera', 'staff_number' => '2316-02178C', 'email' => 'JuliusR@ecc.edu.ph', 'position' => 'Staff', 'has_badge' => 0],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff - ECClinic Admin</title>

    <!-- Bootstrap + Font Awesome + Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- External CSS -->
    <link rel="stylesheet" href="a_dashboard.css">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h3><i class="fas fa-hospital"></i> ECClinic</h3>
        <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
    <a href="manage_staff.php" class="active"><i class="fas fa-user-nurse"></i> Manage Staff</a>
    <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
        <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>Staff Management</h1>
            <p>Manage clinic staff members and their information</p>
        </div>

        <!-- Staff Information Section -->
        <div class="dashboard-section mb-4">
            <div class="section-header d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0"><i class="fas fa-user-nurse me-2"></i>Staff Members</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                    <i class="fas fa-user-plus me-2"></i> Add New Staff
                </button>
            </div>
            <?php if(isset($_GET['status'])): ?>
                <div class="mt-3">
                    <?php if($_GET['status'] === 'success'): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($_GET['message'] ?? 'Action completed') ?></div>
                    <?php else: ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($_GET['message'] ?? 'There was an error') ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="table-responsive mt-3">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th>Staff ID</th>
                            <th>Name</th>
                            <th>Staff Number</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_members as $staff): ?>
                        <tr>
                            <td><?= $staff['staff_id'] ?></td>
                            <td><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></td>
                            <td><?= htmlspecialchars($staff['staff_number']) ?></td>
                            <td><?= htmlspecialchars($staff['email']) ?></td>
                            <td>
                                <span class="badge bg-info"><?= htmlspecialchars($staff['position']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-success">Active</span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-btn" title="Edit Staff"
                                    data-id="<?= $staff['staff_id'] ?>"
                                    data-first="<?= htmlspecialchars($staff['first_name'], ENT_QUOTES) ?>"
                                    data-last="<?= htmlspecialchars($staff['last_name'], ENT_QUOTES) ?>"
                                    data-staffno="<?= htmlspecialchars($staff['staff_number'], ENT_QUOTES) ?>"
                                    data-email="<?= htmlspecialchars($staff['email'], ENT_QUOTES) ?>"
                                    data-position="<?= htmlspecialchars($staff['position'], ENT_QUOTES) ?>"
                                    data-has-badge="<?= htmlspecialchars($staff['has_badge'], ENT_QUOTES) ?>"
                                    data-bs-toggle="modal" data-bs-target="#editStaffModal">
                                    <i class="fas fa-edit"></i> Edit
                                </button>

                                <form method="POST" action="manage_staff.php" style="display:inline" onsubmit="return confirm('Remove this staff member?');">
                                    <input type="hidden" name="action" value="delete_staff">
                                    <input type="hidden" name="staff_id" value="<?= $staff['staff_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Staff">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-footer">
            <p>&copy; 2025 ECClinic Portal | Staff Management</p>
        </div>
        </div>

        <!-- Add Staff Modal -->
        <div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addStaffModalLabel">Add New Staff</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="manage_staff.php">
                        <input type="hidden" name="action" value="add_staff">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Staff Number</label>
                                <input type="text" name="staff_number" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Position</label>
                                <div class="p-2 border rounded">
                                    <span class="badge bg-info">Staff</span>
                                    <input type="hidden" name="position" value="Staff">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Temporary Password (optional)</label>
                                <input type="text" name="password" class="form-control" placeholder="Leave blank to generate">
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="has_badge" class="form-check-input" id="hasBadge">
                                <label class="form-check-label" for="hasBadge">Staff has received badge</label>
                            </div>
                        </div>

                        <!-- Edit Staff Modal removed from inside Add modal to avoid nested forms; it's rendered after the Add modal -->
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Staff</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

            <!-- Edit Staff Modal (placed outside Add modal to avoid nested forms) -->
            <div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editStaffModalLabel">Edit Staff</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="manage_staff.php">
                            <input type="hidden" name="action" value="edit_staff">
                            <input type="hidden" name="staff_id" id="edit_staff_id" value="">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Staff Number</label>
                                    <input type="text" name="staff_number" id="edit_staff_number" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" id="edit_email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Position</label>
                                    <input type="text" name="position" id="edit_position" class="form-control" value="Staff">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Bootstrap JS -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
            <script>
                // Fill edit modal with data from the clicked row
                document.addEventListener('DOMContentLoaded', function(){
                    var editButtons = document.querySelectorAll('.edit-btn');
                    editButtons.forEach(function(btn){
                        btn.addEventListener('click', function(){
                            document.getElementById('edit_staff_id').value = this.dataset.id || '';
                            document.getElementById('edit_first_name').value = this.dataset.first || '';
                            document.getElementById('edit_last_name').value = this.dataset.last || '';
                            document.getElementById('edit_staff_number').value = this.dataset.staffno || '';
                            document.getElementById('edit_email').value = this.dataset.email || '';
                            document.getElementById('edit_position').value = this.dataset.position || 'Staff';
                        });
                    });
                });
            </script>
</body>
</html>