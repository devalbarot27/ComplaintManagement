function userApprovalConfigModuleSlug() {
    const select = document.getElementById('userApprovalConfigModule');
    return select ? String(select.value || '') : '';
}

function syncUserApprovalConfigLevels() {
    const moduleSlug = userApprovalConfigModuleSlug();
    const level2Wrap = document.getElementById('userApprovalConfigLevel2Wrap');
    const level2 = document.getElementById('userApprovalConfigLevel2');
    const isService = moduleSlug === (window.USER_APPROVAL_CONFIG_SERVICE_MODULE || 'service-claims');

    if (level2Wrap) {
        level2Wrap.style.display = isService ? 'none' : '';
    }
    if (isService && level2) {
        level2.checked = false;
    }
}

function initUserApprovalConfigFormValidation() {
    const form = document.getElementById('userApprovalConfigForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        user_id: {
            presence: { allowEmpty: false, message: '^User is required' }
        },
        module_slug: {
            presence: { allowEmpty: false, message: '^Module is required' }
        }
    };

    function clearValidationState() {
        form.querySelectorAll('.validation-msg').forEach(function (msg) {
            msg.textContent = '';
        });
        form.querySelectorAll('.form-control, .form-check-input').forEach(function (input) {
            input.classList.remove('is-invalid');
        });
        if (window.jQuery) {
            window.jQuery('#userApprovalConfigUserId').next('.select2-container').find('.select2-selection').removeClass('is-invalid');
        }
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
            if (field === 'user_id' && window.jQuery) {
                window.jQuery('#userApprovalConfigUserId').next('.select2-container').find('.select2-selection').addClass('is-invalid');
            }
            if (msg && errors[field]) {
                msg.textContent = errors[field][0];
            }
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        syncUserApprovalConfigLevels();
        const errors = validate(form, constraints) || {};
        const level1 = document.getElementById('userApprovalConfigLevel1');
        const level2 = document.getElementById('userApprovalConfigLevel2');
        const level2Visible = document.getElementById('userApprovalConfigLevel2Wrap')
            && document.getElementById('userApprovalConfigLevel2Wrap').style.display !== 'none';
        if (!(level1 && level1.checked) && !(level2Visible && level2 && level2.checked)) {
            errors.approval_levels = ['Select at least one approval level'];
        }
        showErrors(Object.keys(errors).length ? errors : null);
        if (!Object.keys(errors).length) {
            form.submit();
        }
    });
}

function initUserApprovalConfigDatatable() {
    const $table = $('#userApprovalConfigTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/user_approval_configurations_datatable.php',
            type: 'POST'
        },
        order: [[0, 'desc']],
        pageLength: 10,
        columns: [
            { data: 'id' },
            { data: 'user_name' },
            { data: 'module_slug' },
            { data: 'level_1_approval' },
            { data: 'level_2_approval' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });
}

function fillUserApprovalConfigForm(record) {
    const form = document.getElementById('userApprovalConfigForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('userApprovalConfigRecordId').value = record.id || '';
    document.getElementById('userApprovalConfigFormModeLabel').textContent = record.id
        ? 'Edit User Approval Configuration'
        : 'Add User Approval Configuration';
    document.getElementById('submitUserApprovalConfigBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update'
        : '<i class="bi bi-check-lg"></i> Save';

    const userSelect = form.querySelector('[name="user_id"]');
    userSelect.value = record.user_id ? String(record.user_id) : '';
    if (window.jQuery) {
        window.jQuery(userSelect).trigger('change');
    }
    form.querySelector('[name="module_slug"]').value = record.module_slug || '';
    document.getElementById('userApprovalConfigLevel1').checked = !!record.level_1_approval;
    document.getElementById('userApprovalConfigLevel2').checked = !!record.level_2_approval;
    syncUserApprovalConfigLevels();
}

function resetUserApprovalConfigForm() {
    const form = document.getElementById('userApprovalConfigForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('userApprovalConfigRecordId').value = '';
    document.getElementById('userApprovalConfigFormModeLabel').textContent = 'Add User Approval Configuration';
    document.getElementById('submitUserApprovalConfigBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save';
    if (window.jQuery) {
        window.jQuery('#userApprovalConfigUserId').val('').trigger('change');
    }
    syncUserApprovalConfigLevels();
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openUserApprovalConfigFormPanel() {
    document.getElementById('userApprovalConfigFormCard').classList.add('show');
    document.getElementById('openUserApprovalConfigForm').style.display = 'none';
    document.getElementById('closeUserApprovalConfigForm').classList.add('show');
}

function closeUserApprovalConfigFormPanel() {
    document.getElementById('userApprovalConfigFormCard').classList.remove('show');
    document.getElementById('openUserApprovalConfigForm').style.display = 'flex';
    document.getElementById('closeUserApprovalConfigForm').classList.remove('show');
    resetUserApprovalConfigForm();
}

function bootUserApprovalConfigPage() {
    if (window.jQuery && $.fn.select2) {
        $('#userApprovalConfigUserId').select2({
            placeholder: 'Select User',
            allowClear: true,
            width: '100%'
        });
        $('#userApprovalConfigModule').select2({
            placeholder: 'Select Module',
            allowClear: true,
            width: '100%'
        });
    }

    const moduleSelect = document.getElementById('userApprovalConfigModule');
    if (moduleSelect) {
        moduleSelect.addEventListener('change', syncUserApprovalConfigLevels);
        if (window.jQuery) {
            window.jQuery(moduleSelect).on('change select2:select select2:clear', syncUserApprovalConfigLevels);
        }
    }

    syncUserApprovalConfigLevels();
    initUserApprovalConfigFormValidation();
    initUserApprovalConfigDatatable();

    document.getElementById('cancelUserApprovalConfigForm').addEventListener('click', closeUserApprovalConfigFormPanel);
    document.getElementById('closeUserApprovalConfigForm').addEventListener('click', closeUserApprovalConfigFormPanel);
    document.getElementById('openUserApprovalConfigForm').addEventListener('click', function () {
        resetUserApprovalConfigForm();
        openUserApprovalConfigFormPanel();
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-user-approval-config-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/user_approval_configurations_get.php', { id: id })
            .done(function (record) {
                resetUserApprovalConfigForm();
                fillUserApprovalConfigForm(record);
                openUserApprovalConfigFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load record details.');
            });
    });

    if (window.USER_APPROVAL_CONFIG_KEEP_FORM_OPEN) {
        openUserApprovalConfigFormPanel();
    }

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootUserApprovalConfigPage);
} else {
    bootUserApprovalConfigPage();
}
