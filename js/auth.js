document.addEventListener('DOMContentLoaded', function() {
    // Show/hide role-specific fields on registration form
    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            // Hide all role-specific fields first
            document.querySelectorAll('.role-fields').forEach(field => {
                field.style.display = 'none';
            });
            
            // Show fields for selected role
            const selectedRole = this.value + 'Fields';
            const roleFields = document.getElementById(selectedRole);
            if (roleFields) {
                roleFields.style.display = 'block';
            }
        });
    }
    
    // Form validation for registration
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            const role = document.getElementById('role').value;
            if (role === 'student') {
                const studentId = document.getElementById('studentId').value;
                const program = document.getElementById('program').value;
                
                if (!studentId || !program) {
                    e.preventDefault();
                    alert('Student ID and Program are required for students!');
                    return false;
                }
            }
            
            return true;
        });
    }
    
    // Form validation for login
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Both username and password are required!');
                return false;
            }
            
            return true;
        });
    }
});