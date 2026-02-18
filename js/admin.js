document.addEventListener('DOMContentLoaded', function () {
    // Theme toggle
    const themeToggle = document.createElement('div');
    themeToggle.className = 'toggle-theme';
    themeToggle.innerHTML = '🌓';
    document.body.appendChild(themeToggle);

    themeToggle.addEventListener('click', function () {
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

    // Bulk actions
    const bulkCheckbox = document.getElementById('bulk-select-all');
    const itemCheckboxes = document.querySelectorAll('.bulk-select-item');

    if (bulkCheckbox) {
        bulkCheckbox.addEventListener('change', function () {
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Confirm before dangerous actions
    const deleteButtons = document.querySelectorAll('.btn-danger');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to perform this action? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // Modal functionality
    const modals = document.querySelectorAll('.modal');
    const openModalButtons = document.querySelectorAll('[data-modal-target]');
    const closeModalButtons = document.querySelectorAll('[data-close-modal]');

    openModalButtons.forEach(button => {
        button.addEventListener('click', function () {
            const target = this.getAttribute('data-modal-target');
            document.querySelector(target).style.display = 'flex';
        });
    });

    closeModalButtons.forEach(button => {
        button.addEventListener('click', function () {
            this.closest('.modal').style.display = 'none';
        });
    });

    window.addEventListener('click', function (event) {
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'red';
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields');
            }
        });
    });

    // Reset field styles on input
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('input', function () {
            this.style.borderColor = '';
        });
    });

    // CSV import preview
    const csvFileInput = document.getElementById('csv_file');
    if (csvFileInput) {
        csvFileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById('csv-preview');
                    if (preview) {
                        preview.innerHTML = `<p>File selected: ${file.name}</p>`;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    reader.onload = function (e) {
        const preview = document.getElementById('csv-preview');
        if (preview) {
            const content = e.target.result;
            const lines = content.split('\n').slice(0, 5); // Show first 5 lines
            preview.innerHTML = `<p>File selected: ${file.name}</p><pre>${lines.join('\n')}</pre>`;
        }
    };
});