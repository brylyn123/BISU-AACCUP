// Mobile Navigation Toggle
document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    if (navToggle) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
        });

        // Close menu when a link is clicked
        const navLinks = navMenu.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
            });
        });
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && document.querySelector(href)) {
                e.preventDefault();
                document.querySelector(href).scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Active nav link highlighting
    const currentLocation = location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentLocation) {
            link.classList.add('active');
        }
    });
});

// Scroll animation for elements
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -100px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.animation = 'slideUp 0.6s ease-out forwards';
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

document.addEventListener('DOMContentLoaded', function() {
    const animatedElements = document.querySelectorAll('.feature-card, .footer-section');
    animatedElements.forEach(el => {
        observer.observe(el);
    });
});

// Add some interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Highlight active page in navigation
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || (currentPage === '' && href === 'index.php')) {
            link.style.color = '#6c63ff';
            link.style.borderBottom = '2px solid #6c63ff';
        }
    });
});

console.log('Modern University Theme Script loaded successfully');
// Password strength and matching listener
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordFeedbackDiv = document.getElementById('password-feedback');

    if (passwordInput && confirmPasswordInput && passwordFeedbackDiv) {
        function showFeedback(message, ok) {
            passwordFeedbackDiv.innerText = message;
            passwordFeedbackDiv.style.color = ok ? 'green' : 'red';
        }

        passwordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (password.length === 0) {
                passwordFeedbackDiv.innerText = '';
                return;
            }

            if (password.length < 8 || !/\d/.test(password)) {
                showFeedback('Password must be at least 8 characters and contain a number.', false);
            } else if (confirmPassword && password !== confirmPassword) {
                showFeedback('Passwords do not match.', false);
            } else if (password.length >= 8 && /\d/.test(password)) {
                if (confirmPassword) {
                    showFeedback(password === confirmPassword ? 'Passwords match.' : 'Passwords do not match.', password === confirmPassword);
                } else {
                    showFeedback('Password looks good.', true);
                }
            }
        });

        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (confirmPassword.length === 0) {
                passwordFeedbackDiv.innerText = '';
                return;
            }

            if (password !== confirmPassword) {
                showFeedback('Passwords do not match.', false);
            } else if (password.length >= 8 && /\d/.test(password)) {
                showFeedback('Passwords match.', true);
            } else {
                showFeedback('Password must be at least 8 characters and contain a number.', false);
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-target');
            const target = document.getElementById(targetId);
            if (!target) return;
            const isPassword = target.getAttribute('type') === 'password';
            target.setAttribute('type', isPassword ? 'text' : 'password');
            button.innerHTML = `<i class="fas ${isPassword ? 'fa-eye-slash' : 'fa-eye'}"></i>`;
            button.setAttribute('aria-label', `${isPassword ? 'Hide' : 'Show'} password`);
        });
    });
});
