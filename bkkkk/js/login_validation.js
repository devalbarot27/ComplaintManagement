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

    function pemToArrayBuffer(pem) {
        const b64 = String(pem || '')
            .replace(/-----BEGIN PUBLIC KEY-----/g, '')
            .replace(/-----END PUBLIC KEY-----/g, '')
            .replace(/\s+/g, '');
        const binary = atob(b64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i += 1) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function bufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.length; i += 1) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    async function encryptPasswordForTransport(plainPassword) {
        const pem = window.LOGIN_TRANSPORT_PUBLIC_KEY || '';
        if (!pem || !window.crypto || !window.crypto.subtle) {
            throw new Error('Secure password encryption is unavailable in this browser.');
        }

        // RSA-OAEP with SHA-1 matches PHP openssl_private_decrypt(OPENSSL_PKCS1_OAEP_PADDING).
        const key = await window.crypto.subtle.importKey(
            'spki',
            pemToArrayBuffer(pem),
            {
                name: 'RSA-OAEP',
                hash: 'SHA-1'
            },
            false,
            ['encrypt']
        );

        const encoded = new TextEncoder().encode(plainPassword);
        const cipherBuffer = await window.crypto.subtle.encrypt(
            { name: 'RSA-OAEP' },
            key,
            encoded
        );

        return bufferToBase64(cipherBuffer);
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

        const passwordInput = form.querySelector('#password');
        const encryptedInput = form.querySelector('#password_encrypted');
        const submitButton = form.querySelector('[type="submit"]');
        const plainPassword = passwordInput ? String(passwordInput.value || '') : '';

        isSubmitting = true;
        if (submitButton) {
            submitButton.disabled = true;
        }

        /* // HTTPS / encrypt enabled.
        encryptPasswordForTransport(plainPassword)
            .then(function (encrypted) {
                if (encryptedInput) {
                    encryptedInput.value = encrypted;
                }
                // Do not transmit plaintext password.
                if (passwordInput) {
                    passwordInput.value = '';
                    passwordInput.removeAttribute('name');
                }
                form.submit();
            })
            .catch(function () {
                isSubmitting = false;
                if (submitButton) {
                    submitButton.disabled = false;
                }
                showErrors({
                    password: ['Unable to secure password for transmission. Please try again.']
                });
            });
            */
        form.submit(); // HTTP / encrypt disabled.    
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