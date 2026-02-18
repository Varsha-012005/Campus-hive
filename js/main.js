// Theme Management
function toggleMode() {
    const currentTheme = document.body.dataset.theme;
    const newTheme = currentTheme === "dark" ? "light" : "dark";
    document.body.dataset.theme = newTheme;
    localStorage.setItem('theme', newTheme);
    updateThemeDependentElements(newTheme);
}

function initTheme() {
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = savedTheme || (systemPrefersDark ? "dark" : "light");
    document.body.dataset.theme = theme;
    updateThemeDependentElements(theme);
}

function updateThemeDependentElements(theme) {
    const themeIcons = document.querySelectorAll('.theme-icon');
    themeIcons.forEach(icon => {
        icon.textContent = theme === "dark" ? "🌞" : "🌙";
    });
    
    // Update other theme-dependent elements
    const logo = document.getElementById('main-logo');
    if (logo) {
        logo.src = theme === "dark" ? "assets/logo-light.png" : "assets/logo-dark.png";
    }
}

// Animated Bee Cursor
function setupBeeCursor() {
    const beeCursor = document.getElementById("bee-cursor");
    if (!beeCursor) return;

    let mouseX = 0, mouseY = 0;
    let beeX = 0, beeY = 0;
    const ease = 0.1;
    let isHovering = false;
    let scale = 1;

    function animateBee() {
        const dx = mouseX - beeX;
        const dy = mouseY - beeY;

        beeX += dx * ease;
        beeY += dy * ease;

        const bob = isHovering ? Math.sin(Date.now() / 200) * 5 : 0;
        
        beeCursor.style.transform = `translate(${beeX}px, ${beeY + bob}px) rotate(${dx * 0.1}deg) scale(${scale})`;
        requestAnimationFrame(animateBee);
    }

    document.addEventListener("mousemove", (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
    });

    const hoverElements = document.querySelectorAll('a, button, .honeycomb-cell, [data-hover-effect]');
    hoverElements.forEach(el => {
        el.addEventListener('mouseenter', () => {
            beeCursor.classList.add('hover');
            isHovering = true;
            scale = 1.2;
        });
        el.addEventListener('mouseleave', () => {
            beeCursor.classList.remove('hover');
            isHovering = false;
            scale = 1;
        });
    });

    animateBee();
    return beeCursor;
}

// Service Page Initialization
function initServicePage() {
    // Initialize date pickers
    const datePickers = document.querySelectorAll('.date-picker');
    datePickers.forEach(picker => {
        picker.min = new Date().toISOString().split('T')[0];
        picker.value = new Date().toISOString().split('T')[0];
    });

    // Initialize time pickers
    const timePickers = document.querySelectorAll('.time-picker');
    timePickers.forEach(picker => {
        const now = new Date();
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        picker.value = `${hours}:${minutes}`;
    });

    // Initialize form validations
    const forms = document.querySelectorAll('.service-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                showToast('Please fill all required fields correctly', 'error');
            }
            this.classList.add('was-validated');
        }, false);
    });

    // Initialize interactive elements
    setupInteractiveCards();
    setupAccordions();
    setupTabs();
    setupModals();
}

function setupInteractiveCards() {
    const cards = document.querySelectorAll('.service-card, .room-card, .book-card, .job-card');
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
            
            if (this.dataset.target) {
                window.location.href = this.dataset.target;
            }
        });
    });
}

function setupAccordions() {
    const accordions = document.querySelectorAll('.accordion-button');
    accordions.forEach(button => {
        button.addEventListener('click', function() {
            this.classList.toggle('active');
            const panel = this.nextElementSibling;
            if (panel.style.maxHeight) {
                panel.style.maxHeight = null;
            } else {
                panel.style.maxHeight = panel.scrollHeight + "px";
            }
        });
    });
}

function setupTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    if (tabButtons.length > 0) {
        tabButtons[0].click(); // Activate first tab by default
    }
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            tabButtons.forEach(btn => {
                btn.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
            this.classList.add('active');
        });
    });
}

