function brochureEmailRows() {
    return Array.from(document.querySelectorAll('#brochureEmailList .brochure-email-row'));
}

function refreshBrochureEmailRows() {
    const rows = brochureEmailRows();
    rows.forEach(function (row, index) {
        const message = row.querySelector('.validation-msg');
        if (message) {
            message.setAttribute('data-field', 'emails_' + index);
        }
        const removeBtn = row.querySelector('.brochure-email-remove');
        if (removeBtn) {
            removeBtn.disabled = rows.length === 1;
        }
    });
}

function addBrochureEmailRow(value) {
    const list = document.getElementById('brochureEmailList');
    const first = list ? list.querySelector('.brochure-email-row') : null;
    if (!list || !first) {
        return null;
    }
    const row = first.cloneNode(true);
    const input = row.querySelector('.brochure-email-input');
    if (input) {
        input.value = value || '';
        input.classList.remove('is-invalid');
    }
    const message = row.querySelector('.validation-msg');
    if (message) {
        message.textContent = '';
    }
    list.appendChild(row);
    refreshBrochureEmailRows();
    return row;
}

function resetBrochureEmailRows() {
    const rows = brochureEmailRows();
    rows.forEach(function (row, index) {
        if (index === 0) {
            const input = row.querySelector('.brochure-email-input');
            if (input) {
                input.value = '';
            }
            return;
        }
        row.remove();
    });
    refreshBrochureEmailRows();
}

function initOutlookEmailFormValidation() {
    const form = document.getElementById('outlookEmailForm');
    if (!form) {
        return;
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
            const message = form.querySelector('.validation-msg[data-field="' + field + '"]');
            if (message && errors[field]) {
                message.textContent = errors[field][0];
            }
            if (field.indexOf('emails_') === 0) {
                const index = parseInt(field.replace('emails_', ''), 10);
                const row = brochureEmailRows()[index];
                const input = row ? row.querySelector('.brochure-email-input') : null;
                if (input) {
                    input.classList.add('is-invalid');
                }
            } else {
                const input = form.querySelector('[name="' + field + '"]');
                if (input) {
                    input.classList.add('is-invalid');
                }
            }
        });
    }

    function clientErrors() {
        const errors = {};
        const category = form.querySelector('[name="category"]').value.trim();
        if (category === '') {
            errors.category = ['Category is required.'];
        } else if (!/^[A-Za-z0-9][A-Za-z0-9 .,&'\/()-]{0,99}$/.test(category)) {
            errors.category = ['Enter a valid category.'];
        }

        const seen = {};
        brochureEmailRows().forEach(function (row, index) {
            const email = row.querySelector('.brochure-email-input').value.trim();
            const field = 'emails_' + index;
            if (email === '') {
                errors[field] = ['Email is required.'];
                return;
            }
            if (email.length > 255 || !/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(email)) {
                errors[field] = ['Enter a valid email address.'];
                return;
            }
            const key = email.toLowerCase();
            if (seen[key]) {
                errors[field] = ['This email address is already entered.'];
                return;
            }
            seen[key] = true;
        });

        return Object.keys(errors).length ? errors : null;
    }

    let isSubmitting = false;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (isSubmitting) {
            return;
        }

        const errors = clientErrors();
        showErrors(errors);
        if (errors) {
            return;
        }

        const recordId = parseInt(document.getElementById('outlookEmailRecordId').value, 10) || 0;
        $.ajax({
            url: 'api/outlook_emails_check_unique.php',
            type: 'POST',
            dataType: 'json',
            data: $(form).serialize() + '&record_id=' + encodeURIComponent(String(recordId))
        }).done(function (response) {
            if (response && response.errors && Object.keys(response.errors).length > 0) {
                showErrors(response.errors);
                return;
            }
            isSubmitting = true;
            form.submit();
        }).fail(function () {
            showErrors({
                emails: ['Unable to verify these email addresses.']
            });
        });
    });

    document.getElementById('addBrochureEmailBtn').addEventListener('click', function () {
        const row = addBrochureEmailRow('');
        const input = row ? row.querySelector('.brochure-email-input') : null;
        if (input) {
            input.focus();
        }
    });

    document.getElementById('brochureEmailList').addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.brochure-email-remove');
        if (!removeBtn || removeBtn.disabled) {
            return;
        }
        const row = removeBtn.closest('.brochure-email-row');
        if (row && brochureEmailRows().length > 1) {
            row.remove();
            refreshBrochureEmailRows();
        }
    });

    refreshBrochureEmailRows();
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
            { data: 'category' },
            { data: 'outlook_email' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No brochure email configurations found.',
            zeroRecords: 'No matching brochure email configurations found.'
        }
    });
}

function fillOutlookEmailForm(record) {
    const form = document.getElementById('outlookEmailForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('outlookEmailRecordId').value = record.id || '';
    document.getElementById('outlookEmailFormModeLabel').textContent = record.id
        ? 'Edit Brochure Email'
        : 'Add Brochure Email';
    document.getElementById('submitOutlookEmailBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update'
        : '<i class="bi bi-check-lg"></i> Save';

    form.querySelector('[name="category"]').value = record.category || '';
    resetBrochureEmailRows();
    const emails = Array.isArray(record.emails) && record.emails.length ? record.emails : [''];
    const first = brochureEmailRows()[0];
    const firstInput = first ? first.querySelector('.brochure-email-input') : null;
    if (firstInput) {
        firstInput.value = emails[0] || '';
    }
    emails.slice(1).forEach(function (email) {
        addBrochureEmailRow(email);
    });
    refreshBrochureEmailRows();
}

function resetOutlookEmailForm() {
    const form = document.getElementById('outlookEmailForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('outlookEmailRecordId').value = '';
    document.getElementById('outlookEmailFormModeLabel').textContent = 'Add Brochure Email';
    document.getElementById('submitOutlookEmailBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save';
    resetBrochureEmailRows();
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
