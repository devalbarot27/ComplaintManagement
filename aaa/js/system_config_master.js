function initScmFormValidation() {
    const form = document.getElementById('scmForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        name: {
            presence: { allowEmpty: false, message: '^Name is required' },
            length: { maximum: 100, message: '^Name cannot exceed 100 characters' }
        },
        status: {
            presence: { allowEmpty: false, message: '^Status is required' }
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

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const errors = validate(form, constraints);
        showErrors(errors);
        if (!errors) {
            form.submit();
        }
    });
}

function initScmDatatable(config) {
    const $table = $('#scmTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: config.apiDatatable,
            type: 'POST',
            data: function (payload) {
                payload.type = config.type;
            }
        },
        order: [[0, 'desc']],
        pageLength: 10,
        columns: [
            { data: 'id' },
            { data: 'name' },
            { data: 'status', orderable: false },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });
}

function fillScmForm(record, config) {
    const form = document.getElementById('scmForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('scmRecordId').value = record.id || '';
    document.getElementById('scmFormModeLabel').textContent = record.id
        ? 'Edit ' + config.label
        : 'Add ' + config.label;
    document.getElementById('submitScmBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update ' + config.label
        : '<i class="bi bi-check-lg"></i> Save ' + config.label;

    form.querySelector('[name="name"]').value = record.name || '';
    form.querySelector('[name="status"]').value = record.status || 'active';
}

function resetScmForm(config) {
    const form = document.getElementById('scmForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('scmRecordId').value = '';
    document.getElementById('scmFormModeLabel').textContent = 'Add ' + config.label;
    document.getElementById('submitScmBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save ' + config.label;
    form.querySelector('[name="status"]').value = 'active';
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openScmFormPanel() {
    document.getElementById('scmFormCard').classList.add('show');
    document.getElementById('openScmForm').style.display = 'none';
    document.getElementById('closeScmForm').classList.add('show');
}

function closeScmFormPanel(config) {
    document.getElementById('scmFormCard').classList.remove('show');
    document.getElementById('openScmForm').style.display = 'flex';
    document.getElementById('closeScmForm').classList.remove('show');
    resetScmForm(config);
}

function bootScmPage() {
    const config = window.SCM_PAGE_CONFIG || {};
    if (!config.type) {
        return;
    }

    initScmFormValidation();
    initScmDatatable(config);

    document.getElementById('cancelScmForm').addEventListener('click', function () {
        closeScmFormPanel(config);
    });
    document.getElementById('closeScmForm').addEventListener('click', function () {
        closeScmFormPanel(config);
    });
    document.getElementById('openScmForm').addEventListener('click', function () {
        resetScmForm(config);
        openScmFormPanel();
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-scm-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON(config.apiGet, { type: config.type, id: id })
            .done(function (record) {
                resetScmForm(config);
                fillScmForm(record, config);
                openScmFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load record details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootScmPage);
} else {
    bootScmPage();
}
