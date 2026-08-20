function getDistanceWisePriceRangeType(form) {
    const select = form.querySelector('[name="range_type"]');
    return select ? select.value : 'between';
}

function toggleDistanceWisePriceRangeFields(form) {
    const rangeType = getDistanceWisePriceRangeType(form);
    const fromGroup = document.getElementById('distanceWisePriceFromGroup');
    const toGroup = document.getElementById('distanceWisePriceToGroup');
    const fromInput = form.querySelector('[name="from_km"]');
    const toInput = form.querySelector('[name="to_km"]');

    fromGroup.style.display = rangeType === 'lt' ? 'none' : '';
    toGroup.style.display = rangeType === 'gt' ? 'none' : '';

    if (rangeType === 'lt') {
        fromInput.value = '';
        fromInput.removeAttribute('required');
        toInput.setAttribute('placeholder', 'e.g. 50');
    } else if (rangeType === 'gt') {
        toInput.value = '';
        toInput.removeAttribute('required');
        fromInput.setAttribute('placeholder', 'e.g. 500');
    } else {
        fromInput.setAttribute('placeholder', 'e.g. 51');
        toInput.setAttribute('placeholder', 'e.g. 100');
    }
}

function initDistanceWisePriceFormValidation() {
    const form = document.getElementById('distanceWisePriceForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    function constraintsForRangeType(rangeType) {
        const constraints = {
            range_type: {
                presence: { allowEmpty: false, message: '^Range type is required' }
            },
            price: {
                presence: { allowEmpty: false, message: '^Price is required' },
                numericality: { greaterThanOrEqualTo: 0, message: '^Price must be 0 or greater' }
            },
            status: {
                presence: { allowEmpty: false, message: '^Status is required' }
            }
        };

        if (rangeType === 'lt') {
            constraints.to_km = {
                presence: { allowEmpty: false, message: '^To KM is required' },
                numericality: { greaterThan: 0, message: '^To KM must be greater than 0' }
            };
        } else if (rangeType === 'gt') {
            constraints.from_km = {
                presence: { allowEmpty: false, message: '^From KM is required' },
                numericality: { greaterThanOrEqualTo: 0, message: '^From KM must be 0 or greater' }
            };
        } else {
            constraints.from_km = {
                presence: { allowEmpty: false, message: '^From KM is required' },
                numericality: { greaterThanOrEqualTo: 0, message: '^From KM must be 0 or greater' }
            };
            constraints.to_km = {
                presence: { allowEmpty: false, message: '^To KM is required' },
                numericality: { greaterThan: 0, message: '^To KM must be greater than 0' }
            };
        }

        return constraints;
    }

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

    form.querySelector('[name="range_type"]').addEventListener('change', function () {
        toggleDistanceWisePriceRangeFields(form);
        clearValidationState();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const rangeType = getDistanceWisePriceRangeType(form);
        const errors = validate(form, constraintsForRangeType(rangeType)) || {};
        if (rangeType === 'between') {
            const fromKm = parseFloat(form.querySelector('[name="from_km"]').value);
            const toKm = parseFloat(form.querySelector('[name="to_km"]').value);
            if (!isNaN(fromKm) && !isNaN(toKm) && toKm <= fromKm) {
                errors.to_km = ['To KM must be greater than From KM'];
            }
        }
        showErrors(Object.keys(errors).length ? errors : null);
        if (!Object.keys(errors).length) {
            form.submit();
        }
    });
}

function initDistanceWisePricesDatatable() {
    const $table = $('#distanceWisePricesTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/distance_wise_prices_datatable.php',
            type: 'POST'
        },
        order: [[1, 'asc']],
        pageLength: 10,
        columns: [
            { data: 'id' },
            { data: 'km_range' },
            { data: 'price' },
            { data: 'status', orderable: false },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });
}

function fillDistanceWisePriceForm(record) {
    const form = document.getElementById('distanceWisePriceForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('distanceWisePriceRecordId').value = record.id || '';
    document.getElementById('distanceWisePriceFormModeLabel').textContent = record.id
        ? 'Edit Distance Wise Price'
        : 'Add Distance Wise Price';
    document.getElementById('submitDistanceWisePriceBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Distance Wise Price'
        : '<i class="bi bi-check-lg"></i> Save Distance Wise Price';

    form.querySelector('[name="range_type"]').value = record.range_type || 'between';
    toggleDistanceWisePriceRangeFields(form);
    form.querySelector('[name="from_km"]').value = record.from_km && record.from_km !== '-' ? record.from_km : '';
    form.querySelector('[name="to_km"]').value = record.to_km && record.to_km !== '-' ? record.to_km : '';
    form.querySelector('[name="price"]').value = record.price && record.price !== '-' ? record.price : '';
    form.querySelector('[name="status"]').value = record.status || 'active';
}

function resetDistanceWisePriceForm() {
    const form = document.getElementById('distanceWisePriceForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('distanceWisePriceRecordId').value = '';
    document.getElementById('distanceWisePriceFormModeLabel').textContent = 'Add Distance Wise Price';
    document.getElementById('submitDistanceWisePriceBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Distance Wise Price';
    form.querySelector('[name="range_type"]').value = 'between';
    form.querySelector('[name="status"]').value = 'active';
    toggleDistanceWisePriceRangeFields(form);
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openDistanceWisePriceFormPanel() {
    document.getElementById('distanceWisePriceFormCard').classList.add('show');
    document.getElementById('openDistanceWisePriceForm').style.display = 'none';
    document.getElementById('closeDistanceWisePriceForm').classList.add('show');
}

function closeDistanceWisePriceFormPanel() {
    document.getElementById('distanceWisePriceFormCard').classList.remove('show');
    document.getElementById('openDistanceWisePriceForm').style.display = 'flex';
    document.getElementById('closeDistanceWisePriceForm').classList.remove('show');
    resetDistanceWisePriceForm();
}

function bootDistanceWisePricesPage() {
    const form = document.getElementById('distanceWisePriceForm');
    if (form) {
        toggleDistanceWisePriceRangeFields(form);
    }

    initDistanceWisePriceFormValidation();
    initDistanceWisePricesDatatable();

    document.getElementById('cancelDistanceWisePriceForm').addEventListener('click', closeDistanceWisePriceFormPanel);
    document.getElementById('closeDistanceWisePriceForm').addEventListener('click', closeDistanceWisePriceFormPanel);
    document.getElementById('openDistanceWisePriceForm').addEventListener('click', function () {
        resetDistanceWisePriceForm();
        openDistanceWisePriceFormPanel();
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-distance-wise-price-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/distance_wise_prices_get.php', { id: id })
            .done(function (record) {
                resetDistanceWisePriceForm();
                fillDistanceWisePriceForm(record);
                openDistanceWisePriceFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load record details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootDistanceWisePricesPage);
} else {
    bootDistanceWisePricesPage();
}
