<?php 
require 'config.php'; 
if (is_logged_in()) {
  header('Location: ' . (is_admin() ? 'admin_dashboard.php' : 'user_dashboard.php'));
  exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>IT Daily Checklist - Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Responsive meta tag -->

  <!-- Favicon (tab icon) -->
  <link rel="icon" type="image/png" href="assets/logo.png">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <style>
    body {
      background: #f4f8fb;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card-ghost {
      box-shadow: 0 4px 24px rgba(0,0,0,0.08);
      border-radius: 18px;
      border: none;
    }
    .logo {
      width: 80px;
      height: 80px;
      object-fit: contain;
      margin-bottom: 12px;
      display: block;
      margin-left: auto;
      margin-right: auto;
    }
    @media (max-width: 575.98px) {
      .card-ghost {
        padding: 1rem !important;
      }
    }
  </style>
</head>
<body>

<div class="container px-3">
  <div class="row justify-content-center">
    <div class="col-lg-5 col-md-7 col-sm-10">
      <div class="card card-ghost p-4">
        <img src="assets/logo1.png" alt="Hospital IT Logo" class="logo">
        <h3 class="text-center mb-1">IT Daily Checklist</h3>
        <p class="text-center text-muted mb-4">Sign in to access your daily checklist</p>
        
        <?php if(isset($_GET['error'])): ?>
          <div class="alert alert-danger"><?=htmlspecialchars($_GET['error'])?></div>
        <?php endif; ?>

        <form method="post" action="authenticate.php">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" placeholder="Enter your email" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group">
              <input id="password" name="password" type="password" class="form-control" placeholder="Enter your password" required>
              <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <button class="btn btn-primary w-100">Sign In</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap + Icons -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<script>
  const togglePassword = document.getElementById("togglePassword");
  const passwordInput = document.getElementById("password");

  togglePassword.addEventListener("click", function () {
    const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
    passwordInput.setAttribute("type", type);
    this.innerHTML = type === "password" ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
  });
</script>

</body>
</html>
