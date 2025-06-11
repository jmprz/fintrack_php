<?php
session_start();
require_once '../connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// Get selected date (default to today)
$selected_date = isset($_GET['date']) ? date('Y-m-d', strtotime($_GET['date'])) : date('Y-m-d');

// Get login activities for selected date
$activities_stmt = $con->prepare("
    SELECT 
        la.*,
        u.first_name,
        u.last_name,
        u.email_address,
        u.account_type
    FROM login_activities la
    JOIN users u ON la.user_id = u.user_id
    WHERE DATE(la.login_time) = ?
    ORDER BY la.login_time DESC
");
$activities_stmt->bind_param("s", $selected_date);
$activities_stmt->execute();
$activities_result = $activities_stmt->get_result();

// Get current page name for navbar
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Activities | FinTrack</title>
    <link rel="icon" type="image/x-icon" href="../img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
                <h1 class="text-5xl font-semibold text-gray-800">Log Activities</h1>
                <button id="menuBtn" class="md:hidden px-4 py-2 bg-blue-200 text-white rounded">
                    <div class="text-2xl font-bold text-blue-500">☰</div>
                </button>
            </header>

            <div class="bg-white rounded-lg shadow-md p-6">
                <!-- Date Filter -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold">Activity Log</h2>
                    <div class="flex items-center space-x-2">
                        <label for="date" class="text-gray-700">Select Date:</label>
                        <input type="text" id="datePicker" 
                               value="<?php echo date('F j, Y', strtotime($selected_date)); ?>"
                               class="border rounded-md py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline cursor-pointer"
                               readonly>
                    </div>
                </div>

                <!-- Activities Table -->
                <div class="overflow-x-auto">
                    <table id="activitiesTable" class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Login Time</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Logout Time</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Browser</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($activity = $activities_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo htmlspecialchars($activity['email_address']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $activity['account_type'] === 'Admin' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo htmlspecialchars($activity['account_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <?php echo date('h:i:s A', strtotime($activity['login_time'])); ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <?php echo $activity['logout_time'] ? date('h:i:s A', strtotime($activity['logout_time'])) : 'Active'; ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <?php echo htmlspecialchars($activity['ip_address']); ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500 max-w-xs truncate">
                                        <?php echo htmlspecialchars($activity['user_agent']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
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

        // Initialize date picker
        flatpickr("#datePicker", {
            dateFormat: "F j, Y",
            maxDate: "today",
            defaultDate: "<?php echo $selected_date; ?>",
            onChange: function(selectedDates, dateStr) {
                // Get the date in local timezone and format it
                const date = selectedDates[0];
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const formattedDate = `${year}-${month}-${day}`;
                window.location.href = '?date=' + formattedDate;
            }
        });

        // DataTables initialization with compact styling
        $(document).ready(function() {
            $('#activitiesTable').DataTable({
                "order": [[2, "desc"]], // Sort by login time by default
                "pageLength": 10,
                "language": {
                    "search": "Search employees:"
                },
                "dom": '<"top"f>rt<"bottom"ip>', // Simplified controls
                "scrollX": false // Prevent horizontal scroll
            });

            // Style the DataTables elements
            $('.dataTables_filter input').addClass('border rounded-md py-1 px-2 text-sm');
            $('.dataTables_length select').addClass('border rounded-md py-1 px-2 text-sm');
        });
    </script>
</body>
</html>

<?php
$activities_stmt->close();
$con->close();
?> 