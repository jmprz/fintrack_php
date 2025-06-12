<?php
session_start();
require_once 'connection.php';

// Check if the user is logged in and verified
if (!isset($_SESSION['user_id'])) {
    // User is not logged in
    header("Location: login.php");
    exit();
}

// Check if company is selected
if (!isset($_SESSION['selected_company_id'])) {
    header("Location: select_company_message.php");
    exit();
}

// Fetch user details from DB
$user_id = $_SESSION['user_id'];
$stmt = $con->prepare("SELECT first_name, last_name FROM users WHERE user_id = ? AND is_verified = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Not found or not verified
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();
$full_name = $user['first_name'] . ' ' . $user['last_name'];

// Get current month and year
$current_month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$current_year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Get search term if any
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get sort column and direction
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$sort_direction = isset($_GET['direction']) ? $_GET['direction'] : 'ASC';

// Fetch companies for the user
$companies_stmt = $con->prepare("
    SELECT c.* 
    FROM companies c 
    JOIN user_companies uc ON c.company_id = uc.company_id 
    WHERE uc.user_id = ?
    ORDER BY c.company_name
");
$companies_stmt->bind_param("i", $user_id);
$companies_stmt->execute();
$companies_result = $companies_stmt->get_result();

// Fetch expense categories
$categories = [
    'PROF FEE', 'DONATION', 'VEHICLE', 'SUPPLIES', 'EMPLOYEES BENEFIT',
    'MEDICAL SUPPLIES', 'BONUS', 'TAXES AND LICENSES', 'MATERIALS',
    'COM/LIGHT/WATER', 'REP & MAINTENANCE', 'OTHER EXPENSES', 'EQUIPMENT',
    'CA', 'TRAINORS FEE', 'CONSTRUCTION FEE', 'CONSTRUCTION MATERIALS',
    'MEALS', 'TRANSPORTATION', 'FUEL AND OIL', 'DIRECTORS FEE',
    'TUTORIAL FEE', 'GIFTS', 'SALARY', 'ALLOWANCE', 'SSS/HMDF/PHEALTH',
    'SERVICE', 'UNIFORM', 'LOAN AMORTIZATION'
];

// Fetch monthly totals for summary
$monthly_totals = [];
$category_totals = [];

$stmt->close();
$con->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/x-icon" href="img/favicon.ico" />
  <title>FinTrack | Expenses</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" rel="stylesheet" type="text/css" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
  <link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
  <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
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
          <li><a href="expenses.php" class="block py-2 px-3 rounded bg-[#e4fbeaff] text-[#1bb34cff] font-semibold">Expenses</a></li>
          <li><a href="sales.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Sales</a></li>
          <li><a href="trial_balance.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Trial Balance</a></li>
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
          <h1 class="text-5xl font-semibold text-gray-800">Expenses</h1>
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
          <!-- Month/Year Selector -->
          <div class="flex gap-4">
            <select id="monthSelect" class="rounded border p-2">
              <?php
              $months = [
                1 => 'January', 2 => 'February', 3 => 'March',
                4 => 'April', 5 => 'May', 6 => 'June',
                7 => 'July', 8 => 'August', 9 => 'September',
                10 => 'October', 11 => 'November', 12 => 'December'
              ];
              foreach ($months as $num => $name) {
                $selected = $num === $current_month ? 'selected' : '';
                echo "<option value='$num' $selected>$name</option>";
              }
              ?>
            </select>
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
            <input type="text" id="searchBox" placeholder="Search Category" 
                   class="w-full rounded border p-2" value="<?php echo htmlspecialchars($search); ?>">
          </div>

          <!-- Add New Expense Button -->
          <div class="flex gap-4">
            <button id="addExpenseBtn" class="bg-[#1bb34cff] text-white px-4 py-2 rounded hover:bg-[#158f3cff] disabled:opacity-50 disabled:cursor-not-allowed">
              Add New Expense
            </button>
            <button id="viewYearlySummaryBtn" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
              View Yearly Summary
            </button>
          </div>
        </div>
        <!-- No Categories Warning -->
        <div id="noCategoriesWarning" class="hidden mt-4 p-4 bg-yellow-50 text-yellow-800 rounded-md">
          <p>No expense categories found. Please add categories in your <a href="profile.php" class="underline">profile page</a> first.</p>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-4">
          <h3 class="text-lg font-semibold text-gray-700">Total Expenses</h3>
          <p class="text-2xl font-bold text-[#1bb34cff]"><span id="totalExpenses">₱0.00</span></p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4">
          <h3 class="text-lg font-semibold text-gray-700">Total Expenses (Without Loan & Uniform)</h3>
          <p class="text-2xl font-bold text-[#1bb34cff]"><span id="totalExpensesWithoutLoan">₱0.00</span></p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-4">
          <h3 class="text-lg font-semibold text-gray-700">Selected Account Total</h3>
          <p class="text-2xl font-bold text-[#1bb34cff]"><span id="selectedAccountTotal">₱0.00</span></p>
        </div>
      </div>

      <!-- Expenses Table -->
      <div class="p-4 bg-white rounded-lg shadow-md overflow-hidden p-4">
        <div class="overflow-x-auto">
          <table id="expensesTable" class="min-w-full">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Particulars</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <!-- Data will be populated by DataTables -->
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add/Edit Expense Modal -->
      <div id="expenseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
        <div class="flex items-center justify-center min-h-screen">
          <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h2 id="modalTitle" class="text-2xl font-bold mb-4">Add New Expense</h2>
            <form id="expenseForm">
              <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="date">
                  Date
                </label>
                <input type="date" id="date" name="date" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
              </div>
              <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="particulars">
                  Particulars
                </label>
                <input type="text" id="particulars" name="particulars" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
              </div>
              <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="category">
                  Account Title
                </label>
                <select id="category" name="category" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                  <option value="">Select a category...</option>
                </select>
              </div>
              <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="amount">
                  Amount
                </label>
                <input type="number" id="amount" name="amount" step="0.01" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
              </div>
              <div class="flex items-center justify-between">
                <button type="submit" class="bg-[#1bb34cff] text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hover:bg-[#158f3cff]">
                  Save Expense
                </button>
                <button type="button" onclick="closeModal()" class="bg-gray-500 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hover:bg-gray-600">
                  Cancel
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Yearly Summary Modal -->
      <div id="yearlySummaryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
        <div class="flex items-center justify-end min-h-screen p-4">
          <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-6xl max-h-[80vh] overflow-auto">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-2xl font-bold">Yearly Expenses Summary (<span id="summaryYear"></span>)</h2>
              <button onclick="closeYearlySummaryModal()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <div id="yearlySummaryContent" class="overflow-x-auto">
              <!-- Content will be loaded dynamically -->
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- JavaScript -->
  <script>
    // Initialize DataTable
    $(document).ready(function() {
      // Load categories and update UI
      loadCategories();

      const table = $('#expensesTable').DataTable({
        ajax: {
          url: 'expensesActions/get_expenses.php',
          data: function(d) {
            d.month = $('#monthSelect').val();
            d.year = $('#yearSelect').val();
            d.category_search = $('#searchBox').val();
            d.company_id = $('#companySelect').val();
          },
          dataSrc: function(json) {
            // Calculate and update selected account total from the filtered data
            const total = json.data.reduce((sum, row) => sum + parseFloat(row.amount), 0);
            $('#selectedAccountTotal').text('₱' + total.toLocaleString('en-US', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            }));
            return json.data;
          }
        },
        columns: [
          { data: 'date' },
          { data: 'particulars' },
          { data: 'category' },
          { 
            data: 'amount',
            render: function(data) {
              return '₱' + parseFloat(data).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
              });
            }
          },
          {
            data: null,
            render: function(data, type, row) {
              return `
                <button onclick="editExpense(${row.id})" class="text-blue-600 hover:text-blue-800 mr-2">
                  Edit
                </button>
                <button onclick="deleteExpense(${row.id})" class="text-red-600 hover:text-red-800">
                  Delete
                </button>
              `;
            }
          }
        ],
        processing: true,
        serverSide: true,
        order: [[0, 'desc']],
        pageLength: 200,
        lengthMenu: [[50, 100, 200, -1], [50, 100, 200, "All"]],
        responsive: true,
        searching: false // We'll handle search manually
      });

      // Handle month/year change
      $('#monthSelect, #yearSelect').change(function() {
        updateSummaryCards();
        table.ajax.reload();
      });

      // Handle category search
      $('#searchBox').on('keyup', function() {
        table.ajax.reload();
      });

      // Handle company change
      $('#companySelect').change(function() {
        const companyId = $(this).val();
        
        // Update session via AJAX
        $.post('company_selector.php', { company_id: companyId })
          .done(function(response) {
            if (response.success) {
              // Reload the table with new company data
              table.ajax.reload();
              // Update summary cards
              updateSummaryCards();
            }
          });
      });

      // Function to update summary cards
      function updateSummaryCards() {
        const month = $('#monthSelect').val();
        const year = $('#yearSelect').val();
        const search = $('#searchBox').val();

        console.log('Fetching summary data for:', { month, year, search });

        fetch(`expensesActions/get_summary.php?month=${month}&year=${year}&category_search=${encodeURIComponent(search)}`)
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.json();
          })
          .then(data => {
            console.log('Summary data received:', data);
            
            if (!data.success) {
              console.error('Error from server:', data.message);
              return;
            }

            const total = parseFloat(data.total) || 0;
            const totalWithoutLoan = parseFloat(data.total_without_loan) || 0;

            console.log('Parsed values:', { total, totalWithoutLoan });
            
            $('#totalExpenses').text('₱' + total.toLocaleString('en-US', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            }));
            $('#totalExpensesWithoutLoan').text('₱' + totalWithoutLoan.toLocaleString('en-US', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            }));
          })
          .catch(error => {
            console.error('Error updating summary cards:', error);
            $('#totalExpenses').text('₱0.00');
            $('#totalExpensesWithoutLoan').text('₱0.00');
          });
      }

      // Initial update of summary cards
      updateSummaryCards();
    });

    // Modal Functions
    function resetForm() {
      const form = document.getElementById('expenseForm');
      form.reset();
      delete form.dataset.id;
      document.getElementById('modalTitle').textContent = 'Add New Expense';
      loadCategories(); // Reload categories when form is reset
    }

    function openModal() {
      resetForm();
      document.getElementById('expenseModal').classList.remove('hidden');
    }

    function closeModal() {
      document.getElementById('expenseModal').classList.add('hidden');
      resetForm();
    }

    // Load categories function
    function loadCategories() {
      fetch('expensesActions/get_expense_categories.php')
        .then(response => response.json())
        .then(response => {
          const categories = response.data || [];
          const categorySelect = document.getElementById('category');
          const addExpenseBtn = document.getElementById('addExpenseBtn');
          const noCategoriesWarning = document.getElementById('noCategoriesWarning');
          
          // Clear existing options
          categorySelect.innerHTML = '<option value="">Select a category...</option>';
          
          if (categories.length === 0) {
            // Disable add expense button and show warning
            addExpenseBtn.disabled = true;
            noCategoriesWarning.classList.remove('hidden');
          } else {
            // Enable add expense button and hide warning
            addExpenseBtn.disabled = false;
            noCategoriesWarning.classList.add('hidden');
            
            // Add new options
            categories.forEach(category => {
              const option = document.createElement('option');
              option.value = category;
              option.textContent = category;
              categorySelect.appendChild(option);
            });
          }
        })
        .catch(error => {
          console.error('Error loading categories:', error);
          // Show error state
          addExpenseBtn.disabled = true;
          noCategoriesWarning.classList.remove('hidden');
          noCategoriesWarning.innerHTML = 'Error loading categories. Please try again later.';
        });
    }

    // Add New Expense Button
    document.getElementById('addExpenseBtn').addEventListener('click', function() {
      if (!this.disabled) {
        openModal();
      }
    });

    // Mobile Menu Toggle
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

    // CRUD Functions
    function editExpense(id) {
      // Fetch expense details and open modal
      fetch(`expensesActions/get_expense.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
          document.getElementById('modalTitle').textContent = 'Edit Expense';
          document.getElementById('date').value = data.date;
          document.getElementById('particulars').value = data.particulars;
          document.getElementById('category').value = data.category;
          document.getElementById('amount').value = data.amount;
          document.getElementById('expenseForm').dataset.id = id;
          document.getElementById('expenseModal').classList.remove('hidden');
        });
    }

    function deleteExpense(id) {
      if (confirm('Are you sure you want to delete this expense?')) {
        fetch(`expensesActions/delete_expense.php?id=${id}`, { method: 'DELETE' })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              $('#expensesTable').DataTable().ajax.reload();
            } else {
              alert('Error deleting expense');
            }
          });
      }
    }

    // Form Submission
    document.getElementById('expenseForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      const id = this.dataset.id;
      
      fetch(id ? `expensesActions/update_expense.php?id=${id}` : 'expensesActions/add_expense.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          closeModal();
          $('#expensesTable').DataTable().ajax.reload();
          this.reset();
          delete this.dataset.id;
        } else {
          alert('Error saving expense');
        }
      });
    });

    function openYearlySummaryModal() {
      const year = $('#yearSelect').val();
      const companyName = <?php echo json_encode(isset($_SESSION['selected_company_name']) ? $_SESSION['selected_company_name'] : ''); ?>;
      $('#summaryYear').text(year + (companyName ? ' - ' + companyName : ''));
      
      fetch(`expensesActions/get_yearly_summary.php?year=${year}`)
        .then(response => response.json())
        .then(data => {
          const content = document.getElementById('yearlySummaryContent');
          
          // Create table HTML
          let tableHtml = `
            <table class="min-w-full bg-white border border-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 border-b text-left">Account Title</th>
                  <th class="px-6 py-3 border-b text-right">Jan</th>
                  <th class="px-6 py-3 border-b text-right">Feb</th>
                  <th class="px-6 py-3 border-b text-right">Mar</th>
                  <th class="px-6 py-3 border-b text-right">Apr</th>
                  <th class="px-6 py-3 border-b text-right">May</th>
                  <th class="px-6 py-3 border-b text-right">Jun</th>
                  <th class="px-6 py-3 border-b text-right">Jul</th>
                  <th class="px-6 py-3 border-b text-right">Aug</th>
                  <th class="px-6 py-3 border-b text-right">Sep</th>
                  <th class="px-6 py-3 border-b text-right">Oct</th>
                  <th class="px-6 py-3 border-b text-right">Nov</th>
                  <th class="px-6 py-3 border-b text-right">Dec</th>
                  <th class="px-6 py-3 border-b text-right">Total</th>
                </tr>
              </thead>
              <tbody>`;

          // Add rows for each account title
          data.forEach(row => {
            tableHtml += `
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-3 border-b">${row.account_title}</td>
                ${row.monthly_totals.map(amount => `
                  <td class="px-6 py-3 border-b text-right">₱${parseFloat(amount).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                  })}</td>
                `).join('')}
                <td class="px-6 py-3 border-b text-right font-bold">₱${parseFloat(row.total).toLocaleString('en-US', {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2
                })}</td>
              </tr>`;
          });

          // Add total row
          const totals = data.reduce((acc, row) => {
            row.monthly_totals.forEach((amount, i) => {
              acc[i] = (acc[i] || 0) + parseFloat(amount);
            });
            return acc;
          }, []);

          const grandTotal = totals.reduce((a, b) => a + b, 0);

          tableHtml += `
              <tr class="bg-gray-100 font-bold">
                <td class="px-6 py-3 border-b">TOTAL</td>
                ${totals.map(total => `
                  <td class="px-6 py-3 border-b text-right">₱${total.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                  })}</td>
                `).join('')}
                <td class="px-6 py-3 border-b text-right">₱${grandTotal.toLocaleString('en-US', {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2
                })}</td>
              </tr>
            </tbody>
          </table>`;

          content.innerHTML = tableHtml;
        });

      document.getElementById('yearlySummaryModal').classList.remove('hidden');
    }

    function closeYearlySummaryModal() {
      document.getElementById('yearlySummaryModal').classList.add('hidden');
    }

    document.getElementById('viewYearlySummaryBtn').addEventListener('click', openYearlySummaryModal);
  </script>
</body>
</html>
