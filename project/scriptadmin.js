
// Configuration
const API_BASE_URL = 'api/'; // Base URL for API endpoints

// Global variables
let currentUser = null;
let csrfToken = '';

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is already logged in
    checkLoginStatus();
    
    // Setup event listeners
    setupEventListeners();
    
    // Set current date
    setCurrentDate();
});

// Check login status from session
function checkLoginStatus() {
    fetch(API_BASE_URL + 'check_session.php')
        .then(response => response.json())
        .then(data => {
            if (data.logged_in) {
                currentUser = data.user;
                csrfToken = data.csrf_token;
                showDashboard();
                loadDashboardData();
            } else {
                showLoginPage();
            }
        })
        .catch(error => {
            console.error('Session check error:', error);
            showLoginPage();
        });
}

// Setup all event listeners
function setupEventListeners() {
    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            loginUser(username, password);
        });
    }
    
    // Logout button
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logoutUser);
    }
    
    // Navigation menu
    document.querySelectorAll('.menu li').forEach(item => {
        item.addEventListener('click', function() {
            if (this.classList.contains('active')) return;
            
            // Remove active class from all menu items
            document.querySelectorAll('.menu li').forEach(i => i.classList.remove('active'));
            // Add active class to clicked item
            this.classList.add('active');
            
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            const sectionId = this.getAttribute('data-section');
            document.getElementById(sectionId).style.display = 'block';
            
            // Update page title and breadcrumb
            const sectionName = this.querySelector('span').textContent;
            document.getElementById('page-title').textContent = sectionName;
            document.getElementById('current-page').textContent = sectionName;
            
            // Load section data
            loadSectionData(sectionId);
        });
    });
    
    // Tab buttons
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            
            // Load tab data
            if (tabId === 'all-orders') {
                loadOrders();
            }
        });
    });
    
    // Search and filter events
    const productSearch = document.getElementById('product-search');
    if (productSearch) {
        productSearch.addEventListener('input', debounce(filterProducts, 300));
    }
    
    const categoryFilter = document.getElementById('category-filter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterProducts);
    }
    
    const statusFilter = document.getElementById('status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', filterProducts);
    }
    
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('select-all-products');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('#products-table input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Period selector
    const periodSelector = document.getElementById('period-selector');
    if (periodSelector) {
        periodSelector.addEventListener('change', function() {
            loadDashboardStats(this.value);
        });
    }
    
    // Report generation
    const generateReportBtn = document.querySelector('#reports-section .btn-primary');
    if (generateReportBtn) {
        generateReportBtn.addEventListener('click', generateReport);
    }
    
    // Settings form
    const settingsForm = document.querySelector('.settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', saveSettings);
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('add-modal');
        if (event.target === modal) {
            closeModal();
        }
    });
}

// ====================
// AUTHENTICATION FUNCTIONS
// ====================

// Login user
function loginUser(username, password) {
    const formData = new FormData();
    formData.append('username', username);
    formData.append('password', password);
    
    fetch(API_BASE_URL + 'login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentUser = data.user;
            csrfToken = data.csrf_token;
            showDashboard();
            loadDashboardData();
            showNotification('Login successful!', 'success');
        } else {
            showNotification(data.message || 'Login failed!', 'error');
        }
    })
    .catch(error => {
        console.error('Login error:', error);
        showNotification('Connection error. Please try again.', 'error');
    });
}

// Logout user
function logoutUser() {
    fetch(API_BASE_URL + 'logout.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentUser = null;
                csrfToken = '';
                showLoginPage();
                showNotification('Logged out successfully.', 'success');
            }
        })
        .catch(error => {
            console.error('Logout error:', error);
            showNotification('Logout error.', 'error');
        });
}

// ====================
// PAGE MANAGEMENT
// ====================

// Show login page
function showLoginPage() {
    document.getElementById('login-page').style.display = 'flex';
    document.getElementById('dashboard').style.display = 'none';
    if (document.getElementById('password')) {
        document.getElementById('password').value = '';
    }
}

