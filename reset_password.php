<?php
require 'connection.php';

$success = $error = "";
$showForm = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Fix: Correct SQL syntax with WHERE condition
    $stmt = $con->prepare("SELECT * FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $showForm = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newPassword = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];

            if ($newPassword !== $confirmPassword) {
                $error = "Passwords do not match.";
            } elseif (strlen($newPassword) < 6) {
                $error = "Password must be at least 6 characters.";
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                $update = $con->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?");
                $update->bind_param("ss", $hashedPassword, $token);

                if ($update->execute()) {
                    $success = "Your password has been reset. You can now <a href='login.php' class='text-[#1bb34c] underline'>login</a>.";
                    $showForm = false;
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    } else {
        $error = "Invalid token.";
    }
} else {
    $error = "No token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | FinTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white p-8 rounded shadow-md">
        <div class="flex justify-center mb-6">
            <img class="w-48" src="img/powered.png" alt="Logo" />
        </div>

        <?php if (!empty($success)): ?>
            <div class="mb-4 text-green-600 text-center font-medium"><?= $success ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="mb-4 text-red-600 text-center font-medium"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($showForm): ?>
        <form method="POST" class="space-y-4">
            <label class="block">
                <span class="text-gray-700">New Password</span>
                <input type="password" name="password" required class="w-full border p-2 rounded" minlength="6">
            </label>

            <label class="block">
                <span class="text-gray-700">Confirm New Password</span>
                <input type="password" name="confirm_password" required class="w-full border p-2 rounded" minlength="6">
            </label>

            <button type="submit" class="w-full bg-[#1bb34c] text-white py-2 rounded hover:bg-green-700">
                Reset Password
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="login.php" class="text-[#1bb34c] hover:underline">Back to Login</a>
        </div>
    </div>
</body>
</html>
