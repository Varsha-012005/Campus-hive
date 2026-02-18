// Tab functionality
function openTab(tabName) {
    // Hide all tab contents
    const tabContents = document.getElementsByClassName('tab-content');
    for (let i = 0; i < tabContents.length; i++) {
        tabContents[i].classList.remove('active');
    }

    // Remove active class from all tab buttons
    const tabButtons = document.getElementsByClassName('tab-btn');
    for (let i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove('active');
    }

    // Show the selected tab and mark its button as active
    document.getElementById(tabName).classList.add('active');
    event.currentTarget.classList.add('active');
}

// Modal functionality
function showModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function hideModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let i = 0; i < modals.length; i++) {
        if (event.target == modals[i]) {
            modals[i].style.display = 'none';
        }
    }
}

// Initialize date pickers with default values
document.addEventListener('DOMContentLoaded', function() {
    // Set default dates for forms
    const today = new Date();
    const nextYear = new Date();
    nextYear.setFullYear(today.getFullYear() + 1);
    
    // Hostel allocation dates
    const hostelStartDate = document.getElementById('start_date');
    const hostelEndDate = document.getElementById('end_date');
    if (hostelStartDate && hostelEndDate) {
        hostelStartDate.valueAsDate = today;
        hostelEndDate.valueAsDate = nextYear;
    }
    
    // Library due date (2 weeks from today)
    const libraryDueDate = document.getElementById('due_date');
    if (libraryDueDate) {
        const dueDate = new Date();
        dueDate.setDate(today.getDate() + 14);
        libraryDueDate.valueAsDate = dueDate;
    }
    
    // Medical appointment date/time
    const medApptDate = document.getElementById('appointment_date');
    const medApptTime = document.getElementById('appointment_time');
    if (medApptDate && medApptTime) {
        medApptDate.valueAsDate = today;
        medApptTime.value = '09:00';
    }
});

// Helper function for AJAX requests
function makeRequest(url, method, data, callback) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                callback(JSON.parse(xhr.responseText));
            } else {
                console.error('Request failed:', xhr.statusText);
            }
        }
    };
    
    xhr.send(JSON.stringify(data));
}