// Show dashboard
function showDashboard() {
    document.getElementById('login-page').style.display = 'none';
    document.getElementById('dashboard').style.display = 'flex';
    
    // Update user info in header
    if (currentUser) {
        const userAvatar = document.querySelector('.user-avatar img');
        const userName = document.querySelector('.user-details h3');
        const userEmail = document.querySelector('.user-details p');
        
        if (userName) userName.textContent = currentUser.name;
        if (userEmail) userEmail.textContent = currentUser.email;
        if (userAvatar) {
            userAvatar.src = currentUser.avatar || 
                `https://ui-avatars.com/api/?name=${encodeURIComponent(currentUser.name)}&background=4361ee&color=fff`;
        }
    }
}

// Set current date
function setCurrentDate() {
    const now = new Date();
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    };
    const dateString = now.toLocaleDateString('en-US', options);
    
    const dateElements = document.querySelectorAll('.current-date');
    dateElements.forEach(el => {
        el.textContent = dateString;
    });
}

// Load section data
function loadSectionData(sectionId) {
    switch(sectionId) {
        case 'dashboard-section':
            loadDashboardData();
            break;
        case 'products-section':
            loadProducts();
            break;
        case 'orders-section':
            loadOrders();
            break;
        case 'customers-section':
            loadCustomers();
            break;
        case 'categories-section':
            loadCategories();
            break;
        case 'inventory-section':
            loadInventory();
            break;
        case 'reports-section':
            loadReportFilters();
            break;
        case 'settings-section':
            loadSettings();
            break;
    }
}

// ====================
// DASHBOARD FUNCTIONS
// ====================

// Load dashboard data
function loadDashboardData() {
    loadDashboardStats('month');
    loadRecentOrders();
    loadTopProducts();
}

// Load dashboard statistics
function loadDashboardStats(period = 'month') {
    fetch(`${API_BASE_URL}dashboard_stats.php?period=${period}`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateStatsDisplay(data.stats);
        } else {
            showNotification('Failed to load statistics.', 'error');
        }
    })
    .catch(error => {
        console.error('Stats load error:', error);
        showNotification('Failed to load statistics.', 'error');
    });
}

// Update stats display
function updateStatsDisplay(stats) {
    const statElements = [
        { selector: '.stat-card:nth-child(1) .stat-number', value: `$${formatNumber(stats.revenue)}` },
        { selector: '.stat-card:nth-child(2) .stat-number', value: formatNumber(stats.orders) },
        { selector: '.stat-card:nth-child(3) .stat-number', value: formatNumber(stats.products) },
        { selector: '.stat-card:nth-child(4) .stat-number', value: formatNumber(stats.customers) }
    ];
    
    statElements.forEach(stat => {
        const element = document.querySelector(stat.selector);
        if (element) {
            element.textContent = stat.value;
        }
    });
}

// Load recent orders for dashboard
function loadRecentOrders() {
    fetch(`${API_BASE_URL}recent_orders.php?limit=5`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderRecentOrders(data.orders);
        }
    })
    .catch(error => {
        console.error('Recent orders error:', error);
    });
}

