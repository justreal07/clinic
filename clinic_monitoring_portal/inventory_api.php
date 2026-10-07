<?php
require_once 'config.php';

// Simple inventory lookup API used by staff UI.
// GET parameter: name (item/treatment name)
header('Content-Type: application/json; charset=utf-8');

$name = trim($_GET['name'] ?? '');
if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'missing_name']);
    exit;
}

try {
    // Check if inventory table exists
    $check = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'inventory'");
    $check->execute();
    $hasInventory = (bool)$check->fetchColumn();

    if (!$hasInventory) {
        echo json_encode(['ok' => true, 'exists' => false, 'quantity' => 0, 'threshold' => 5]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
    $stmt->execute([$name]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // return quantity and low threshold (match inventory page)
        $threshold = 5;
        echo json_encode(['ok' => true, 'exists' => true, 'quantity' => (int)$row['quantity'], 'threshold' => $threshold, 'inventory_id' => (int)$row['id']]);
    } else {
        echo json_encode(['ok' => true, 'exists' => false, 'quantity' => 0, 'threshold' => 5]);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}

?>
