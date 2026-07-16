function initLoginFormValidation() {
    const form = document.getElementById('loginForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        usr_name: {
            presence: {
                allowEmpty: false,
                message: '^Username is required'
            }
        },
        password: {
            presence: {
                allowEmpty: false,
                message: '^Password is required'
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

    const passwordInput = form.querySelector('#password');
    const passwordToggle = document.getElementById('passwordToggle');
    const passwordToggleIcon = document.getElementById('passwordToggleIcon');

    if (passwordInput && passwordToggle && passwordToggleIcon) {
        passwordToggle.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            passwordToggleIcon.classList.toggle('bi-eye', !isPassword);
            passwordToggleIcon.classList.toggle('bi-eye-slash', isPassword);
            passwordToggle.setAttribute(
                'aria-label',
                isPassword ? 'Hide password' : 'Show password'
            );
        });
    }
}

document.addEventListener('DOMContentLoaded', initLoginFormValidation);
