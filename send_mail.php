<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Composer autoload

function sendVerificationEmail($toEmail, $toName, $verificationCode) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'fintrack.system.ph@gmail.com';        // Your Gmail address
        $mail->Password   = 'vcrr iwth ndjm soah';                 // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;           // Use SSL encryption
        $mail->Port       = 465;                                   // 465 for SSL

        // Recipients
        $mail->setFrom('fintrack.system.ph@gmail.com', 'FinTrack');
        $mail->addAddress($toEmail, $toName);

        // Email Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Email Verification Code';
        $mail->Body    = "
            <h3>Hello $toName,</h3>
            <p>Thank you for registering with <strong>FinTrack</strong>.</p>
            <p>Your verification code is:</p>
            <h2 style='color: blue;'>$verificationCode</h2>
            <p>Please enter this code on the verification page to activate your account.</p>
            <p>If you did not register, please ignore this email.</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
