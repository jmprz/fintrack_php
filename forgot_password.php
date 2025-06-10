<?php
require 'connection.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$success = $error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email_address']);

    // Check if email exists
    $stmt = $con->prepare("SELECT * FROM users WHERE email_address = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $token = bin2hex(random_bytes(32));

        // Store the token (no expiration)
        $update = $con->prepare("UPDATE users SET reset_token = ? WHERE email_address = ?");
        $update->bind_param("ss", $token, $email);
        $update->execute();

        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'fintrack.system.ph@gmail.com';
            $mail->Password   = 'vcrr iwth ndjm soah';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('fintrack.system.ph@gmail.com', 'FinTrack');
            $mail->addAddress($email, $user['first_name']);

            $resetLink = "http://localhost/fintrack/reset_password.php?token=" . $token;

            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password - FinTrack';
            $mail->Body    = "
                <h3>Hello {$user['first_name']},</h3>
                <p>You requested a password reset. Click the link below to reset your password:</p>
                <a href='$resetLink' style='color: blue;'>Reset Password</a>
                <p>If you did not request this, please ignore this email.</p>
            ";

            $mail->send();
            $success = "A reset link has been sent to your email.";
        } catch (Exception $e) {
            $error = "Failed to send email. Please try again.";
        }
    } else {
        $error = "No user found with that email address.";
    }
}
?>



<!-- Tailwind UI -->
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password | FinTrack</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-white p-8 rounded shadow">
        <h2 class="text-xl font-semibold mb-4 text-center">Forgot Password</h2>

        <?php if ($success): ?>
            <div class="mb-4 text-green-600 text-center"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="mb-4 text-red-600 text-center"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <label class="block">
                <span class="text-gray-700">Email Address</span>
                <input type="email" name="email_address" required class="w-full border p-2 rounded">
            </label>
            <button type="submit" class="w-full bg-[#1bb34c] text-white py-2 rounded hover:bg-green-700">Send Reset Link</button>
        </form>

        <div class="mt-6 text-center">
            <a href="login.php" class="text-[#1bb34c] hover:underline">Back to Login</a>
        </div>
    </div>
</body>
</html>
