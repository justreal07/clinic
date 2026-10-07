<?php
require_once 'config.php'; // Already starts session

// ✅ Allow both staff and admin
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['staff', 'admin'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = intval($_POST['student_id']);
    // Prefer typed other_reason when provided (comes from staff_dashboard.php 'Other' field)
    $other_reason = trim($_POST['other_reason'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    if ($other_reason !== '') {
        $reason = $other_reason;
    }
    $details = trim($_POST['details'] ?? '');
    // Accept custom other_treatment when provided, otherwise treatment (new name) or medicine (legacy name)
    $other_treatment = trim($_POST['other_treatment'] ?? '');
    if ($other_treatment !== '') {
        $medicine_given = $other_treatment;
    } else {
        $medicine_given = trim($_POST['treatment'] ?? $_POST['medicine'] ?? '');
    }
    // Quantity used for inventory decrement (default 1)
    $quantity_used = max(1, intval($_POST['quantity_used'] ?? 1));

    // ✅ If staff, get ID; if admin, allow null
    $staff_id = ($_SESSION['user']['role'] === 'staff') ? $_SESSION['user']['id'] : null;

    if ($student_id && $reason) {
        try {
            // Use transaction to ensure visit insert and inventory decrement happen together
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO visits (student_id, staff_id, reason, details, medicine_given, visit_date) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$student_id, $staff_id, $reason, $details, $medicine_given]);

            // If inventory table exists and a matching item is found, decrement its quantity by requested amount
            if (!empty($medicine_given)) {
                $check = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'inventory'");
                $check->execute();
                $hasInventory = (bool)$check->fetchColumn();

                if ($hasInventory) {
                    // case-insensitive match on name (trimmed)
                    $invStmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                    $invStmt->execute([$medicine_given]);
                    $inv = $invStmt->fetch(PDO::FETCH_ASSOC);
                    if ($inv) {
                        $before = (int)$inv['quantity'];
                        $requested = $quantity_used;
                        $actualChange = min($before, $requested);
                        if ($actualChange > 0) {
                            $upd = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
                            $upd->execute([$actualChange, (int)$inv['id']]);

                            // ensure inventory_log table exists (safe to run)
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
                            try { $pdo->exec($createLog); } catch (Exception $e) { error_log('Failed to ensure inventory_log table: ' . $e->getMessage()); }

                            // insert into inventory_log (inventory_id, change_amount, before_qty, after_qty, note, user_id, visit_id)
                            $visitId = $pdo->lastInsertId();
                            $after = $before - $actualChange;
                            $userId = $_SESSION['user']['id'] ?? null;
                            $note = 'Used for visit';
                            try {
                                $logStmt = $pdo->prepare("INSERT INTO inventory_log (inventory_id, change_amount, before_qty, after_qty, note, user_id, visit_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $logStmt->execute([(int)$inv['id'], $actualChange, $before, $after, $note, $userId, $visitId]);
                            } catch (Exception $e) {
                                error_log('Failed to insert inventory_log: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }

            $pdo->commit();

            // ✅ Redirect correctly
            if ($_SESSION['user']['role'] === 'admin') {
                header("Location: admin_dashboard.php?success=1");
            } else {
                header("Location: staff_dashboard.php?success=1");
            }
            exit;
        } catch (Exception $e) {
            // Rollback on error and fail gracefully
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            // Optionally log $e->getMessage()
            if ($_SESSION['user']['role'] === 'admin') {
                header("Location: admin_dashboard.php?error=1");
            } else {
                header("Location: staff_dashboard.php?error=1");
            }
            exit;
        }
        
    }
}
?>