function setupModals() {
    const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.dataset.bsTarget;
            const modal = document.querySelector(modalId);
            if (modal) {
                const modalInstance = new bootstrap.Modal(modal);
                modalInstance.show();
            }
        });
    });
}

// AJAX Functions for Services
async function fetchServiceData(service, endpoint, params = {}) {
    try {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(`/api/${service}/${endpoint}?${queryString}`);
        if (!response.ok) throw new Error('Network response was not ok');
        return await response.json();
    } catch (error) {
        console.error('Error fetching data:', error);
        showToast('Failed to load data. Please try again.', 'error');
        return null;
    }
}

async function submitServiceForm(formId, service) {
    const form = document.getElementById(formId);
    const formData = new FormData(form);
    
    try {
        const response = await fetch(`/api/${service}/submit`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast(result.message || 'Operation successful!', 'success');
            form.reset();
            form.classList.remove('was-validated');
            
            if (result.redirect) {
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 1500);
            }
            
            // Refresh data if needed
            if (result.refresh) {
                const path = window.location.pathname.split('/').pop();
                switch(path) {
                    case 'hostel.php':
                        loadHostelRooms();
                        break;
                    case 'library.php':
                        searchBooks(document.getElementById('search-input').value);
                        break;
                    case 'canteen.php':
                        loadMenuItems();
                        updateCartCount();
                        break;
                    case 'recruitment.php':
                        loadJobPostings();
                        break;
                }
            }
        } else {
            throw new Error(result.message || 'Operation failed');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast(error.message, 'error');
    }
}

// Notification System
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast show align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '1100';
    document.body.appendChild(container);
    return container;
}

// Hostel Management
async function loadHostelRooms(hostelId = 1) {
    try {
        const response = await fetch(`/api/hostel/rooms?hostelId=${hostelId}`);
        const rooms = await response.json();
        
        const container = document.getElementById('rooms-container');
        if (!container) return;
        
        container.innerHTML = rooms.map(room => `
            <div class="col-md-4 mb-4">
                <div class="card room-card h-100" data-target="room-detail.php?id=${room.id}">
                    <img src="${room.image || 'assets/default-room.jpg'}" class="card-img-top" alt="Room ${room.number}">
                    <div class="card-body">
                        <h5 class="card-title">Room ${room.number}</h5>
                        <p class="card-text">${room.type} - Floor ${room.floor}</p>
                        <span class="badge ${room.available ? 'bg-success' : 'bg-warning'}">
                            ${room.available ? 'Available' : 'Occupied'}
                        </span>
                        <p class="mt-2">$${room.price}/month</p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <button class="btn btn-primary w-100" onclick="event.stopPropagation();showBookingModal(${room.id})">
                            Book Now
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Reattach event listeners
        setupInteractiveCards();
    } catch (error) {
        console.error('Error loading rooms:', error);
        showToast('Failed to load room data', 'error');
    }
}

function showBookingModal(roomId) {
    const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
    document.getElementById('booking-room-id').value = roomId;
    modal.show();
}

async function submitBooking() {
    const form = document.getElementById('booking-form');
    const formData = new FormData(form);
    
    try {
        const response = await fetch('/api/hostel/book', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('Booking successful!', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
            modal.hide();
            loadHostelRooms();
        } else {
            throw new Error(result.message || 'Booking failed');
        }
    } catch (error) {
        showToast(error.message, 'error');
    }
}

// Library Management
async function searchBooks(query = '') {
    try {
        const response = await fetch(`/api/library/search?query=${encodeURIComponent(query)}`);
        const books = await response.json();
        
        const container = document.getElementById('books-results');
        if (!container) return;
        
        container.innerHTML = books.map(book => `
            <div class="col-md-6 mb-4">
                <div class="card book-card h-100">
                    <div class="row g-0">
                        <div class="col-md-4">
                            <img src="${book.cover || 'assets/default-book.jpg'}" class="img-fluid rounded-start h-100" alt="${book.title}" style="object-fit: cover;">
                        </div>
                        <div class="col-md-8">
                            <div class="card-body d-flex flex-column h-100">
                                <h5 class="card-title">${book.title}</h5>
                                <p class="card-text">by ${book.author}</p>
                                <p class="card-text"><small class="text-muted">${book.category} • ${book.year}</small></p>
                                <div class="mt-auto">
                                    <span class="badge ${book.available > 0 ? 'bg-success' : 'bg-danger'}">
                                        ${book.available > 0 ? `${book.available} Available` : 'Checked Out'}
                                    </span>
                                    <button class="btn btn-primary btn-sm float-end" 
                                            onclick="borrowBook(${book.id})" 
                                            ${book.available <= 0 ? 'disabled' : ''}>
                                        Borrow
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error searching books:', error);
        showToast('Failed to search books', 'error');
    }
}

async function borrowBook(bookId) {
    try {
        const response = await fetch('/api/library/borrow', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ bookId })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast(result.message || 'Book borrowed successfully!', 'success');
            searchBooks(document.getElementById('search-input').value);
        } else {
            throw new Error(result.message || 'Failed to borrow book');
        }
    } catch (error) {
        showToast(error.message, 'error');
    }
}

