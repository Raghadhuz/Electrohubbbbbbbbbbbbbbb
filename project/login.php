<?php
session_start();
require_once "Database.php";

// إذا مش POST، روح عال login
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $_SESSION['error'] = "Invalid request method";
    header("Location: index.php?page=login");
    exit();
}

// خذ البيانات من الفورم
$email = trim($_POST["email"] ?? '');
$password = $_POST["password"] ?? '';

// شوف إذا في بيانات ناقصة
if (empty($email) || empty($password)) {
    $_SESSION['error'] = "Please enter email and password";
    header("Location: index.php?page=login");
    exit();
}

// تحقق من الإيميل
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email format";
    header("Location: index.php?page=login");
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // إبحث عن المستخدم
    $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['error'] = "User not found";
        header("Location: index.php?page=login");
        exit();
    }

    // تأكد من كلمة المرور
    if (!password_verify($password, $user['password'])) {
        $_SESSION['error'] = "Invalid password";
        header("Location: index.php?page=login");
        exit();
    }

    // سجل الدخول
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    // روح للصفحة المناسبة
    if ($user['role'] == 'admin') {
        $_SESSION['success'] = "Admin login successful!";
        header("Location: admin.php");
        exit();
    } else {
        $_SESSION['success'] = "Login successful! Welcome " . $user['name'];
        header("Location: index.php");
        exit();
    }

} catch (Exception $e) {
    $_SESSION['error'] = "System error. Please try again";
    header("Location: index.php?page=login");
    exit();
}
?>