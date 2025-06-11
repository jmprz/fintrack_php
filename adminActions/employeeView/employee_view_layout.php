<?php
session_start();

// Include database connection
require_once '../../connection.php';
global $con;

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header("Location: ../../login.php");
    exit();
}

// Get employee information
if (!isset($_GET['employee_id'])) {
    header("Location: ../../admin_dashboard.php");
    exit();
}

$employee_id = $_GET['employee_id'];
$stmt = $con->prepare("SELECT first_name, last_name FROM users WHERE user_id = ? AND account_type = 'Employee'");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    header("Location: ../../admin_dashboard.php");
    exit();
}

$employee_name = $employee['first_name'] . ' ' . $employee['last_name'];
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> | FinTrack</title>
    <link rel="icon" type="image/x-icon" href="../../img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php echo isset($additional_head) ? $additional_head : ''; ?>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-64 bg-white shadow-md fixed inset-y-0 left-0 transform -translate-x-full md:translate-x-0 transition-transform duration-200 z-50">
            <div class="items-center border-b p-4">
                <img class="w-96" src="../../img/eclick.png" alt="Logo" />
            </div>      
            <nav class="p-4">
                <!-- Admin Navigation -->
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Admin</h3>
                    <ul class="space-y-2 text-gray-700">
                        <li>
                            <a href="../../admin_dashboard.php" 
                               class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="../admin_profile.php" 
                               class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">
                                Profile
                            </a>
                        </li>
                        <li>
                            <a href="../log_activities.php" 
                               class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">
                                Log Activities
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Employee Navigation -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">
                        <?php echo htmlspecialchars($employee_name); ?>'s Work
                    </h3>
                    <ul class="space-y-2 text-gray-700">
                        <li>
                            <a href="expenses.php?employee_id=<?php echo $employee_id; ?>" 
                               class="block py-2 px-3 rounded <?php echo $current_page === 'expenses.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                                Expenses
                            </a>
                        </li>
                        <li>
                            <a href="sales.php?employee_id=<?php echo $employee_id; ?>" 
                               class="block py-2 px-3 rounded <?php echo $current_page === 'sales.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                                Sales
                            </a>
                        </li>
                        <li>
                            <a href="trial_balance.php?employee_id=<?php echo $employee_id; ?>" 
                               class="block py-2 px-3 rounded <?php echo $current_page === 'trial_balance.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                                Trial Balance
                            </a>
                        </li>
                        <li>
                            <a href="income_statements.php?employee_id=<?php echo $employee_id; ?>" 
                               class="block py-2 px-3 rounded <?php echo $current_page === 'income_statements.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                                Income Statements
                            </a>
                        </li>
                        <li>
                            <a href="balance_sheet.php?employee_id=<?php echo $employee_id; ?>" 
                               class="block py-2 px-3 rounded <?php echo $current_page === 'balance_sheet.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                                Balance Sheet
                            </a>
                        </li>
                        <li>
                            <a href="profile.php?employee_id=<?php echo $employee_id; ?>" 
                               class="block py-2 px-3 rounded <?php echo $current_page === 'profile.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                                Profile
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <a href="../../logout.php" class="block py-2 px-8 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Logout</a>
        </aside>

        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-40 md:hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:ml-64">
            <header class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-5xl font-semibold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
                    <p class="text-lg text-gray-600 mt-2">Viewing <?php echo htmlspecialchars($employee_name); ?>'s work</p>
                </div>
                <button id="menuBtn" class="md:hidden px-4 py-2 bg-blue-200 text-white rounded">
                    <div class="text-2xl font-bold text-blue-500">☰</div>
                </button>
            </header>

            <?php echo $content; ?>
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

        <?php echo isset($additional_scripts) ? $additional_scripts : ''; ?>
    </script>
</body>
</html>

<?php
$stmt->close();
$con->close();
?> 