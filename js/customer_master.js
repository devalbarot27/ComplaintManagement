function initcustomerMasterFormValidation() {
    const form = document.getElementById('customerMasterForm');
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
            window.jQuery('#customerMasterPincodeSelect')
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
            window.jQuery('#customerMasterPincodeSelect')
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

        // Auto-filled address fields clear once pincode selection populates them.
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
        window.jQuery('#customerMasterPincodeSelect').on('select2:select select2:clear change', function () {
            validateField(form.querySelector('[name="pincode"]'));
        });
    }

    function getRecordId() {
        const recordId = document.getElementById('customerMasterRecordId');
        return recordId && recordId.value !== '' ? parseInt(recordId.value, 10) : 0;
    }

    function checkUniqueFields(recordId) {
        return $.ajax({
            url: 'api/customer_master_check_unique.php',
            type: 'POST',
            dataType: 'json',
            data: {
                record_id: recordId || 0,
                email: form.querySelector('[name="email"]').value.trim(),
                mobile: form.querySelector('[name="mobile"]').value.trim()
            }
        });
    }

    let isSubmitting = false;
    const submitButton = document.getElementById('submitcustomerMasterBtn');

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

        checkUniqueFields(getRecordId())
            .done(function (response) {
                if (response && response.errors && Object.keys(response.errors).length > 0) {
                    showErrors(response.errors);
                    return;
                }

                isSubmitting = true;
                if (submitButton) {
                    submitButton.classList.add('disabled_btn');
                }
                form.submit();
            })
            .fail(function () {
                showErrors({
                    email: ['Unable to verify email and mobile. Please try again.']
                });
            });
    });
}

function initcustomerMasterDatatable() {
    const $table = $('#customerMasterTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/customer_master_datatable.php',
            type: 'POST'
        },
        order: [[0, 'desc']],
        pageLength: 10,
        columns: [
            { data: 'id' },
            { data: 'customer_name' },
            { data: 'email' },
            { data: 'mobile' },
            { data: 'city' },
            { data: 'state' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No customers found.',
            zeroRecords: 'No matching customers found.'
        }
    });
}

function fillcustomerMasterForm(record) {
    const form = document.getElementById('customerMasterForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('customerMasterRecordId').value = record.id || '';
    document.getElementById('customerMasterFormModeLabel').textContent = record.id
        ? 'Edit Customer'
        : 'Add Customer';
    document.getElementById('submitcustomerMasterBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Customer'
        : '<i class="bi bi-check-lg"></i> Save Customer';

    form.querySelector('[name="customer_name"]').value = record.customer_name || '';
    form.querySelector('[name="email"]').value = record.email || '';
    form.querySelector('[name="mobile"]').value = record.mobile || '';
    form.querySelector('[name="street_1"]').value = record.street_1 || '';
    form.querySelector('[name="street_2"]').value = record.street_2 || '';

    setPincodeSelect2(form, 'customerMasterPincodeSelect', {
        pincode: record.pincode || '',
        city: record.city || '',
        district: record.district || '',
        state: record.state || ''
    });
}

function resetcustomerMasterForm() {
    const form = document.getElementById('customerMasterForm');
    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('customerMasterRecordId').value = '';
    document.getElementById('customerMasterFormModeLabel').textContent = 'Add Customer';
    document.getElementById('submitcustomerMasterBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Customer';
    resetPincodeSelect2(form, 'customerMasterPincodeSelect');

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
    $('#customerMasterPincodeSelect')
        .next('.select2-container')
        .find('.select2-selection')
        .removeClass('is-invalid');
}

function opencustomerMasterFormPanel() {
    document.getElementById('customerMasterFormCard').classList.add('show');
    document.getElementById('opencustomerMasterForm').style.display = 'none';
    document.getElementById('closecustomerMasterForm').classList.add('show');
}

function closecustomerMasterFormPanel() {
    document.getElementById('customerMasterFormCard').classList.remove('show');
    document.getElementById('opencustomerMasterForm').style.display = 'flex';
    document.getElementById('closecustomerMasterForm').classList.remove('show');
    resetcustomerMasterForm();
}

function bootcustomerMasterPage() {
    initPincodeSelect2('customerMasterForm', 'customerMasterPincodeSelect');
    initcustomerMasterFormValidation();
    initcustomerMasterDatatable();

    document.getElementById('cancelcustomerMasterForm').addEventListener('click', closecustomerMasterFormPanel);
    document.getElementById('closecustomerMasterForm').addEventListener('click', closecustomerMasterFormPanel);
    document.getElementById('opencustomerMasterForm').addEventListener('click', function () {
        resetcustomerMasterForm();
        opencustomerMasterFormPanel();
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-customer-master-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/customer_master_get.php', { id: id })
            .done(function (record) {
                resetcustomerMasterForm();
                fillcustomerMasterForm(record);
                opencustomerMasterFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load customer details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootcustomerMasterPage);
} else {
    bootcustomerMasterPage();
}
