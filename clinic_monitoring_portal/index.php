<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ECClinic Portal - Welcome</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="relog.css">
    <link rel="stylesheet" href="landing.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
        <script>alert("Student record submitted successfully!");</script>
    <?php elseif (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
        <script>alert("Please fill in all fields.");</script>
    <?php endif; ?>

    <div class="landing-wrapper">
        <div class="landing-container">
            <div class="card clinic-card">
                <div class="card-header">
                    <h4>
                        <i class="fas fa-hospital"></i>
                        <span class="brand-acronym">ECC</span>linic Portal
                    </h4>
                </div>
                <div class="card-body">
                    <div class="clinic-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    
                    <h1 class="role-title">What are you?</h1>
                    
                    <div class="role-buttons-container">
                        <!-- STUDENT login -->
                        <a href="student.php" class="btn role-btn student-btn">
                            <div class="btn-icon">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="btn-text">I'm a Student</div>
                            <div class="btn-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                        
                        <!-- STAFF login - using the better URL from first file -->
                        <a href="login.php?role=staff" class="btn role-btn staff-btn">
                            <div class="btn-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="btn-text">I'm a Staff</div>
                            <div class="btn-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                        
                        <!-- ADMIN login - using the better URL from first file -->
                        <a href="login.php?role=admin" class="btn role-btn admin-btn">
                            <div class="btn-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="btn-text">I'm an Admin</div>
                            <div class="btn-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </div>
                    
                    <div class="welcome-message">
                        <p>Welcome to ECClinic Portal. Please select your role to continue.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add loading animation to buttons
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.role-btn');
            
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Add pulse animation on click
                    this.style.animation = 'pulse 0.6s ease';
                    setTimeout(() => {
                        this.style.animation = '';
                    }, 600);
                });
                
                // Hover effects
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>