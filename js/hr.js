// HR Management System - Complete JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date pickers with default ranges
    initializeDatePickers();
    
    // Modal handling
    setupModals();
    
    // Form validations
    setupFormValidations();
    
    // Bulk operations
    setupBulkOperations();
    
    // Data export functionality
    setupDataExport();
    
    // Attendance calculations
    setupAttendanceCalculations();
    
    // Performance review calculations
    setupPerformanceCalculations();
    
    // Payroll calculations
    setupPayrollCalculations();
});

// Initialize all date pickers
function initializeDatePickers() {
    const today = new Date();
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    
    // Set default dates for all date inputs
    document.querySelectorAll('input[type="date"]').forEach(input => {
        if (!input.value) {
            if (input.id.includes('start') || input.id.includes('from')) {
                input.valueAsDate = firstDayOfMonth;
            } else if (input.id.includes('end') || input.id.includes('to')) {
                input.valueAsDate = today;
            } else if (input.id.includes('due')) {
                const nextWeek = new Date();
                nextWeek.setDate(nextWeek.getDate() + 7);
                input.valueAsDate = nextWeek;
            }
        }
    });
}

// Modal handling functions
function setupModals() {
    // Global modal show/hide functions
    window.showModal = function(modalId) {
        document.getElementById(modalId).style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };
    
    window.hideModal = function(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto';
    };
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    };
    
    // Close buttons for all modals
    document.querySelectorAll('.modal .close').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    });
}

// Form validation setup
function setupFormValidations() {
    // Employee form validation
    const employeeForm = document.querySelector('form[action="employee_profiles.php"]');
    if (employeeForm) {
        employeeForm.addEventListener('submit', function(e) {
            const email = this.querySelector('input[name="email"]');
            if (email && !validateEmail(email.value)) {
                alert('Please enter a valid email address');
                e.preventDefault();
                email.focus();
                return;
            }
            
            const joinDate = this.querySelector('input[name="join_date"]');
            if (joinDate && new Date(joinDate.value) > new Date()) {
                alert('Join date cannot be in the future');
                e.preventDefault();
                joinDate.focus();
                return;
            }
        });
    }
    
    // Recruitment form validation
    const recruitmentForm = document.querySelector('form[action="recruitment.php"]');
    if (recruitmentForm) {
        recruitmentForm.addEventListener('submit', function(e) {
            const closingDate = this.querySelector('input[name="closing_date"]');
            const postingDate = this.querySelector('input[name="posting_date"]');
            
            if (closingDate && postingDate && new Date(closingDate.value) <= new Date(postingDate.value)) {
                alert('Closing date must be after posting date');
                e.preventDefault();
                closingDate.focus();
                return;
            }
        });
    }
    
    // Payroll form validation
    const payrollForm = document.querySelector('form[action="attendance_payroll.php"]');
    if (payrollForm) {
        payrollForm.addEventListener('submit', function(e) {
            const periodStart = this.querySelector('input[name="period_start"]');
            const periodEnd = this.querySelector('input[name="period_end"]');
            
            if (periodStart && periodEnd && new Date(periodEnd.value) <= new Date(periodStart.value)) {
                alert('Pay period end must be after start date');
                e.preventDefault();
                periodEnd.focus();
                return;
            }
        });
    }
}

// Bulk operations setup
function setupBulkOperations() {
    const bulkUploadForm = document.querySelector('form[action="admin_utilities.php"]');
    if (bulkUploadForm) {
        bulkUploadForm.addEventListener('submit', function(e) {
            const fileInput = this.querySelector('input[type="file"]');
            if (fileInput.files.length === 0) {
                alert('Please select a file to upload');
                e.preventDefault();
                return;
            }
            
            const file = fileInput.files[0];
            const validTypes = ['application/vnd.ms-excel', 'text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            
            if (!validTypes.includes(file.type)) {
                alert('Please upload a valid CSV or Excel file');
                e.preventDefault();
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) { // 5MB limit
                alert('File size must be less than 5MB');
                e.preventDefault();
                return;
            }
        });
    }
}

// Data export functionality
function setupDataExport() {
    document.querySelectorAll('.export-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.dataset.type;
            exportData(type);
        });
    });
}

