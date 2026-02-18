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

    // Assignment grading modal
    const assignmentModal = document.getElementById('assignmentModal');
    const openModalButtons = document.querySelectorAll('.open-submission-modal');
    const closeModalButtons = document.querySelectorAll('.close');
    
    openModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            const submissionId = this.getAttribute('data-submission-id');
            document.getElementById('modal_submission_id').value = submissionId;
            assignmentModal.style.display = 'block';
        });
    });
    
    // Close modals
    closeModalButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (assignmentModal) assignmentModal.style.display = 'none';
        });
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (assignmentModal && event.target === assignmentModal) {
            assignmentModal.style.display = 'none';
        }
    });

    // Grade submission form validation
    const gradeForms = document.querySelectorAll('.grade-form');
    gradeForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const gradeSelect = this.querySelector('select[name="grade"]');
            if (!gradeSelect.value) {
                e.preventDefault();
                gradeSelect.style.borderColor = 'red';
                alert('Please select a grade');
            }
        });
    });

    // Attendance marking system
    const attendanceCheckboxes = document.querySelectorAll('.attendance-checkbox');
    attendanceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const studentId = this.getAttribute('data-student-id');
            const classId = this.getAttribute('data-class-id');
            const status = this.checked ? 'present' : 'absent';
            
            fetch('/university-system/php/update_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    student_id: studentId,
                    class_id: classId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error updating attendance');
                    this.checked = !this.checked;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.checked = !this.checked;
            });
        });
    });

    // Document upload for course materials
    const materialUploadForms = document.querySelectorAll('.material-upload-form');
    materialUploadForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const fileInput = this.querySelector('input[type="file"]');
            if (!fileInput.files.length) {
                e.preventDefault();
                fileInput.style.borderColor = 'red';
                alert('Please select a file to upload');
            }
        });
    });

    // Initialize date pickers
    const datePickers = document.querySelectorAll('.date-picker');
    datePickers.forEach(picker => {
        picker.addEventListener('focus', function() {
            this.type = 'date';
        });
        picker.addEventListener('blur', function() {
            if (!this.value) this.type = 'text';
        });
    });
});