<?php
$statusMessage = "";
if (isset($_GET['status'])) {
  if ($_GET['status'] == 'success') {
    $statusMessage = "Student record submitted successfully!";
  } elseif ($_GET['status'] == 'error') {
    $statusMessage = "Please fill in all fields before submitting.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ECClinic Portal - Student</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
    body { background-color: #fde9e9; display: flex; justify-content: center; align-items: center; height: 100vh; }
    .container { background: #fff; width: 520px; max-width: 95%; border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12); overflow: hidden; text-align: center; position: relative; animation: fadeIn 0.8s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .header { background-color: #b60d0d; padding: 22px 24px; color: white; font-size: 24px; font-weight: 700; }
    .header .header-icon { width: 28px; height: 28px; display: inline-block; vertical-align: middle; margin-right: 8px; }
    .content { padding: 36px 28px; }
    .content h2 { color: #222; font-size: 28px; margin-bottom: 22px; }
    .form-group { text-align: left; margin-bottom: 18px; }
    label { display: block; color: #333; font-weight: 600; margin-bottom: 6px; font-size: 15px; }
    input, select { width: 100%; padding: 12px 14px; border-radius: 10px; border: 1px solid #ccc; outline: none; transition: 0.3s; font-size: 15px; }
    input:focus, select:focus { border-color: #b60d0d; box-shadow: 0 0 5px rgba(182, 13, 13, 0.2); }
    button { width: 100%; background-color: #b60d0d; color: white; padding: 14px; border: none; border-radius: 10px; font-size: 18px; cursor: pointer; transition: 0.18s ease; margin-top: 12px; }
    button:hover { background-color: #9e0b0b; transform: scale(1.02); }
    .footer { background-color: #fde1e1; padding: 12px; font-size: 15px; color: #b60d0d; }
    .alert { position: absolute; top: 10px; left: 50%; transform: translateX(-50%); background: #b60d0d; color: white; padding: 10px 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); animation: slideDown 0.6s ease forwards; z-index: 100; }
    @keyframes slideDown { from { opacity: 0; transform: translate(-50%, -20px); } to { opacity: 1; transform: translate(-50%, 0); } }

    /* Responsive tweaks for smaller screens */
    @media (max-width: 600px) {
      .container { width: 92%; }
      .header { font-size: 20px; padding: 16px; }
      .content { padding: 20px; }
      .content h2 { font-size: 22px; }
      button { font-size: 16px; padding: 12px; }
    }
  </style>
</head>
<body>

  <?php if (!empty($statusMessage)): ?>
    <div class="alert"><?= htmlspecialchars($statusMessage) ?></div>
    <script>
      setTimeout(() => { document.querySelector('.alert').style.display = 'none'; }, 3000);
    </script>
  <?php endif; ?>

  <div class="container">
    <div class="header">
      <span class="header-icon" aria-hidden="true">
        <!-- Inline heart SVG for consistent styling and no external dependency -->
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M12 21s-6.716-4.433-9.11-7.111C-1.61 9.744 2.3 4 7.5 6.25 9.286 7.14 10.5 8.5 12 10c1.5-1.5 2.714-2.86 4.5-3.75C21.7 4 25.61 9.744 21.11 13.889 18.716 16.567 12 21 12 21z" fill="#ffffff"/>
        </svg>
      </span>
      ECClinic Portal
    </div>

    <div class="content">
      <h2>Student Information</h2>
      <form action="student_submit.php" method="POST">
        <div class="form-group">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" placeholder="Enter your full name" required>
        </div>

        <div class="form-group">
          <label for="student_number">Student Number</label>
          <input type="text" id="student_number" name="student_number" placeholder="Enter your student number" required>
        </div>

        <div class="form-group">
          <label for="course">Course</label>
          <select id="course" name="course" required>
            <option value="">Select course</option>
            <option value="BSCS">BSCS</option>
            <option value="BSBA">BSBA</option>
            <option value="BSIT">BSIT</option>
            <option value="BSED">BSED</option>
            <option value="BSTM">BSTM</option>
          </select>
        </div>

        <div class="form-group">
          <label for="gender">Gender</label>
          <select id="gender" name="gender" required>
            <option value="">Select gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <button type="submit">Submit</button>
      </form>
    </div>

    <div class="footer">
      Welcome to ECClinic Portal. Please fill out your details.
    </div>
  </div>

</body>
</html>
