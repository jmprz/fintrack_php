<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinTrack | Select Company</title>
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full text-center">
            <img src="img/eclick.png" alt="Logo" class="mx-auto mb-6 w-48">
            <h1 class="text-2xl font-semibold text-gray-800 mb-4">No Company Selected</h1>
            <p class="text-gray-600 mb-6">Please select a company from your profile page to access this feature.</p>
            <div class="space-x-4">
                <a href="profile.php" class="inline-block bg-[#1bb34cff] text-white px-6 py-2 rounded hover:bg-[#18a045ff] transition-colors">
                    Go to Profile
                </a>
            </div>
        </div>
    </div>
</body>
</html> 