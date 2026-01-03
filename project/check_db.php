<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Checking Database</h2>";

// Try different database names
$possible_dbs = ['ElectroHubbb', 'electrohub', 'ElectroHub', 'electro_hub'];

foreach ($possible_dbs as $dbname) {
    try {
        $conn = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Try to use this database
        $conn->exec("USE `$dbname`");
        
        // Check tables
        $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<div style='background: lightgreen; padding: 10px; margin: 5px;'>";
        echo "<strong>✓ FOUND DATABASE: $dbname</strong><br>";
        echo "Tables: " . implode(", ", $tables);
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='background: #ffe6e6; padding: 10px; margin: 5px;'>";
        echo "<strong>✗ $dbname:</strong> " . $e->getMessage();
        echo "</div>";
    }
}

echo "<hr><h3>Create Database if missing</h3>";
echo '<form method="POST">
    <input type="text" name="dbname" placeholder="Database name" value="ElectroHubbb">
    <button type="submit" name="create_db">Create Database</button>
</form>';

if (isset($_POST['create_db'])) {
    try {
        $conn = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
        $conn->exec("CREATE DATABASE IF NOT EXISTS `{$_POST['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p style='color: green;'>Database '{$_POST['dbname']}' created or already exists.</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
}
?>