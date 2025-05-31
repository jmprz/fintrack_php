<?php

include("connection.php");

// Session initialization and "Remember Me" functionality
if (isset($_COOKIE['user_id']) && !isset($_SESSION['user_id'])) {
    // Start session only if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Get user ID from cookie
    $user_id = $_COOKIE['user_id'];

    // Query user by user ID
    $query = "SELECT * FROM user WHERE user_id = '$user_id' LIMIT 1";
    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $user_data = mysqli_fetch_assoc($result);

        // Set session variables
        $_SESSION['user_id'] = $user_data['user_id'];
        $_SESSION['account_type'] = $user_data['account_type'];

        if ($user_data['account_type'] === 'Admin') {
            $_SESSION['is_admin'] = 1;
        }
    }
}

// Check if the function exists before declaring it
if (!function_exists('check_login')) {
    function check_login($con)
    {
        if (isset($_SESSION['user_id'])) {
            $id = $_SESSION['user_id'];
            $query = "SELECT * FROM user WHERE user_id = '$id' LIMIT 1";

            $result = mysqli_query($con, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                $user_data = mysqli_fetch_assoc($result);
                return $user_data;
            }
        }

        // Redirect to login
        header("Location: login.php");
        die;
    }
}

// Check if the function exists before declaring it
if (!function_exists('random_num')) {
    function random_num($length)
    {
        $text = "";
        if ($length < 5) {
            $length = 5;
        }

        $len = rand(4, $length);

        for ($i = 0; $i < $len; $i++) {
            $text .= rand(0, 9);
        }
        return $text;
    }
}
?>
