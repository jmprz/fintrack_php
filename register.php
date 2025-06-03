<?php
session_start();
require_once 'connection.php';
require_once 'send_mail.php'; // PHPMailer function

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_type = $_POST['account_type'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email_address = trim($_POST['email_address']);
    $password = $_POST['password'];

    $full_name = $first_name . ' ' . $last_name;

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 6-digit verification code
    $verification_code = rand(100000, 999999);

    // Check if email already exists
    $stmt = $con->prepare("SELECT * FROM users WHERE email_address = ?");
    if (!$stmt) {
        die("Prepare failed (SELECT): " . $con->error);
    }

    $stmt->bind_param("s", $email_address);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $error = "Email already registered.";
    } else {
        // Insert new user
        $stmt = $con->prepare("INSERT INTO users (account_type, first_name, last_name, email_address, password, verification_code, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
        if (!$stmt) {
            die("Prepare failed (INSERT): " . $con->error);
        }

        $stmt->bind_param("ssssss", $account_type, $first_name, $last_name, $email_address, $hashed_password, $verification_code);

        if ($stmt->execute()) {
            // Send verification email with code
            if (sendVerificationEmail($email_address, $full_name, $verification_code)) {
                $_SESSION['message'] = "A verification code has been sent to your email.";
                header("Location: verify.php");
                exit;
            } else {
                $error = "Failed to send verification email.";
            }
        } else {
            $error = "Registration failed. Try again.";
        }
    }

    $stmt->close();
}

$con->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinTrack | Register</title>
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Add Font Awesome for the eye icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

    <div class="w-full max-w-md bg-white p-8 rounded shadow-md">
        <div class="flex justify-center mb-6">
            <img class="w-48" src="img/powered.png" alt="Logo" />
        </div>

        <?php if (isset($error)): ?>
            <div class="mb-4 text-red-500 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Account Type</label>
                <select name="account_type" class="mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200">
                    <option value="employee" selected="selected">Employee</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">First Name</label>
                <input type="text" name="first_name" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Last Name</label>
                <input type="text" name="last_name" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Email Address</label>
                <input type="email" name="email_address" required class="mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <div class="relative">
                    <input type="password" 
                           name="password" 
                           id="password"
                           required 
                           class="mt-1 w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200">
                    <button 
                        type="button"
                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
                        onclick="togglePasswordVisibility()">
                        <i class="fas fa-eye" id="togglePassword"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full bg-[#0b3553ff] hover:bg-[#061a2bff] text-white py-2 rounded font-semibold">
                Register
            </button>

            <p class="text-sm text-center text-gray-600">Already have an account?
                <a href="login.php" class="text-[#1bb34cff] hover:underline">Login</a>
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
