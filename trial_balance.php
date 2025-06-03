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

// Fetch trial balance data from database
$query = "SELECT 
            id,
            classification,
            category,
            account_code_sap,
            description,
            ending_balance
          FROM trial_balance 
          WHERE company_id = ? AND year = ?
          ORDER BY classification ASC, category ASC";

$stmt = $con->prepare($query);
$stmt->bind_param("ii", $company_id, $current_year);
$stmt->execute();
$result = $stmt->get_result();

$trial_balance_data = array();
while ($row = $result->fetch_assoc()) {
    $trial_balance_data[] = $row;
}

$stmt->close();
$con->close();

// If this is an AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(array("data" => $trial_balance_data));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/x-icon" href="img/favicon.ico" />
    <title>FinTrack | Trial Balance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <style>
        .negative-amount { color: red; }
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            border-radius: 8px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: black; }
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
                    <li><a href="trial_balance.php" class="block py-2 px-3 rounded bg-[#e4fbeaff] text-[#1bb34cff] font-semibold">Trial Balance</a></li>
                    <li><a href="income_statements.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Income Statements</a></li>
                    <li><a href="balance_sheet.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Balance Sheet</a></li>
                    <li><a href="profile.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Profile</a></li>
                </ul>
            </nav>
            <a href="logout.php" class="block py-2 px-8 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Logout</a>
        </aside>

        <!-- Overlay (only on mobile when sidebar is open) -->
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-40 hidden z-40 md:hidden"></div>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:ml-64">
            <header class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-5xl font-semibold text-gray-800">Trial Balance</h1>
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

                <!-- Search Box -->
                <div class="flex-1 max-w-md mx-4">
                  <input type="text" id="searchBox" placeholder="Search..." 
                        class="w-full rounded border p-2">
                </div>

                <!-- Add New Entry Button -->
                <button id="addEntryBtn" class="bg-[#1bb34cff] text-white px-4 py-2 rounded hover:bg-[#17a044ff]">
                    Add Entry
                </button>
              </div>
              <!-- No Categories Warning -->
              <div id="noCategoriesWarning" class="hidden mt-4 p-4 bg-yellow-50 text-yellow-800 rounded-md">
                <p>No sales categories found. Please add categories in your <a href="profile.php" class="underline">profile page</a> first.</p>
              </div>
            </div>

            <div class="overflow-x-auto bg-white rounded shadow-md p-4">
                <table id="trialBalanceTable" class="min-w-full text-sm">
                    <thead class="bg-gray-200 text-gray-700 uppercase">
                        <tr>
                            <th>Classification</th>
                            <th>Category</th>
                            <th>Account Code</th>
                            <th>Description</th>
                            <th class="text-right">Ending Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </main>
    </div>

    <!-- Add/Edit Entry Modal -->
    <div id="entryModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 class="text-2xl font-bold mb-4" id="modalTitle">Add Trial Balance Entry</h2>
            
            <form id="entryForm" class="space-y-4">
                <input type="hidden" id="entryId" name="id">
                <input type="hidden" id="year" name="year" value="<?php echo $current_year; ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="classification">
                            Classification *
                        </label>
                        <select id="classification" name="classification" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">Select Classification</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="category">
                            Category
                        </label>
                        <select id="category" name="category"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">Select Category</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="account_code_sap">
                            Account Code SAP *
                        </label>
                        <input type="text" id="account_code_sap" name="account_code_sap" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="description">
                            Description *
                        </label>
                        <input type="text" id="description" name="description" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>

                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="ending_balance">
                            Ending Balance *
                        </label>
                        <input type="number" step="0.01" id="ending_balance" name="ending_balance" required
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                </div>

                <div class="flex items-center justify-end mt-6 gap-4">
                    <button type="button" class="close-modal bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Cancel
                    </button>
                    <button type="submit" class="bg-[#1bb34cff] hover:bg-[#17a044ff] text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Save Entry
                    </button>
                </div>
            </form>
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

        // Modal functionality
        const modal = document.getElementById('entryModal');
        const addEntryBtn = document.getElementById('addEntryBtn');
        const closeButtons = document.querySelectorAll('.close, .close-modal');
        const entryForm = document.getElementById('entryForm');

        addEntryBtn.onclick = function() {
            document.getElementById('modalTitle').textContent = 'Add Trial Balance Entry';
            entryForm.reset();
            modal.style.display = 'block';
        }

        closeButtons.forEach(button => {
            button.onclick = function() {
                modal.style.display = 'none';
            }
        });

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Form submission
        entryForm.onsubmit = function(e) {
            e.preventDefault();
            
            const formData = new FormData(entryForm);
            const id = formData.get('id');
            const url = id ? 'update_trial_balance.php' : 'add_trial_balance.php';

            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modal.style.display = 'none';
                    table.ajax.reload();
                } else {
                    alert(data.message || 'An error occurred');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        };

        function formatAmount(amount) {
            if (amount === null || amount === '') return '-';
            
            const num = parseFloat(amount);
            if (isNaN(num)) return '-';
            
            const formatted = Math.abs(num).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            if (num < 0) {
                return `<span class="negative-amount">(${formatted})</span>`;
            }
            return formatted;
        }

        // Initialize DataTable
        const table = $('#trialBalanceTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'trialBalanceActions/get_trial_balance_data.php',
                type: 'POST',
                data: function(d) {
                    d.year = $('#yearSelect').val();
                }
            },
            pageLength: 100,
            order: [[0, 'asc'], [1, 'asc']],
            columns: [
                { data: 'classification' },
                { 
                    data: 'category',
                    render: function(data, type, row) {
                        return data || '-';
                    }
                },
                { data: 'account_code_sap' },
                { data: 'description' },
                { 
                    data: 'ending_balance',
                    render: formatAmount,
                    className: 'text-right'
                },
                {
                    data: 'id',
                    render: function(data, type, row) {
                        return `
                            <div class="flex gap-2 justify-center">
                                <button onclick="editEntry(${data})" class="text-blue-600 hover:text-blue-800">
                                    Edit
                                </button>
                                <button onclick="deleteEntry(${data})" class="text-red-600 hover:text-red-800">
                                    Delete
                                </button>
                            </div>
                        `;
                    },
                    className: 'text-center'
                }
            ]
        });

        // Edit entry function
        function editEntry(id) {
            fetch(`get_trial_balance.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalTitle').textContent = 'Edit Trial Balance Entry';
                        document.getElementById('entryId').value = data.entry.id;
                        document.getElementById('classification').value = data.entry.classification;
                        document.getElementById('category').value = data.entry.category;
                        document.getElementById('account_code_sap').value = data.entry.account_code_sap;
                        document.getElementById('description').value = data.entry.description;
                        document.getElementById('ending_balance').value = data.entry.ending_balance;
                        modal.style.display = 'block';
                    } else {
                        alert(data.message || 'Failed to load entry');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while loading the entry');
                });
        }

        // Delete entry function
        function deleteEntry(id) {
            if (confirm('Are you sure you want to delete this entry?')) {
                fetch('delete_trial_balance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        table.ajax.reload();
                    } else {
                        alert(data.message || 'Failed to delete entry');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the entry');
                });
            }
        }

        // Search functionality
        $('#searchBox').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Classifications and Categories data
        const classifications = [
            'Cash', 'Cash Total',
            'Trade and other receivables', 'Trade and other receivables Total',
            'Prepayments and other current assets', 'Prepayments and other current assets Total',
            'Inventories', 'Inventories Total',
            'Trade and other payables', 'Trade and other payables Total',
            'Deferred tax assets', 'Deferred tax assets Total',
            'Property and equipment – net', 'Property and equipment – net Total',
            'Income Tax Payable', 'Income Tax Payable Total',
            'Output VAT 12%-Goods Total',
            'RETIREMENT BENEFIT OBLIGATIONS', 'RETIREMENT BENEFIT OBLIGATIONS Total',
            'Share capital', 'Share capital Total',
            'Retained earnings', 'Retained earnings Total',
            'Revenues', 'Revenues Total',
            'Cost of sales and services', 'Cost of sales and services Total',
            'Marketing expenses', 'Marketing expenses Total',
            'Administrative expenses', 'Administrative expenses Total',
            'Income Tax Expense', 'Income Tax Expense Total',
            'Other income', 'Other income Total'
        ];

        const categoryMap = {
            'Cash': ['Cash on hand', 'Cash in banks', 'Cash Equivalents'],
            'Trade and other receivables': ['Outside parties', 'Other Receivable', 'Factory receivables'],
            'Prepayments and other current assets': ['Advances to suppliers', 'Advances to officers and employees', 'Insurance and warranty claims'],
            'Inventories': ['Passenger cars', 'Commercial vehicle', 'Parts, accessories and supplies'],
            'Trade and other payables': ['Customer deposit', 'Government payables', 'Output Vat Payable', 'Withholding tax payable', 'Accrued expenses'],
            'Property and equipment – net': ['Land', 'Building and improvements', 'Machineries and tools', 'Transportation equipment', 'Computer equipment and peripherals', 'Office equipment', 'Accumulated Depreciation', 'Construction in Progress']
        };

        // Populate classification dropdown
        const classificationSelect = document.getElementById('classification');
        classifications.forEach(classification => {
            const option = new Option(classification, classification);
            classificationSelect.add(option);
        });

        // Update category dropdown based on classification
        classificationSelect.addEventListener('change', function() {
            const categorySelect = document.getElementById('category');
            categorySelect.innerHTML = '<option value="">Select Category</option>'; // Reset with default option
            
            if (this.value.includes('Total')) {
                categorySelect.disabled = true;
                categorySelect.required = false;
            } else {
                categorySelect.disabled = false;
                categorySelect.required = true;
                const categories = categoryMap[this.value] || [];
                categories.forEach(category => {
                    const option = new Option(category, category);
                    categorySelect.add(option);
                });
            }
        });
    </script>
</body>
</html>
