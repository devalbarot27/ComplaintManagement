function initOutlookEmailUserSelect() {
    const $select = $('#outlookEmailUserId');
    if (!$select.length || typeof $.fn.select2 !== 'function') {
        return;
    }
    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }
    $select.select2({
        width: '100%',
        placeholder: 'Select user',
        dropdownParent: $('#outlookEmailFormCard')
    });
}

function initOutlookEmailFormValidation() {
    const form = document.getElementById('outlookEmailForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        user_id: {
            presence: { allowEmpty: false, message: '^User is required' }
        },
        outlook_email: {
            presence: { allowEmpty: false, message: '^Outlook email is required' },
            email: { message: '^Enter a valid Outlook email address' }
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
                if (input.id === 'outlookEmailUserId') {
                    const selection = form.querySelector('.select2-selection');
                    if (selection) {
                        selection.classList.add('is-invalid');
                    }
                }
            }
            if (msg && errors[field]) {
                msg.textContent = errors[field][0];
            }
        });
    }

    let isSubmitting = false;

    function checkOutlookEmailUnique(recordId) {
        return $.ajax({
            url: 'api/outlook_emails_check_unique.php',
            type: 'POST',
            dataType: 'json',
            data: {
                record_id: recordId || 0,
                outlook_email: form.querySelector('[name="outlook_email"]').value.trim()
            }
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (isSubmitting) {
            return;
        }

        const errors = validate(form, constraints);
        showErrors(errors || null);
        if (errors) {
            return;
        }

        const recordId = parseInt(document.getElementById('outlookEmailRecordId').value, 10) || 0;
        checkOutlookEmailUnique(recordId)
            .done(function (response) {
                if (response && response.errors && Object.keys(response.errors).length > 0) {
                    showErrors(response.errors);
                    return;
                }
                isSubmitting = true;
                form.submit();
            })
            .fail(function () {
                showErrors({
                    outlook_email: ['Unable to verify this Outlook email address.']
                });
            });
    });
}

function initOutlookEmailsDatatable() {
    const $table = $('#outlookEmailsTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/outlook_emails_datatable.php',
            type: 'POST'
        },
        order: [[1, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'user_name' },
            { data: 'username' },
            { data: 'outlook_email' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No Outlook email addresses found.',
            zeroRecords: 'No matching Outlook email addresses found.'
        }
    });
}

function ensureOutlookEmailUserOption(record) {
    const select = document.getElementById('outlookEmailUserId');
    if (!select || !record || !record.user_id) {
        return;
    }
    const value = String(record.user_id);
    const existing = select.querySelector('option[value="' + value + '"]');
    if (existing) {
        return;
    }
    const option = document.createElement('option');
    option.value = value;
    option.textContent = record.user_label || ('User #' + value);
    select.appendChild(option);
}

function fillOutlookEmailForm(record) {
    const form = document.getElementById('outlookEmailForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('outlookEmailRecordId').value = record.id || '';
    document.getElementById('outlookEmailFormModeLabel').textContent = record.id
        ? 'Edit Outlook Email'
        : 'Add Outlook Email';
    document.getElementById('submitOutlookEmailBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Outlook Email'
        : '<i class="bi bi-check-lg"></i> Save Outlook Email';

    ensureOutlookEmailUserOption(record);
    $('#outlookEmailUserId').val(record.user_id ? String(record.user_id) : '').trigger('change');
    form.querySelector('[name="outlook_email"]').value = record.outlook_email || '';
}

function resetOutlookEmailForm() {
    const form = document.getElementById('outlookEmailForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('outlookEmailRecordId').value = '';
    document.getElementById('outlookEmailFormModeLabel').textContent = 'Add Outlook Email';
    document.getElementById('submitOutlookEmailBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Outlook Email';
    $('#outlookEmailUserId').val('').trigger('change');
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openOutlookEmailFormPanel() {
    document.getElementById('outlookEmailFormCard').classList.add('show');
    document.getElementById('openOutlookEmailForm').style.display = 'none';
    document.getElementById('closeOutlookEmailForm').classList.add('show');
    initOutlookEmailUserSelect();
}

function closeOutlookEmailFormPanel() {
    document.getElementById('outlookEmailFormCard').classList.remove('show');
    document.getElementById('openOutlookEmailForm').style.display = 'flex';
    document.getElementById('closeOutlookEmailForm').classList.remove('show');
    resetOutlookEmailForm();
}

function bootOutlookEmailsPage() {
    initOutlookEmailFormValidation();
    initOutlookEmailsDatatable();

    document.getElementById('cancelOutlookEmailForm').addEventListener('click', closeOutlookEmailFormPanel);
    document.getElementById('closeOutlookEmailForm').addEventListener('click', closeOutlookEmailFormPanel);
    document.getElementById('openOutlookEmailForm').addEventListener('click', function () {
        resetOutlookEmailForm();
        openOutlookEmailFormPanel();
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-outlook-email-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/outlook_emails_get.php', { id: id })
            .done(function (record) {
                resetOutlookEmailForm();
                fillOutlookEmailForm(record);
                openOutlookEmailFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load record details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootOutlookEmailsPage);
} else {
    bootOutlookEmailsPage();
}
