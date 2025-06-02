<?php
session_start();
require_once 'connection.php';

// Check if the user is logged in and verified
if (!isset($_SESSION['user_id'])) {
    // User is not logged in
    header("Location: login.php");
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

$stmt->close();
$con->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/x-icon" href="img/favicon.ico" />
  <title>FinTrack | Trial Balance</title>
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

    <div class="overflow-x-auto bg-white rounded shadow-md">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-gray-200 text-gray-700 uppercase">
          <tr>
            <th class="py-3 px-4 border">Company Name</th>
            <th class="py-3 px-4 border text-right">Debit</th>
            <th class="py-3 px-4 border text-right">Credit</th>
          </tr>
        </thead>
        <tbody>
          <!-- Example row -->
          <tr class="border-b hover:bg-gray-50">
            <td class="py-2 px-4">Cash</td>
            <td class="py-2 px-4 text-right">₱10,000.00</td>
            <td class="py-2 px-4 text-right"></td>
          </tr>
          <tr class="border-b hover:bg-gray-50">
            <td class="py-2 px-4">Revenue</td>
            <td class="py-2 px-4 text-right"></td>
            <td class="py-2 px-4 text-right">₱5,000.00</td>
          </tr>
          <!-- Add more rows dynamically -->
        </tbody>
        <tfoot class="bg-gray-200 font-semibold text-gray-900">
          <tr>
            <td class="py-3 px-4 border text-right">Total</td>
            <td class="py-3 px-4 border text-right">₱10,000.00</td>
            <td class="py-3 px-4 border text-right">₱5,000.00</td>
          </tr>
        </tfoot>
      </table>
    </div>
    </main>
  </div>

  <!-- JavaScript -->
  <script>
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
