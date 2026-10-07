<?php
require_once 'config.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $student_number = trim($_POST['student_number']);
    $course = trim($_POST['course']);
    $gender = trim($_POST['gender']);

    // Split full name into first and last name (simple split)
    $parts = explode(' ', $name, 2);
    $first_name = $parts[0];
    $last_name = $parts[1] ?? '';

    // Validate all fields
    if (!empty($first_name) && !empty($student_number) && !empty($course) && !empty($gender)) {
        try {
            // ✅ Insert student with course
            $stmt = $pdo->prepare("
                INSERT INTO students (first_name, last_name, student_number, course, gender)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$first_name, $last_name, $student_number, $course, $gender]);

            header("Location: student.php?status=success");
            exit;
        } catch (PDOException $e) {
            // Handle duplicate student number or DB error
            if ($e->getCode() == 23000) {
                header("Location: student.php?status=error&msg=duplicate");
            } else {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    } else {
        header("Location: student.php?status=error");
        exit;
    }
}
?>
