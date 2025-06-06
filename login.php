<?php
session_start();
include("connection.php");
include("functions.php");

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $email_address = trim($_POST['email_address']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);

    if (!empty($email_address) && !empty($password)) {
        // Prepared statement to prevent SQL injection
        $stmt = $con->prepare("SELECT * FROM users WHERE email_address = ? LIMIT 1");
        $stmt->bind_param("s", $email_address);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $user_data = $result->fetch_assoc();

            if (password_verify($password, $user_data['password'])) {
                if ($user_data['is_verified'] == 1) {
                    $_SESSION['user_id'] = $user_data['user_id'];
                    $_SESSION['account_type'] = $user_data['account_type'];

                    if ($remember_me) {
                        setcookie('user_id', $user_data['user_id'], time() + (86400 * 30), "/");
                    }

                    if ($user_data['account_type'] === 'Admin') {
                        $_SESSION['is_admin'] = 1;
                        header("Location: admin_dashboard.php");
                        exit;
                    } else {
                        header("Location: profile.php");
                        exit;
                    }
                } else {
                    // Redirect to email verification if not yet verified
                    header("Location: verify_email.php?email=" . urlencode($email_address));
                    exit;
                }
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }

        $stmt->close();
    } else {
        $error_message = "Please enter both email and password.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FinTrack | Login</title>
  <link rel="icon" type="image/x-icon" href="img/favicon.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Add Font Awesome for the eye icon -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
  <div class="w-full max-w-md p-8 bg-white rounded-lg shadow-lg">
    <div class="mb-6 flex justify-center">
      <img src="img/powered.png" alt="FinTrack Logo" class="w-48">
    </div>

    <?php if (!empty($error_message)): ?>
      <div class="mb-4 text-red-600 text-center font-medium"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
      <div>
        <label class="block text-gray-700">Email Address</label>
        <input 
          type="email" 
          name="email_address" 
          value="<?= isset($_POST['email_address']) ? htmlspecialchars($_POST['email_address']) : '' ?>"
          class="w-full px-4 py-2 mt-1 border rounded-md focus:outline-none focus:ring-2 focus:ring-[#0b3553ff]" 
          required>
      </div>

      <div>
        <label class="block text-gray-700">Password</label>
        <div class="relative">
          <input 
            type="password" 
            name="password" 
            id="password"
            class="w-full px-4 py-2 mt-1 border rounded-md focus:outline-none focus:ring-2 focus:ring-[#0b3553ff]" 
            required>
          <button 
            type="button"
            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
            onclick="togglePasswordVisibility()">
            <i class="fas fa-eye" id="togglePassword"></i>
          </button>
        </div>
      </div>

      <div class="flex items-center justify-between text-sm text-gray-600">
        <label class="flex items-center">
          <input type="checkbox" name="remember_me" class="mr-2">
          Remember Me
        </label>
        <a href="forgot_password.php" class="text-[#1bb34cff] hover:underline">Forgot Password?</a>
      </div>

      <button type="submit" class="w-full py-2 font-semibold text-white bg-[#0b3553ff] rounded hover:bg-[#061a2bff]">
        Login
      </button>

      <p class="text-sm text-center text-gray-600">
        Don't have an account? 
        <a href="register.php" class="text-[#1bb34cff] hover:underline">Register</a>
      </p>
    </form>
  </div>

  <script>
    function togglePasswordVisibility() {
      const passwordInput = document.getElementById('password');
      const toggleIcon = document.getElementById('togglePassword');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
      }
    }
  </script>
</body>
</html>
