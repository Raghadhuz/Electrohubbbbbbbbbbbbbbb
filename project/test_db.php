<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "<h2>Testing Database Connection</h2>";

// Test 1: Check if Database.php exists
if (file_exists('Database.php')) {
    echo "✓ Database.php exists<br>";
    
    // Include it
    require_once 'Database.php';
    
    // Test if $db variable exists
    if (isset($db)) {
        echo "✓ \$db variable exists<br>";
        
        // Try a simple query
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM products");
            $data = $result->fetch();
            echo "✓ Can query database. Current products: " . $data['count'] . "<br>";
        } catch (Exception $e) {
            echo "✗ Database query failed: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "✗ \$db variable does NOT exist after including Database.php<br>";
    }
} else {
    echo "✗ Database.php does NOT exist<br>";
}

echo "<hr><h2>Test Direct Database Connection</h2>";
try {
    $host = 'localhost';
    $dbname = 'electrohub';
    $username = 'root';
    $password = '';
    
    $test_db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $test_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Direct connection successful<br>";
    
    // Check tables
    $tables = $test_db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database: " . implode(", ", $tables) . "<br>";
    
} catch (PDOException $e) {
    echo "✗ Direct connection failed: " . $e->getMessage() . "<br>";
}
?>