// Render recent orders
function renderRecentOrders(orders) {
    const tbody = document.getElementById('recent-orders');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (orders.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center">No recent orders</td>
            </tr>
        `;
        return;
    }
    
    orders.forEach(order => {
        const statusClass = `status-${order.status}`;
        const statusText = order.status.charAt(0).toUpperCase() + order.status.slice(1);
        
        tbody.innerHTML += `
            <tr>
                <td>${order.order_id}</td>
                <td>${order.customer_name}</td>
                <td>${formatDate(order.order_date)}</td>
                <td>$${order.total_amount}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
            </tr>
        `;
    });
}

// Load top products
function loadTopProducts() {
    fetch(`${API_BASE_URL}top_products.php?limit=5`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderTopProducts(data.products);
        }
    })
    .catch(error => {
        console.error('Top products error:', error);
    });
}

// Render top products
function renderTopProducts(products) {
    const container = document.querySelector('.top-products-list');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (products.length === 0) {
        container.innerHTML = '<p class="text-center">No products found</p>';
        return;
    }
    
    products.forEach(product => {
        const statusClass = `status-${product.stock_status}`;
        
        container.innerHTML += `
            <div class="top-product-item">
                <div class="product-info">
                    <h4>${product.name}</h4>
                    <p>${product.category_name}</p>
                </div>
                <div class="product-stats">
                    <span class="price">$${product.price}</span>
                    <span class="status-badge ${statusClass}">${product.stock_quantity} in stock</span>
                </div>
            </div>
        `;
    });
}

// ====================
// PRODUCTS FUNCTIONS
// ====================

// Load products
function loadProducts() {
    showLoading('products-table');
    
    fetch(`${API_BASE_URL}products.php`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderProducts(data.products);
            updateProductsCount(data.products.length);
        } else {
            showNotification('Failed to load products.', 'error');
            document.getElementById('products-table').innerHTML = `
                <tr>
                    <td colspan="7" class="text-center">Failed to load products</td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Products load error:', error);
        showNotification('Connection error.', 'error');
    });
}

// Render products table
function renderProducts(products) {
    const tbody = document.getElementById('products-table');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (products.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center">No products found</td>
            </tr>
        `;
        return;
    }
    
    products.forEach(product => {
        const statusClass = `status-${product.stock_status}`;
        const statusText = product.stock_status.replace('_', ' ');
        
        tbody.innerHTML += `
            <tr>
                <td>
                    <input type="checkbox" class="product-checkbox" data-id="${product.id}">
                </td>
                <td>
                    <div class="product-cell">
                        <strong>${product.name}</strong>
                        <small>SKU: ${product.sku}</small>
                    </div>
                </td>
                <td>${product.category_name}</td>
                <td>$${product.price}</td>
                <td>${product.stock_quantity}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" onclick="viewProduct(${product.id})" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon" onclick="editProduct(${product.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon" onclick="deleteProduct(${product.id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
}

// Filter products
function filterProducts() {
    const searchTerm = document.getElementById('product-search')?.value || '';
    const categoryFilter = document.getElementById('category-filter')?.value || '';
    const statusFilter = document.getElementById('status-filter')?.value || '';
    
    const params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    if (categoryFilter) params.append('category', categoryFilter);
    if (statusFilter) params.append('status', statusFilter);
    
    showLoading('products-table');
    
    fetch(`${API_BASE_URL}products.php?${params.toString()}`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderProducts(data.products);
            updateProductsCount(data.products.length);
        }
    })
    .catch(error => {
        console.error('Filter error:', error);
    });
}

// Update products count
function updateProductsCount(count) {
    const countElement = document.getElementById('products-count');
    if (countElement) {
        countElement.textContent = count;
    }
}

// View product
function viewProduct(id) {
    fetch(`${API_BASE_URL}product.php?id=${id}`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showProductModal(data.product, 'view');
        } else {
            showNotification('Product not found.', 'error');
        }
    })
    .catch(error => {
        console.error('View product error:', error);
    });
}

// Edit product
function editProduct(id) {
    fetch(`${API_BASE_URL}product.php?id=${id}`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showProductModal(data.product, 'edit');
        } else {
            showNotification('Product not found.', 'error');
        }
    })
    .catch(error => {
        console.error('Edit product error:', error);
    });
}

