<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if company is selected
if (!isset($_SESSION['selected_company_id'])) {
    header("Location: select_company.php");
    exit();
}

$company_id = $_SESSION['selected_company_id'];
$current_year = isset($_GET['year']) ? intval($_GET['year']) : 2025;
$last_year = $current_year - 1;

// If this is an AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Calculate income statement data
    $data = [];
    
    try {
        // Get current year data
        $current_year_query = "SELECT classification, SUM(ending_balance) as total 
                              FROM trial_balance 
                              WHERE company_id = ? AND year = ?
                              GROUP BY classification";
        $stmt = $con->prepare($current_year_query);
        $stmt->bind_param("ii", $company_id, $current_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $current_year_data = [];
        while ($row = $result->fetch_assoc()) {
            $current_year_data[$row['classification']] = $row['total'];
        }
        
        // Get last year data
        $last_year_query = "SELECT classification, SUM(ending_balance) as total 
                           FROM trial_balance 
                           WHERE company_id = ? AND year = ?
                           GROUP BY classification";
        $stmt = $con->prepare($last_year_query);
        $stmt->bind_param("ii", $company_id, $last_year);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $last_year_data = [];
        while ($row = $result->fetch_assoc()) {
            $last_year_data[$row['classification']] = $row['total'];
        }
        
        // Calculate income statement rows
        $revenues = $current_year_data['Revenues'] ?? 0;
        $revenues_ly = $last_year_data['Revenues'] ?? 0;
        
        $cost_of_sales = $current_year_data['Cost of sales and services'] ?? 0;
        $cost_of_sales_ly = $last_year_data['Cost of sales and services'] ?? 0;
        
        $gross_profit = $revenues - $cost_of_sales;
        $gross_profit_ly = $revenues_ly - $cost_of_sales_ly;
        
        $admin_expenses = $current_year_data['Administrative expenses'] ?? 0;
        $admin_expenses_ly = $last_year_data['Administrative expenses'] ?? 0;
        
        $marketing_expenses = $current_year_data['Marketing expenses'] ?? 0;
        $marketing_expenses_ly = $last_year_data['Marketing expenses'] ?? 0;
        
        $finance_costs = $current_year_data['Finance costs'] ?? 0;
        $finance_costs_ly = $last_year_data['Finance costs'] ?? 0;
        
        $other_income = $current_year_data['Other income'] ?? 0;
        $other_income_ly = $last_year_data['Other income'] ?? 0;
        
        $profit_before_tax = $gross_profit + $other_income - $finance_costs - $admin_expenses - $marketing_expenses;
        $profit_before_tax_ly = $gross_profit_ly + $other_income_ly - $finance_costs_ly - $admin_expenses_ly - $marketing_expenses_ly;
        
        $income_tax = $current_year_data['Income Tax Expense'] ?? 0;
        $income_tax_ly = $last_year_data['Income Tax Expense'] ?? 0;
        
        $profit = $profit_before_tax - $income_tax;
        $profit_ly = $profit_before_tax_ly - $income_tax_ly;
        
        $other_comprehensive = $current_year_data['Other Comprehensive Income'] ?? 0;
        $other_comprehensive_ly = $last_year_data['Other Comprehensive Income'] ?? 0;
        
        $total_comprehensive = $profit + $other_comprehensive;
        $total_comprehensive_ly = $profit_ly + $other_comprehensive_ly;
        
        $pre_tax_margin = $revenues != 0 ? ($profit_before_tax / $revenues) * 100 : 0;
        $pre_tax_margin_ly = $revenues_ly != 0 ? ($profit_before_tax_ly / $revenues_ly) * 100 : 0;
        
        $data = [
            [
                'notes' => 'Revenues',
                'current_year' => $revenues,
                'last_year' => $revenues_ly
            ],
            [
                'notes' => 'Cost of Sales',
                'current_year' => $cost_of_sales,
                'last_year' => $cost_of_sales_ly
            ],
            [
                'notes' => 'Gross Profit',
                'current_year' => $gross_profit,
                'last_year' => $gross_profit_ly,
                'is_calculated' => true
            ],
            [
                'notes' => 'Administrative Expenses',
                'current_year' => $admin_expenses,
                'last_year' => $admin_expenses_ly
            ],
            [
                'notes' => 'Marketing Expenses',
                'current_year' => $marketing_expenses,
                'last_year' => $marketing_expenses_ly
            ],
            [
                'notes' => 'Finance Costs',
                'current_year' => $finance_costs,
                'last_year' => $finance_costs_ly
            ],
            [
                'notes' => 'Other Income',
                'current_year' => $other_income,
                'last_year' => $other_income_ly
            ],
            [
                'notes' => 'Profit Before Tax',
                'current_year' => $profit_before_tax,
                'last_year' => $profit_before_tax_ly,
                'is_calculated' => true
            ],
            [
                'notes' => 'Income Tax Expense',
                'current_year' => $income_tax,
                'last_year' => $income_tax_ly
            ],
            [
                'notes' => 'Profit',
                'current_year' => $profit,
                'last_year' => $profit_ly,
                'is_calculated' => true
            ],
            [
                'notes' => 'Other Comprehensive Income',
                'current_year' => $other_comprehensive,
                'last_year' => $other_comprehensive_ly
            ],
            [
                'notes' => 'Total Comprehensive Income',
                'current_year' => $total_comprehensive,
                'last_year' => $total_comprehensive_ly,
                'is_calculated' => true
            ],
            [
                'notes' => 'Pre-Tax Profit Margin',
                'current_year' => $pre_tax_margin,
                'last_year' => $pre_tax_margin_ly,
                'is_percentage' => true,
                'is_calculated' => true
            ]
        ];
        
        echo json_encode(['data' => $data]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/x-icon" href="img/favicon.ico" />
    <title>FinTrack | Income Statements</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <style>
        .negative-amount { color: red; }
        .calculated-row { font-weight: bold; }
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
                    <li><a href="expenses.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Expenses</a></li>
                    <li><a href="sales.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Sales</a></li>
                    <li><a href="trial_balance.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Trial Balance</a></li>
                    <li><a href="income_statements.php" class="block py-2 px-3 rounded bg-[#e4fbeaff] text-[#1bb34cff] font-semibold">Income Statements</a></li>
                    <li><a href="balance_sheet.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Balance Sheet</a></li>
                    <li><a href="profile.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Profile</a></li>
                </ul>
            </nav>
            <a href="logout.php" class="block py-2 px-8 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Logout</a>
        </aside>

        <!-- Overlay -->
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-40 md:hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:ml-64">
            <header class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-5xl font-semibold text-gray-800">Income Statements</h1>
                    <?php if (isset($_SESSION['selected_company_name'])): ?>
                        <p class="text-lg text-gray-600 mt-2">for <?php echo htmlspecialchars($_SESSION['selected_company_name']); ?></p>
                    <?php endif; ?>
                </div>
                <button id="menuBtn" class="md:hidden px-4 py-2 bg-blue-200 text-white rounded">
                    <div class="text-2xl font-bold text-blue-500">☰</div>
                </button>
            </header>

            <!-- Controls Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-wrap gap-4 justify-between items-center">
                    <!-- Year Selector -->
                    <div class="flex gap-4">
                        <select id="yearSelect" class="rounded border p-2">
                            <?php
                            $start_year = 2020;
                            $end_year = date('Y') + 1;
                            for ($year = $start_year; $year <= $end_year; $year++) {
                                $selected = $year === $current_year ? 'selected' : '';
                                echo "<option value='$year' $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Income Statement Table -->
            <div class="overflow-x-auto bg-white rounded shadow-md p-4">
                <table id="incomeStatementTable" class="min-w-full text-sm">
                    <thead class="bg-gray-200 text-gray-700 uppercase">
                        <tr>
                            <th class="text-left px-6 py-3 w-1/2">Notes</th>
                            <th class="text-right px-6 py-3 w-1/4" id="currentYearHeader"></th>
                            <th class="text-right px-6 py-3 w-1/4" id="lastYearHeader"></th>
                        </tr>
                    </thead>
                </table>
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

        function updateTableHeaders() {
            const currentYear = $('#yearSelect').val();
            const lastYear = parseInt(currentYear) - 1;
            $('#currentYearHeader').text(currentYear);
            $('#lastYearHeader').text(lastYear);
        }

        function formatAmount(amount, isPercentage = false) {
            if (amount === null || amount === '') return '-';
            
            const num = parseFloat(amount);
            if (isNaN(num)) return '-';
            
            if (isPercentage) {
                const formatted = Math.abs(num).toFixed(2);
                return num < 0 ? 
                    `<span class="negative-amount">(${formatted}%)</span>` : 
                    `${formatted}%`;
            } else {
                const formatted = Math.abs(num).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                return num < 0 ? 
                    `<span class="negative-amount">(₱${formatted})</span>` : 
                    `₱${formatted}`;
            }
        }

        // Initialize DataTable
        const table = $('#incomeStatementTable').DataTable({
            processing: true,
            serverSide: false,
            paging: false,
            searching: false,
            ordering: false,
            info: false,
            ajax: {
                url: 'incomeStatementsActions/get_income_statement_data.php',
                type: 'POST',
                data: function(d) {
                    d.year = $('#yearSelect').val();
                }
            },
            columns: [
                { 
                    data: 'notes',
                    className: 'px-6 py-3',
                    render: function(data, type, row) {
                        return row.is_calculated ? 
                            `<span class="font-bold">${data}</span>` : 
                            data;
                    }
                },
                { 
                    data: 'current_year',
                    className: 'text-right px-6 py-3',
                    render: function(data, type, row) {
                        return formatAmount(data, row.is_percentage);
                    }
                },
                { 
                    data: 'last_year',
                    className: 'text-right px-6 py-3',
                    render: function(data, type, row) {
                        return formatAmount(data, row.is_percentage);
                    }
                }
            ],
            drawCallback: function() {
                updateTableHeaders();
            }
        });

        // Year selection
        $('#yearSelect').on('change', function() {
            table.ajax.reload();
        });

        // Initial header update
        updateTableHeaders();
    </script>
</body>
</html>
