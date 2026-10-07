<?php
require_once 'config.php';

$errors = [];
// Enhanced role detection with multiple methods
$role = $_GET['role'] ?? $_POST['role'] ?? (isset($_GET['admin']) ? 'admin' : 'staff');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Accept either admin 'username' (legacy) or staff 'employee_number' from the form
    $username_input = sanitizeInput(trim($_POST['username'] ?? ''));
    $employee_number = sanitizeInput(trim($_POST['employee_number'] ?? ''));
    // prefer explicit username input (used for admin), otherwise use employee_number
    $login_id = $username_input !== '' ? $username_input : $employee_number;
    $password = trim($_POST['password'] ?? '');

    $missing_label = $role === 'admin' ? 'Username' : 'Employee number';
    if (empty($login_id) || empty($password)) {
        $errors[] = "$missing_label and password are required";
    } else {
        // ✅ ADMIN LOGIN (multiple admin credential options)
        if ($role === 'admin') {
            // Check admin credential sets using the submitted id ($login_id)
            // NOTE: accept the numeric admin id 6111997 with the given password
            if (($login_id === 'admin' && $password === 'admin123') || 
                ($login_id === '6111997' && $password === 'earist@2025')) {

                $_SESSION['user'] = [
                    'username' => $login_id,
                    'role' => 'admin',
                    'name' => 'Administrator'
                ];

                // Redirect to appropriate admin dashboard
                redirect('admin_dashboard.php');
            } else {
                $errors[] = "Invalid admin credentials";
            }
        }

        // 👨‍⚕️ STAFF LOGIN (from database)
        else {
            // Staff login: use employee number only (staff_number)
            $stmt = $pdo->prepare("SELECT * FROM clinic_staff WHERE staff_number = ?");
            $stmt->execute([$employee_number]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'username' => $user['staff_number'],
                    'role' => 'staff',
                    'id' => $user['staff_id'],
                    'name' => $user['first_name'] . ' ' . $user['last_name']
                ];

                redirect('staff_dashboard.php');
            } else {
                $errors[] = "Invalid staff credentials";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $role === 'admin' ? 'Admin Login - ECClinic' : 'Staff Login - ECClinic' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="relog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Banner -->
    <div class="bannerContainer">
        <img class="bannerText" src="ECCBanner.png" alt="ECClinic Banner"
             onerror="this.src='https://via.placeholder.com/800x80?text=ECClinic+Banner+Not+Found';">
    </div>

    <div class="login-wrapper">
        <div class="login-container">
            <div class="card clinic-card">
                <div class="card-header <?= $role === 'admin' ? 'bg-danger text-white' : 'bg-primary text-white' ?> d-flex align-items-center position-relative">
                    <!-- Role Selection Tabs -->
                    <ul class="nav nav-tabs card-header-tabs w-100">
                        <li class="nav-item">
                            <a class="nav-link <?= $role !== 'admin' ? 'active' : 'text-dark' ?>" href="?role=staff">
                                <i class="fas fa-user-tie"></i> Staff Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $role === 'admin' ? 'active bg-white text-danger' : 'text-dark' ?>" href="?role=admin">
                                <i class="fas fa-user-shield"></i> Admin Login
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Back Button - Positioned in top-right corner -->
                    <a href="index.php" class="position-absolute end-0 top-0 h-100 d-flex align-items-center px-3 text-white" style="text-decoration: none;" title="Back to Home">
                        <i class="fas fa-times"></i>
                    </a>
                </div>

                <div class="card-body">
                    <div class="clinic-icon">
                        <i class="<?= $role === 'admin' ? 'fas fa-shield-alt' : 'fas fa-heartbeat' ?>"></i>
                    </div>

                    <?php 
                    // Only show registration success message on staff login
                    if ($role !== 'admin' && isset($_GET['register']) && $_GET['register'] == 'success'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Registration successful! Please login with your credentials.
                        </div>
                    <?php 
                    // Show login errors
                    elseif (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-0"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="loginForm">
                        <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                        
                        <div class="mb-3">
                            <label for="employee_number" class="form-label">
                                    <i class="fas fa-user"></i>
                                    <?= $role === 'admin' ? 'Admin Username' : 'Employee Number' ?>
                                </label>
                                <input type="text" class="form-control" id="employee_number" name="employee_number"
                                       value="<?= htmlspecialchars($_POST['employee_number'] ?? '') ?>" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label"><i class="fas fa-lock"></i> Password</label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button type="button" class="password-toggle" id="passwordToggle">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <!-- Password strength indicator (from first file) -->
                            <div class="password-strength mt-2">
                                <div class="password-strength-fill" id="passwordStrength"></div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login w-100 mb-3">
                            <i class="fas fa-sign-in-alt"></i> 
                            <?= $role === 'admin' ? 'Admin Login' : 'Staff Login' ?>
                        </button>
                    </form>

                    <?php if ($role !== 'admin'): ?>
                        <hr>
                        <div class="text-center">
                            <p class="mb-2">New staff member?</p>
                            <a href="register.php" class="btn btn-register">
                                <i class="fas fa-user-plus"></i> Create Staff Account
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('passwordToggle');
            const backBtn = document.getElementById('backButton');
            const passwordInput = document.getElementById('password');
            
            // Back button behavior: always go to index.php (explicit landing page intent)
            if(backBtn){
                backBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    window.location.href = 'index.php';
                });
            }

            // Password toggle functionality (enhanced from both files)
            if(toggleBtn && passwordInput){
                toggleBtn.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    if(passwordInput.type === 'password'){
                        passwordInput.type = 'text';
                        icon.classList.replace('fa-eye','fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.replace('fa-eye-slash','fa-eye');
                    }
                });
            }

            // Password strength indicator (from first file)
            if(passwordInput){
                passwordInput.addEventListener('input', function(){
                    const strengthBar = document.getElementById('passwordStrength');
                    if (!strengthBar) return;
                    
                    let strength = 0;
                    if(this.value.length > 0) strength += 25;
                    if(this.value.length > 6) strength += 25;
                    if(/[A-Z]/.test(this.value)) strength += 25;
                    if(/[0-9]/.test(this.value)) strength += 25;
                    
                    strengthBar.style.width = strength + '%';
                    strengthBar.style.backgroundColor = strength < 50 ? '#dc3545' : (strength < 75 ? '#ffc107' : '#28a745');
                });
            }
        });
    </script>
</body>
</html>