<?php
session_start();
require_once "Database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm = $_POST["confirm_password"];

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $_SESSION['error'] = "All fields are required";
        header("Location: index.php?page=register");
        exit();
    }

    if ($password !== $confirm) {
        $_SESSION['error'] = "Passwords do not match";
        header("Location: index.php?page=register");
        exit();
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters";
        header("Location: index.php?page=register");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header("Location: index.php?page=register");
        exit();
    }

    try {
        $db = Database::getInstance()->getConnection();
        
        // تحقق إذا كان البريد الإلكتروني مستخدم
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        
        if ($checkStmt->fetch()) {
            $_SESSION['error'] = "Email already exists";
            header("Location: index.php?page=register");
            exit();
        }
        
        // تجزئة كلمة المرور
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // إدخال المستخدم الجديد
        $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
        $stmt->execute([$name, $email, $hashedPassword]);
        
        $userId = $db->lastInsertId();
        
        // تسجيل الدخول تلقائياً
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = 'user';
        
        $_SESSION['success'] = "Registration successful!";
        header("Location: index.php?page=home");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error";
        header("Location: index.php?page=register");
        exit();
    }
} else {
    header("Location: index.php?page=register");
    exit();
}
?>