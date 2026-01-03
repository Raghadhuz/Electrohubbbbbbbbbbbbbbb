<?php
require_once "Database.php";

try {
    $db = Database::getInstance()->getConnection();
    
    $email = "admin@gmail.com";
    $password = "admin2002";
    $name = "Administrator";
    
    // تأكد إذا الأدمن موجود
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    
    if (!$check->fetch()) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$name, $email, $hashedPassword]);
        echo "Admin user created successfully!<br>";
        echo "Email: $email<br>";
        echo "Password: $password<br>";
        echo "<a href='index.php?page=login'>Go to Login</a>";
    } else {
        echo "Admin user already exists!";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>