<?php
require_once '../../connection.php';
global $con;

// Set page title and any additional head content
$page_title = "Sales";
$viewing_company_id = isset($_SESSION['viewing_company_id']) ? $_SESSION['viewing_company_id'] : 'null';
$additional_head = '
<link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
';

// Start output buffering
ob_start();
?>

<!-- Page specific content -->
<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex flex-wrap gap-4 justify-between items-center mb-6">
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
                $current_month = date('n');
                foreach ($months as $num => $name) {
                    $selected = $num === $current_month ? 'selected' : '';
                    echo "<option value='$num' $selected>$name</option>";
                }
                ?>
            </select>
            <select id="yearSelect" class="rounded border p-2">
                <?php
                $current_year = date('Y');
                $start_year = 2020;
                $end_year = $current_year + 1;
                for ($year = $start_year; $year <= $end_year; $year++) {
                    $selected = $year === $current_year ? 'selected' : '';
                    echo "<option value='$year' $selected>$year</option>";
                }
                ?>
            </select>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="overflow-x-auto">
        <table id="salesTable" class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Particulars</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<?php
// Get the buffered content
$content = ob_get_clean();

// Additional scripts
$additional_scripts = '
$(document).ready(function() {
    var salesTable = $("#salesTable").DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "../../adminActions/get_employee_sales.php",
            "type": "GET",
            "data": function(d) {
                d.month = $("#monthSelect").val();
                d.year = $("#yearSelect").val();
            }
        },
        "columns": [
            { "data": "date" },
            { "data": "particulars" },
            { "data": "category" },
            { 
                "data": "amount",
                "render": function(data, type, row) {
                    return parseFloat(data).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
        ],
        "order": [[0, "desc"]], // Sort by date by default
        "pageLength": 10,
        "language": {
            "search": "Search sales:"
        },
        "dom": \'<"top"f>rt<"bottom"ip>\', // Simplified controls
        "scrollX": false // Prevent horizontal scroll
    });

    // Handle month/year change
    $("#monthSelect, #yearSelect").change(function() {
        salesTable.ajax.reload();
    });

    // Style the DataTables elements
    $(".dataTables_filter input").addClass("border rounded-md py-1 px-2 text-sm");
    $(".dataTables_length select").addClass("border rounded-md py-1 px-2 text-sm");
});
';

// Include the layout
require_once 'employee_view_layout.php';
?> 