// Delete product
function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', csrfToken);
    
    fetch(`${API_BASE_URL}delete_product.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Product deleted successfully!', 'success');
            loadProducts();
        } else {
            showNotification(data.message || 'Failed to delete product.', 'error');
        }
    })
    .catch(error => {
        console.error('Delete product error:', error);
        showNotification('Connection error.', 'error');
    });
}

// Show product modal
function showProductModal(product, mode = 'view') {
    const modal = document.getElementById('add-modal');
    const title = document.getElementById('modal-title');
    
    title.textContent = mode === 'view' ? 'View Product' : 'Edit Product';
    
    let formHTML = `
        <div class="form-group">
            <label>Product Name</label>
            <input type="text" class="form-control" id="product-name" value="${escapeHtml(product.name)}" ${mode === 'view' ? 'readonly' : 'required'}>
        </div>
        <div class="form-group">
            <label>Category</label>
            <select class="form-control" id="product-category" ${mode === 'view' ? 'disabled' : 'required'}>
                <option value="">Select Category</option>
                <!-- Categories will be loaded via AJAX -->
            </select>
        </div>
        <div class="row">
            <div class="form-group">
                <label>Price ($)</label>
                <input type="number" class="form-control" id="product-price" step="0.01" value="${product.price}" ${mode === 'view' ? 'readonly' : 'required'}>
            </div>
            <div class="form-group">
                <label>Stock Quantity</label>
                <input type="number" class="form-control" id="product-stock" value="${product.stock_quantity}" ${mode === 'view' ? 'readonly' : 'required'}>
            </div>
        </div>
        <div class="form-group">
            <label>SKU</label>
            <input type="text" class="form-control" id="product-sku" value="${escapeHtml(product.sku)}" ${mode === 'view' ? 'readonly' : 'required'}>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea class="form-control" id="product-description" rows="3" ${mode === 'view' ? 'readonly' : ''}>${escapeHtml(product.description || '')}</textarea>
        </div>
    `;
    
    if (mode === 'edit') {
        formHTML += `
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" class="form-control" id="product-image" accept="image/*">
                ${product.image ? `<small>Current: ${product.image}</small>` : ''}
            </div>
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Update Product
                </button>
            </div>
        `;
    } else {
        formHTML += `
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                <button type="button" class="btn btn-primary" onclick="editProduct(${product.id})">Edit</button>
            </div>
        `;
    }
    
    document.getElementById('add-form').innerHTML = formHTML;
    
    // Load categories for dropdown
    if (mode === 'edit') {
        loadCategoriesForDropdown(product.category_id);
        
        // Set form submit handler
        document.getElementById('add-form').onsubmit = function(e) {
            e.preventDefault();
            updateProduct(product.id);
        };
    }
    
    modal.style.display = 'flex';
}

// ====================
// ORDERS FUNCTIONS
// ====================

// Load orders
function loadOrders() {
    showLoading('orders-table');
    
    fetch(`${API_BASE_URL}orders.php`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderOrders(data.orders);
        } else {
            showNotification('Failed to load orders.', 'error');
        }
    })
    .catch(error => {
        console.error('Orders load error:', error);
    });
}

// Render orders table
function renderOrders(orders) {
    const tbody = document.getElementById('orders-table');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (orders.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center">No orders found</td>
            </tr>
        `;
        return;
    }
    
    orders.forEach(order => {
        const statusClass = `status-${order.status}`;
        const statusText = order.status.charAt(0).toUpperCase() + order.status.slice(1);
        
        tbody.innerHTML += `
            <tr>
                <td>${order.order_id}</td>
                <td>${order.customer_name}</td>
                <td>${formatDate(order.order_date)}</td>
                <td>${order.items_count} items</td>
                <td>$${order.total_amount}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-primary" onclick="viewOrder(${order.id})">
                            View
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="updateOrderStatus(${order.id})">
                            Update
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
}

// ====================
// CUSTOMERS FUNCTIONS
// ====================

// Load customers
function loadCustomers() {
    showLoading('customers-table');
    
    fetch(`${API_BASE_URL}customers.php`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderCustomers(data.customers);
        } else {
            showNotification('Failed to load customers.', 'error');
        }
    })
    .catch(error => {
        console.error('Customers load error:', error);
    });
}

// ====================
// CATEGORIES FUNCTIONS
// ====================

// Load categories
function loadCategories() {
    showLoading('categories-grid');
    
    fetch(`${API_BASE_URL}categories.php`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderCategories(data.categories);
        } else {
            showNotification('Failed to load categories.', 'error');
        }
    })
    .catch(error => {
        console.error('Categories load error:', error);
    });
}

// Load categories for dropdown
function loadCategoriesForDropdown(selectedId = null) {
    fetch(`${API_BASE_URL}categories.php`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('product-category');
            if (select) {
                select.innerHTML = '<option value="">Select Category</option>';
                data.categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.name;
                    if (selectedId && category.id == selectedId) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            }
        }
    })
    .catch(error => {
        console.error('Categories dropdown error:', error);
    });
}

// ====================
// INVENTORY FUNCTIONS
// ====================

// Load inventory
function loadInventory() {
    showLoading('inventory-table');
    
    fetch(`${API_BASE_URL}inventory.php`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderInventory(data.inventory);
            updateInventoryStats(data.stats);
        } else {
            showNotification('Failed to load inventory.', 'error');
        }
    })
    .catch(error => {
        console.error('Inventory load error:', error);
    });
}

// ====================
// REPORTS FUNCTIONS
// ====================

// Load report filters
function loadReportFilters() {
    // Set default date range (last 30 days)
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    
    document.getElementById('report-from').valueAsDate = startDate;
    document.getElementById('report-to').valueAsDate = endDate;
}

// Generate report
function generateReport() {
    const fromDate = document.getElementById('report-from').value;
    const toDate = document.getElementById('report-to').value;
    const reportType = document.getElementById('report-type').value;
    
    if (!fromDate || !toDate) {
        showNotification('Please select date range.', 'warning');
        return;
    }
    
    const params = new URLSearchParams({
        from_date: fromDate,
        to_date: toDate,
        type: reportType
    });
    
    showLoading('report-chart');
    
    fetch(`${API_BASE_URL}generate_report.php?${params.toString()}`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderReport(data.report);
        } else {
            showNotification('Failed to generate report.', 'error');
        }
    })
    .catch(error => {
        console.error('Report error:', error);
    });
}

// ====================
// SETTINGS FUNCTIONS
// ====================

// Load settings
function loadSettings() {
    fetch(`${API_BASE_URL}settings.php`, {
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            populateSettingsForm(data.settings);
        }
    })
    .catch(error => {
        console.error('Settings load error:', error);
    });
}

// Save settings
function saveSettings(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('csrf_token', csrfToken);
    
    fetch(`${API_BASE_URL}save_settings.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Settings saved successfully!', 'success');
        } else {
            showNotification(data.message || 'Failed to save settings.', 'error');
        }
    })
    .catch(error => {
        console.error('Save settings error:', error);
        showNotification('Connection error.', 'error');
    });
}

// ====================
// MODAL FUNCTIONS
// ====================

// Show add modal
function showAddModal(type) {
    const modal = document.getElementById('add-modal');
    const form = document.getElementById('add-form');
    const title = document.getElementById('modal-title');
    
    // Set modal title
    const typeNames = {
        'product': 'Product',
        'order': 'Order',
        'customer': 'Customer',
        'category': 'Category',
        'inventory': 'Inventory'
    };
    
    title.textContent = `Add New ${typeNames[type]}`;
    
    // Generate form based on type
    let formHTML = '';
    
    if (type === 'product') {
        formHTML = `
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" class="form-control" id="product-name" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select class="form-control" id="product-category" required>
                    <option value="">Select Category</option>
                    <!-- Categories will be loaded via AJAX -->
                </select>
            </div>
            <div class="row">
                <div class="form-group">
                    <label>Price ($)</label>
                    <input type="number" class="form-control" id="product-price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" class="form-control" id="product-stock" required>
                </div>
            </div>
            <div class="form-group">
                <label>SKU</label>
                <input type="text" class="form-control" id="product-sku" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea class="form-control" id="product-description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" class="form-control" id="product-image" accept="image/*">
            </div>
            <div class="form-group" style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Save Product
                </button>
            </div>
        `;
    }
    // Add other form types as needed
    
    form.innerHTML = formHTML;
    
    // Load dynamic data for form
    if (type === 'product') {
        loadCategoriesForDropdown();
    }
    
    // Set form submit handler
    form.onsubmit = function(e) {
        e.preventDefault();
        handleAddSubmit(type);
    };
    
    // Show modal
    modal.style.display = 'flex';
}

// Handle add form submission
function handleAddSubmit(type) {
    const formData = new FormData();
    
    if (type === 'product') {
        formData.append('name', document.getElementById('product-name').value);
        formData.append('category_id', document.getElementById('product-category').value);
        formData.append('price', document.getElementById('product-price').value);
        formData.append('stock_quantity', document.getElementById('product-stock').value);
        formData.append('sku', document.getElementById('product-sku').value);
        formData.append('description', document.getElementById('product-description').value);
        
        const imageInput = document.getElementById('product-image');
        if (imageInput.files[0]) {
            formData.append('image', imageInput.files[0]);
        }
    }
    
    formData.append('type', type);
    formData.append('csrf_token', csrfToken);
    
    fetch(`${API_BASE_URL}add_item.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            showNotification(`${type.charAt(0).toUpperCase() + type.slice(1)} added successfully!`, 'success');
            
            // Refresh the relevant section
            switch(type) {
                case 'product':
                    loadProducts();
                    break;
                case 'order':
                    loadOrders();
                    loadRecentOrders();
                    break;
                case 'customer':
                    loadCustomers();
                    break;
                case 'category':
                    loadCategories();
                    break;
            }
        } else {
            showNotification(data.message || 'Failed to add item.', 'error');
        }
    })
    .catch(error => {
        console.error('Add item error:', error);
        showNotification('Connection error.', 'error');
    });
}

// Close modal
function closeModal() {
    document.getElementById('add-modal').style.display = 'none';
}

// ====================
// UTILITY FUNCTIONS
// ====================

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    const messageElement = document.getElementById('notification-message');
    
    if (!notification || !messageElement) return;
    
    // Set message and type
    messageElement.textContent = message;
    notification.className = 'notification';
    notification.classList.add(`notification-${type}`);
    
    // Show notification
    notification.style.display = 'block';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        notification.style.display = 'none';
    }, 5000);
}

// Show loading state
function showLoading(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        if (element.tagName === 'TBODY') {
            element.innerHTML = `
                <tr>
                    <td colspan="${element.querySelector('th')?.length || 7}" class="text-center">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
                    </td>
                </tr>
            `;
        } else if (element.classList.contains('categories-grid')) {
            element.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        } else {
            element.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        }
    }
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Format number
function formatNumber(num) {
    return num.toLocaleString('en-US');
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Add CSS for loading spinner
const style = document.createElement('style');
style.textContent = `
    .loading-spinner {
        text-align: center;
        padding: 20px;
        color: #666;
    }
    
    .loading-spinner i {
        margin-right: 10px;
    }
    
    .text-center {
        text-align: center;
    }
    
    .notification-success {
        background-color: #2ecc71;
    }
    .notification-error {
        background-color: #e74c3c;
    }
    .notification-warning {
        background-color: #f39c12;
    }
    .notification-info {
        background-color: #3498db;
    }
    
    .top-product-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #eee;
    }
    
    .top-product-item:last-child {
        border-bottom: none;
    }
    
    .product-cell, .customer-cell {
        display: flex;
        flex-direction: column;
    }
    
    .product-cell small, .customer-cell small {
        color: #666;
        font-size: 0.85rem;
    }
    
    .product-stats {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
    }
    
    .product-stats .price {
        font-weight: bold;
        color: #2c3e50;
    }
    
    .action-buttons {
        display: flex;
        gap: 5px;
    }
    
    .row {
        display: flex;
        gap: 15px;
    }
    
    .row .form-group {
        flex: 1;
    }
    
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
`;
document.head.appendChild(style);