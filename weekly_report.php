<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "config.php"; // contains $sqlsrv_pdo

// Function to get week dates from a given date
function getWeekDates($startDate) {
    $dates = [];
    $date = new DateTime($startDate);
    
    // Adjust to Monday if not already
    $dayOfWeek = $date->format('N'); // 1 (Monday) to 7 (Sunday)
    if ($dayOfWeek != 1) {
        $date->modify('-' . ($dayOfWeek - 1) . ' days');
    }
    
    for ($i = 0; $i < 7; $i++) {
        $dates[] = $date->format('Y-m-d');
        $date->modify('+1 day');
    }
    
    return $dates;
}

// Function to fetch data for a specific date
function fetchDayData($sqlsrv_pdo, $date) {
    $data = [
        'Loyalty' => 0.0,
        'Donations' => 0.0,
        'allOtherSales' => 0.0,
        'nonCashB' => 0.0,
        'CP' => 0.0,
        'cashSales' => 0.0,
        'currentFloat' => 0.0,
        'Lodge' => 0.0,
        'Difference' => 0.0,
        'prevFloatHeld' => 0.0,
        'zCount' => 0.0,
        'allSales' => 0.0,
        'cashOnHand' => 0.0
    ];
    
    try {
        // Check if there are records for this date
        $stmt = $sqlsrv_pdo->prepare("
            SELECT TOP 1 1 
            FROM CashDecHeader 
            WHERE CAST(dtTimeStamp AS DATE) = ?
        ");
        $stmt->execute([$date]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $data; // Return zeros if no data
        }
    } catch (PDOException $e) {
        error_log("Date check failed for $date: " . $e->getMessage());
        return $data;
    }
    
    // Loyalty
    try {
        $stmt = $sqlsrv_pdo->prepare("
            SELECT SUM(cdl.TillTotal) AS Loyalty
            FROM CashDecLines cdl
            INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
            WHERE CAST(cdh.dtTimeStamp AS DATE) = ?
              AND cdl.PaymentNo = '10'
        ");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $data['Loyalty'] = (float)($row['Loyalty'] ?? 0);
    } catch (PDOException $e) {
        error_log("Loyalty query failed for $date: " . $e->getMessage());
    }
    
    // Donations
    try {
        $stmt = $sqlsrv_pdo->prepare("
            SELECT SUM(SN_Actual) AS Donations 
            FROM SALES 
            WHERE PostedDate = CONCAT(?, ' 00:00:00') 
              AND SN_ITEM = 'DONAT01'
            GROUP BY SN_ITEM
        ");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $data['Donations'] = (float)($row['Donations'] ?? 0);
    } catch (PDOException $e) {
        error_log("Donations query failed for $date: " . $e->getMessage());
    }
    
    // Previous Float
    try {
        $stmt = $sqlsrv_pdo->prepare("
            SELECT TOP 1 SUM(cdl.FloatHeld) AS PrevFloatHeld
            FROM CashDecLines cdl
            INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
            WHERE CAST(cdh.dtTimeStamp AS DATE) < ?
              AND cdl.PaymentNo = '01'
            GROUP BY CAST(cdh.dtTimeStamp AS DATE)
            ORDER BY CAST(cdh.dtTimeStamp AS DATE) DESC
        ");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['prevFloatHeld'] = $row ? (float)($row['PrevFloatHeld'] ?? 0) : 0;
    } catch (PDOException $e) {
        error_log("Previous Float query failed for $date: " . $e->getMessage());
    }
    
    // Credit Cards (allOtherSales)
    try {
        $stmt = $sqlsrv_pdo->prepare("
            SELECT SUM(cdl.TillTotal) AS AE
            FROM CashDecLines cdl
            INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
            WHERE CAST(cdh.dtTimeStamp AS DATE) = ?
              AND cdl.PaymentNo = '04'
        ");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $data['allOtherSales'] = (float)($row['AE'] ?? 0);
    } catch (PDOException $e) {
        error_log("Credit Cards query failed for $date: " . $e->getMessage());
    }
    
    // Non Cash B
    try {
        $stmt = $sqlsrv_pdo->prepare("
            SELECT (SUM(cdl.TillTotal) + ?) AS NCB
            FROM CashDecLines cdl
            INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
            WHERE CAST(cdh.dtTimeStamp AS DATE) = ?
              AND cdl.PaymentNo = '04'
        ");
        $stmt->execute([$data['Loyalty'], $date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $data['nonCashB'] = (float)($row['NCB'] ?? 0);
    } catch (PDOException $e) {
        error_log("Non Cash B query failed for $date: " . $e->getMessage());
    }
    
    // Cash Payments (Petty Cash)
    try {
        $stmt = $sqlsrv_pdo->prepare("
            SELECT SUM(t.PN_CURR) AS CP
            FROM svp.dbo.TENDER t
            WHERE CAST(t.dtTimeStamp AS DATE) = ?
              AND t.PN_RECTYPE = '13'
        ");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $data['CP'] = (float)($row['CP'] ?? 0);
    } catch (PDOException $e) {
        error_log("Cash Payments query failed for $date: " . $e->getMessage());
    }
    
    // Net Cash (cashSales)
    try {
        $stmt = $sqlsrv_pdo->prepare("
            SELECT (SUM(cdl.UserTotal) - SUM(cdl.FloatHeld)) AS CashSales
            FROM CashDecLines cdl
            INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
            WHERE CAST(cdh.dtTimeStamp AS DATE) = ?
              AND cdl.PaymentNo = '01'
        ");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $data['cashSales'] = (float)($row['CashSales'] ?? 0);
    } catch (PDOException $e) {
        error_log("Cash Sales query failed for $date: " . $e->getMessage());
    }
    
    // Current Float
    try {
        $stmt = $sqlsrv_pdo->prepare("
            SELECT SUM(cdl.FloatHeld) AS FloatHeld
            FROM CashDecLines cdl
            INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
            WHERE CAST(cdh.dtTimeStamp AS DATE) = ?
              AND cdl.PaymentNo = '01'
        ");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $data['currentFloat'] = (float)($row['FloatHeld'] ?? 0);
    } catch (PDOException $e) {
        error_log("Current Float query failed for $date: " . $e->getMessage());
    }
    
    // Lodge
    try {
        $stmt = $sqlsrv_pdo->prepare("
            SELECT SUM(cdl.Lodged) AS Lodge
            FROM CashDecLines cdl
            INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
            WHERE CAST(cdh.dtTimeStamp AS DATE) = ?
              AND cdl.PaymentNo = '01'
        ");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $data['Lodge'] = (float)($row['Lodge'] ?? 0);
    } catch (PDOException $e) {
        error_log("Lodge query failed for $date: " . $e->getMessage());
    }
    
    // Difference
    try {
        $stmt = $sqlsrv_pdo->prepare("
            SELECT SUM(cdl.Difference) AS Difference
            FROM CashDecLines cdl
            INNER JOIN CashDecHeader cdh ON cdl.CashDecRef = cdh.CashDecRef
            WHERE CAST(cdh.dtTimeStamp AS DATE) = ?
              AND cdl.PaymentNo = '01'
        ");
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $data['Difference'] = (float)($row['Difference'] ?? 0);
    } catch (PDOException $e) {
        error_log("Difference query failed for $date: " . $e->getMessage());
    }
    
    // Calculations
    $data['zCount'] = (((($data['currentFloat'] - $data['prevFloatHeld']) + $data['nonCashB'] + $data['Lodge']) - $data['Difference']) - $data['CP']);
    $data['allSales'] = ($data['cashSales'] + $data['nonCashB']);
    $data['cashOnHand'] = ($data['cashSales'] + $data['currentFloat']);
    
    return $data;
}

// Handle form submission
$weekData = [];
$weekDates = [];
$startDate = '';

if (isset($_POST['start_date'])) {
    $startDate = $_POST['start_date'];
    $weekDates = getWeekDates($startDate);
    
    foreach ($weekDates as $date) {
        $weekData[$date] = fetchDayData($sqlsrv_pdo, $date);
    }
}

// Calculate totals for the week
$totals = [
    'cashOnHand' => 0.0,
    'currentFloat' => 0.0,
    'cashSales' => 0.0,
    'allOtherSales' => 0.0,
    'Loyalty' => 0.0,
    'nonCashB' => 0.0,
    'allSales' => 0.0,
    'zCount' => 0.0,
    'Difference' => 0.0,
    'CP' => 0.0,
    'Lodge' => 0.0
];

foreach ($weekData as $data) {
    $totals['cashOnHand'] += $data['cashOnHand'];
    $totals['currentFloat'] += $data['currentFloat'];
    $totals['cashSales'] += $data['cashSales'];
    $totals['allOtherSales'] += $data['allOtherSales'];
    $totals['Loyalty'] += $data['Loyalty'];
    $totals['nonCashB'] += $data['nonCashB'];
    $totals['allSales'] += $data['allSales'];
    $totals['zCount'] += $data['zCount'];
    $totals['Difference'] += $data['Difference'];
    $totals['CP'] += $data['CP'];
    $totals['Lodge'] += $data['Lodge'];
}

$currency = fn($v) => "&euro;" . number_format($v, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Weekly Transaction Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        .date-selector {
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .date-selector label {
            font-weight: bold;
            margin-right: 10px;
        }
        .date-selector input[type="date"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .date-selector button {
            padding: 8px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 14px;
        }
        .date-selector button:hover {
            background: #45a049;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .report-table th,
        .report-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: right;
        }
        .report-table th {
            background-color: #4CAF50;
            color: green;
            font-weight: bold;
        }
        .report-table td:first-child,
        .report-table th:first-child {
            text-align: left;
            font-weight: bold;
            background-color: #f2f2f2;
        }
        .report-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .report-table tr:hover {
            background-color: #f5f5f5;
        }
        .total-column {
            background-color: #e8f5e9 !important;
            font-weight: bold;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        .week-info {
            text-align: center;
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
    <img src="https://svp.hopto.org:9443/svp/svplogo.png" alt="SVP Logo" style="height:80px;">
                <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>

        <h1>Weekly Transaction Report (White-Book Helper)</h1>
        
        <div class="date-selector">
            <form method="POST">
                <label for="start_date">Select Week Starting (Monday):</label>
                <input type="date" id="start_date" name="start_date" 
                       value="<?php echo htmlspecialchars($startDate); ?>" required>
                <button type="submit">Generate Report</button>
            </form>
        </div>
        
        <?php if (!empty($weekData)): ?>
            <div class="week-info">
                Week of <?php echo date('F d, Y', strtotime($weekDates[0])); ?> 
                to <?php echo date('F d, Y', strtotime($weekDates[6])); ?>
            </div>
            
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Monday<br><?php echo date('d/m', strtotime($weekDates[0])); ?></th>
                        <th>Tuesday<br><?php echo date('d/m', strtotime($weekDates[1])); ?></th>
                        <th>Wednesday<br><?php echo date('d/m', strtotime($weekDates[2])); ?></th>
                        <th>Thursday<br><?php echo date('d/m', strtotime($weekDates[3])); ?></th>
                        <th>Friday<br><?php echo date('d/m', strtotime($weekDates[4])); ?></th>
                        <th>Saturday<br><?php echo date('d/m', strtotime($weekDates[5])); ?></th>
                        <th>Sunday<br><?php echo date('d/m', strtotime($weekDates[6])); ?></th>
                        <th class="total-column">Week Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Cash on Hand</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['cashOnHand']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['cashOnHand']); ?></td>
                    </tr>
                    <tr>
                        <td>Deduct Float</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['currentFloat']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['currentFloat']); ?></td>
                    </tr>
                    <tr>
                        <td>Net Cash (A)</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['cashSales']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['cashSales']); ?></td>
                    </tr>
                    <tr>
                        <td>Credit Card</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['allOtherSales']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['allOtherSales']); ?></td>
                    </tr>
                    <tr>
                        <td>Loyalty</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['Loyalty']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['Loyalty']); ?></td>
                    </tr>
                    <tr>
                        <td>Non Cash (B)</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['nonCashB']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['nonCashB']); ?></td>
                    </tr>
                    <tr>
                        <td>Total (A+B)</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['allSales']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['allSales']); ?></td>
                    </tr>
                    <tr>
                        <td>Z Read</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['zCount']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['zCount']); ?></td>
                    </tr>
                    <tr>
                        <td>Difference</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['Difference']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['Difference']); ?></td>
                    </tr>
                    <tr>
                        <td>Petty Cash</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['CP']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['CP']); ?></td>
                    </tr>
                    <tr>
                        <td>Lodge</td>
                        <?php foreach ($weekDates as $date): ?>
                            <td><?php echo $currency($weekData[$date]['Lodge']); ?></td>
                        <?php endforeach; ?>
                        <td class="total-column"><?php echo $currency($totals['Lodge']); ?></td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <p>Please select a date to generate the weekly report.</p>
                <p>The report will show Monday through Sunday for the week containing your selected date.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
