<?php
require_once '../../connection.php';
global $con;

// Set page title and any additional head content
$page_title = "Income Statements";
$viewing_company_id = isset($_SESSION['viewing_company_id']) ? $_SESSION['viewing_company_id'] : 'null';
$additional_head = '
<link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<style>
    .negative-amount { color: red; }
    .calculated-row { font-weight: bold; }
</style>
';

// Start output buffering
ob_start();
?>

<!-- Page specific content -->
<div id="printableArea" class="bg-white rounded-lg shadow-md p-6">
    <div class="flex flex-wrap gap-4 justify-between items-center mb-6">
        <!-- Year Selector -->
        <div class="flex gap-4">
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

    <!-- Income Statement Table -->
    <div class="overflow-x-auto">
        <table id="incomeStatementTable" class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-1/2">Notes</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase w-1/4" id="currentYearHeader"></th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase w-1/4" id="lastYearHeader"></th>
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
    function formatAmount(amount, isPercentage = false) {
        if (amount === null || amount === "") return "-";
        
        const num = parseFloat(amount);
        if (isNaN(num)) return "-";
        
        if (isPercentage) {
            const formatted = Math.abs(num).toFixed(2);
            return num < 0 ? 
                `<span class="negative-amount">(${formatted}%)</span>` : 
                `${formatted}%`;
        } else {
            const formatted = Math.abs(num).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, "$&,");
            return num < 0 ? 
                `<span class="negative-amount">(₱${formatted})</span>` : 
                `₱${formatted}`;
        }
    }

    function updateTableHeaders() {
        const currentYear = $("#yearSelect").val();
        const lastYear = parseInt(currentYear) - 1;
        $("#currentYearHeader").text(currentYear);
        $("#lastYearHeader").text(lastYear);
    }

    var incomeStatementTable = $("#incomeStatementTable").DataTable({
        processing: true,
        serverSide: false,
        paging: false,
        searching: false,
        ordering: false,
        info: false,
        ajax: {
            url: "../../adminActions/get_employee_income_statement.php",
            type: "POST",
            data: function(d) {
                d.year = $("#yearSelect").val();
            }
        },
        columns: [
            { 
                data: "notes",
                className: "px-4 py-2",
                render: function(data, type, row) {
                    return row.is_calculated ? 
                        `<span class="font-bold">${data}</span>` : 
                        data;
                }
            },
            { 
                data: "current_year",
                className: "text-right px-4 py-2",
                render: function(data, type, row) {
                    return formatAmount(data, row.is_percentage);
                }
            },
            { 
                data: "last_year",
                className: "text-right px-4 py-2",
                render: function(data, type, row) {
                    return formatAmount(data, row.is_percentage);
                }
            }
        ],
        drawCallback: function() {
            updateTableHeaders();
        }
    });

    // Year selection change handler
    $("#yearSelect").change(function() {
        incomeStatementTable.ajax.reload();
    });

    // Initial header update
    updateTableHeaders();
});
';

// Include the layout
require_once 'employee_view_layout.php';
?> 