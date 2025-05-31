<?php
require_once 'connection.php';

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = trim($_POST['code']);

    $stmt = $con->prepare("SELECT * FROM users WHERE verification_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if ($user['is_verified'] == 0) {
            $update = $con->prepare("UPDATE users SET is_verified = 1 WHERE verification_code = ?");
            $update->bind_param("s", $code);
            if ($update->execute()) {
                // Optionally, you can log in the user here or redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Something went wrong. Please try again.";
            }
            $update->close();
        } else {
            $success = "Your email is already verified.";
        }
    } else {
        $error = "Invalid verification code.";
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
    <title>Verify Email | FinTrack</title>
    <link rel="icon" href="img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white p-8 rounded shadow-md">
        <div class="flex justify-center mb-6">
            <img class="w-48" src="img/powered.png" alt="Logo" />
        </div>

        <?php if (!empty($success)): ?>
            <div class="mb-4 text-green-600 text-center font-medium">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-4 text-red-600 text-center font-medium">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <label class="block">
                <span class="text-gray-700">Verification Code</span>
                <input type="text" name="code" maxlength="6" required
                    class="mt-1 block w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-[#1bb34c]">
            </label>
            <button type="submit"
                class="w-full bg-[#1bb34c] text-white py-2 rounded hover:bg-green-700 transition">
                Verify Email
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="login.php" class="text-[#1bb34c] hover:underline">Back to Login</a>
        </div>
    </div>
</body>
</html>
