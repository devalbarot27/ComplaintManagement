function initProductFormValidation() {
    const form = document.getElementById('productForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        dpst: {
            presence: { allowEmpty: false, message: '^DPST is required' },
            length: { maximum: 20, message: '^DPST cannot exceed 20 characters' }
        },
        product_group: {
            presence: { allowEmpty: false, message: '^Product Group is required' },
            length: { maximum: 50, message: '^Product Group cannot exceed 50 characters' }
        },
        tplcode: {
            presence: { allowEmpty: false, message: '^TPL Code is required' },
            length: { maximum: 20, message: '^TPL Code cannot exceed 20 characters' }
        },
        tpldesc: {
            presence: { allowEmpty: false, message: '^TPL Description is required' },
            length: { maximum: 60, message: '^TPL Description cannot exceed 60 characters' }
        },
        dealer_price: {
            format: {
                pattern: /^-?\d+(\.\d+)?$/,
                message: '^Dealer Price must be numeric'
            }
        },
        mc: {
            format: {
                pattern: /^-?\d+(\.\d+)?$/,
                message: '^MC must be numeric'
            }
        },
        vc: {
            format: {
                pattern: /^-?\d+(\.\d+)?$/,
                message: '^VC must be numeric'
            }
        },
        fc: {
            format: {
                pattern: /^-?\d+(\.\d+)?$/,
                message: '^FC must be numeric'
            }
        },
        cos: {
            presence: { allowEmpty: false, message: '^COS (Price) is required' },
            format: {
                pattern: /^-?\d+(\.\d+)?$/,
                message: '^COS (Price) must be numeric'
            }
        },
        valid: {
            presence: { allowEmpty: false, message: '^Valid is required' },
            inclusion: {
                within: ['Y', 'N'],
                message: '^Valid must be Y or N'
            }
        },
        company: {
            presence: { allowEmpty: false, message: '^Company is required' },
            length: { maximum: 50, message: '^Company cannot exceed 50 characters' }
        },
        warehouse: {
            presence: { allowEmpty: false, message: '^Warehouse is required' },
            length: { maximum: 20, message: '^Warehouse cannot exceed 20 characters' }
        },
        payment_term: {
            length: { maximum: 100, message: '^Payment Term cannot exceed 100 characters' }
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
            if (input) {
                input.classList.add('is-invalid');
            }
            if (msg && errors[field]) {
                msg.textContent = errors[field][0];
            }
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
    }

    function setFieldError(fieldName, message) {
        const input = form.querySelector('[name="' + fieldName + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + fieldName + '"]');
        if (input) {
            input.classList.add('is-invalid');
        }
        if (msg) {
            msg.textContent = message || '';
        }
    }

    function getActiveConstraints(values) {
        const activeConstraints = Object.assign({}, constraints);
        ['dealer_price', 'mc', 'vc', 'fc'].forEach(function (field) {
            if (values[field] === '' || values[field] == null) {
                delete activeConstraints[field];
            }
        });
        return activeConstraints;
    }

    function validateField(input) {
        const fieldName = input.name;
        if (!fieldName || !constraints[fieldName]) {
            return;
        }

        const values = validate.collectFormValues(form);
        const activeConstraints = getActiveConstraints(values);

        if (!activeConstraints[fieldName]) {
            clearFieldError(fieldName);
            return;
        }

        const fieldErrors = validate.single(input.value, activeConstraints[fieldName]);
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

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const values = validate.collectFormValues(form);
        const activeConstraints = getActiveConstraints(values);
        const errors = validate(form, activeConstraints);
        showErrors(errors);
        if (!errors) {
            form.submit();
        }
    });

    ['dealer_price', 'mc', 'vc', 'fc', 'cos'].forEach(function (fieldName) {
        initNumericOnlyInput(form.querySelector('[name="' + fieldName + '"]'));
    });
}

