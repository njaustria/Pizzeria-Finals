window.addEventListener('scroll', () => {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

const hamburger = document.querySelector('.hamburger');
const navLinks = document.querySelector('.nav-links');

if (hamburger) {
    hamburger.addEventListener('click', () => {
        navLinks.classList.toggle('active');

        const spans = hamburger.querySelectorAll('span');
        spans[0].style.transform = navLinks.classList.contains('active')
            ? 'rotate(45deg) translate(5px, 5px)'
            : 'rotate(0) translate(0, 0)';
        spans[1].style.opacity = navLinks.classList.contains('active') ? '0' : '1';
        spans[2].style.transform = navLinks.classList.contains('active')
            ? 'rotate(-45deg) translate(7px, -6px)'
            : 'rotate(0) translate(0, 0)';
    });
}

document.addEventListener('click', (e) => {
    if (navLinks && navLinks.classList.contains('active')) {
        if (!e.target.closest('.nav-links') && !e.target.closest('.hamburger')) {
            navLinks.classList.remove('active');
        }
    }
});

const flashMessage = document.querySelector('.flash-message');
if (flashMessage) {
    setTimeout(() => {
        flashMessage.style.animation = 'slideOutRight 0.5s ease';
        setTimeout(() => flashMessage.remove(), 500);
    }, 5000);
}

function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = 'rgba(220, 53, 69, 0.5)';
            isValid = false;
        } else {
            input.style.borderColor = 'var(--glass-border)';
        }
    });

    return isValid;
}

function addToCartAnimation(button) {
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Added!';
    button.disabled = true;
    button.style.background = 'rgba(40, 167, 69, 0.2)';

    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
        button.style.background = '';
    }, 2000);
}

document.addEventListener('DOMContentLoaded', () => {
    const quantityInputs = document.querySelectorAll('.quantity-input');

    quantityInputs.forEach(input => {
        const minusBtn = input.querySelector('.qty-minus');
        const plusBtn = input.querySelector('.qty-plus');
        const qtyInput = input.querySelector('input');

        if (minusBtn) {
            minusBtn.addEventListener('click', () => {
                let value = parseInt(qtyInput.value) || 1;
                if (value > 1) {
                    qtyInput.value = value - 1;
                    qtyInput.dispatchEvent(new Event('change'));
                }
            });
        }

        if (plusBtn) {
            plusBtn.addEventListener('click', () => {
                let value = parseInt(qtyInput.value) || 1;
                qtyInput.value = value + 1;
                qtyInput.dispatchEvent(new Event('change'));
            });
        }
    });
});

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById(previewId).src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function updateCartTotal() {
    const rows = document.querySelectorAll('.cart-item');
    let total = 0;

    rows.forEach(row => {
        const price = parseFloat(row.dataset.price);
        const quantity = parseInt(row.querySelector('.quantity-input input').value);
        const subtotal = price * quantity;

        row.querySelector('.item-subtotal').textContent = formatCurrency(subtotal);
        total += subtotal;
    });

    if (document.getElementById('cart-total')) {
        document.getElementById('cart-total').textContent = formatCurrency(total);
    }
}

setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);
