function changePasswordStrengthError(password) {
    if (!password || password.length < 8) {
        return 'Password must be at least 8 characters long.';
    }

    if (!/[0-9]/.test(password)) {
        return 'Password must contain at least one digit (0-9).';
    }

    if (!/[A-Z]/.test(password)) {
        return 'Password must contain at least one uppercase letter (A-Z).';
    }

    if (!/[a-z]/.test(password)) {
        return 'Password must contain at least one lowercase letter (a-z).';
    }

    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?~`]/.test(password)) {
        return 'Password must contain at least one special character (!@#$%^&* etc.).';
    }

    return null;
}

function initChangePasswordFormValidation() {
    const form = document.getElementById('changePasswordForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    validate.validators.changePasswordStrength = function (value) {
        const error = changePasswordStrengthError(value);
        return error ? '^' + error : null;
    };

    const constraints = {
        current_password: {
            presence: {
                allowEmpty: false,
                message: '^Current Password is required'
            }
        },
        new_password: {
            presence: {
                allowEmpty: false,
                message: '^New Password is required'
            },
            changePasswordStrength: true
        },
        confirm_password: {
            presence: {
                allowEmpty: false,
                message: '^Confirm Password is required'
            },
            equality: {
                attribute: 'new_password',
                message: '^Confirm Password must match New Password'
            }
        }
    };

    function clearValidationState() {
        form.querySelectorAll('.validation-msg').forEach(function (msg) {
            msg.textContent = '';
        });
        form.querySelectorAll('.form-control').forEach(function (input) {
            input.classList.remove('is-invalid');
        });
    }

    function showErrors(errors) {
        clearValidationState();

        if (!errors) {
            return;
        }

        Object.keys(errors).forEach(function (field) {
            const input = form.querySelector('[name="' + field + '"]');
            const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
            const fieldErrors = errors[field];

            if (input) {
                input.classList.add('is-invalid');
            }

            if (msg) {
                msg.textContent = fieldErrors ? fieldErrors[0] : '';
            }
        });
    }

    let isSubmitting = false;

    form.addEventListener('submit', function (e) {
        if (isSubmitting) {
            e.preventDefault();
            return;
        }

        const errors = validate(form, constraints);
        showErrors(errors);

        if (errors) {
            e.preventDefault();
            return;
        }

        isSubmitting = true;
        const submitButton = form.querySelector('[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }
    });

    form.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-toggle-password');
            const input = document.getElementById(targetId);
            const icon = button.querySelector('i');

            if (!input || !icon) {
                return;
            }

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('bi-eye', !isPassword);
            icon.classList.toggle('bi-eye-slash', isPassword);
            button.setAttribute(
                'aria-label',
                isPassword ? 'Hide password' : 'Show password'
            );
        });
    });

    const modalEl = document.getElementById('changePasswordModal');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            form.reset();
            clearValidationState();
            isSubmitting = false;
            const submitButton = form.querySelector('[type="submit"]');
            if (submitButton) {
                submitButton.disabled = false;
            }
        });
    }
}

function bootChangePasswordPage() {
    initChangePasswordFormValidation();

    const modalEl = document.getElementById('changePasswordModal');
    if (modalEl && window.openChangePasswordModal) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootChangePasswordPage);
} else {
    bootChangePasswordPage();
}
