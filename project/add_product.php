<?php
session_start();
require_once "Database.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = "Access denied. Admin privileges required.";
    header("Location: index.php?page=login");
    exit();
}
//  && isset($_POST['add_product'])
// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get form data
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $price = $_POST["price"];
    $stock = $_POST["stock"];
    $category_id = $_POST["category_id"];

    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    
    if (empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = "Valid price is required";
    }
    
    if (empty($stock) || !is_numeric($stock) || $stock < 0) {
        $errors[] = "Valid stock quantity is required";
    }
    
    if (empty($category_id)) {
        $errors[] = "Category is required";
    }

    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Prepare SQL statement
            $stmt = $db->prepare("
                INSERT INTO products (name, description, price, stock, category_id) 
                VALUES (:name, :description, :price, :stock, :category_id)
            ");
            
            // Bind parameters
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':stock', $stock);
            $stmt->bindParam(':category_id', $category_id);
            
            // Execute query
            if ($stmt->execute()) {
                $_SESSION['admin_success'] = "Product added successfully!";
                header("Location: admin.php");
                exit();
            } else {
                $_SESSION['admin_error'] = "Failed to add product.";
                header("Location: admin.php");
                exit();
            }
            
        } catch (PDOException $e) {
            $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
            header("Location: admin.php");
            exit();
        }
    } else {
        // Store errors and redirect back
        $_SESSION['admin_error'] = implode("<br>", $errors);
        header("Location: admin.php");
        exit();
    }
} else {
    // If accessed directly without POST, redirect to admin panel
    header("Location: admin.php");
    exit();
}




?>






