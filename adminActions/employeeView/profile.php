<?php
// Set page title
$page_title = "Profile";

require_once '../../connection.php';
global $con;

// Get employee_id from URL parameter
if (!isset($_GET['employee_id'])) {
    die("Employee ID not provided");
}
$employee_id = intval($_GET['employee_id']);

// Get employee details
$stmt = $con->prepare("
    SELECT 
        u.*,
        GROUP_CONCAT(c.company_name) as companies
    FROM users u
    LEFT JOIN user_companies uc ON u.user_id = uc.user_id
    LEFT JOIN companies c ON uc.company_id = c.company_id
    WHERE u.user_id = ?
    GROUP BY u.user_id
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Employee not found");
}

$employee = $result->fetch_assoc();

// Start output buffering
ob_start();
?>

<!-- Profile Content -->
<div class="bg-white rounded-lg shadow-md p-6">
    <h2 class="text-2xl font-bold mb-4">Employee Profile</h2>
    
    <!-- Basic Information -->
    <div class="mb-6">
        <h3 class="text-lg font-semibold mb-2">Basic Information</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-gray-600">Name</p>
                <p class="font-medium"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
            </div>
            <div>
                <p class="text-gray-600">Email</p>
                <p class="font-medium"><?php echo htmlspecialchars($employee['email_address']); ?></p>
            </div>
            <div>
                <p class="text-gray-600">Account Type</p>
                <p class="font-medium"><?php echo htmlspecialchars($employee['account_type']); ?></p>
            </div>
            <div>
                <p class="text-gray-600">Companies</p>
                <p class="font-medium"><?php echo htmlspecialchars($employee['companies'] ?? 'None'); ?></p>
            </div>
            <div>
                <p class="text-gray-600">Created At</p>
                <p class="font-medium"><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></p>
            </div>
            <div>
                <p class="text-gray-600">Last Updated</p>
                <p class="font-medium"><?php echo date('M d, Y', strtotime($employee['updated_at'])); ?></p>
            </div>
        </div>
    </div>

    <!-- Activity Statistics -->
    <div class="mb-6">
        <h3 class="text-lg font-semibold mb-2">Activity Statistics</h3>
        <div class="grid grid-cols-2 gap-4">
            <?php
            // Get total expenses
            $expenses_stmt = $con->prepare("
                SELECT COUNT(*) as count, SUM(amount) as total
                FROM expenses e
                JOIN user_companies uc ON e.company_id = uc.company_id
                WHERE uc.user_id = ?
            ");
            $expenses_stmt->bind_param("i", $employee_id);
            $expenses_stmt->execute();
            $expenses_result = $expenses_stmt->get_result()->fetch_assoc();
            
            // Get total sales
            $sales_stmt = $con->prepare("
                SELECT COUNT(*) as count, SUM(amount) as total
                FROM sales s
                JOIN user_companies uc ON s.company_id = uc.company_id
                WHERE uc.user_id = ?
            ");
            $sales_stmt->bind_param("i", $employee_id);
            $sales_stmt->execute();
            $sales_result = $sales_stmt->get_result()->fetch_assoc();
            ?>
            <div>
                <p class="text-gray-600">Total Expenses Recorded</p>
                <p class="font-medium"><?php echo number_format($expenses_result['count']); ?></p>
                <p class="text-sm text-gray-500">Total Amount: ₱<?php echo number_format($expenses_result['total'] ?? 0, 2); ?></p>
            </div>
            <div>
                <p class="text-gray-600">Total Sales Recorded</p>
                <p class="font-medium"><?php echo number_format($sales_result['count']); ?></p>
                <p class="text-sm text-gray-500">Total Amount: ₱<?php echo number_format($sales_result['total'] ?? 0, 2); ?></p>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div>
        <h3 class="text-lg font-semibold mb-2">Recent Activity</h3>
        <?php
        // Get recent work history
        $history_stmt = $con->prepare("
            SELECT *
            FROM user_work_history
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $history_stmt->bind_param("i", $employee_id);
        $history_stmt->execute();
        $history_result = $history_stmt->get_result();
        
        if ($history_result->num_rows > 0):
        ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($activity = $history_result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($activity['action_type']))); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                    $details = json_decode($activity['details'], true);
                                    if ($details) {
                                        foreach ($details as $key => $value) {
                                            if (!is_array($value)) {
                                                echo htmlspecialchars(str_replace('_', ' ', ucfirst($key))) . ': ' . htmlspecialchars($value) . '<br>';
                                            }
                                        }
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo date('M d, Y H:i:s', strtotime($activity['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-500">No recent activity found.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// Get the buffered content
$content = ob_get_clean();

// Include the layout
require_once 'employee_view_layout.php';
?> 