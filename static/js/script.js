// Form validation for registration and login pages
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function (event) {
            const email = form.querySelector('input[name="email"]');
            const password = form.querySelector('input[name="password"]');
            if (email && !isValidEmail(email.value)) {
                event.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
            if (password && password.value.length < 6) {
                event.preventDefault();
                alert('Password must be at least 6 characters long.');
                return;
            }
        });
    });

    // Handle action confirmation
    window.handleAction = function(button, action) {
        const id = button.getAttribute('data-id');
        const url = button.getAttribute('data-url') || '';
        const actionValue = button.getAttribute('data-action') || '';
        let confirmMessage = 'Are you sure?';
        if (action === 'book_session') {
            confirmMessage = 'Are you sure you want to book this session?';
        } else if (action === 'session_action') {
            confirmMessage = `Are you sure you want to ${actionValue} this session?`;
        } else if (action === 'logout') {
            confirmMessage = 'Are you sure you want to log out?';
        }

        if (confirm(confirmMessage)) {
            let finalUrl = url;
            switch (action) {
                case 'therapist_profile':
                    finalUrl = `{{ url_for('therapist_profile', therapist_id=0) }}`.replace('0', id);
                    break;
                case 'book_session':
                    finalUrl = url; // Use pre-rendered URL
                    break;
                case 'user_profile':
                    finalUrl = `{{ url_for('user_profile') }}`;
                    break;
                case 'session_action':
                    finalUrl = url; // Use pre-rendered URL
                    break;
                case 'logout':
                    finalUrl = `{{ url_for('logout') }}`;
                    break;
            }
            window.location.href = finalUrl;
        }
    };
});

// Email validation function
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}