document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    const passwordToggles = document.querySelectorAll('.password-toggle i');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
    });

    // Add toggle for confirm password
    const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
    const confirmPassword = document.querySelector('#confirm_password');

    toggleConfirmPassword.addEventListener('click', function (e) {
        const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPassword.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });

    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const password = form.querySelector('input[type="password"]');
            if (password && password.value.length < 6) {
                e.preventDefault();
                showAlert('Password must be at least 6 characters long', 'error');
            }
        });
    });

    // Add password match validation
    const password = document.querySelector('#password');
    confirmPassword.addEventListener('input', function() {
        if (this.value !== password.value) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });

    password.addEventListener('input', function() {
        if (confirmPassword.value !== this.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    });

    // Helper function to show alerts
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;

        const form = document.querySelector('form');
        form.parentNode.insertBefore(alertDiv, form);

        setTimeout(() => alertDiv.remove(), 5000);
    }
    // Show terms and condtions
});

function showTerms() {
    // You can replace this with a modal or redirect to a terms page
    alert("Terms and Conditions\n\n1. Your personal information will be stored securely.\n2. We will not share your information with third parties without your consent.\n3. You are responsible for maintaining the confidentiality of your account credentials.\n4. Misuse of the platform may result in account termination.\n\nFor more details, please contact support.");
}