function initNumericOnlyInput(input) {
    if (!input) {
        return;
    }

    const allowedControlKeys = {
        Backspace: true,
        Delete: true,
        Tab: true,
        ArrowLeft: true,
        ArrowRight: true
    };

    input.addEventListener('keydown', function (e) {
        if (e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }

        if (allowedControlKeys[e.key]) {
            return;
        }

        if (/^[0-9]$/.test(e.key)) {
            return;
        }

        if (e.key === '.') {
            if (input.value.indexOf('.') !== -1) {
                e.preventDefault();
            }
            return;
        }

        e.preventDefault();
    });

    input.addEventListener('paste', function (e) {
        e.preventDefault();
        const clipboard = (e.clipboardData || window.clipboardData);
        const pasted = clipboard ? String(clipboard.getData('text') || '') : '';
        const sanitized = pasted.replace(/[^0-9.]/g, '');
        const parts = sanitized.split('.');
        const normalized = parts.length > 1
            ? parts.shift() + '.' + parts.join('')
            : parts[0] || '';

        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        const nextValue = input.value.slice(0, start) + normalized + input.value.slice(end);
        const nextParts = nextValue.split('.');
        input.value = nextParts.length > 1
            ? nextParts.shift() + '.' + nextParts.join('')
            : nextParts[0] || '';

        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    input.addEventListener('input', function () {
        const cleaned = input.value.replace(/[^0-9.]/g, '');
        const parts = cleaned.split('.');
        const normalized = parts.length > 1
            ? parts.shift() + '.' + parts.join('')
            : parts[0] || '';
        if (input.value !== normalized) {
            input.value = normalized;
        }
    });
}

function initProductDatatable() {
    const $table = $('#productsTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: 'api/products_datatable.php',
            type: 'POST',
            data: function (payload) {
                const filterEl = document.getElementById('productValidFilter');
                if (filterEl) {
                    payload.valid_filter = filterEl.value;
                }
            }
        },
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'dpst' },
            { data: 'product_group' },
            { data: 'tplcode' },
            { data: 'tpldesc' },
            { data: 'cos' },
            { data: 'valid' },
            { data: 'company' },
            { data: 'warehouse' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No products found.',
            zeroRecords: 'No matching products found.'
        }
    });
}

function fillProductForm(record) {
    const form = document.getElementById('productForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('productRecordId').value = record.id || '';
    document.getElementById('productFormModeLabel').textContent = record.id
        ? 'Edit Product'
        : 'Add Product';
    document.getElementById('submitProductBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Product'
        : '<i class="bi bi-check-lg"></i> Save Product';

    const fields = [
        'dpst', 'product_group', 'tplcode', 'tpldesc', 'dealer_price',
        'tod_flag', 'excisable', 'mc', 'vc', 'fc', 'cos', 'valid',
        'company', 'warehouse', 'payment_term'
    ];

    fields.forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = record[field] != null ? record[field] : '';
        }
    });
}

function resetProductForm() {
    const form = document.getElementById('productForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('productRecordId').value = '';
    document.getElementById('productFormModeLabel').textContent = 'Add Product';
    document.getElementById('submitProductBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Product';
    form.querySelector('[name="tod_flag"]').value = 'N';
    form.querySelector('[name="excisable"]').value = 'N';
    form.querySelector('[name="valid"]').value = '';
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openProductFormPanel() {
    document.getElementById('productFormCard').classList.add('show');
    document.getElementById('openProductForm').style.display = 'none';
    document.getElementById('closeProductForm').classList.add('show');
}

function closeProductFormPanel() {
    document.getElementById('productFormCard').classList.remove('show');
    document.getElementById('openProductForm').style.display = 'flex';
    document.getElementById('closeProductForm').classList.remove('show');
    resetProductForm();
}

function bootProductsPage() {
    initProductFormValidation();
    const table = initProductDatatable();

    document.getElementById('cancelProductForm').addEventListener('click', closeProductFormPanel);
    document.getElementById('closeProductForm').addEventListener('click', closeProductFormPanel);
    document.getElementById('openProductForm').addEventListener('click', function () {
        resetProductForm();
        openProductFormPanel();
    });

    const validFilter = document.getElementById('productValidFilter');
    if (validFilter) {
        validFilter.addEventListener('change', function () {
            if (table) {
                table.ajax.reload();
            }
        });
    }

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-product-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/products_get.php', { id: id })
            .done(function (record) {
                resetProductForm();
                fillProductForm(record);
                openProductFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load product details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootProductsPage);
} else {
    bootProductsPage();
}
