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
  <title>FinTrack | Balance Sheet</title>
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
          <li><a href="balance_sheet.php" class="block py-2 px-3 rounded bg-[#e4fbeaff] text-[#1bb34cff] font-semibold">Balance Sheet</a></li>
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
          <h1 class="text-5xl font-semibold text-gray-800">Balance Sheet</h1>
          <?php if (isset($_SESSION['selected_company_name'])): ?>
            <p class="text-lg text-gray-600 mt-2">for <?php echo htmlspecialchars($_SESSION['selected_company_name']); ?></p>
          <?php endif; ?>
        </div>
        <button id="menuBtn" class="md:hidden px-4 py-2 bg-blue-200 text-white rounded">
          <div class="text-2xl font-bold text-blue-500">☰</div>
        </button>
      </header>

         <p class="mb-6 text-gray-600">As of December 31, 2025</p>

    <section class="mb-6">
      <h2 class="text-2xl font-semibold mb-3">Assets</h2>
      <ul>
        <li class="flex justify-between border-b py-2">
          <span>Cash</span>
          <span>₱10,000.00</span>
        </li>
        <li class="flex justify-between border-b py-2">
          <span>Equipment</span>
          <span>₱15,000.00</span>
        </li>
        <!-- More assets -->
      </ul>
    </section>

    <section class="mb-6">
      <h2 class="text-2xl font-semibold mb-3">Liabilities</h2>
      <ul>
        <li class="flex justify-between border-b py-2">
          <span>Accounts Payable</span>
          <span>₱2,000.00</span>
        </li>
        <!-- More liabilities -->
      </ul>
    </section>

    <section class="mb-6">
      <h2 class="text-2xl font-semibold mb-3">Equity</h2>
      <ul>
        <li class="flex justify-between border-b py-2">
          <span>Retained Earnings</span>
          <span>₱23,000.00</span>
        </li>
        <!-- More equity -->
      </ul>
    </section>

    <div class="flex justify-between border-t pt-4 font-bold text-lg">
      <span>Total Assets</span>
      <span>₱25,000.00</span>
    </div>
    <div class="flex justify-between font-bold text-lg">
      <span>Total Liabilities + Equity</span>
      <span>₱25,000.00</span>
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
