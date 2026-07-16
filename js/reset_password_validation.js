function resetPasswordStrengthError(password) {
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

function initResetPasswordFormValidation() {
    const form = document.getElementById('resetPasswordForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    validate.validators.resetPasswordStrength = function (value) {
        const error = resetPasswordStrengthError(value);
        return error ? '^' + error : null;
    };

    const constraints = {
        new_password: {
            presence: {
                allowEmpty: false,
                message: '^New Password is required'
            },
            resetPasswordStrength: true
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
        form.querySelectorAll('.custom-input').forEach(function (input) {
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
        e.preventDefault();

        if (isSubmitting) {
            return;
        }

        const errors = validate(form, constraints);
        showErrors(errors);

        if (errors) {
            return;
        }

        const submitButton = form.querySelector('[type="submit"]');
        const newPassword = form.querySelector('[name="new_password"]').value;
        const tokenInput = form.querySelector('[name="token"]');
        const payload = { new_password: newPassword };

        if (tokenInput && tokenInput.value) {
            payload.token = tokenInput.value;
        }

        checkPasswordHistory(payload)
            .then(function (response) {
                if (!response || !response.valid) {
                    showErrors({
                        new_password: [passwordHistoryFieldError(response)]
                    });
                    return;
                }

                isSubmitting = true;
                if (submitButton) {
                    submitButton.classList.add('disabled_btn');
                }
                form.submit();
            })
            .catch(function () {
                showErrors({
                    new_password: ['Unable to verify password history. Please try again.']
                });
            });
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
}

function bootResetPasswordPage() {
    initResetPasswordFormValidation();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootResetPasswordPage);
} else {
    bootResetPasswordPage();
}