// Canteen Management
let cartItems = JSON.parse(localStorage.getItem('cartItems')) || [];

async function loadMenuItems(categoryId = null) {
    try {
        const url = categoryId ? `/api/canteen/menu?category=${categoryId}` : '/api/canteen/menu';
        const response = await fetch(url);
        const items = await response.json();
        
        const container = document.getElementById('menu-items');
        if (!container) return;
        
        container.innerHTML = items.map(item => `
            <div class="menu-item card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div class="d-flex align-items-center flex-grow-1">
                            <img src="${item.image || 'assets/default-food.jpg'}" 
                                 alt="${item.name}" 
                                 class="me-3 rounded" 
                                 style="width: 80px; height: 80px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <h5>${item.name}</h5>
                                <p class="text-muted mb-1">${item.description || 'No description available'}</p>
                                <span class="badge ${item.vegetarian ? 'bg-success' : 'bg-danger'} me-2">
                                    ${item.vegetarian ? 'Vegetarian' : 'Non-Vegetarian'}
                                </span>
                                <span class="badge bg-info">
                                    ${item.category}
                                </span>
                            </div>
                        </div>
                        <div class="text-end" style="min-width: 120px;">
                            <h5 class="text-primary mb-2">$${item.price.toFixed(2)}</h5>
                            <button class="btn btn-outline-primary btn-sm" onclick="addToCart(${item.id}, '${item.name}', ${item.price})">
                                <i class="fas fa-plus"></i> Add to Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        updateCartCount();
    } catch (error) {
        console.error('Error loading menu:', error);
        showToast('Failed to load menu items', 'error');
    }
}

function addToCart(itemId, name, price) {
    const existingItem = cartItems.find(item => item.id === itemId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cartItems.push({
            id: itemId,
            name,
            price,
            quantity: 1
        });
    }
    
    localStorage.setItem('cartItems', JSON.stringify(cartItems));
    updateCartCount();
    showToast(`${name} added to cart`, 'success');
}

function updateCartCount() {
    const countElements = document.querySelectorAll('.cart-count');
    const totalItems = cartItems.reduce((sum, item) => sum + item.quantity, 0);
    
    countElements.forEach(el => {
        el.textContent = totalItems;
        el.style.display = totalItems > 0 ? 'inline-block' : 'none';
    });
}

function openCartModal() {
    const modalBody = document.getElementById('cart-items');
    if (!modalBody) return;
    
    if (cartItems.length === 0) {
        modalBody.innerHTML = '<p>Your cart is empty</p>';
        document.getElementById('cart-total').textContent = '$0.00';
        document.getElementById('checkout-btn').disabled = true;
        return;
    }
    
    modalBody.innerHTML = cartItems.map(item => `
        <div class="cart-item d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6>${item.name}</h6>
                <small>$${item.price.toFixed(2)} × ${item.quantity}</small>
            </div>
            <div class="d-flex align-items-center">
                <span class="me-3">$${(item.price * item.quantity).toFixed(2)}</span>
                <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
    
    const total = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    document.getElementById('cart-total').textContent = `$${total.toFixed(2)}`;
    document.getElementById('checkout-btn').disabled = false;
    
    const modal = new bootstrap.Modal(document.getElementById('cartModal'));
    modal.show();
}

function removeFromCart(itemId) {
    cartItems = cartItems.filter(item => item.id !== itemId);
    localStorage.setItem('cartItems', JSON.stringify(cartItems));
    openCartModal();
    updateCartCount();
}

async function checkout() {
    try {
        const response = await fetch('/api/canteen/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                items: cartItems,
                notes: document.getElementById('order-notes').value
            })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('Order placed successfully!', 'success');
            cartItems = [];
            localStorage.setItem('cartItems', JSON.stringify(cartItems));
            updateCartCount();
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('cartModal'));
            modal.hide();
        } else {
            throw new Error(result.message || 'Checkout failed');
        }
    } catch (error) {
        showToast(error.message, 'error');
    }
}

