function slugifyPermissionName(value) {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function initPermissionsFormValidation() {
    const form = document.getElementById('permissionForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    validate.validators.permissionSlugFormat = function (value) {
        if (!/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/.test(String(value || ''))) {
            return '^Permission slug may only contain lowercase letters, numbers, hyphens, and underscores';
        }
        return null;
    };

    const constraints = {
        module_id: {
            presence: { allowEmpty: false, message: '^Module is required' }
        },
        permission_name: {
            presence: { allowEmpty: false, message: '^Permission Name is required' },
            length: { maximum: 100, message: '^Permission Name cannot exceed 100 characters' }
        },
        permission_slug: {
            presence: { allowEmpty: false, message: '^Permission Slug is required' },
            permissionSlugFormat: true
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

    const permissionNameInput = form.querySelector('[name="permission_name"]');
    const permissionSlugInput = form.querySelector('[name="permission_slug"]');
    let slugEdited = false;

    permissionSlugInput.addEventListener('input', function () {
        slugEdited = permissionSlugInput.value.trim() !== '';
    });

    permissionNameInput.addEventListener('input', function () {
        if (!slugEdited && !document.getElementById('permissionRecordId').value) {
            permissionSlugInput.value = slugifyPermissionName(permissionNameInput.value);
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const errors = validate(form, constraints);
        showErrors(errors);
        if (!errors) {
            form.submit();
        }
    });
}

function initPermissionsDatatable() {
    const $table = $('#permissionsTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: 'api/permissions_datatable.php', type: 'POST' },
        order: [[0, 'desc']],
        pageLength: 10,
        columns: [
            { data: 'id' },
            { data: 'module_name' },
            { data: 'permission_name' },
            { data: 'permission_slug' },
            { data: 'description' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });
}

function fillPermissionForm(record) {
    const form = document.getElementById('permissionForm');
    if (!form || !record) {
        return;
    }

    const isEdit = !!record.id;
    document.getElementById('permissionRecordId').value = record.id || '';
    document.getElementById('permissionFormModeLabel').textContent = isEdit ? 'Edit Permission' : 'Add Permission';
    document.getElementById('submitPermissionBtn').innerHTML = isEdit
        ? '<i class="bi bi-check-lg"></i> Update Permission'
        : '<i class="bi bi-check-lg"></i> Save Permission';

    form.querySelector('[name="module_id"]').value = record.module_id || '';
    form.querySelector('[name="permission_name"]').value = record.permission_name || '';
    form.querySelector('[name="permission_slug"]').value = record.permission_slug || '';
    form.querySelector('[name="description"]').value = record.description || '';
}

function resetPermissionForm() {
    const form = document.getElementById('permissionForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('permissionRecordId').value = '';
    document.getElementById('permissionFormModeLabel').textContent = 'Add Permission';
    document.getElementById('submitPermissionBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Permission';
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openPermissionFormPanel() {
    document.getElementById('permissionFormCard').classList.add('show');
    document.getElementById('openPermissionForm').style.display = 'none';
    document.getElementById('closePermissionForm').classList.add('show');
}

function closePermissionFormPanel() {
    document.getElementById('permissionFormCard').classList.remove('show');
    document.getElementById('openPermissionForm').style.display = 'flex';
    document.getElementById('closePermissionForm').classList.remove('show');
    resetPermissionForm();
}

function bootPermissionsPage() {
    initPermissionsFormValidation();
    initPermissionsDatatable();

    document.getElementById('cancelPermissionForm').addEventListener('click', closePermissionFormPanel);
    document.getElementById('closePermissionForm').addEventListener('click', closePermissionFormPanel);
    document.getElementById('openPermissionForm').addEventListener('click', function () {
        resetPermissionForm();
        openPermissionFormPanel();
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-permission-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/permissions_get.php', { id: id })
            .done(function (record) {
                resetPermissionForm();
                fillPermissionForm(record);
                openPermissionFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load permission details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPermissionsPage);
} else {
    bootPermissionsPage();
}
