// ============================================
// APPLICATION STATE & DATA
// ============================================

const state = {
  currentUser: null,
  cart: [],
  products: []
};

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener("DOMContentLoaded", function () {
  // تحميل البيانات من الصفحة
  loadPageData();
  
  // Setup event listeners
  setupEventListeners();
});

function loadPageData() {
  // سيتم تحميل البيانات من PHP مباشرة
  // Cart count سيتم عرضه من PHP
}

function setupEventListeners() {
  // Mobile menu toggle
  const mobileMenuBtn = document.getElementById("mobileMenuBtn");
  const navLinks = document.getElementById("navLinks");
  
  if (mobileMenuBtn && navLinks) {
    mobileMenuBtn.addEventListener("click", function () {
      navLinks.classList.toggle("active");
    });
  }

  // Close mobile menu when clicking outside
  document.addEventListener("click", function (event) {
    if (
      !event.target.closest(".header-container") &&
      navLinks &&
      navLinks.classList.contains("active")
    ) {
      navLinks.classList.remove("active");
    }
  });

  // Password toggles
  setupPasswordToggles();
}

function setupPasswordToggles() {
  // Register password toggle
  const registerPasswordToggle = document.getElementById("register-password-toggle");
  const registerPassword = document.getElementById("register-password");
  const registerConfirmToggle = document.getElementById("register-confirm-toggle");
  const registerConfirm = document.getElementById("register-confirm");

  if (registerPasswordToggle && registerPassword) {
    registerPasswordToggle.addEventListener("click", function () {
      const type = registerPassword.getAttribute("type") === "password" ? "text" : "password";
      registerPassword.setAttribute("type", type);
      this.innerHTML = type === "password" ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
  }

  if (registerConfirmToggle && registerConfirm) {
    registerConfirmToggle.addEventListener("click", function () {
      const type = registerConfirm.getAttribute("type") === "password" ? "text" : "password";
      registerConfirm.setAttribute("type", type);
      this.innerHTML = type === "password" ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
  }

  // Login password toggle
  const loginPasswordToggle = document.getElementById("login-password-toggle");
  const loginPassword = document.getElementById("login-password");

  if (loginPasswordToggle && loginPassword) {
    loginPasswordToggle.addEventListener("click", function () {
      const type = loginPassword.getAttribute("type") === "password" ? "text" : "password";
      loginPassword.setAttribute("type", type);
      this.innerHTML = type === "password" ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
  }
}

// ============================================
// PAGE NAVIGATION
// ============================================
function showPage(pageId) {
  window.location.href = 'index.php?page=' + pageId;
}

function filterProducts(category) {
  window.location.href = 'index.php?page=products&category=' + category;
}

// ============================================
// CART FUNCTIONALITY
// ============================================
function addToCart(productId) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'index.php?page=products';
  
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
  window.location.href = 'index.php?page=checkout';
}

// ============================================
// CHECKOUT FUNCTIONALITY
// ============================================
function selectPaymentMethod(method) {
  // Remove selected class from all
  document.querySelectorAll(".payment-method").forEach((el) => {
    el.classList.remove("selected");
  });
  
  // Add selected class to clicked
  const methodElement = document.getElementById(`payment-${method}`);
  if (methodElement) {
    methodElement.classList.add("selected");
    // Update hidden input
    const paymentInput = document.getElementById('payment_method');
    if (paymentInput) {
      paymentInput.value = method;
    }
  }
}

function placeOrder() {
  const orderForm = document.getElementById('checkoutForm');
  if (orderForm) {
    orderForm.submit();
  }
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function getCategoryName(category) {
  const categories = {
    smartphone: "Smartphone",
    laptop: "Laptop",
    audio: "Audio",
    gaming: "Gaming",
    wearable: "Wearable",
    tablet: "Tablet",
    accessory: "Accessory",
  };

  return categories[category] || "Electronics";
}

function generateStarRating(rating) {
  let stars = "";
  const fullStars = Math.floor(rating);
  const hasHalfStar = rating % 1 >= 0.5;

  for (let i = 0; i < 5; i++) {
    if (i < fullStars) {
      stars += '<i class="fas fa-star"></i>';
    } else if (i === fullStars && hasHalfStar) {
      stars += '<i class="fas fa-star-half-alt"></i>';
    } else {
      stars += '<i class="far fa-star"></i>';
    }
  }

  return stars;
}

// Auto-hide notifications
setTimeout(() => {
  const notifications = document.querySelectorAll('.notification');
  notifications.forEach(notification => {
    notification.style.display = 'none';
  });
}, 5000);

// Initialize payment method on checkout page
if (document.getElementById('payment-credit')) {
  selectPaymentMethod('credit');
}