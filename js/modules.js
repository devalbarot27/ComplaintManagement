function slugifyModuleName(value) {
    return String(value || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function initModulesFormValidation() {
    const form = document.getElementById('moduleForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    validate.validators.moduleSlugFormat = function (value) {
        if (!/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/.test(String(value || ''))) {
            return '^Module slug may only contain lowercase letters, numbers, hyphens, and underscores';
        }
        return null;
    };

    const constraints = {
        module_name: {
            presence: { allowEmpty: false, message: '^Module Name is required' },
            length: { maximum: 100, message: '^Module Name cannot exceed 100 characters' }
        },
        module_slug: {
            presence: { allowEmpty: false, message: '^Module Slug is required' },
            moduleSlugFormat: true
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

    const moduleNameInput = form.querySelector('[name="module_name"]');
    const moduleSlugInput = form.querySelector('[name="module_slug"]');
    let slugEdited = false;

    moduleSlugInput.addEventListener('input', function () {
        slugEdited = moduleSlugInput.value.trim() !== '';
    });

    moduleNameInput.addEventListener('input', function () {
        if (!slugEdited && !document.getElementById('moduleRecordId').value) {
            moduleSlugInput.value = slugifyModuleName(moduleNameInput.value);
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

function initModulesDatatable() {
    const $table = $('#modulesTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: 'api/modules_datatable.php', type: 'POST' },
        order: [[0, 'desc']],
        pageLength: 10,
        columns: [
            { data: 'id' },
            { data: 'module_name' },
            { data: 'module_slug' },
            { data: 'description' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });
}

function fillModuleForm(record) {
    const form = document.getElementById('moduleForm');
    if (!form || !record) {
        return;
    }

    const isEdit = !!record.id;
    document.getElementById('moduleRecordId').value = record.id || '';
    document.getElementById('moduleFormModeLabel').textContent = isEdit ? 'Edit Module' : 'Add Module';
    document.getElementById('submitModuleBtn').innerHTML = isEdit
        ? '<i class="bi bi-check-lg"></i> Update Module'
        : '<i class="bi bi-check-lg"></i> Save Module';

    form.querySelector('[name="module_name"]').value = record.module_name || '';
    form.querySelector('[name="module_slug"]').value = record.module_slug || '';
    form.querySelector('[name="description"]').value = record.description || '';

    const defaultWrap = document.getElementById('defaultPermissionsWrap');
    if (defaultWrap) {
        defaultWrap.style.display = isEdit ? 'none' : '';
    }
}

function resetModuleForm() {
    const form = document.getElementById('moduleForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('moduleRecordId').value = '';
    document.getElementById('moduleFormModeLabel').textContent = 'Add Module';
    document.getElementById('submitModuleBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Module';
    document.getElementById('createDefaultPermissions').checked = true;
    document.getElementById('defaultPermissionsWrap').style.display = '';
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openModuleFormPanel() {
    document.getElementById('moduleFormCard').classList.add('show');
    document.getElementById('openModuleForm').style.display = 'none';
    document.getElementById('closeModuleForm').classList.add('show');
}

function closeModuleFormPanel() {
    document.getElementById('moduleFormCard').classList.remove('show');
    document.getElementById('openModuleForm').style.display = 'flex';
    document.getElementById('closeModuleForm').classList.remove('show');
    resetModuleForm();
}

function bootModulesPage() {
    initModulesFormValidation();
    initModulesDatatable();

    document.getElementById('cancelModuleForm').addEventListener('click', closeModuleFormPanel);
    document.getElementById('closeModuleForm').addEventListener('click', closeModuleFormPanel);
    document.getElementById('openModuleForm').addEventListener('click', function () {
        resetModuleForm();
        openModuleFormPanel();
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-module-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/modules_get.php', { id: id })
            .done(function (record) {
                resetModuleForm();
                fillModuleForm(record);
                openModuleFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load module details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootModulesPage);
} else {
    bootModulesPage();
}
