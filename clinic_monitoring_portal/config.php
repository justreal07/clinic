<?php
// Database configuration
$host = 'localhost';
$dbname = 'clinic_monitoring';
$username = 'root'; // Change if needed
$password = '';     // Change if needed

// ✅ Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // ✅ Use utf8mb4 for full Unicode support (emojis, special chars)
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

/* -------------------------------
   🔹 Utility Functions
--------------------------------*/

// Get current logged-in user
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

// Redirect to another page
function redirect($url) {
    header("Location: $url");
    exit();
}

// Sanitize form input
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Hash a password securely
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify hashed password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Fetch all courses (optional helper)
function getAllCourses() {
    global $pdo;
    $stmt = $pdo->query("SELECT course_id, course_code, course_name FROM courses ORDER BY course_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -------------------------------
   Predefined inventory helpers
--------------------------------*/
function ensurePredefinedTable() {
    global $pdo;
    $createPre = "CREATE TABLE IF NOT EXISTS predefined_inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_name_type (name(191), type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($createPre);
}

function seedPredefinedFromExisting() {
    global $pdo;
    ensurePredefinedTable();
    // Only seed defaults when the predefined table is empty. This prevents re-adding
    // items that an admin explicitly deleted later (we want delete to be permanent).
    $count = $pdo->query("SELECT COUNT(*) FROM predefined_inventory")->fetchColumn();
    if ($count > 0) return;

    // seed from inventory
    try {
        $invMed = $pdo->query("SELECT DISTINCT name FROM inventory WHERE LOWER(TRIM(type)) = 'medicine'")->fetchAll(PDO::FETCH_COLUMN);
        $invItem = $pdo->query("SELECT DISTINCT name FROM inventory WHERE LOWER(TRIM(type)) = 'item'")->fetchAll(PDO::FETCH_COLUMN);
        $ins = $pdo->prepare("INSERT IGNORE INTO predefined_inventory (name, type) VALUES (?, ?)");
        foreach ($invMed as $n) { $ins->execute([$n, 'Medicine']); }
        foreach ($invItem as $n) { $ins->execute([$n, 'Item']); }
        // seed from visits.medicine_given as Medicine by default
        $visitNames = $pdo->query("SELECT DISTINCT medicine_given FROM visits WHERE medicine_given IS NOT NULL AND medicine_given != ''")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($visitNames as $vn) { $ins->execute([$vn, 'Medicine']); }

        // If predefined list still empty after trying inventory/visits, seed a sensible default set
        // These are common items and medicines used by the clinic and map to common visit reasons.
        $defaultMeds = [
            'Paracetamol', 'Ibuprofen', 'Cetirizine', 'Syndex', 'Diatabs', 'Antibiotic', 'Cough Syrup', 'Antacid', 'ORS', 'Amoxicillin'
        ];
        $defaultItems = [
            'Bandaid', 'First-aid', 'Thermometer', 'Gauze', 'Saline', 'Cotton', 'Alcohol', 'Disposable Gloves'
        ];
        foreach ($defaultMeds as $m) { $ins->execute([$m, 'Medicine']); }
        foreach ($defaultItems as $i) { $ins->execute([$i, 'Item']); }
    } catch (Exception $e) {
        // ignore seeding errors
    }
}

function getPredefinedItems() {
    global $pdo;
    ensurePredefinedTable();
    seedPredefinedFromExisting();
    $rows = $pdo->query("SELECT id, name, type FROM predefined_inventory ORDER BY type, name")->fetchAll(PDO::FETCH_ASSOC);
    $out = ['Medicine' => [], 'Item' => []];
    foreach ($rows as $r) {
        $t = strtolower(trim($r['type'])) === 'medicine' ? 'Medicine' : 'Item';
        $out[$t][] = ['id' => $r['id'], 'name' => $r['name']];
    }
    return $out;
}

function addPredefinedItem($name, $type) {
    global $pdo;
    ensurePredefinedTable();
    $stmt = $pdo->prepare("INSERT IGNORE INTO predefined_inventory (name, type) VALUES (?, ?)");
    return $stmt->execute([$name, $type]);
}

function updatePredefinedItem($id, $name, $type) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE predefined_inventory SET name = ?, type = ? WHERE id = ?");
    return $stmt->execute([$name, $type, $id]);
}

function deletePredefinedItem($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM predefined_inventory WHERE id = ?");
    return $stmt->execute([$id]);
}

function findUnmappedInventoryNames() {
    global $pdo;
    ensurePredefinedTable();
    $sql = "SELECT DISTINCT i.name FROM inventory i LEFT JOIN predefined_inventory p ON LOWER(TRIM(i.name)) = LOWER(TRIM(p.name)) WHERE p.id IS NULL";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
}
?>
