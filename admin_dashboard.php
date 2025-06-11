<?php
session_start();
require_once 'connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['account_type'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// Get employees
$employees_stmt = $con->prepare("
    SELECT user_id, first_name, last_name, email_address, created_at 
    FROM users 
    WHERE account_type = 'Employee'
    ORDER BY created_at DESC
");
$employees_stmt->execute();
$employees_result = $employees_stmt->get_result();

// Handle employee actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $user_id = $_POST['user_id'];
        
        if ($_POST['action'] === 'delete') {
            $delete_stmt = $con->prepare("DELETE FROM users WHERE user_id = ? AND account_type = 'Employee'");
            $delete_stmt->bind_param("i", $user_id);
            $delete_stmt->execute();
            header("Location: admin_dashboard.php");
            exit();
        }
        
        if ($_POST['action'] === 'promote') {
            $promote_stmt = $con->prepare("UPDATE users SET account_type = 'Admin' WHERE user_id = ?");
            $promote_stmt->bind_param("i", $user_id);
            $promote_stmt->execute();
            header("Location: admin_dashboard.php");
            exit();
        }
    }
}

// Get current page name for navbar
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | FinTrack</title>
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-64 bg-white shadow-md fixed inset-y-0 left-0 transform -translate-x-full md:translate-x-0 transition-transform duration-200 z-50">
            <div class="items-center border-b p-4">
                <img class="w-96" src="img/eclick.png" alt="Logo" />
            </div>      
            <nav class="p-4 mb-[6rem]">
                <ul class="space-y-2 text-gray-700">
                    <li>
                        <a href="admin_dashboard.php" 
                           class="block py-2 px-3 rounded <?php echo $current_page === 'admin_dashboard.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="adminActions/admin_profile.php" 
                           class="block py-2 px-3 rounded <?php echo $current_page === 'admin_profile.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                            Profile
                        </a>
                    </li>
                    <li>
                        <a href="adminActions/log_activities.php" 
                           class="block py-2 px-3 rounded <?php echo $current_page === 'log_activities.php' ? 'bg-[#e4fbeaff] text-[#1bb34cff] font-semibold' : 'hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]'; ?>">
                            Log Activities
                        </a>
                    </li>
                </ul>
            </nav>
            <a href="logout.php" class="block py-2 px-8 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Logout</a>
        </aside>

        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-40 md:hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:ml-64">
            <header class="flex justify-between items-center mb-6">
                <h1 class="text-5xl font-semibold text-gray-800">Dashboard</h1>
                <button id="menuBtn" class="md:hidden px-4 py-2 bg-blue-200 text-white rounded">
                    <div class="text-2xl font-bold text-blue-500">☰</div>
                </button>
            </header>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold mb-6">Employee Management</h2>

                <!-- Employees Table -->
                <div class="overflow-x-auto">
                    <table id="employeesTable" class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Joined Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($employee = $employees_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($employee['email_address']); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($employee['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm space-x-2">
                                        <button onclick="showCompanyModal(<?php echo $employee['user_id']; ?>)"
                                           class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-eye"></i> View Work
                                        </button>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="user_id" value="<?php echo $employee['user_id']; ?>">
                                            <input type="hidden" name="action" value="promote">
                                            <button type="submit" class="text-purple-600 hover:text-purple-900" 
                                                    onclick="return confirm('Are you sure you want to promote this employee to admin?')">
                                                <i class="fas fa-user-shield"></i> Promote
                                            </button>
                                        </form>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="user_id" value="<?php echo $employee['user_id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="text-red-600 hover:text-red-900"
                                                    onclick="return confirm('Are you sure you want to delete this employee?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add this before the closing body tag -->
    <!-- Company Selection Modal -->
    <div id="companyModal" class="modal">
        <div class="modal-content">
            <h2 class="text-2xl font-semibold mb-4">Select Company</h2>
            <p class="mb-4">Please select a company to view the employee's work:</p>
            <select id="companySelect" class="w-full p-2 border rounded mb-4">
                <option value="">Loading companies...</option>
            </select>
            <div class="flex justify-end space-x-2">
                <button id="cancelViewWork" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</button>
                <button id="confirmViewWork" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">View Work</button>
            </div>
        </div>
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

        // Company selection modal functionality
        let selectedEmployeeId = null;

        function showCompanyModal(employeeId) {
            selectedEmployeeId = employeeId;
            document.getElementById('companyModal').style.display = 'block';
            
            // Load companies for the employee
            fetch(`adminActions/select_employee_company.php?employee_id=${employeeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('companySelect');
                        select.innerHTML = '<option value="">Select a company...</option>' +
                            data.companies.map(company => 
                                `<option value="${company.company_id}">${company.company_name}</option>`
                            ).join('');
                    } else {
                        alert(data.message || 'Error loading companies');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading companies');
                });
        }

        document.getElementById('cancelViewWork').addEventListener('click', () => {
            document.getElementById('companyModal').style.display = 'none';
        });

        document.getElementById('confirmViewWork').addEventListener('click', () => {
            const companyId = document.getElementById('companySelect').value;
            if (!companyId) {
                alert('Please select a company');
                return;
            }
            
            // Store the selected company ID in session and redirect
            fetch('adminActions/set_viewing_company.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    employee_id: selectedEmployeeId,
                    company_id: companyId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `adminActions/employeeView/expenses.php?employee_id=${selectedEmployeeId}`;
                } else {
                    alert(data.message || 'Error setting company');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error setting company');
            });
        });

        // DataTables initialization with compact styling
        $(document).ready(function() {
            $('#employeesTable').DataTable({
                "order": [[2, "desc"]], // Sort by joined date by default
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
$employees_stmt->close();
$con->close();
?> 