<?php
session_start();
require_once "Database.php";

// التحقق إذا كان المستخدم أدمن

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: index.php?page=login");
    exit();
}

// تحميل البيانات من قاعدة البيانات
try {
    $db = Database::getInstance()->getConnection();
    
    // إحصائيات لوحة التحكم
    $statsStmt = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM products) as total_products,
            (SELECT COUNT(*) FROM orders) as total_orders,
            (SELECT COUNT(*) FROM users) as total_customers,
            (SELECT SUM(total) FROM orders WHERE status = 'completed') as total_revenue
    ");
    $stats = $statsStmt->fetch();
    
    // الطلبات الحديثة
    $recentOrdersStmt = $db->query("
        SELECT o.*, u.name as customer_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $recentOrders = $recentOrdersStmt->fetchAll();
    
    // المنتجات
    $productsStmt = $db->query("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC
    ");
    $products = $productsStmt->fetchAll();
    
    // العملاء
    $customersStmt = $db->query("
        SELECT u.*, 
               COUNT(o.id) as total_orders,
               COALESCE(SUM(o.total), 0) as total_spent
        FROM users u 
        LEFT JOIN orders o ON u.id = o.user_id 
        WHERE u.role = 'user'
        GROUP BY u.id 
        ORDER BY u.created_at DESC
    ");
    $customers = $customersStmt->fetchAll();
    
    // الفئات
    $categoriesStmt = $db->query("SELECT * FROM categories ORDER BY name");
    $categories = $categoriesStmt->fetchAll();
    
} catch (PDOException $e) {
    $stats = ['total_products' => 0, 'total_orders' => 0, 'total_customers' => 0, 'total_revenue' => 0];
    $recentOrders = [];
    $products = [];
    $customers = [];
    $categories = [];
}

// معالجة تحديث حالة الطلب
if (isset($_POST['update_order_status'])) {
    $orderId = $_POST['order_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        
        $_SESSION['admin_success'] = "Order status updated!";
        header("Location: admin.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Error updating order";
        header("Location: admin.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ElectroHub-Admin Dashboard</title>
    <link rel="stylesheet" href="styleadmin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- الرسائل -->
    <?php if (isset($_SESSION['admin_success'])): ?>
        <div class="notification success">
            <?php echo $_SESSION['admin_success']; unset($_SESSION['admin_success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['admin_error'])): ?>
        <div class="notification error">
            <?php echo $_SESSION['admin_error']; unset($_SESSION['admin_error']); ?>
        </div>
    <?php endif; ?>

    <!-- باقي كود الـ admin.html كما هو مع تحديث البيانات -->
    <div id="dashboard" class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <i class="fas fa-laptop-code"></i>
                <h2>Electro<span>Hub</span></h2>
                <p>Admin Panel</p>
            </div>
            
            <nav class="menu">
                <ul>
                    <li class="active" data-section="dashboard-section">
                        <a href="#">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li data-section="products-section">
                        <a href="#">
                            <i class="fas fa-microchip"></i>
                            <span>Products</span>
                        </a>
                    </li>
                    <li data-section="orders-section">
                        <a href="#">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    <li data-section="customers-section">
                        <a href="#">
                            <i class="fas fa-users"></i>
                            <span>Customers</span>
                        </a>
                    </li>
                    <li data-section="categories-section">
                        <a href="#">
                            <i class="fas fa-tags"></i>
                            <span>Categories</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <a href="logout.php" class="btn btn-warning btn-block">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <h1 id="page-title">Dashboard</h1>
                    <div class="breadcrumb">
                        <span>Admin</span> / <span id="current-page">Dashboard</span>
                    </div>
                </div>
            </header>

            <!-- Dashboard Section -->
            <section id="dashboard-section" class="content-section">
                <div class="section-header">
                    <h2>Overview</h2>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon revenue">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Revenue</h3>
                            <div class="stat-number">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Orders</h3>
                            <div class="stat-number"><?php echo $stats['total_orders'] ?? 0; ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon products">
                            <i class="fas fa-microchip"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Products</h3>
                            <div class="stat-number"><?php echo $stats['total_products'] ?? 0; ?></div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon customers">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Customers</h3>
                            <div class="stat-number"><?php echo $stats['total_customers'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <div class="content-grid">
                    <div class="grid-card">
                        <div class="card-header">
                            <h3>Recent Orders</h3>
                        </div>
                        <div class="card-body">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        <td>$<?php echo number_format($order['total'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Products Section -->
            <section id="products-section" class="content-section" style="display: none;">
                <div class="section-header">
                    <h2>Manage Products</h2>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="showAddProductModal()">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo $product['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                    <?php if ($product['sku']): ?>
                                    <br><small>SKU: <?php echo $product['sku']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $product['category_name'] ?? 'Uncategorized'; ?></td>
                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo $product['stock']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $product['status']; ?>">
                                        <?php echo ucfirst($product['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Orders Section -->
            <section id="orders-section" class="content-section" style="display: none;">
                <div class="section-header">
                    <h2>Manage Orders</h2>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td>$<?php echo number_format($order['total'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <select name="status" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <input type="hidden" name="update_order_status" value="1">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Customers Section -->
            <section id="customers-section" class="content-section" style="display: none;">
                <div class="section-header">
                    <h2>Manage Customers</h2>
                </div>
                
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo $customer['id']; ?></td>
                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                <td><?php echo $customer['email']; ?></td>
                                <td><?php echo $customer['total_orders']; ?></td>
                                <td>$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Categories Section -->
            <section id="categories-section" class="content-section" style="display: none;">
                <div class="section-header">
                    <h2>Product Categories</h2>
                </div>
                
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                    <div class="category-card">
                        <div class="category-icon">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="category-info">
                            <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                            <?php if ($category['description']): ?>
                            <p><?php echo htmlspecialchars($category['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>

   <!-- Add Product Modal -->
<div id="add-product-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Product</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="add_product.php">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Enter product name">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Enter product description"></textarea>
                </div>
                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Stock Quantity *</label>
                    <input type="number" name="stock" class="form-control" min="0" required placeholder="0">
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- value="1" -->
                <input type="hidden" name="add_product">
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Save Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

                    <!-- <div class="form-group">
                        <label>SKU</label>
                        <input type="text" name="sku" class="form-control">
                    </div> 
                     <div class="form-group">
                        <button type="submit" name="add_product" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Save Product
                        </button>
                    </div> -->
                </form>
            </div>
        </div>
    </div>

    <script>
        // Navigation
        document.querySelectorAll('.menu li').forEach(item => {
            item.addEventListener('click', function() {
                if (this.classList.contains('active')) return;
                
                // Remove active class from all
                document.querySelectorAll('.menu li').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                // Hide all sections
                document.querySelectorAll('.content-section').forEach(section => {
                    section.style.display = 'none';
                });
                
                // Show selected section
                const sectionId = this.getAttribute('data-section');
                document.getElementById(sectionId).style.display = 'block';
                
                // Update page title
                const sectionName = this.querySelector('span').textContent;
                document.getElementById('page-title').textContent = sectionName;
                document.getElementById('current-page').textContent = sectionName;
            });
        });

        // Modal functions
        function showAddProductModal() {
            document.getElementById('add-product-modal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('add-product-modal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('add-product-modal');
            if (event.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html>