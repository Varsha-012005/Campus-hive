document.addEventListener('DOMContentLoaded', function() {
    // Theme toggle
    const themeToggle = document.createElement('div');
    themeToggle.className = 'toggle-theme';
    themeToggle.innerHTML = '🌓';
    document.body.appendChild(themeToggle);
    
    themeToggle.addEventListener('click', function() {
        const currentTheme = document.body.getAttribute('data-theme');
        if (currentTheme === 'dark') {
            document.body.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
        } else {
            document.body.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        }
    });
    
    // Check for saved theme preference
    if (localStorage.getItem('theme') === 'dark') {
        document.body.setAttribute('data-theme', 'dark');
    }
    
    // Assignment submission modal
    const assignmentModal = document.getElementById('assignmentModal');
    const openModalButtons = document.querySelectorAll('.open-submission-modal');
    const closeModalButtons = document.querySelectorAll('.close');
    
    openModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            const assignmentId = this.getAttribute('data-assignment-id');
            document.getElementById('modal_assignment_id').value = assignmentId;
            assignmentModal.style.display = 'block';
        });
    });
    
    // Feedback modal
    const feedbackModal = document.getElementById('feedbackModal');
    const viewFeedbackButtons = document.querySelectorAll('.view-feedback');
    
    viewFeedbackButtons.forEach(button => {
        button.addEventListener('click', function() {
            const feedback = this.getAttribute('data-feedback');
            document.getElementById('feedbackContent').textContent = feedback || 'No feedback provided.';
            feedbackModal.style.display = 'block';
        });
    });
    
    // Close modals
    closeModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            assignmentModal.style.display = 'none';
            feedbackModal.style.display = 'none';
        });
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === assignmentModal) {
            assignmentModal.style.display = 'none';
        }
        if (event.target === feedbackModal) {
            feedbackModal.style.display = 'none';
        }
    });
    
    // Payment modal
    const paymentModal = document.getElementById('paymentModal');
    const makePaymentBtn = document.getElementById('makePaymentBtn');
    const paymentCloseBtn = paymentModal.querySelector('.close');
    
    makePaymentBtn.addEventListener('click', function() {
        paymentModal.style.display = 'block';
    });
    
    paymentCloseBtn.addEventListener('click', function() {
        paymentModal.style.display = 'none';
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === paymentModal) {
            paymentModal.style.display = 'none';
        }
    });
    
    // Payment form submission
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // Simulate payment processing
            alert('Payment submitted successfully!');
            paymentModal.style.display = 'none';
        });
    }
    
    // Download transcript
    const downloadTranscriptBtn = document.getElementById('downloadTranscript');
    if (downloadTranscriptBtn) {
        downloadTranscriptBtn.addEventListener('click', function() {
            // In a real app, this would generate and download a PDF
            alert('Transcript download started. This would generate a PDF in a real application.');
        });
    }
    
    // Reserve library resource
    const reserveButtons = document.querySelectorAll('.reserve-resource');
    reserveButtons.forEach(button => {
        button.addEventListener('click', function() {
            const resourceId = this.getAttribute('data-resource-id');
            // In a real app, this would make an API call
            alert(`Resource ${resourceId} reserved successfully!`);
        });
    });
    
    // Join student club
    const joinClubButtons = document.querySelectorAll('.join-club');
    joinClubButtons.forEach(button => {
        button.addEventListener('click', function() {
            const clubId = this.getAttribute('data-club-id');
            // In a real app, this would make an API call
            alert(`Request to join club ${clubId} submitted!`);
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[required], select[required], textarea[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = 'red';
                } else {
                    input.style.borderColor = '';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
});