function exportData(type) {
    let csvContent = "data:text/csv;charset=utf-8,";
    let headers = [];
    let rows = [];
    
    switch(type) {
        case 'employees':
            headers = ["ID", "First Name", "Last Name", "Email", "Department", "Position", "Status"];
            // In a real app, you would fetch this data from the server or DOM
            rows = [
                ["E1001", "John", "Doe", "john@university.edu", "Computer Science", "Professor", "Active"],
                ["E1002", "Jane", "Smith", "jane@university.edu", "Mathematics", "Associate Professor", "Active"]
            ];
            break;
        case 'attendance':
            headers = ["Date", "Employee ID", "Employee Name", "Check In", "Check Out", "Status"];
            rows = [
                ["2023-05-01", "E1001", "John Doe", "08:45", "17:15", "Present"],
                ["2023-05-01", "E1002", "Jane Smith", "09:00", "17:30", "Present"]
            ];
            break;
        case 'payroll':
            headers = ["Period", "Employee ID", "Employee Name", "Basic Salary", "Allowances", "Deductions", "Net Pay"];
            rows = [
                ["May 2023", "E1001", "John Doe", "5000.00", "1000.00", "500.00", "5500.00"],
                ["May 2023", "E1002", "Jane Smith", "4500.00", "800.00", "400.00", "4900.00"]
            ];
            break;
    }
    
    csvContent += headers.join(",") + "\n";
    rows.forEach(row => {
        csvContent += row.join(",") + "\n";
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `${type}_export_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Attendance calculations
function setupAttendanceCalculations() {
    document.querySelectorAll('.attendance-row').forEach(row => {
        const checkIn = row.querySelector('.check-in');
        const checkOut = row.querySelector('.check-out');
        const hours = row.querySelector('.work-hours');
        
        if (checkIn && checkOut && hours) {
            checkOut.addEventListener('change', function() {
                if (checkIn.value && this.value) {
                    const start = new Date(`2000-01-01T${checkIn.value}`);
                    const end = new Date(`2000-01-01T${this.value}`);
                    
                    if (end <= start) {
                        alert('Check out time must be after check in time');
                        this.value = '';
                        hours.textContent = '0';
                        return;
                    }
                    
                    const diff = (end - start) / (1000 * 60 * 60); // hours
                    hours.textContent = diff.toFixed(2);
                }
            });
        }
    });
}

// Performance review calculations
function setupPerformanceCalculations() {
    document.querySelectorAll('.performance-form').forEach(form => {
        const ratingInputs = form.querySelectorAll('input[name^="rating_"]');
        const overallRating = form.querySelector('input[name="overall_rating"]');
        
        if (ratingInputs && overallRating) {
            ratingInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value < 1 || this.value > 5) {
                        alert('Ratings must be between 1 and 5');
                        this.value = '';
                        return;
                    }
                    
                    // Calculate average rating
                    let total = 0;
                    let count = 0;
                    
                    ratingInputs.forEach(rating => {
                        if (rating.value) {
                            total += parseFloat(rating.value);
                            count++;
                        }
                    });
                    
                    if (count > 0) {
                        overallRating.value = (total / count).toFixed(2);
                    }
                });
            });
        }
    });
}

// Payroll calculations
function setupPayrollCalculations() {
    document.querySelectorAll('.payroll-item').forEach(item => {
        const basicSalary = item.querySelector('.basic-salary');
        const allowances = item.querySelector('.allowances');
        const deductions = item.querySelector('.deductions');
        const netPay = item.querySelector('.net-pay');
        
        if (basicSalary && allowances && deductions && netPay) {
            const calculate = () => {
                const basic = parseFloat(basicSalary.value) || 0;
                const allowance = parseFloat(allowances.value) || 0;
                const deduction = parseFloat(deductions.value) || 0;
                
                netPay.value = (basic + allowance - deduction).toFixed(2);
            };
            
            basicSalary.addEventListener('input', calculate);
            allowances.addEventListener('input', calculate);
            deductions.addEventListener('input', calculate);
        }
    });
}

// Helper functions
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// HR-specific functions
function calculateAnnualLeave(joinDate, employmentType) {
    const now = new Date();
    const join = new Date(joinDate);
    const monthsWorked = (now.getFullYear() - join.getFullYear()) * 12 + (now.getMonth() - join.getMonth());
    
    if (employmentType === 'full-time') {
        return Math.min(Math.floor(monthsWorked * 1.25), 30); // 15 days/year, max 30
    } else if (employmentType === 'part-time') {
        return Math.min(Math.floor(monthsWorked * 0.625), 15); // 7.5 days/year, max 15
    }
    return 0;
}

function calculateNoticePeriod(employmentType) {
    switch(employmentType) {
        case 'probation':
            return 14; // days
        case 'contract':
            return 30;
        case 'permanent':
            return 60;
        default:
            return 30;
    }
}

// Initialize tooltips
function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.dataset.tooltip;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = `${rect.left + rect.width/2 - tooltip.offsetWidth/2}px`;
            tooltip.style.top = `${rect.top - tooltip.offsetHeight - 5}px`;
            
            this.addEventListener('mouseleave', function() {
                tooltip.remove();
            }, { once: true });
        });
    });
}

// Initialize the page
initTooltips();