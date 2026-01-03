<?php
session_start();
require_once "Database.php";

// التحقق من صفحة المستخدم
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// تحميل البيانات من قاعدة البيانات
try {
    $db = Database::getInstance()->getConnection();
    
    // تحميل المنتجات المميزة
    $featuredStmt = $db->query("SELECT p.*, c.name as category_name 
                               FROM products p 
                               LEFT JOIN categories c ON p.category_id = c.id 
                               WHERE p.status = 'active' 
                               LIMIT 8");
    $featuredProducts = $featuredStmt->fetchAll();
    
    // تحميل جميع المنتجات
    $allProductsStmt = $db->query("SELECT p.*, c.name as category_name 
                                  FROM products p 
                                  LEFT JOIN categories c ON p.category_id = c.id 
                                  WHERE p.status = 'active'");
    $allProducts = $allProductsStmt->fetchAll();
    
    // تحميل الفئات
    $categoriesStmt = $db->query("SELECT * FROM categories");
    $categories = $categoriesStmt->fetchAll();
    
} catch (PDOException $e) {
    $featuredProducts = [];
    $allProducts = [];
    $categories = [];
}

// التحقق من تسجيل الدخول
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['user_name'] : 'Guest';
$userInitial = $isLoggedIn ? strtoupper(substr($userName, 0, 1)) : 'G';
$userRole = $isLoggedIn ? $_SESSION['user_role'] : 'guest';

// تحميل عربة التسوق
$cartItems = [];
$cartCount = 0;
$cartSubtotal = 0;
if ($isLoggedIn) {
    try {
        $cartStmt = $db->prepare("SELECT c.*, p.name, p.price, p.image_url 
                                 FROM cart c 
                                 JOIN products p ON c.product_id = p.id 
                                 WHERE c.user_id = ?");
        $cartStmt->execute([$_SESSION['user_id']]);
        $cartItems = $cartStmt->fetchAll();
        
        $cartCount = array_sum(array_column($cartItems, 'quantity'));
        $cartSubtotal = array_sum(array_map(function($item) {
            return $item['price'] * $item['quantity'];
        }, $cartItems));
    } catch (PDOException $e) {
        // تجاهل الخطأ
    }
}

// معالجة إضافة إلى العربة
if (isset($_POST['add_to_cart'])) {
    if (!$isLoggedIn) {
        $_SESSION['error'] = "Please login to add items to cart";
        header("Location: index.php?page=login");
        exit();
    }
    
    $productId = $_POST['product_id'];
    $quantity = $_POST['quantity'] ?? 1;
    
    try {
        // تحقق إذا كان المنتج موجود في العربة
        $checkStmt = $db->prepare("SELECT id, quantity FROM cart 
                                  WHERE user_id = ? AND product_id = ?");
        $checkStmt->execute([$_SESSION['user_id'], $productId]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // تحديث الكمية
            $newQuantity = $existing['quantity'] + $quantity;
            $updateStmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $updateStmt->execute([$newQuantity, $existing['id']]);
        } else {
            // إضافة جديد
            $insertStmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantity) 
                                       VALUES (?, ?, ?)");
            $insertStmt->execute([$_SESSION['user_id'], $productId, $quantity]);
        }
        
        $_SESSION['success'] = "Product added to cart!";
        header("Location: index.php?page=" . $page);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding to cart";
        header("Location: index.php?page=" . $page);
        exit();
    }
}

// معالجة إزالة من العربة
if (isset($_GET['remove_from_cart'])) {
    if (!$isLoggedIn) {
        $_SESSION['error'] = "Please login first";
        header("Location: index.php?page=login");
        exit();
    }
    
    $cartId = $_GET['remove_from_cart'];
    
    try {
        $deleteStmt = $db->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $deleteStmt->execute([$cartId, $_SESSION['user_id']]);
        
        $_SESSION['success'] = "Item removed from cart";
        header("Location: index.php?page=cart");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error removing item";
        header("Location: index.php?page=cart");
        exit();
    }
}

// معالجة تحديث كمية العربة
if (isset($_POST['update_cart_quantity'])) {
    if (!$isLoggedIn) {
        $_SESSION['error'] = "Please login first";
        header("Location: index.php?page=login");
        exit();
    }
    
    $cartId = $_POST['cart_id'];
    $quantity = $_POST['quantity'];
    
    if ($quantity < 1) {
        // حذف إذا كانت الكمية صفر
        try {
            $deleteStmt = $db->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $deleteStmt->execute([$cartId, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating cart";
        }
    } else {
        try {
            $updateStmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $updateStmt->execute([$quantity, $cartId, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating cart";
        }
    }
    
    header("Location: index.php?page=cart");
    exit();
}

// معالجة الطلب
if (isset($_POST['place_order'])) {
    if (!$isLoggedIn) {
        $_SESSION['error'] = "Please login to place order";
        header("Location: index.php?page=login");
        exit();
    }
    
    $name = $_POST['name'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $zip = $_POST['zip'];
    $paymentMethod = $_POST['payment_method'];
    
    if (empty($name) || empty($email) || empty($address) || empty($city) || empty($zip)) {
        $_SESSION['error'] = "Please fill in all required fields";
        header("Location: index.php?page=checkout");
        exit();
    }
    
    try {
        $db->beginTransaction();
        
        // الحصول على محتويات العربة
        $cartStmt = $db->prepare("SELECT c.*, p.price, p.stock 
                                 FROM cart c 
                                 JOIN products p ON c.product_id = p.id 
                                 WHERE c.user_id = ?");
        $cartStmt->execute([$_SESSION['user_id']]);
        $cartItems = $cartStmt->fetchAll();
        
        if (empty($cartItems)) {
            throw new Exception("Your cart is empty");
        }
        
        // حساب الإجمالي والتحقق من المخزون
        $total = 0;
        foreach ($cartItems as $item) {
            if ($item['quantity'] > $item['stock']) {
                throw new Exception("Insufficient stock for product");
            }
            $total += $item['price'] * $item['quantity'];
        }
        
        // إضافة الضريبة والشحن
        $shipping = 5.99;
        $tax = $total * 0.08;
        $grandTotal = $total + $shipping + $tax;
        
        // إنشاء الطلب
        $shippingAddress = "$name, $address, $city, $zip";
        $orderStmt = $db->prepare("INSERT INTO orders (user_id, total, status, shipping_address, payment_method) 
                                  VALUES (?, ?, 'pending', ?, ?)");
        $orderStmt->execute([$_SESSION['user_id'], $grandTotal, $shippingAddress, $paymentMethod]);
        $orderId = $db->lastInsertId();
        
        // إضافة عناصر الطلب وتحديث المخزون
        foreach ($cartItems as $item) {
            // إضافة عنصر الطلب
            $orderItemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                          VALUES (?, ?, ?, ?)");
            $orderItemStmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
            
            // تحديث المخزون
            $updateStockStmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $updateStockStmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // تفريغ العربة
        $clearCartStmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
        $clearCartStmt->execute([$_SESSION['user_id']]);
        
        $db->commit();
        
        $_SESSION['success'] = "Order placed successfully! Order ID: #$orderId";
        header("Location: index.php?page=home");
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
        header("Location: index.php?page=checkout");
        exit();
    }
}

// حساب إجماليات العربة
$shipping = 5.99;
$tax = $cartSubtotal * 0.08;
$total = $cartSubtotal + $shipping + $tax;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ElectroHub - Premium Electronics Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container container">
            <a href="index.php?page=home" class="logo">
                <i class="fas fa-bolt"></i>
                <span class="logo-text">ElectroHub</span>
            </a>

            <nav class="nav-links" id="navLinks">
                <a href="index.php?page=home" class="nav-link <?php echo $page == 'home' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="index.php?page=products" class="nav-link <?php echo $page == 'products' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
                <a href="index.php?page=cart" class="nav-link <?php echo $page == 'cart' ? 'active' : ''; ?>">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Cart</span>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-count"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="index.php?page=contact" class="nav-link <?php echo $page == 'contact' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i>
                    <span>Contact</span>
                </a>
            </nav>

            <div class="header-actions">
                <?php if (!$isLoggedIn): ?>
                    <div class="auth-buttons">
                        <a href="index.php?page=login" class="btn btn-sm btn-outline">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Login</span>
                        </a>
                        <a href="index.php?page=register" class="btn btn-sm" style="background-color: var(--accent); margin-left: 10px">
                            <i class="fas fa-user-plus"></i>
                            <span>Register</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="user-menu">
                        <div class="user-avatar"><?php echo $userInitial; ?></div>
                        <span class="user-name"><?php echo $userName; ?></span>
                        <?php if ($userRole == 'admin'): ?>
                            <a href="admin.php" class="btn btn-sm" style="margin-left: 10px; background-color: var(--primary);">
                                <i class="fas fa-cog"></i>
                                <span>Admin</span>
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="btn btn-sm btn-outline" style="margin-left: 10px;">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="cart-icon" onclick="window.location.href='index.php?page=cart'">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-count"><?php echo $cartCount; ?></span>
                    <?php endif; ?>
                </div>

                <button class="mobile-menu-btn" id="mobileMenuBtn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="notification success">
            <div class="notification-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div style="font-weight: 600; margin-bottom: 5px">Success</div>
                <div><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="notification error">
            <div class="notification-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div>
                <div style="font-weight: 600; margin-bottom: 5px">Error</div>
                <div><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="container">
        <?php if ($page == 'home'): ?>
            <!-- Home Page -->
            <section id="home-page" class="page active">
                <div class="hero">
                    <div class="hero-content" style="max-width: 1200px; margin: 0 auto; padding: 0 40px">
                        <div class="hero-text" style="padding-right: 40px">
                            <h1 class="hero-title">Latest Tech at Amazing Prices</h1>
                            <p class="hero-subtitle">
                                Discover the newest smartphones, laptops, gadgets, and home
                                electronics with exclusive deals and fast shipping. Premium
                                quality with 2-year warranty on all products.
                            </p>
                            <div style="display: flex; gap: 15px; flex-wrap: wrap">
                                <a href="index.php?page=products" class="btn btn-accent">
                                    <i class="fas fa-shopping-bag"></i>
                                    Shop Now
                                </a>
                                <a href="index.php?page=products" class="btn btn-outline" style="color: white; border-color: white">
                                    <i class="fas fa-eye"></i>
                                    Browse All
                                </a>
                            </div>
                        </div>
                        <div class="hero-image">
                            <img src="https://images.unsplash.com/photo-1498049794561-7780e7231661?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" alt="Electronics" />
                        </div>
                    </div>
                </div>

                <h2 class="section-title center">Featured Products</h2>
                <div class="products-grid" id="featuredProducts">
                    <?php foreach ($featuredProducts as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?php echo $product['image_url'] ?: 'https://images.unsplash.com/photo-1498049794561-7780e7231661?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>
                        <div class="product-info">
                            <div class="product-category">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($product['category_name'] ?? 'Electronics'); ?>
                            </div>
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-description"><?php echo htmlspecialchars($product['description']); ?></div>
                            <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-actions">
                                <button class="btn btn-sm" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-cart-plus"></i>
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="categories-section">
                    <h2 class="section-title center">Shop by Category</h2>
                    <div class="categories-grid">
                        <?php foreach ($categories as $category): ?>
                        <div class="category-card" onclick="window.location.href='index.php?page=products&category=<?php echo urlencode($category['name']); ?>'">
                            <div style="font-size: 3rem; color: var(--primary); margin-bottom: 20px">
                                <i class="fas fa-tag"></i>
                            </div>
                            <h3 style="margin-bottom: 10px; font-size: 1.3rem"><?php echo htmlspecialchars($category['name']); ?></h3>
                            <p style="color: var(--gray)"><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>
                            <div style="margin-top: 15px; color: var(--primary); font-weight: 500">
                                <span>View Collection</span>
                                <i class="fas fa-arrow-right" style="margin-left: 8px"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

        <?php elseif ($page == 'products'): ?>
            <!-- Products Page -->
            <section id="products-page" class="page active">
                <h1 class="section-title">All Products</h1>

                <div style="margin-bottom: 30px; display: flex; gap: 12px; flex-wrap: wrap">
                    <button class="btn btn-outline active-filter" onclick="window.location.href='index.php?page=products'">
                        All Products
                    </button>
                    <?php foreach ($categories as $category): ?>
                    <button class="btn btn-outline" onclick="window.location.href='index.php?page=products&category=<?php echo urlencode($category['name']); ?>'">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="products-grid" id="allProducts">
                    <?php 
                    // فلترة المنتجات حسب الفئة
                    $filteredProducts = $allProducts;
                    if (isset($_GET['category']) && !empty($_GET['category'])) {
                        $selectedCategory = $_GET['category'];
                        $filteredProducts = array_filter($allProducts, function($product) use ($selectedCategory) {
                            return $product['category_name'] == $selectedCategory;
                        });
                    }
                    
                    foreach ($filteredProducts as $product): 
                    ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?php echo $product['image_url'] ?: 'https://images.unsplash.com/photo-1498049794561-7780e7231661?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>
                        <div class="product-info">
                            <div class="product-category">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($product['category_name'] ?? 'Electronics'); ?>
                            </div>
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-description"><?php echo htmlspecialchars($product['description']); ?></div>
                            <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-actions">
                                <button class="btn btn-sm" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-cart-plus"></i>
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

        <?php elseif ($page == 'cart'): ?>
            <!-- Cart Page -->
            <section id="cart-page" class="page active">
                <h1 class="section-title">Your Shopping Cart</h1>

                <div class="cart-container">
                    <div class="cart-items" id="cartItems">
                        <?php if (empty($cartItems)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="empty-state-title">Your cart is empty</div>
                                <div class="empty-state-text">Add some products to your cart to see them here</div>
                                <a href="index.php?page=products" class="btn">
                                    <i class="fas fa-shopping-bag"></i>
                                    Browse Products
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($cartItems as $item): ?>
                            <div class="cart-item">
                                <div class="cart-item-image">
                                    <img src="<?php echo $item['image_url'] ?: 'https://images.unsplash.com/photo-1498049794561-7780e7231661?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="cart-item-details">
                                    <h4 class="cart-item-title"><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <div class="cart-item-price">$<?php echo number_format($item['price'], 2); ?></div>
                                    <div class="cart-item-actions">
                                        <div class="quantity-control">
                                            <button class="quantity-btn" onclick="updateCartQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity'] - 1; ?>)">-</button>
                                            <span class="quantity"><?php echo $item['quantity']; ?></span>
                                            <button class="quantity-btn" onclick="updateCartQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity'] + 1; ?>)">+</button>
                                        </div>
                                        <button class="remove-item" onclick="removeFromCart(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="cart-summary">
                        <h3 class="summary-title">Order Summary</h3>
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="subtotal">$<?php echo number_format($cartSubtotal, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping</span>
                            <span id="shipping">$<?php echo number_format($shipping, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Tax</span>
                            <span id="tax">$<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div class="summary-row summary-total">
                            <span>Total</span>
                            <span id="total">$<?php echo number_format($total, 2); ?></span>
                        </div>
                        <button class="btn btn-block" style="margin-top: 25px" onclick="proceedToCheckout()">
                            Proceed to Checkout
                        </button>
                        <a href="index.php?page=products" class="btn btn-outline btn-block" style="margin-top: 15px; text-decoration: none; text-align: center;">
                            Continue Shopping
                        </a>
                    </div>
                </div>
            </section>

        <?php elseif ($page == 'checkout'): ?>
            <!-- Checkout Page -->
            <section id="checkout-page" class="page active">
                <h1 class="section-title">Checkout</h1>

                <div class="checkout-container">
                    <div>
                        <form method="POST" id="checkoutForm">
                            <div class="checkout-section">
                                <h3 class="section-heading">
                                    <i class="fas fa-user"></i> Contact Information
                                </h3>
                                <div class="form-group">
                                    <label class="form-label" for="checkout-email">Email Address</label>
                                    <input type="email" class="form-control" id="checkout-email" name="email" value="<?php echo $isLoggedIn ? $_SESSION['user_email'] : ''; ?>" required>
                                </div>
                            </div>

                            <div class="checkout-section">
                                <h3 class="section-heading">
                                    <i class="fas fa-truck"></i> Shipping Address
                                </h3>
                                <div class="form-group">
                                    <label class="form-label" for="checkout-name">Full Name</label>
                                    <input type="text" class="form-control" id="checkout-name" name="name" value="<?php echo $isLoggedIn ? $_SESSION['user_name'] : ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="checkout-address">Address</label>
                                    <input type="text" class="form-control" id="checkout-address" name="address" required>
                                </div>
                                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px">
                                    <div>
                                        <label class="form-label" for="checkout-city">City</label>
                                        <input type="text" class="form-control" id="checkout-city" name="city" required>
                                    </div>
                                    <div>
                                        <label class="form-label" for="checkout-zip">ZIP Code</label>
                                        <input type="text" class="form-control" id="checkout-zip" name="zip" required>
                                    </div>
                                </div>
                            </div>

                            <div class="checkout-section">
                                <h3 class="section-heading">
                                    <i class="fas fa-credit-card"></i> Payment Method
                                </h3>
                                <div class="payment-methods">
                                    <div class="payment-method selected" id="payment-credit" onclick="selectPaymentMethod('credit')">
                                        <div class="payment-icon">
                                            <i class="far fa-credit-card"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600">Credit/Debit Card</div>
                                            <div style="font-size: 0.9rem; color: var(--gray)">Pay with your card</div>
                                        </div>
                                    </div>

                                    <div class="payment-method" id="payment-paypal" onclick="selectPaymentMethod('paypal')">
                                        <div class="payment-icon">
                                            <i class="fab fa-paypal"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600">PayPal</div>
                                            <div style="font-size: 0.9rem; color: var(--gray)">Pay with your PayPal account</div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="payment_method" id="payment_method" value="credit">
                            </div>
                            <input type="hidden" name="place_order" value="1">
                        </form>
                    </div>

                    <div>
                        <div class="checkout-section">
                            <h3 class="section-heading">
                                <i class="fas fa-receipt"></i> Order Summary
                            </h3>
                            <div id="checkout-items">
                                <?php foreach ($cartItems as $item): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--gray-light)">
                                    <div style="display: flex; align-items: center; gap: 15px">
                                        <div style="width: 60px; height: 60px; border-radius: 8px; overflow: hidden">
                                            <img src="<?php echo $item['image_url'] ?: 'https://images.unsplash.com/photo-1498049794561-7780e7231661?ixlib=rb-4.0.3&auto=format&fit=crop&w=1470&q=80'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 100%; height: 100%; object-fit: cover">
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; margin-bottom: 5px"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <div style="font-size: 0.9rem; color: var(--gray)">Qty: <?php echo $item['quantity']; ?></div>
                                        </div>
                                    </div>
                                    <div style="font-weight: 700; color: var(--primary)">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top: 25px; padding-top: 25px; border-top: 2px solid var(--gray-light)">
                                <div class="summary-row">
                                    <span>Subtotal</span>
                                    <span id="checkout-subtotal">$<?php echo number_format($cartSubtotal, 2); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Shipping</span>
                                    <span id="checkout-shipping">$<?php echo number_format($shipping, 2); ?></span>
                                </div>
                                <div class="summary-row">
                                    <span>Tax</span>
                                    <span id="checkout-tax">$<?php echo number_format($tax, 2); ?></span>
                                </div>
                                <div class="summary-row summary-total">
                                    <span>Total</span>
                                    <span id="checkout-total">$<?php echo number_format($total, 2); ?></span>
                                </div>
                            </div>
                            <button class="btn btn-block" style="margin-top: 25px" onclick="document.getElementById('checkoutForm').submit()">
                                <i class="fas fa-lock"></i>
                                Place Order
                            </button>
                            <a href="index.php?page=cart" class="btn btn-outline btn-block" style="margin-top: 15px; text-decoration: none; text-align: center">
                                <i class="fas fa-arrow-left"></i>
                                Back to Cart
                            </a>
                        </div>
                    </div>
                </div>
            </section>

        <?php elseif ($page == 'contact'): ?>
            <!-- Contact Page -->
            <section id="contact-page" class="page active">
                <h1 class="section-title center">Contact Us</h1>
                <p style="text-align: center; color: var(--gray); margin-bottom: 40px; max-width: 700px; margin-left: auto; margin-right: auto">
                    Have questions? We're here to help. Send us a message and we'll
                    respond as soon as possible.
                </p>

                <div class="contact-container">
                    <div class="contact-info">
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h3 style="margin-bottom: 10px">Our Location</h3>
                                <p style="color: var(--gray)">123 Tech Street, San Francisco, CA 94107</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <h3 style="margin-bottom: 10px">Phone Number</h3>
                                <p style="color: var(--gray)">+1 (555) 123-4567</p>
                            </div>
                        </div>

                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h3 style="margin-bottom: 10px">Email Address</h3>
                                <p style="color: var(--gray)">support@electrohub.com</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-container">
                        <h2 class="form-title">Send Us a Message</h2>
                        <form>
                            <div class="form-group">
                                <label class="form-label" for="contact-name">Your Name *</label>
                                <input type="text" class="form-control" id="contact-name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="contact-email">Email Address *</label>
                                <input type="email" class="form-control" id="contact-email" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="contact-subject">Subject *</label>
                                <input type="text" class="form-control" id="contact-subject" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="contact-message">Message *</label>
                                <textarea class="form-control" id="contact-message" rows="6" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-block">
                                <i class="fas fa-paper-plane"></i>
                                Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </section>

        <?php elseif ($page == 'login'): ?>
<!-- Login Page -->
<section id="login-page" class="page active">
    <div class="form-container">
        <h2 class="form-title">Log In to Your Account</h2>
        
        <!-- رسائل الخطأ -->
        <?php if (isset($_SESSION['error'])): ?>
            <div style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #c62828;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- رسائل النجاح -->
        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2e7d32;">
                <i class="fas fa-check-circle"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <!-- الفورم -->
        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input type="email" class="form-control" name="email" 
                       value="admin@gmail.com" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password *</label>
                <input type="password" class="form-control" name="password" 
                       value="admin2002" placeholder="Enter your password" required>
                <span class="password-toggle" onclick="togglePassword(this)">
                    <i class="fas fa-eye"></i>
                </span>
            </div>
            <button type="submit" class="btn btn-block">
                <i class="fas fa-sign-in-alt"></i>
                Log In
            </button>
            
            <div style="text-align: center; margin-top: 20px; padding: 10px; background: #f3f4f6; border-radius: 8px;">
                <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">
                    <strong>Admin Test:</strong> admin@gmail.com / admin2002
                </p>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: #6b7280;">
                    Don't have an account? 
                    <a href="index.php?page=register" style="color: #2563eb; font-weight: 600;">
                        Sign Up
                    </a>
                </p>
            </div>
        </form>
    </div>
</section>

        <?php elseif ($page == 'register'): ?>
            <!-- Register Page -->
            <section id="register-page" class="page active">
                <div class="form-container">
                    <h2 class="form-title">Create an Account</h2>
                    <form method="POST" action="register.php">
                        <div class="form-group">
                            <label class="form-label" for="register-name">Full Name *</label>
                            <input type="text" class="form-control" id="register-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="register-email">Email Address *</label>
                            <input type="email" class="form-control" id="register-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="register-password">Password *</label>
                            <input type="password" class="form-control" id="register-password" name="password" required>
                            <span class="password-toggle" id="register-password-toggle">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="register-confirm">Confirm Password *</label>
                            <input type="password" class="form-control" id="register-confirm" name="confirm_password" required>
                            <span class="password-toggle" id="register-confirm-toggle">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <button type="submit" class="btn btn-block" name="submit">
                            <i class="fas fa-user-plus"></i>
                            Create Account
                        </button>
                        <div class="auth-switch">
                            <p>Already have an account? <a href="index.php?page=login" class="form-link">Log In</a></p>
                        </div>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-container">
                <div>
                    <div class="footer-logo">
                        <i class="fas fa-bolt"></i>
                        ElectroHub
                    </div>
                    <p style="color: #cbd5e1; margin-bottom: 25px; line-height: 1.7">
                        Your trusted destination for the latest electronics and gadgets.
                        Quality products with warranty and excellent customer service.
                    </p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <div>
                    <h3 class="footer-title">Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="index.php?page=home"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="index.php?page=products"><i class="fas fa-chevron-right"></i> Products</a></li>
                        <li><a href="index.php?page=cart"><i class="fas fa-chevron-right"></i> Cart</a></li>
                        <li><a href="index.php?page=contact"><i class="fas fa-chevron-right"></i> Contact</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="footer-title">Support</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Shipping Policy</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Returns & Exchanges</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="footer-title">Contact Info</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-map-marker-alt"></i> 123 Tech Street, San Francisco</a></li>
                        <li><a href="#"><i class="fas fa-phone"></i> +1 (555) 123-4567</a></li>
                        <li><a href="#"><i class="fas fa-envelope"></i> support@electrohub.com</a></li>
                        <li><a href="#"><i class="fas fa-clock"></i> Mon-Fri: 9am-8pm</a></li>
                    </ul>
                </div>
            </div>

            <div class="copyright">
                <p>&copy; 2023 ElectroHub. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="javascr (1).js"></script>
    <script>
        // وظائف خاصة بالصفحة
        function addToCart(productId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php?page=<?php echo $page; ?>';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'add_to_cart';
            input.value = '1';
            form.appendChild(input);
            
            const productInput = document.createElement('input');
            productInput.type = 'hidden';
            productInput.name = 'product_id';
            productInput.value = productId;
            form.appendChild(productInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        function updateCartQuantity(cartId, quantity) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php?page=cart';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'update_cart_quantity';
            input.value = '1';
            form.appendChild(input);
            
            const cartInput = document.createElement('input');
            cartInput.type = 'hidden';
            cartInput.name = 'cart_id';
            cartInput.value = cartId;
            form.appendChild(cartInput);
            
            const quantityInput = document.createElement('input');
            quantityInput.type = 'hidden';
            quantityInput.name = 'quantity';
            quantityInput.value = quantity;
            form.appendChild(quantityInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        function removeFromCart(cartId) {
            if (confirm('Are you sure you want to remove this item?')) {
                window.location.href = 'index.php?page=cart&remove_from_cart=' + cartId;
            }
        }

        function proceedToCheckout() {
            <?php if (!$isLoggedIn): ?>
                alert('Please login to proceed to checkout');
                window.location.href = 'index.php?page=login';
            <?php else: ?>
                window.location.href = 'index.php?page=checkout';
            <?php endif; ?>
        }

        function selectPaymentMethod(method) {
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            document.getElementById('payment-' + method).classList.add('selected');
            document.getElementById('payment_method').value = method;
        }

        // تهيئة اختيار طريقة الدفع
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('payment-credit')) {
                selectPaymentMethod('credit');
            }
            
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const navLinks = document.getElementById('navLinks');
            
            if (mobileMenuBtn && navLinks) {
                mobileMenuBtn.addEventListener('click', function() {
                    navLinks.classList.toggle('active');
                });
            }
            
            // Password toggles
            const loginToggle = document.getElementById('login-password-toggle');
            const loginPassword = document.getElementById('login-password');
            
            if (loginToggle && loginPassword) {
                loginToggle.addEventListener('click', function() {
                    const type = loginPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                    loginPassword.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
                });
            }
            
            const registerToggle = document.getElementById('register-password-toggle');
            const registerPassword = document.getElementById('register-password');
            
            if (registerToggle && registerPassword) {
                registerToggle.addEventListener('click', function() {
                    const type = registerPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                    registerPassword.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
                });
            }
            
            const confirmToggle = document.getElementById('register-confirm-toggle');
            const confirmPassword = document.getElementById('register-confirm');
            
            if (confirmToggle && confirmPassword) {
                confirmToggle.addEventListener('click', function() {
                    const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPassword.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
                });
            }
            
            // Auto-hide notifications
            setTimeout(() => {
                const notifications = document.querySelectorAll('.notification');
                notifications.forEach(notification => {
                    notification.style.display = 'none';
                });
            }, 5000);
        });
    
    // وظيفة لعرض/إخفاء كلمة المرور
function togglePassword(element) {
    const formGroup = element.closest('.form-group');
    const input = formGroup.querySelector('input[type="password"], input[type="text"]');
    const icon = element.querySelector('i');
    
    if (input.type === "password") {
        input.type = "text";
        icon.className = "fas fa-eye-slash";
    } else {
        input.type = "password";
        icon.className = "fas fa-eye";
    }
}

// مؤشر تحميل عند الدخول
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.querySelector('form[action="login.php"]');
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            if (button) {
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
                button.disabled = true;
            }
        });
    }
    
    // إخفاء الرسائل بعد 5 ثواني
    setTimeout(function() {
        const messages = document.querySelectorAll('[style*="background: #ffebee"], [style*="background: #e8f5e9"]');
        messages.forEach(msg => msg.style.display = 'none');
    }, 5000);
});

</script>
</body>
</html>