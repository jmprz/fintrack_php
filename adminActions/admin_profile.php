<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get admin information
$stmt = $con->prepare("SELECT * FROM users WHERE user_id = ? AND account_type = 'Admin'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Get total number of employees
$emp_stmt = $con->prepare("SELECT COUNT(*) as total FROM users WHERE account_type = 'Employee'");
$emp_stmt->execute();
$emp_result = $emp_stmt->get_result();
$total_employees = $emp_result->fetch_assoc()['total'];

// Get total number of companies
$comp_stmt = $con->prepare("SELECT COUNT(*) as total FROM companies");
$comp_stmt->execute();
$comp_result = $comp_stmt->get_result();
$total_companies = $comp_result->fetch_assoc()['total'];

// Get today's login activities
$today = date('Y-m-d');
$activity_stmt = $con->prepare("
    SELECT COUNT(*) as total 
    FROM login_activities 
    WHERE DATE(login_time) = ?
");
$activity_stmt->bind_param("s", $today);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();
$today_logins = $activity_result->fetch_assoc()['total'];

// Get current page name for navbar
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | FinTrack</title>
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-64 bg-white shadow-md fixed inset-y-0 left-0 transform -translate-x-full md:translate-x-0 transition-transform duration-200 z-50">
            <div class="items-center border-b p-4">
                <img class="w-96" src="../img/eclick.png" alt="Logo" />
            </div>      
            <nav class="p-4 mb-[6rem]">
                <ul class="space-y-2 text-gray-700">
                    <li>
                        <a href="../admin_dashboard.php" 
                           class="block py-2 px-3 rounded <?php echo $current_page === 'admin_dashboard.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="admin_profile.php" 
                           class="block py-2 px-3 rounded <?php echo $current_page === 'admin_profile.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                            Profile
                        </a>
                    </li>
                    <li>
                        <a href="log_activities.php" 
                           class="block py-2 px-3 rounded <?php echo $current_page === 'log_activities.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                            Log Activities
                        </a>
                    </li>
                </ul>
            </nav>
            <a href="../logout.php" class="block py-2 px-8 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Logout</a>
        </aside>

        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-40 md:hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:ml-64">
            <header class="flex justify-between items-center mb-6">
                <h1 class="text-5xl font-semibold text-gray-800">Profile</h1>
                <button id="menuBtn" class="md:hidden px-4 py-2 bg-blue-200 text-white rounded">
                    <div class="text-2xl font-bold text-blue-500">☰</div>
                </button>
            </header>

            <!-- Profile Information -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-2xl font-semibold mb-4">Account Information</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-600">Name</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Email</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($admin['email_address']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Account Type</p>
                        <p class="font-semibold capitalize"><?php echo htmlspecialchars($admin['account_type']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Member Since</p>
                        <p class="font-semibold"><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold mb-4">System Statistics</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Total Employees Card -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg font-semibold">Total Employees</h3>
                            <i class="fas fa-users text-2xl text-blue-600"></i>
                        </div>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_employees; ?></p>
                        <p class="text-sm text-gray-600 mt-2">Registered employees in the system</p>
                    </div>

                    <!-- Total Companies Card -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg font-semibold">Total Companies</h3>
                            <i class="fas fa-building text-2xl text-green-600"></i>
                        </div>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $total_companies; ?></p>
                        <p class="text-sm text-gray-600 mt-2">Registered companies in the system</p>
                    </div>

                    <!-- Today's Logins Card -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-lg font-semibold">Today's Logins</h3>
                            <i class="fas fa-sign-in-alt text-2xl text-purple-600"></i>
                        </div>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $today_logins; ?></p>
                        <p class="text-sm text-gray-600 mt-2">Total login activities today</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        });
    </script>
</body>
</html>

<?php
// Close database connections
$stmt->close();
$emp_stmt->close();
$comp_stmt->close();
$activity_stmt->close();
$con->close();
?> 