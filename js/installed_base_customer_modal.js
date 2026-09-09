function getInstalledBaseAddCustomerModal() {
    const el = document.getElementById('installedBaseAddCustomerModal');
    if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return null;
    }
    return bootstrap.Modal.getOrCreateInstance(el);
}

function clearInstalledBaseAddCustomerAlert() {
    const alert = document.getElementById('installedBaseAddCustomerAlert');
    if (!alert) {
        return;
    }
    alert.classList.add('d-none');
    alert.textContent = '';
}

function showInstalledBaseAddCustomerAlert(message) {
    const alert = document.getElementById('installedBaseAddCustomerAlert');
    if (!alert) {
        return;
    }
    alert.textContent = message || 'Unable to save customer.';
    alert.classList.remove('d-none');
}

function resetInstalledBaseAddCustomerForm() {
    const form = document.getElementById('installedBaseAddCustomerForm');
    if (!form) {
        return;
    }

    form.reset();
    clearInstalledBaseAddCustomerAlert();
    resetPincodeSelect2(form, 'installedBaseCustomerModalPincodeSelect');

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
    $('#installedBaseCustomerModalPincodeSelect')
        .next('.select2-container')
        .find('.select2-selection')
        .removeClass('is-invalid');

    const submitButton = document.getElementById('installedBaseAddCustomerSubmitBtn');
    if (submitButton) {
        submitButton.classList.remove('disabled_btn');
        submitButton.disabled = false;
    }
}

function openInstalledBaseAddCustomerModal() {
    const modal = getInstalledBaseAddCustomerModal();
    if (!modal) {
        return;
    }
    resetInstalledBaseAddCustomerForm();
    modal.show();
}

function closeInstalledBaseAddCustomerModal() {
    const modal = getInstalledBaseAddCustomerModal();
    if (!modal) {
        return;
    }
    modal.hide();
}

