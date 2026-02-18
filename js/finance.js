// Finance-specific JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize date pickers with default ranges
    const today = new Date();
    const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    
    document.getElementById('start_date')?.valueAsDate = firstDayOfMonth;
    document.getElementById('end_date')?.valueAsDate = today;
    
    // CSV Export functionality
    document.getElementById('exportCsv')?.addEventListener('click', function() {
        const financeDataElement = document.getElementById('finance-data');
        const reportData = financeDataElement ? JSON.parse(financeDataElement.dataset.report) : [];
        
        if (reportData.length === 0) {
            alert('No data to export');
            return;
        }
        
        // Create CSV content
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Add headers
        csvContent += "Type,Program,Count,Total Amount\n";
        
        // Add data rows
        reportData.forEach(row => {
            csvContent += `"${row.type}","${row.program_name || 'N/A'}","${row.count}","${row.total}"\n`;
        });
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "financial_report.csv");
        document.body.appendChild(link);
        
        // Trigger download
        link.click();
        document.body.removeChild(link);
    });
    
    // Settings form validation
    const settingsForm = document.querySelector('form[action="settings.php"]');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function(e) {
            const lateFee = document.getElementById('late_fee_percentage');
            if (lateFee && (lateFee.value < 0 || lateFee.value > 100)) {
                alert('Late fee percentage must be between 0 and 100');
                e.preventDefault();
                return;
            }
            
            const dueDays = document.getElementById('payment_due_days');
            if (dueDays && dueDays.value < 1) {
                alert('Payment due days must be at least 1');
                e.preventDefault();
                return;
            }
        });
    }
    
    // Auto-format semester input
    const semesterInput = document.getElementById('current_semester');
    if (semesterInput) {
        semesterInput.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value && !/^(Fall|Spring|Summer|Winter)\s\d{4}$/.test(value)) {
                alert('Semester should be in format "Season Year", e.g., "Fall 2023"');
                this.focus();
            }
        });
    }
});

// Additional finance-specific functions
function calculateLateFee(amount, daysLate, percentage) {
    if (daysLate <= 0) return 0;
    return (amount * (percentage / 100) * daysLate).toFixed(2);
}