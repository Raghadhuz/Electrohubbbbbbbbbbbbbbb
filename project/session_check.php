<?php
session_start();

// تحقق إذا كانت الجلسة منتهية الصلاحية (1 ساعة)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    $response = ["logged_in" => false];
} else if (isset($_SESSION['user'])) {
    // تجديد وقت الجلسة
    $_SESSION['last_activity'] = time();
    $response = [
        "logged_in" => true,
        "user" => $_SESSION['user']
    ];
} else {
    $response = ["logged_in" => false];
}

header('Content-Type: application/json');
echo json_encode($response);
?>