function initInstalledBaseAddCustomerFormValidation() {
    const form = document.getElementById('installedBaseAddCustomerForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    if (typeof validate.validators.ccmEmailFormat === 'undefined') {
        validate.validators.ccmEmailFormat = function (value) {
            if (!value) {
                return;
            }
            if (!/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(String(value).trim())) {
                return '^Please enter a valid email address';
            }
        };
    }

    if (typeof validate.validators.ccmMobileNumber === 'undefined') {
        validate.validators.ccmMobileNumber = function (value) {
            if (!value) {
                return;
            }
            if (!/^[1-9]\d{9}$/.test(String(value).trim())) {
                return '^Mobile must be a valid 10-digit number';
            }
        };
    }

    const constraints = {
        customer_name: {
            presence: { allowEmpty: false, message: '^Customer Name is required' },
            length: { maximum: 150, message: '^Customer Name cannot exceed 150 characters' }
        },
        email: {
            presence: { allowEmpty: false, message: '^Email is required' },
            email: { message: '^Please enter a valid email address' },
            ccmEmailFormat: true,
            length: { maximum: 150, message: '^Email cannot exceed 150 characters' }
        },
        mobile: {
            presence: { allowEmpty: false, message: '^Mobile is required' },
            ccmMobileNumber: true
        },
        street_1: {
            presence: { allowEmpty: false, message: '^Street 1 is required' },
            length: { maximum: 255, message: '^Street 1 cannot exceed 255 characters' }
        },
        street_2: {
            presence: { allowEmpty: false, message: '^Street 2 is required' },
            length: { maximum: 255, message: '^Street 2 cannot exceed 255 characters' }
        },
        pincode: {
            presence: { allowEmpty: false, message: '^Pincode is required' },
            format: {
                pattern: /^\d{6}$/,
                message: '^Pincode must be a 6-digit number'
            }
        },
        city: {
            presence: { allowEmpty: false, message: '^City is required' }
        },
        district: {
            presence: { allowEmpty: false, message: '^District is required' }
        },
        state: {
            presence: { allowEmpty: false, message: '^State is required' }
        }
    };

    function bindInputRestrictions() {
        const mobileInput = form.querySelector('[name="mobile"]');
        const emailInput = form.querySelector('[name="email"]');

        if (mobileInput) {
            mobileInput.addEventListener('input', function () {
                mobileInput.value = mobileInput.value.replace(/\D/g, '').slice(0, 10);
            });
        }

        if (emailInput) {
            emailInput.addEventListener('input', function () {
                emailInput.value = emailInput.value.replace(/[^A-Za-z0-9._%+\-@]/g, '');
            });
        }
    }

    bindInputRestrictions();

    function clearValidationState() {
        clearInstalledBaseAddCustomerAlert();
        form.querySelectorAll('.validation-msg').forEach(function (msg) {
            msg.textContent = '';
        });
        form.querySelectorAll('.form-control, .select2-selection').forEach(function (input) {
            input.classList.remove('is-invalid');
        });
    }

    function clearFieldError(fieldName) {
        const input = form.querySelector('[name="' + fieldName + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + fieldName + '"]');
        if (input) {
            input.classList.remove('is-invalid');
        }
        if (msg) {
            msg.textContent = '';
        }
        if (fieldName === 'pincode' && window.jQuery) {
            window.jQuery('#installedBaseCustomerModalPincodeSelect')
                .next('.select2-container')
                .find('.select2-selection')
                .removeClass('is-invalid');
        }
    }

    function setFieldError(fieldName, message) {
        const input = form.querySelector('[name="' + fieldName + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + fieldName + '"]');
        if (input) {
            input.classList.add('is-invalid');
        }
        if (fieldName === 'pincode' && window.jQuery) {
            window.jQuery('#installedBaseCustomerModalPincodeSelect')
                .next('.select2-container')
                .find('.select2-selection')
                .addClass('is-invalid');
        }
        if (msg) {
            msg.textContent = message || '';
        }
    }

    function showErrors(errors) {
        clearValidationState();
        if (!errors) {
            return;
        }
        Object.keys(errors).forEach(function (field) {
            const message = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
            setFieldError(field, message);
        });
    }

    function validateField(input) {
        const fieldName = input && input.name;
        if (!fieldName || !constraints[fieldName]) {
            return;
        }

        const fieldErrors = validate.single(input.value, constraints[fieldName]);
        if (fieldErrors) {
            setFieldError(fieldName, fieldErrors[0]);
        } else {
            clearFieldError(fieldName);
        }

        if (fieldName === 'pincode' && !fieldErrors) {
            ['city', 'district', 'state'].forEach(function (autoField) {
                const autoInput = form.querySelector('[name="' + autoField + '"]');
                if (autoInput) {
                    validateField(autoInput);
                }
            });
        }
    }

    form.querySelectorAll('input, textarea, select').forEach(function (input) {
        if (!constraints[input.name]) {
            return;
        }

        const eventName = input.tagName === 'SELECT' ? 'change' : 'input';
        input.addEventListener(eventName, function () {
            validateField(input);
        });
        input.addEventListener('blur', function () {
            validateField(input);
        });
    });

    if (window.jQuery) {
        window.jQuery('#installedBaseCustomerModalPincodeSelect').on('select2:select select2:clear change', function () {
            validateField(form.querySelector('[name="pincode"]'));
        });
    }

    function checkUniqueFields() {
        return $.ajax({
            url: 'api/customer_master_check_unique.php',
            type: 'POST',
            dataType: 'json',
            data: {
                record_id: 0,
                email: form.querySelector('[name="email"]').value.trim(),
                mobile: form.querySelector('[name="mobile"]').value.trim()
            }
        });
    }

    let isSubmitting = false;
    const submitButton = document.getElementById('installedBaseAddCustomerSubmitBtn');

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

        checkUniqueFields()
            .done(function (response) {
                if (response && response.errors && Object.keys(response.errors).length > 0) {
                    showErrors(response.errors);
                    return;
                }

                isSubmitting = true;
                if (submitButton) {
                    submitButton.classList.add('disabled_btn');
                    submitButton.disabled = true;
                }

                const formData = $(form).serialize();

                $.ajax({
                    url: 'api/customer_master_create.php',
                    type: 'POST',
                    dataType: 'json',
                    data: formData
                }).done(function (result) {
                    if (!result || !result.success || !result.id) {
                        showInstalledBaseAddCustomerAlert((result && result.error) || 'Failed to save customer.');
                        if (result && result.field_errors) {
                            showErrors(result.field_errors);
                        }
                        return;
                    }

                    const label = result.text || result.customer_name || '';
                    if (typeof setInstalledBaseCustomerSelect2 === 'function'
                        && document.getElementById('installedBaseCustomerSelect')) {
                        setInstalledBaseCustomerSelect2(result.id, label);
                    }
                    if (typeof setComplaintCustomerSelect2 === 'function'
                        && document.getElementById('complaintCustomerSelect')) {
                        setComplaintCustomerSelect2(result.id, label);
                    }
                    if (typeof applyOrderBookingEndCustomer === 'function'
                        && document.getElementById('orderBookingEndCustomerSelect')) {
                        applyOrderBookingEndCustomer(result);
                    }

                    closeInstalledBaseAddCustomerModal();
                }).fail(function (xhr) {
                    let message = 'Failed to save customer.';
                    let fieldErrors = null;
                    try {
                        const payload = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
                        if (payload && payload.error) {
                            message = payload.error;
                        }
                        if (payload && payload.field_errors) {
                            fieldErrors = payload.field_errors;
                        }
                    } catch (err) {
                        // keep default message
                    }
                    showInstalledBaseAddCustomerAlert(message);
                    if (fieldErrors) {
                        showErrors(fieldErrors);
                    }
                }).always(function () {
                    isSubmitting = false;
                    if (submitButton) {
                        submitButton.classList.remove('disabled_btn');
                        submitButton.disabled = false;
                    }
                });
            })
            .fail(function () {
                showErrors({
                    email: ['Unable to verify email and mobile. Please try again.']
                });
            });
    });
}

function initInstalledBaseAddCustomerModal() {
    const modalEl = document.getElementById('installedBaseAddCustomerModal');
    const form = document.getElementById('installedBaseAddCustomerForm');
    if (!modalEl || !form) {
        return;
    }

    initPincodeSelect2('installedBaseAddCustomerForm', 'installedBaseCustomerModalPincodeSelect', {
        dropdownParent: $('#installedBaseAddCustomerModal')
    });
    initInstalledBaseAddCustomerFormValidation();

    modalEl.addEventListener('hidden.bs.modal', function () {
        resetInstalledBaseAddCustomerForm();
    });
}
