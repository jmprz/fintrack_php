<?php
session_start();
require_once 'connection.php';

// Check if the user is logged in and verified
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user details from DB
$user_id = $_SESSION['user_id'];
$stmt = $con->prepare("SELECT first_name, last_name, email_address, account_type FROM users WHERE user_id = ? AND is_verified = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: login.php");
    exit();
}

$user = $result->fetch_assoc();
$full_name = $user['first_name'] . ' ' . $user['last_name'];

// Fetch companies associated with the user
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

// Handle form submission for adding new company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_company') {
        $company_name = trim($_POST['company_name']);
        $address = trim($_POST['address']);
        $contact_number = trim($_POST['contact_number']);

        // Insert new company
        $insert_company = $con->prepare("INSERT INTO companies (company_name, address, contact_number) VALUES (?, ?, ?)");
        $insert_company->bind_param("sss", $company_name, $address, $contact_number);
        
        if ($insert_company->execute()) {
            $company_id = $con->insert_id;
            
            // Associate company with user
            $insert_user_company = $con->prepare("INSERT INTO user_companies (user_id, company_id) VALUES (?, ?)");
            $insert_user_company->bind_param("ii", $user_id, $company_id);
            $insert_user_company->execute();
            
            header("Location: profile.php");
            exit();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FinTrack | Profile</title>
    <link rel="icon" type="image/x-icon" href="img/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <li><a href="income_statements.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Income Statements</a></li>
                    <li><a href="balance_sheet.php" class="block py-2 px-3 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Balance Sheet</a></li>
                    <li><a href="profile.php" class="block py-2 px-3 rounded bg-[#e4fbeaff] text-[#1bb34cff] font-semibold">Profile</a></li>
                </ul>
            </nav>
            <a href="logout.php" class="block py-2 px-8 rounded hover:bg-[#e4fbeaff] hover:text-[#1bb34cff]">Logout</a>
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
                        <p class="font-semibold"><?php echo htmlspecialchars($full_name); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Email</p>
                        <p class="font-semibold"><?php echo htmlspecialchars($user['email_address']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Account Type</p>
                        <p class="font-semibold capitalize"><?php echo htmlspecialchars($user['account_type']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Companies Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-semibold">Companies</h2>
                    <button onclick="openAddCompanyModal()" class="bg-[#1bb34cff] text-white px-4 py-2 rounded hover:bg-[#158f3cff]">
                        Add New Company
                    </button>
                </div>

                <!-- Company Selection -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Select Working Company</h3>
                    <div class="space-y-3">
                        <?php
                        // Fetch companies for the user
                        $companies_query = "SELECT c.* 
                            FROM companies c 
                            JOIN user_companies uc ON c.company_id = uc.company_id 
                            WHERE uc.user_id = ?
                            ORDER BY c.company_name";
                        
                        $stmt = $con->prepare($companies_query);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $companies_result = $stmt->get_result();

                        while ($company = $companies_result->fetch_assoc()): 
                            $isSelected = isset($_SESSION['selected_company_id']) && $_SESSION['selected_company_id'] == $company['company_id'];
                        ?>
                            <div class="flex items-center p-3 border rounded <?php echo $isSelected ? 'bg-[#e4fbeaff] border-[#1bb34cff]' : 'hover:bg-gray-50'; ?>">
                                <input type="radio" id="company_<?php echo $company['company_id']; ?>" 
                                       name="selected_company" 
                                       value="<?php echo $company['company_id']; ?>"
                                       <?php echo $isSelected ? 'checked' : ''; ?>
                                       class="mr-3"
                                       onchange="updateSelectedCompany(this.value)">
                                <label for="company_<?php echo $company['company_id']; ?>" class="flex-1 cursor-pointer">
                                    <div class="font-semibold"><?php echo htmlspecialchars($company['company_name']); ?></div>
                                    <div class="text-sm text-gray-600">
                                        <?php if ($company['address']): ?>
                                            <div><?php echo htmlspecialchars($company['address']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($company['contact_number']): ?>
                                            <div>Contact: <?php echo htmlspecialchars($company['contact_number']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <hr class="my-6">

                <!-- Companies List -->
                <div>
                    <h3 class="text-lg font-semibold mb-3">Manage Companies</h3>
                    <div class="space-y-3">
                        <?php 
                        // Reset result pointer
                        $companies_result->data_seek(0);
                        while ($company = $companies_result->fetch_assoc()): 
                        ?>
                            <div class="flex justify-between items-center p-3 border rounded hover:bg-gray-50">
                                <div>
                                    <div class="font-semibold"><?php echo htmlspecialchars($company['company_name']); ?></div>
                                    <div class="text-sm text-gray-600">
                                        <?php if ($company['address']): ?>
                                            <div><?php echo htmlspecialchars($company['address']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($company['contact_number']): ?>
                                            <div>Contact: <?php echo htmlspecialchars($company['contact_number']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <!-- Categories Management Group -->
                                    <div class="flex gap-2">
                                        <button onclick="openAccountTitlesModal(<?php echo $company['company_id']; ?>, 'expense', '<?php echo addslashes($company['company_name']); ?>')" 
                                                class="px-3 py-1 rounded border border-green-600 text-green-600 hover:bg-green-50">
                                                Expense Categories
                                        </button>
                                        <button onclick="openAccountTitlesModal(<?php echo $company['company_id']; ?>, 'sale', '<?php echo addslashes($company['company_name']); ?>')" 
                                                class="px-3 py-1 rounded border border-blue-600 text-blue-600 hover:bg-blue-50">
                                                Sales Categories
                                        </button>
                                    </div>
                                    <!-- Company Management Group -->
                                    <div class="flex gap-2 border-l pl-4">
                                        <button onclick="editCompany(<?php echo $company['company_id']; ?>)" 
                                                class="px-3 py-1 rounded bg-gray-100 text-gray-600 hover:bg-gray-200">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                        </button>
                                        <button onclick="deleteCompany(<?php echo $company['company_id']; ?>)" 
                                                class="px-3 py-1 rounded bg-gray-100 text-gray-600 hover:bg-gray-200">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Account Titles Modal -->
            <div id="accountTitlesModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-2xl">
                        <div class="flex justify-between items-center mb-4">
                            <h2 id="accountTitlesModalTitle" class="text-2xl font-bold"></h2>
                            <button onclick="closeAccountTitlesModal()" class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="mb-4 flex justify-between items-center">
                            <div id="selectedCompanyName" class="text-lg text-gray-600"></div>
                            <button onclick="openAddTitleModal()" 
                                    class="bg-[#1bb34cff] text-white px-4 py-2 rounded hover:bg-[#158f3cff] flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add New Category
                            </button>
                        </div>
                        <div id="accountTitlesList" class="space-y-2 max-h-[60vh] overflow-y-auto">
                            <!-- Account titles will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Title Modal -->
            <div id="titleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
                <div class="flex items-center justify-center min-h-screen">
                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                        <h2 id="titleModalHeader" class="text-2xl font-bold mb-4">Add New Category</h2>
                        <form id="titleForm" class="space-y-4">
                            <input type="hidden" id="title_id" name="title_id">
                            <input type="hidden" id="title_type" name="type">
                            <input type="hidden" id="company_id" name="company_id">
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="title_name">
                                    Category Name
                                </label>
                                <input type="text" id="title_name" name="title_name" required
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>

                            <div class="flex items-center justify-between">
                                <button type="submit" class="bg-[#1bb34cff] text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hover:bg-[#158f3cff]">
                                    Save Category
                                </button>
                                <button type="button" onclick="closeTitleModal()" class="bg-gray-500 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Add Company Modal -->
            <div id="addCompanyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
                <div class="flex items-center justify-center min-h-screen">
                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                        <h2 class="text-2xl font-bold mb-4">Add New Company</h2>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_company">
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="company_name">
                                    Company Name
                                </label>
                                <input type="text" id="company_name" name="company_name" required
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="address">
                                    Address
                                </label>
                                <textarea id="address" name="address" rows="3"
                                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="contact_number">
                                    Contact Number
                                </label>
                                <input type="text" id="contact_number" name="contact_number"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>

                            <div class="flex items-center justify-between">
                                <button type="submit" class="bg-[#1bb34cff] text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hover:bg-[#158f3cff]">
                                    Add Company
                                </button>
                                <button type="button" onclick="closeAddCompanyModal()" class="bg-gray-500 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Company Modal -->
            <div id="editCompanyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
                <div class="flex items-center justify-center min-h-screen">
                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                        <h2 class="text-2xl font-bold mb-4">Edit Company</h2>
                        <form id="editCompanyForm" class="space-y-4">
                            <input type="hidden" id="edit_company_id" name="company_id">
                            <input type="hidden" name="action" value="edit_company">
                            
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_company_name">
                                    Company Name
                                </label>
                                <input type="text" id="edit_company_name" name="company_name" required
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_address">
                                    Address
                                </label>
                                <textarea id="edit_address" name="address" rows="3"
                                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="edit_contact_number">
                                    Contact Number
                                </label>
                                <input type="text" id="edit_contact_number" name="contact_number"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>

                            <div class="flex items-center justify-between">
                                <button type="submit" class="bg-[#1bb34cff] text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hover:bg-[#158f3cff]">
                                    Save Changes
                                </button>
                                <button type="button" onclick="closeEditCompanyModal()" class="bg-gray-500 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline hover:bg-gray-600">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
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

        // Modal Functions
        function openAddCompanyModal() {
            document.getElementById('addCompanyModal').classList.remove('hidden');
        }

        function closeAddCompanyModal() {
            document.getElementById('addCompanyModal').classList.add('hidden');
        }

        function updateSelectedCompany(companyId) {
            fetch('company_selector.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'company_id=' + companyId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Highlight the selected company
                    document.querySelectorAll('[name="selected_company"]').forEach(radio => {
                        const container = radio.closest('.flex');
                        if (radio.value === companyId) {
                            container.classList.add('bg-[#e4fbeaff]', 'border-[#1bb34cff]');
                        } else {
                            container.classList.remove('bg-[#e4fbeaff]', 'border-[#1bb34cff]');
                        }
                    });
                }
            });
        }

        function editCompany(companyId) {
            // Fetch company details
            fetch(`get_company.php?id=${companyId}`)
                .then(response => response.json())
                .then(company => {
                    document.getElementById('edit_company_id').value = company.company_id;
                    document.getElementById('edit_company_name').value = company.company_name;
                    document.getElementById('edit_address').value = company.address;
                    document.getElementById('edit_contact_number').value = company.contact_number;
                    document.getElementById('editCompanyModal').classList.remove('hidden');
                });
        }

        function closeEditCompanyModal() {
            document.getElementById('editCompanyModal').classList.add('hidden');
        }

        function deleteCompany(companyId) {
            if (confirm('Are you sure you want to delete this company? This action cannot be undone.')) {
                fetch('delete_company.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `company_id=${companyId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error deleting company: ' + data.message);
                    }
                });
            }
        }

        // Add submit handler for edit company form
        document.getElementById('editCompanyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('update_company.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error updating company: ' + data.message);
                }
            });
        });

        // Account Title Management Functions
        let currentCompanyId = null;
        let currentTitleType = null;

        function openAccountTitlesModal(companyId, type, companyName) {
            currentCompanyId = companyId;
            currentTitleType = type;
            
            // Update modal title and company name
            document.getElementById('accountTitlesModalTitle').textContent = 
                type === 'expense' ? 'Expense Categories' : 'Sales Categories';
            document.getElementById('selectedCompanyName').textContent = companyName;
            
            // Load account titles
            fetch(`get_company_titles.php?company_id=${companyId}&type=${type}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(response => {
                    console.log('Received response:', response); // Debug log
                    
                    if (!response.success) {
                        throw new Error(response.message || 'Failed to load categories');
                    }
                    
                    const titles = response.data;
                    const listContainer = document.getElementById('accountTitlesList');
                    listContainer.innerHTML = '';
                    
                    if (!Array.isArray(titles) || titles.length === 0) {
                        listContainer.innerHTML = '<div class="text-gray-500 text-center py-4">No categories found. Click "Add New Category" to create one.</div>';
                        return;
                    }
                    
                    titles.forEach(title => {
                        const titleElement = document.createElement('div');
                        titleElement.className = 'flex justify-between items-center p-3 bg-gray-50 rounded hover:bg-gray-100';
                        titleElement.innerHTML = `
                            <span class="font-medium">${title.title_name}</span>
                            <div class="flex gap-2">
                                <button onclick="editTitle(${title.title_id})" 
                                        class="px-2 py-1 rounded text-blue-600 hover:bg-blue-50 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit
                                </button>
                                <button onclick="deleteTitle(${title.title_id})" 
                                        class="px-2 py-1 rounded text-red-600 hover:bg-red-50 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Delete
                                </button>
                            </div>
                        `;
                        listContainer.appendChild(titleElement);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    const listContainer = document.getElementById('accountTitlesList');
                    listContainer.innerHTML = '<div class="text-red-500 text-center py-4">Error loading categories: ' + error.message + '</div>';
                });
            
            document.getElementById('accountTitlesModal').classList.remove('hidden');
        }

        function closeAccountTitlesModal() {
            document.getElementById('accountTitlesModal').classList.add('hidden');
            currentCompanyId = null;
            currentTitleType = null;
        }

        function openAddTitleModal() {
            document.getElementById('titleModalHeader').textContent = 'Add New Category';
            document.getElementById('title_id').value = '';
            document.getElementById('title_type').value = currentTitleType;
            document.getElementById('company_id').value = currentCompanyId;
            document.getElementById('title_name').value = '';
            document.getElementById('titleModal').classList.remove('hidden');
        }

        function closeTitleModal() {
            document.getElementById('titleModal').classList.add('hidden');
        }

        function editTitle(titleId) {
            fetch(`get_title.php?id=${titleId}`)
                .then(response => response.json())
                .then(title => {
                    document.getElementById('titleModalHeader').textContent = 'Edit Category';
                    document.getElementById('title_id').value = title.title_id;
                    document.getElementById('title_type').value = title.type;
                    document.getElementById('company_id').value = title.company_id;
                    document.getElementById('title_name').value = title.title_name;
                    document.getElementById('titleModal').classList.remove('hidden');
                });
        }

        function deleteTitle(titleId) {
            if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
                fetch('delete_title.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `title_id=${titleId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh the account titles list
                        openAccountTitlesModal(currentCompanyId, currentTitleType, document.getElementById('selectedCompanyName').textContent);
                    } else {
                        alert('Error deleting category: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the category.');
                });
            }
        }

        // Add submit handler for title form
        document.getElementById('titleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const titleId = formData.get('title_id');

            fetch(titleId ? 'update_title.php' : 'add_title.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeTitleModal();
                    // Refresh the account titles list
                    openAccountTitlesModal(currentCompanyId, currentTitleType, document.getElementById('selectedCompanyName').textContent);
                } else {
                    alert('Error saving category: ' + data.message);
                }
            });
        });
    </script>
</body>
</html>

<?php
// Add closing statements at the end of the PHP code
$stmt->close();
$companies_stmt->close();
$con->close();
?> 