// Recruitment Portal
async function loadJobPostings() {
    try {
        const response = await fetch('/api/recruitment/jobs');
        const jobs = await response.json();
        
        const container = document.getElementById('job-postings');
        if (!container) return;
        
        container.innerHTML = jobs.map(job => `
            <div class="card job-card mb-3" data-target="job-detail.php?id=${job.id}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h4 class="card-title">
                                ${job.title}
                                <span class="badge bg-${job.active ? 'success' : 'danger'} ms-2">
                                    ${job.active ? 'Open' : 'Closed'}
                                </span>
                            </h4>
                            <h6 class="card-subtitle mb-2 text-muted">${job.department} • ${job.type}</h6>
                        </div>
                        <span class="badge bg-info">$${job.salary} ${job.salary_type}</span>
                    </div>
                    <p class="card-text mt-2">${job.description.substring(0, 200)}...</p>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <small class="text-muted">
                            Posted: ${new Date(job.posted_date).toLocaleDateString()} • 
                            Applications: ${job.applications}
                        </small>
                        <button class="btn btn-primary btn-sm" onclick="event.stopPropagation();applyForJob(${job.id})">
                            Apply Now
                        </button>
                    </div>
                </div>
            </div>
        `).join('');
        
        setupInteractiveCards();
    } catch (error) {
        console.error('Error loading jobs:', error);
        showToast('Failed to load job postings', 'error');
    }
}

function showApplicationModal(jobId) {
    const modal = new bootstrap.Modal(document.getElementById('applicationModal'));
    document.getElementById('application-job-id').value = jobId;
    modal.show();
}

async function applyForJob(jobId) {
    showApplicationModal(jobId);
}

async function submitApplication() {
    const form = document.getElementById('application-form');
    const formData = new FormData(form);
    
    try {
        const response = await fetch('/api/recruitment/apply', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('Application submitted successfully!', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('applicationModal'));
            modal.hide();
            form.reset();
            loadJobPostings();
        } else {
            throw new Error(result.message || 'Application failed');
        }
    } catch (error) {
        showToast(error.message, 'error');
    }
}

