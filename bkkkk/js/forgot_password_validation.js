function initForgotPasswordFormValidation() {
    const form = document.getElementById('forgotPasswordForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    validate.validators.emailFormat = function (value) {
        if (!value) {
            return null;
        }

        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailPattern.test(String(value).trim()) ? null : '^Please enter a valid email address';
    };

    const constraints = {
        email: {
            presence: {
                allowEmpty: false,
                message: '^Email address is required'
            },
            emailFormat: true
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
}

function bootForgotPasswordPage() {
    initForgotPasswordFormValidation();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootForgotPasswordPage);
} else {
    bootForgotPasswordPage();
}
