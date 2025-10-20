<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "PHP is working<br>";

require_once 'config.php';
echo "Config loaded<br>";

$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM InventoryScans");
$result = $stmt->fetch();
echo "MySQL works - {$result['cnt']} scans<br>";

echo "All good! Try the dashboard now.";
?>
