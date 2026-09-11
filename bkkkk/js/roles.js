function initRolesFormValidation() {
    const form = document.getElementById('roleForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        role_name: {
            presence: { allowEmpty: false, message: '^Role Name is required' },
            length: { maximum: 100, message: '^Role Name cannot exceed 100 characters' }
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

function initRolesDatatable() {
    const $table = $('#rolesTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: 'api/roles_datatable.php', type: 'POST' },
        order: [[0, 'desc']],
        pageLength: 10,
        columns: [
            { data: 'id' },
            { data: 'role_name' },
            { data: 'description' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });
}

function fillRoleForm(record) {
    const form = document.getElementById('roleForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('roleRecordId').value = record.id || '';
    document.getElementById('roleFormModeLabel').textContent = record.id ? 'Edit Role' : 'Add Role';
    document.getElementById('submitRoleBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Role'
        : '<i class="bi bi-check-lg"></i> Save Role';

    form.querySelector('[name="role_name"]').value = record.role_name || '';
    form.querySelector('[name="description"]').value = record.description || '';
}

function resetRoleForm() {
    const form = document.getElementById('roleForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('roleRecordId').value = '';
    document.getElementById('roleFormModeLabel').textContent = 'Add Role';
    document.getElementById('submitRoleBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Role';
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openRoleFormPanel() {
    document.getElementById('roleFormCard').classList.add('show');
    document.getElementById('openRoleForm').style.display = 'none';
    document.getElementById('closeRoleForm').classList.add('show');
}

function closeRoleFormPanel() {
    document.getElementById('roleFormCard').classList.remove('show');
    document.getElementById('openRoleForm').style.display = 'flex';
    document.getElementById('closeRoleForm').classList.remove('show');
    resetRoleForm();
}

function bootRolesPage() {
    initRolesFormValidation();
    initRolesDatatable();

    document.getElementById('cancelRoleForm').addEventListener('click', closeRoleFormPanel);
    document.getElementById('closeRoleForm').addEventListener('click', closeRoleFormPanel);
    document.getElementById('openRoleForm').addEventListener('click', function () {
        resetRoleForm();
        openRoleFormPanel();
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-role-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/roles_get.php', { id: id })
            .done(function (record) {
                resetRoleForm();
                fillRoleForm(record);
                openRoleFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load role details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootRolesPage);
} else {
    bootRolesPage();
}
