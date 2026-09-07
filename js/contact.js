function setContactCustomerSelect2(id, text) {
    const $customer = $('#contactCustomerSelect');
    if (!$customer.length) {
        return;
    }

    $customer.find('option').remove();
    id = id != null ? String(id).trim() : '';
    text = text != null ? String(text).trim() : '';

    if (id !== '') {
        const option = new Option(text || id, id, true, true);
        $customer.append(option).trigger('change');
    } else {
        $customer.val(null).trigger('change');
    }
}

function resetContactCustomerSelect2() {
    setContactCustomerSelect2('', '');
}

function initContactCustomerSelect2() {
    const form = document.getElementById('contactForm');
    const $customer = $('#contactCustomerSelect');

    if (!form || !$customer.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    $customer.select2({
        width: '100%',
        placeholder: $customer.data('placeholder') || 'Search customer',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: 'api/customer_masters_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (data) {
                return data;
            },
            cache: true
        },
        language: {
            noResults: function () {
                return 'No customer found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });
}

function initContactFormValidation() {
    const form = document.getElementById('contactForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    if (typeof validate.validators.contactEmailFormat === 'undefined') {
        validate.validators.contactEmailFormat = function (value) {
            if (!value) {
                return;
            }
            if (!/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(String(value).trim())) {
                return '^Please enter a valid email address';
            }
        };
    }

    if (typeof validate.validators.contactMobileNumber === 'undefined') {
        validate.validators.contactMobileNumber = function (value) {
            if (!value) {
                return;
            }
            if (!/^[1-9]\d{9}$/.test(String(value).trim())) {
                return '^Mobile must be a valid 10-digit number';
            }
        };
    }

    if (typeof validate.validators.contactPersonName === 'undefined') {
        validate.validators.contactPersonName = function (value) {
            if (!value) {
                return;
            }
            if (!/^[A-Za-z]+(?:\s+[A-Za-z]+)*$/.test(String(value).trim())) {
                return '^Name can contain only alphabetic characters and spaces';
            }
        };
    }

    const constraints = {
        customer_id: {
            presence: { allowEmpty: false, message: '^Customer is required' }
        },
        first_name: {
            presence: { allowEmpty: false, message: '^First Name is required' },
            length: { maximum: 100, message: '^First Name cannot exceed 100 characters' },
            contactPersonName: true
        },
        last_name: {
            presence: { allowEmpty: false, message: '^Last Name is required' },
            length: { maximum: 100, message: '^Last Name cannot exceed 100 characters' },
            contactPersonName: true
        },
        email: {
            presence: { allowEmpty: false, message: '^Email is required' },
            email: { message: '^Please enter a valid email address' },
            contactEmailFormat: true,
            length: { maximum: 150, message: '^Email cannot exceed 150 characters' }
        },
        mobile: {
            presence: { allowEmpty: false, message: '^Mobile is required' },
            contactMobileNumber: true
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
        if (fieldName === 'customer_id' && window.jQuery) {
            window.jQuery('#contactCustomerSelect')
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
        if (fieldName === 'customer_id' && window.jQuery) {
            window.jQuery('#contactCustomerSelect')
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
        window.jQuery('#contactCustomerSelect').on('select2:select select2:clear change', function () {
            validateField(form.querySelector('[name="customer_id"]'));
        });
    }

    function getRecordId() {
        const recordId = document.getElementById('contactRecordId');
        return recordId && recordId.value !== '' ? parseInt(recordId.value, 10) : 0;
    }

    function checkUniqueFields(recordId) {
        return $.ajax({
            url: 'api/contact_check_unique.php',
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
    const submitButton = document.getElementById('submitContactBtn');

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

function initContactDatatable() {
    const $table = $('#contactTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/contact_datatable.php',
            type: 'POST'
        },
        order: [[0, 'desc']],
        pageLength: 10,
        columns: [
            { data: 'id' },
            { data: 'customer_name' },
            { data: 'first_name' },
            { data: 'last_name' },
            { data: 'email' },
            { data: 'mobile' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No contacts found.',
            zeroRecords: 'No matching contacts found.'
        }
    });
}

function fillContactForm(record) {
    const form = document.getElementById('contactForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('contactRecordId').value = record.id || '';
    document.getElementById('contactFormModeLabel').textContent = record.id
        ? 'Edit Contact'
        : 'Add Contact';
    document.getElementById('submitContactBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Contact'
        : '<i class="bi bi-check-lg"></i> Save Contact';

    setContactCustomerSelect2(record.customer_id || '', record.customer_label || record.customer_name || '');
    form.querySelector('[name="first_name"]').value = record.first_name || '';
    form.querySelector('[name="last_name"]').value = record.last_name || '';
    form.querySelector('[name="email"]').value = record.email || '';
    form.querySelector('[name="mobile"]').value = record.mobile || '';
}

function resetContactForm() {
    const form = document.getElementById('contactForm');
    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('contactRecordId').value = '';
    document.getElementById('contactFormModeLabel').textContent = 'Add Contact';
    document.getElementById('submitContactBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Contact';
    resetContactCustomerSelect2();

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
    $('#contactCustomerSelect')
        .next('.select2-container')
        .find('.select2-selection')
        .removeClass('is-invalid');

    const submitButton = document.getElementById('submitContactBtn');
    if (submitButton) {
        submitButton.classList.remove('disabled_btn');
    }
}

function openContactFormPanel() {
    const card = document.getElementById('contactFormCard');
    const openBtn = document.getElementById('openContactForm');
    const closeBtn = document.getElementById('closeContactForm');
    if (card) {
        card.classList.add('show');
    }
    if (openBtn) {
        openBtn.style.display = 'none';
    }
    if (closeBtn) {
        closeBtn.classList.add('show');
    }
}

function closeContactFormPanel() {
    const card = document.getElementById('contactFormCard');
    const openBtn = document.getElementById('openContactForm');
    const closeBtn = document.getElementById('closeContactForm');
    if (card) {
        card.classList.remove('show');
    }
    if (openBtn) {
        openBtn.style.display = 'flex';
    }
    if (closeBtn) {
        closeBtn.classList.remove('show');
    }
    resetContactForm();
}

function bootContactPage() {
    initContactCustomerSelect2();
    initContactFormValidation();
    initContactDatatable();

    const openBtn = document.getElementById('openContactForm');
    const closeBtn = document.getElementById('closeContactForm');
    const cancelBtn = document.getElementById('cancelContactForm');

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeContactFormPanel);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closeContactFormPanel);
    }
    if (openBtn) {
        openBtn.addEventListener('click', function () {
            resetContactForm();
            openContactFormPanel();
        });
    }

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-contact-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/contact_get.php', { id: id })
            .done(function (record) {
                resetContactForm();
                fillContactForm(record);
                openContactFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load contact details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootContactPage);
} else {
    bootContactPage();
}
