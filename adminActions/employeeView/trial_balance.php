<?php
require_once '../../connection.php';
global $con;

// Set page title and any additional head content
$page_title = "Trial Balance";
$viewing_company_id = isset($_SESSION['viewing_company_id']) ? $_SESSION['viewing_company_id'] : 'null';
$additional_head = '
<link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<style>
    .negative-amount { color: red; }
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
';

// Start output buffering
ob_start();
?>

<!-- Page specific content -->
<div class="bg-white rounded-lg shadow-md p-6">
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

        <!-- View Totals Button -->
        <button id="viewTotalsBtn" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
            View Totals
        </button>
    </div>

    <!-- Trial Balance Table -->
    <div class="overflow-x-auto">
        <table id="trialBalanceTable" class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Classification</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account Code</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ending Balance</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- View Totals Modal -->
<div id="totalsModal" class="modal">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold">Trial Balance Totals</h2>
            <span class="close">&times;</span>
        </div>
        
        <div class="overflow-y-auto max-h-[70vh]">
            <table id="totalsTable" class="min-w-full text-sm">
                <thead class="bg-gray-200 text-gray-700 uppercase sticky top-0">
                    <tr>
                        <th class="px-4 py-2">Classification</th>
                        <th class="px-4 py-2 text-right">Total Amount</th>
                    </tr>
                </thead>
                <tbody id="totalsTableBody">
                    <!-- Totals will be populated here -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Get the buffered content
$content = ob_get_clean();

// Additional scripts
$additional_scripts = '
$(document).ready(function() {
    function formatAmount(amount) {
        const numAmount = parseFloat(amount);
        const formatted = Math.abs(numAmount).toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return `<span class="${numAmount < 0 ? \'negative-amount\' : \'\'}">${formatted}</span>`;
    }

    var trialBalanceTable = $("#trialBalanceTable").DataTable({
        "processing": true,
        "serverSide": false,
        "ajax": {
            "url": "../../adminActions/get_employee_trial_balance.php",
            "type": "POST",
            "data": function(d) {
                d.year = $("#yearSelect").val();
            }
        },
        "columns": [
            { "data": "classification" },
            { 
                "data": "category",
                "render": function(data, type, row) {
                    return data || "-";
                }
            },
            { "data": "account_code_sap" },
            { "data": "description" },
            { 
                "data": "ending_balance",
                "render": function(data, type, row) {
                    if (type === "display") {
                        return formatAmount(data);
                    }
                    return data;
                }
            }
        ],
        "order": [[0, "asc"], [1, "asc"]],
        "pageLength": 25,
        "dom": \'<"top"f>rt<"bottom"ip>\',
        "language": {
            "search": "Search entries:"
        }
    });

    // Handle year change
    $("#yearSelect").change(function() {
        trialBalanceTable.ajax.reload();
    });

    // View Totals Button Handler
    $("#viewTotalsBtn").click(function() {
        const data = trialBalanceTable.data();
        let totals = {
            assets: 0,
            liabilities: 0,
            equity: 0,
            revenue: 0,
            expenses: 0
        };

        data.each(function(row) {
            const amount = parseFloat(row.ending_balance);
            switch(row.classification.toLowerCase()) {
                case "assets":
                    totals.assets += amount;
                    break;
                case "liabilities":
                    totals.liabilities += amount;
                    break;
                case "equity":
                    totals.equity += amount;
                    break;
                case "revenue":
                    totals.revenue += amount;
                    break;
                case "expenses":
                    totals.expenses += amount;
                    break;
            }
        });

        let html = "";
        for (const [classification, amount] of Object.entries(totals)) {
            if (amount !== 0) {
                html += `
                    <tr>
                        <td class="px-4 py-2 border-t">${classification.charAt(0).toUpperCase() + classification.slice(1)}</td>
                        <td class="px-4 py-2 border-t text-right ${amount < 0 ? \'negative-amount\' : \'\'}">${formatAmount(amount)}</td>
                    </tr>
                `;
            }
        }
        
        $("#totalsTableBody").html(html);
        $("#totalsModal").show();
    });

    // Modal Close Button Handler
    $(".close").click(function() {
        $(this).closest(".modal").hide();
    });

    // Close Modal When Clicking Outside
    $(window).click(function(event) {
        if ($(event.target).hasClass("modal")) {
            $(".modal").hide();
        }
    });

    // Style the DataTables elements
    $(".dataTables_filter input").addClass("border rounded-md py-1 px-2 text-sm");
    $(".dataTables_length select").addClass("border rounded-md py-1 px-2 text-sm");
});
';

// Include the layout
require_once 'employee_view_layout.php';
?> 