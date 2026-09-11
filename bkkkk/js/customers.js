function initCustomerFormValidation() {
    const form = document.getElementById('customerForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        customer_code: {
            presence: { allowEmpty: false, message: '^Customer code is required' }
        }
    };

    function clearValidationState() {
        form.querySelectorAll('.validation-msg').forEach(function (msg) {
            msg.textContent = '';
        });
        form.querySelectorAll('.form-control, .select2-selection').forEach(function (input) {
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
            if (input) {
                input.classList.add('is-invalid');
            }
            if (field === 'customer_code' && window.jQuery) {
                window.jQuery('#customerCodeSelect').next('.select2-container').find('.select2-selection').addClass('is-invalid');
            }
            if (msg && errors[field]) {
                msg.textContent = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
            }
        });
    }

    function getRecordId() {
        const recordId = document.getElementById('customerRecordId');
        return recordId && recordId.value !== '' ? parseInt(recordId.value, 10) : 0;
    }

    function checkCustomerUniqueFields(recordId) {
        return $.ajax({
            url: 'api/customers_check_unique.php',
            type: 'POST',
            dataType: 'json',
            data: {
                record_id: recordId || 0,
                customer_code: form.querySelector('[name="customer_code"]').value.trim()
            }
        });
    }

    let isSubmitting = false;
    const submitButton = document.getElementById('submitCustomerBtn');

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

        checkCustomerUniqueFields(getRecordId())
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
                    customer_code: ['Unable to verify customer code. Please try again.']
                });
            });
    });
}

function setCustomerNameDisplay(name) {
    const input = document.getElementById('customerNameDisplay');
    if (input) {
        input.value = name || '';
    }
}

function initCustomerCodeSelect2() {
    const $select = $('#customerCodeSelect');
    if (!$select.length || typeof $select.select2 !== 'function') {
        return;
    }

    $select.select2({
        placeholder: 'Search customer code',
        allowClear: false,
        width: '100%',
        ajax: {
            url: 'api/customer_master_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (data) {
                return { results: (data && data.results) ? data.results : [] };
            },
            cache: true
        }
    });

    $select.on('select2:select', function (e) {
        const data = e.params.data || {};
        setCustomerNameDisplay(data.cuname || '');
    });

    $select.on('change', function () {
        if (!$select.val()) {
            setCustomerNameDisplay('');
        }
    });
}

function setCustomerCodeSelect2Value(id, text, name) {
    const $select = $('#customerCodeSelect');
    if (!$select.length) {
        return;
    }

    $select.find('option').remove();
    if (id) {
        const option = new Option(text || id, id, true, true);
        $select.append(option).trigger('change');
        setCustomerNameDisplay(name || '');
    } else {
        $select.val(null).trigger('change');
        setCustomerNameDisplay('');
    }
}

function initCustomersDatatable() {
    const $table = $('#customersTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/customers_datatable.php',
            type: 'POST'
        },
        order: [[0, 'desc']],
        pageLength: 10,
        columns: [
            { data: 'id' },
            { data: 'customer_code' },
            { data: 'customer_name', orderable: false },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });
}

function fillCustomerForm(record) {
    const form = document.getElementById('customerForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('customerRecordId').value = record.id || '';
    document.getElementById('customerFormModeLabel').textContent = record.id
        ? 'Edit Customer'
        : 'Add Customer';
    document.getElementById('submitCustomerBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Customer'
        : '<i class="bi bi-check-lg"></i> Save Customer';

    setCustomerCodeSelect2Value(
        record.customer_code || '',
        record.customer_code_text || record.customer_code || '',
        record.customer_name || ''
    );
}

function resetCustomerForm() {
    const form = document.getElementById('customerForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('customerRecordId').value = '';
    document.getElementById('customerFormModeLabel').textContent = 'Add Customer';
    document.getElementById('submitCustomerBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Customer';
    setCustomerCodeSelect2Value('', '', '');
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
    $('#customerCodeSelect').next('.select2-container').find('.select2-selection').removeClass('is-invalid');
}

function openCustomerFormPanel() {
    document.getElementById('customerFormCard').classList.add('show');
    document.getElementById('openCustomerForm').style.display = 'none';
    document.getElementById('closeCustomerForm').classList.add('show');
}

function closeCustomerFormPanel() {
    document.getElementById('customerFormCard').classList.remove('show');
    document.getElementById('openCustomerForm').style.display = 'flex';
    document.getElementById('closeCustomerForm').classList.remove('show');
    resetCustomerForm();
}

function bootCustomersPage() {
    initCustomerCodeSelect2();
    initCustomerFormValidation();
    initCustomersDatatable();

    document.getElementById('cancelCustomerForm').addEventListener('click', closeCustomerFormPanel);
    document.getElementById('closeCustomerForm').addEventListener('click', closeCustomerFormPanel);
    document.getElementById('openCustomerForm').addEventListener('click', function () {
        resetCustomerForm();
        openCustomerFormPanel();
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-customer-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/customers_get.php', { id: id })
            .done(function (record) {
                resetCustomerForm();
                fillCustomerForm(record);
                openCustomerFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load customer sync details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootCustomersPage);
} else {
    bootCustomersPage();
}