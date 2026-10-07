<?php
require_once 'config.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $staff_number = sanitizeInput($_POST['staff_number']);
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($staff_number)) $errors[] = "Staff number is required";
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (!empty($password) && $password !== $confirm_password) $errors[] = "Passwords do not match";
    if (empty($password)) $errors[] = "Password is required";
    if (empty($confirm_password)) $errors[] = "Please confirm your password";
    if (!empty($password) && strlen($password) < 6) $errors[] = "Password must be at least 6 characters long";

    // Check if staff number already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clinic_staff WHERE staff_number = ?");
        $stmt->execute([$staff_number]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $errors[] = "Staff number already exists";
        }
    }

    if (empty($errors)) {
        try {
            $hashed_password = hashPassword($password);
            $stmt = $pdo->prepare("INSERT INTO clinic_staff (staff_number, first_name, last_name, password, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$staff_number, $first_name, $last_name, $hashed_password]);

            $success = "Registration successful! You can now login.";
            redirect('login.php?register=success');
        } catch (PDOException $e) {
            $errors[] = "Error registering user: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECClinic Portal - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="relog.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Banner -->
    <div class="bannerContainer">
        <img class="bannerText" src="ECCBanner.png" alt="ECClinic Banner" onerror="this.src='https://via.placeholder.com/800x80?text=ECClinic+Banner+Not+Found';">
    </div>

    <div class="login-wrapper">
        <div class="login-container">
            <div class="card clinic-card">
                <div class="card-header">
                    <h4>
                        <i class="fas fa-hospital"></i> 
                        <span class="brand-acronym">ECC</span>Clinic Portal Register
                    </h4>
                </div>
                <div class="card-body">
                    <div class="clinic-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <p class="mb-0"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="registerForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label"><i class="fas fa-id-badge"></i> First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label"><i class="fas fa-user"></i> Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                            </div>
                        </div> 

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="staff_number" class="form-label"><i class="fas fa-user"></i> Employee Number</label>
                                <input type="text" class="form-control" id="staff_number" name="staff_number" value="<?php echo htmlspecialchars($_POST['staff_number'] ?? ''); ?>" required>
                            </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label"><i class="fas fa-lock"></i> Password *</label>
                                <div class="password-container">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button type="button" class="password-toggle" id="passwordToggle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength mt-2">
                                    <div class="password-strength-fill" id="passwordStrength"></div>
                                </div>
                                <small class="form-text text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label"><i class="fas fa-lock"></i> Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login w-100 mb-3"><i class="fas fa-user-plus"></i> Register</button>
                    </form>

                    <hr>

                    <div class="text-center">
                        <p class="mb-2">Already have an account?</p>
                        <a href="login.php" class="btn btn-register"><i class="fas fa-sign-in-alt"></i> Login Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password toggle & strength (same as login page)
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('passwordToggle');
            if(toggleBtn){
                toggleBtn.addEventListener('click', function() {
                    const passwordInput = document.getElementById('password');
                    const icon = this.querySelector('i');
                    if(passwordInput.type === 'password'){
                        passwordInput.type = 'text';
                        icon.classList.replace('fa-eye', 'fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.replace('fa-eye-slash', 'fa-eye');
                    }
                });
            }

            const passwordInput = document.getElementById('password');
            if(passwordInput){
                passwordInput.addEventListener('input', function(){
                    const strengthBar = document.getElementById('passwordStrength');
                    let strength = 0;
                    if(this.value.length > 0) strength += 25;
                    if(this.value.length > 6) strength += 25;
                    if(/[A-Z]/.test(this.value)) strength += 25;
                    if(/[0-9]/.test(this.value)) strength += 25;
                    strengthBar.style.width = strength+'%';
                    strengthBar.style.backgroundColor = strength < 50 ? '#dc3545' : (strength < 75 ? '#ffc107' : '#28a745');
                });
            }
        });
    </script>
</body>
</html>
