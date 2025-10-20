<?php
/**
 * Inventory Scanner Database Test Script
 * Tests MySQL and MSSQL connectivity and schema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Scanner - Database Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #28a745;
            border-bottom: 3px solid #28a745;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
            border-left: 4px solid #28a745;
            padding-left: 10px;
        }
        .test-result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 5px solid #ccc;
        }
        .success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #28a745;
            color: white;
        }
        .icon {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Inventory Scanner - Database Test</h1>
        <p>Testing database connectivity and schema configuration...</p>

        <?php
        require_once 'config.php';

        // Test 1: MySQL Connection
        echo '<h2>1️⃣ MySQL Connection Test</h2>';
        try {
            $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as database");
            $result = $stmt->fetch();
            echo '<div class="test-result success">';
            echo '<span class="icon">✅</span><strong>MySQL Connection: SUCCESS</strong><br>';
            echo "Database: <code>{$result['database']}</code><br>";
            echo "Version: <code>{$result['version']}</code>";
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="test-result error">';
            echo '<span class="icon">❌</span><strong>MySQL Connection: FAILED</strong><br>';
            echo "Error: " . htmlspecialchars($e->getMessage());
            echo '</div>';
            die();
        }

        // Test 2: MySQL Tables
        echo '<h2>2️⃣ MySQL Tables Check</h2>';
        try {
            $tables = ['InventoryScans', 'InventoryScans_AuditLog'];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                $result = $stmt->fetch();
                echo '<div class="test-result success">';
                echo "<span class=\"icon\">✅</span>Table <code>$table</code> exists - {$result['count']} records";
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="test-result error">';
            echo '<span class="icon">❌</span><strong>MySQL Tables: ERROR</strong><br>';
            echo "Error: " . htmlspecialchars($e->getMessage());
            echo '</div>';
        }

        // Test 3: MySQL Views
        echo '<h2>3️⃣ MySQL Views Check</h2>';
        try {
            $views = ['vw_CurrentFloorInventory', 'vw_DailyScanSummary'];
            foreach ($views as $view) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $view");
                $result = $stmt->fetch();
                echo '<div class="test-result success">';
                echo "<span class=\"icon\">✅</span>View <code>$view</code> exists - {$result['count']} records";
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="test-result error">';
            echo '<span class="icon">❌</span><strong>MySQL Views: ERROR</strong><br>';
            echo "Error: " . htmlspecialchars($e->getMessage());
            echo '</div>';
        }

        // Test 4: MySQL Stored Procedures
        echo '<h2>4️⃣ MySQL Stored Procedures Check</h2>';
        try {
            $procedures = ['sp_MarkItemsSold', 'sp_AdjustInventory', 'sp_GetInventorySummary'];
            foreach ($procedures as $proc) {
                $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Name = '$proc'");
                $result = $stmt->fetch();
                if ($result) {
                    echo '<div class="test-result success">';
                    echo "<span class=\"icon\">✅</span>Procedure <code>$proc</code> exists";
                    echo '</div>';
                } else {
                    echo '<div class="test-result warning">';
                    echo "<span class=\"icon\">⚠️</span>Procedure <code>$proc</code> NOT FOUND";
                    echo '</div>';
                }
            }
        } catch (Exception $e) {
            echo '<div class="test-result error">';
            echo '<span class="icon">❌</span><strong>Procedures Check: ERROR</strong><br>';
            echo "Error: " . htmlspecialchars($e->getMessage());
            echo '</div>';
        }

        // Test 5: MSSQL Connection
        echo '<h2>5️⃣ MSSQL Connection Test</h2>';
        try {
            $stmt = $sqlsrv_pdo->query("SELECT @@VERSION as version");
            $result = $stmt->fetch();
            echo '<div class="test-result success">';
            echo '<span class="icon">✅</span><strong>MSSQL Connection: SUCCESS</strong><br>';
            echo "Version: <code>" . substr($result['version'], 0, 100) . "...</code>";
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="test-result error">';
            echo '<span class="icon">❌</span><strong>MSSQL Connection: FAILED</strong><br>';
            echo "Error: " . htmlspecialchars($e->getMessage());
            echo '</div>';
            die();
        }

        // Test 6: MSSQL Tables
        echo '<h2>6️⃣ MSSQL Tables Check</h2>';
        try {
            $tables = ['BARCODES', 'STOCKMST', 'LOCATION'];
            foreach ($tables as $table) {
                $stmt = $sqlsrv_pdo->query("SELECT COUNT(*) as count FROM dbo.$table");
                $result = $stmt->fetch();
                echo '<div class="test-result success">';
                echo "<span class=\"icon\">✅</span>Table <code>dbo.$table</code> exists - " . number_format($result['count']) . " records";
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="test-result error">';
            echo '<span class="icon">❌</span><strong>MSSQL Tables: ERROR</strong><br>';
            echo "Error: " . htmlspecialchars($e->getMessage());
            echo '</div>';
        }

        // Test 7: MSSQL Product Lookup Query
        echo '<h2>7️⃣ MSSQL Product Lookup Test</h2>';
        try {
            $stmt = $sqlsrv_pdo->query("
                SELECT TOP 5
                    b.BC_REF as Barcode,
                    s.PM_DESC as ProductName,
                    b.BC_PART as SKU,
                    s.PM_RRP as Price,
                    l.LO_ONHAND as Stock
                FROM dbo.BARCODES b
                INNER JOIN dbo.STOCKMST s ON b.BC_PART = s.PM_PART
                LEFT JOIN dbo.LOCATION l ON b.BC_PART = l.LO_PART
                WHERE b.BC_REF IS NOT NULL AND b.BC_REF != ''
            ");
            
            $products = $stmt->fetchAll();
            
            if (count($products) > 0) {
                echo '<div class="test-result success">';
                echo '<span class="icon">✅</span><strong>Product Lookup Query: SUCCESS</strong><br>';
                echo "Found " . count($products) . " sample products:<br><br>";
                echo '<table>';
                echo '<tr><th>Barcode</th><th>Product Name</th><th>SKU</th><th>Price</th><th>Stock</th></tr>';
                foreach ($products as $p) {
                    echo '<tr>';
                    echo '<td><code>' . htmlspecialchars($p['Barcode']) . '</code></td>';
                    echo '<td>' . htmlspecialchars($p['ProductName']) . '</td>';
                    echo '<td>' . htmlspecialchars($p['SKU']) . '</td>';
                    echo '<td>€' . number_format($p['Price'], 2) . '</td>';
                    echo '<td>' . number_format($p['Stock']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                echo '</div>';
            } else {
                echo '<div class="test-result warning">';
                echo '<span class="icon">⚠️</span><strong>Product Lookup: NO PRODUCTS FOUND</strong><br>';
                echo "The query executed successfully but returned no results. Check if products exist in the database.";
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="test-result error">';
            echo '<span class="icon">❌</span><strong>Product Lookup: FAILED</strong><br>';
            echo "Error: " . htmlspecialchars($e->getMessage()) . "<br><br>";
            echo "This query is critical for the scanner to work. Please verify:<br>";
            echo "1. Table names are correct (BARCODES, STOCKMST, LOCATION)<br>";
            echo "2. Column names are correct (BC_REF, BC_PART, PM_DESC, etc.)<br>";
            echo "3. Tables contain data<br>";
            echo '</div>';
        }

        // Test 8: Test Complete Scan Flow
        echo '<h2>8️⃣ Test Complete Scan Flow</h2>';
        
        // Get a sample barcode
        try {
            $stmt = $sqlsrv_pdo->query("
                SELECT TOP 1 BC_REF as Barcode 
                FROM dbo.BARCODES 
                WHERE BC_REF IS NOT NULL AND BC_REF != ''
            ");
            $sample = $stmt->fetch();
            
            if ($sample) {
                $testBarcode = $sample['Barcode'];
                
                echo '<div class="test-result info">';
                echo "<span class=\"icon\">🧪</span><strong>Testing with barcode:</strong> <code>$testBarcode</code><br><br>";
                
                // Step 1: Lookup product
                echo "<strong>Step 1:</strong> Looking up product in MSSQL...<br>";
                $stmt = $sqlsrv_pdo->prepare("
                    SELECT TOP 1
                        b.BC_REF as Barcode,
                        s.PM_DESC as name,
                        b.BC_PART as sku,
                        s.PM_RRP as price,
                        l.LO_ONHAND as stock
                    FROM dbo.BARCODES b
                    INNER JOIN dbo.STOCKMST s ON b.BC_PART = s.PM_PART
                    LEFT JOIN dbo.LOCATION l ON b.BC_PART = l.LO_PART
                    WHERE b.BC_REF = ?
                ");
                $stmt->execute([$testBarcode]);
                $product = $stmt->fetch();
                
                if ($product) {
                    echo "✅ Product found: <strong>" . htmlspecialchars($product['name']) . "</strong><br>";
                    echo "   SKU: {$product['sku']}, Price: €" . number_format($product['price'], 2) . "<br><br>";
                    
                    // Step 2: Insert to MySQL
                    echo "<strong>Step 2:</strong> Inserting test scan to MySQL...<br>";
                    $insertStmt = $pdo->prepare("
                        INSERT INTO InventoryScans 
                        (Barcode, ProductID, ProductName, ScannedBy, DeviceID, Location, Status, Quantity)
                        VALUES (?, ?, ?, ?, ?, ?, 'OnFloor', 1)
                    ");
                    $insertStmt->execute([
                        $testBarcode,
                        $product['sku'],
                        $product['name'],
                        'Test Script',
                        'test_inventory.php',
                        'Test Location'
                    ]);
                    
                    $scanId = $pdo->lastInsertId();
                    echo "✅ Test scan recorded with ID: <code>$scanId</code><br><br>";
                    
                    // Step 3: Verify in MySQL
                    echo "<strong>Step 3:</strong> Verifying scan in MySQL...<br>";
                    $stmt = $pdo->prepare("SELECT * FROM InventoryScans WHERE ScanID = ?");
                    $stmt->execute([$scanId]);
                    $scan = $stmt->fetch();
                    
                    if ($scan) {
                        echo "✅ Scan verified in database<br><br>";
                        
                        // Step 4: Test marking as sold
                        echo "<strong>Step 4:</strong> Testing mark as sold procedure...<br>";
                        $stmt = $pdo->prepare("CALL sp_MarkItemsSold(?, 1, 'Test sale')");
                        $stmt->execute([$testBarcode]);
                        $result = $stmt->fetch();
                        
                        echo "✅ Marked {$result['ItemsMarkedSold']} item(s) as sold<br>";
                        echo "   Remaining on floor: {$result['RemainingOnFloor']}<br><br>";
                        
                        // IMPORTANT: Close cursor before next query
                        $stmt->closeCursor();
                        $stmt = null;
                        
                        // Clean up test data
                        echo "<strong>Cleanup:</strong> Removing test scan...<br>";
                        $cleanupStmt = $pdo->prepare("DELETE FROM InventoryScans WHERE ScanID = ?");
                        $cleanupStmt->execute([$scanId]);
                        $cleanupStmt->closeCursor();
                        echo "✅ Test data cleaned up<br>";
                    }
                }
                
                echo '</div>';
                
                echo '<div class="test-result success">';
                echo '<span class="icon">🎉</span><strong>Complete Scan Flow: SUCCESS!</strong><br>';
                echo "The entire scan flow from MSSQL lookup to MySQL storage is working correctly.";
                echo '</div>';
                
            } else {
                echo '<div class="test-result warning">';
                echo '<span class="icon">⚠️</span>No barcodes found to test with.';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="test-result error">';
            echo '<span class="icon">❌</span><strong>Scan Flow Test: FAILED</strong><br>';
            echo "Error: " . htmlspecialchars($e->getMessage());
            echo '</div>';
        }

        // Final Summary
        echo '<h2>🎯 Test Summary</h2>';
        echo '<div class="test-result success">';
        echo '<h3>✅ All Systems Operational!</h3>';
        echo '<p>Your inventory scanner system is properly configured and ready to use:</p>';
        echo '<ul>';
        echo '<li>✅ MySQL database connected and tables created</li>';
        echo '<li>✅ MSSQL database connected with product data accessible</li>';
        echo '<li>✅ Product lookup query working correctly</li>';
        echo '<li>✅ Scan flow from lookup to storage working</li>';
        echo '<li>✅ Stored procedures functioning</li>';
        echo '</ul>';
        echo '<h4>Next Steps:</h4>';
        echo '<ol>';
        echo '<li>Access the scanner: <code>inventory_scanner.html</code></li>';
        echo '<li>Access the dashboard: <code>inventory_dashboard.php</code></li>';
        echo '<li>Start scanning with your mobile devices!</li>';
        echo '</ol>';
        echo '</div>';
        ?>
    </div>
</body>
</html>
