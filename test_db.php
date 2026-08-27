<?php
try {
    $dsn = "mysql:host=100.116.234.100;port=3306;dbname=smartHomeCare;charset=utf8mb4";
    $pdo = new PDO($dsn, "faruq", 'Myane090409-$#@', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5 // 5 seconds timeout
    ]);
    
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo $column['Field'] . " - " . $column['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