// Medical Service
async function loadDoctors() {
    try {
        const response = await fetch('/api/medical/doctors');
        const doctors = await response.json();
        
        const container = document.getElementById('doctors-list');
        if (!container) return;
        
        container.innerHTML = doctors.map(doctor => `
            <div class="card mb-3 doctor-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <img src="${doctor.image || 'assets/default-doctor.jpg'}" 
                                 class="img-fluid rounded-circle" 
                                 alt="Dr. ${doctor.name}" 
                                 style="width: 120px; height: 120px; object-fit: cover;">
                        </div>
                        <div class="col-md-6">
                            <h4>Dr. ${doctor.name}</h4>
                            <p class="text-muted">${doctor.specialization}</p>
                            <p>${doctor.bio.substring(0, 150)}...</p>
                            <div class="d-flex flex-wrap gap-2">
                                ${doctor.available_days.split(',').map(day => `
                                    <span class="badge bg-light text-dark">${day.trim()}</span>
                                `).join('')}
                            </div>
                        </div>
                        <div class="col-md-3 d-flex flex-column justify-content-between">
                            <div>
                                <h5 class="text-primary">Available Slots</h5>
                                <select class="form-select mb-3" id="slot-${doctor.id}">
                                    ${doctor.available_slots.split(',').map(slot => `
                                        <option value="${slot.trim()}">${slot.trim()}</option>
                                    `).join('')}
                                </select>
                            </div>
                            <button class="btn btn-primary" onclick="bookAppointment(${doctor.id})">
                                Book Appointment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading doctors:', error);
        showToast('Failed to load doctors', 'error');
    }
}

async function bookAppointment(doctorId) {
    const slotSelect = document.getElementById(`slot-${doctorId}`);
    const slot = slotSelect.value;
    
    try {
        const response = await fetch('/api/medical/book', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                doctorId,
                slot
            })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast(`Appointment booked for ${slot}`, 'success');
            loadDoctors();
        } else {
            throw new Error(result.message || 'Booking failed');
        }
    } catch (error) {
        showToast(error.message, 'error');
    }
}

// Transport Service
async function loadTransportRoutes() {
    try {
        const response = await fetch('/api/transport/routes');
        const routes = await response.json();
        
        const container = document.getElementById('routes-container');
        if (!container) return;
        
        container.innerHTML = routes.map(route => `
            <div class="card mb-3 route-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h4>${route.from} → ${route.to}</h4>
                            <p class="text-muted mb-2">${route.description}</p>
                            <div class="d-flex gap-3">
                                <span><i class="fas fa-clock"></i> ${route.duration}</span>
                                <span><i class="fas fa-calendar-alt"></i> ${route.schedule}</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <h5 class="text-primary">$${route.price}</h5>
                            <button class="btn btn-primary" onclick="bookTransport(${route.id})">
                                Book Ticket
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading routes:', error);
        showToast('Failed to load transport routes', 'error');
    }
}

async function bookTransport(routeId) {
    const modal = new bootstrap.Modal(document.getElementById('transportModal'));
    document.getElementById('transport-route-id').value = routeId;
    modal.show();
}

async function submitTransportBooking() {
    const form = document.getElementById('transport-form');
    const formData = new FormData(form);
    
    try {
        const response = await fetch('/api/transport/book', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('Transport booked successfully!', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('transportModal'));
            modal.hide();
            form.reset();
            loadTransportRoutes();
        } else {
            throw new Error(result.message || 'Booking failed');
        }
    } catch (error) {
        showToast(error.message, 'error');
    }
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    setupBeeCursor();
    initServicePage();
    
    // Initialize service page based on URL
    const path = window.location.pathname.split('/').pop();
    switch(path) {
        case 'hostel.php':
            loadHostelRooms();
            break;
        case 'library.php':
            document.getElementById('search-input').addEventListener('input', (e) => {
                searchBooks(e.target.value);
            });
            searchBooks('');
            break;
        case 'canteen.php':
            document.querySelectorAll('.category-filter').forEach(btn => {
                btn.addEventListener('click', function() {
                    const categoryId = this.dataset.category;
                    loadMenuItems(categoryId === 'all' ? null : categoryId);
                });
            });
            loadMenuItems();
            document.getElementById('cart-btn').addEventListener('click', openCartModal);
            break;
        case 'recruitment.php':
            loadJobPostings();
            break;
        case 'medical.php':
            loadDoctors();
            break;
        case 'transport.php':
            loadTransportRoutes();
            break;
    }
    
    // Event delegation for dynamic elements
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-booking-submit]')) {
            submitBooking();
        }
        if (e.target.matches('[data-application-submit]')) {
            submitApplication();
        }
        if (e.target.matches('[data-transport-submit]')) {
            submitTransportBooking();
        }
        if (e.target.matches('[data-checkout]')) {
            checkout();
        }
    });
});