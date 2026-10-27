function helpSupportConstraints() {
    return {
        category: {
            presence: { allowEmpty: false, message: '^Category is required' }
        },
        title: {
            presence: { allowEmpty: false, message: '^Title is required' },
            length: { maximum: 255, message: '^Title cannot exceed 255 characters' }
        },
        description: {
            presence: { allowEmpty: false, message: '^Description is required' },
            length: { maximum: 4000, message: '^Description cannot exceed 4000 characters' }
        },
        qty: {
            presence: { allowEmpty: false, message: '^Qty is required' },
            numericality: {
                onlyInteger: true,
                greaterThanOrEqualTo: 0,
                lessThanOrEqualTo: 999999,
                message: '^Qty must be a whole number from 0 to 999999'
            }
        }
    };
}

function initHelpSupportFormValidation() {
    const form = document.getElementById('helpSupportForm');
    if (!form || typeof validate === 'undefined') {
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
        const errors = validate(form, helpSupportConstraints()) || {};
        const resourceType = helpSupportResourceType();
        const url = form.querySelector('[name="resource_url"]').value.trim();
        const fileInput = form.querySelector('[name="attachment"]');
        const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
        const hasExisting = document.getElementById('helpSupportHasAttachment').value === '1';

        if (resourceType === 'url') {
            if (url === '') {
                errors.resource_url = ['URL is required'];
            } else if (!/^https?:\/\/.+/i.test(url)) {
                errors.resource_url = ['Enter a valid URL starting with http:// or https://'];
            }
        } else if (!hasFile && !hasExisting) {
            errors.attachment = ['Attachment is required.'];
        }
        if (resourceType === 'attachment' && hasFile && fileInput.files[0].size > 10 * 1024 * 1024) {
            errors.attachment = ['Attachment must be 10 MB or smaller.'];
        }

        if (Object.keys(errors).length) {
            e.preventDefault();
            showErrors(errors);
        }
    });
}

function helpSupportDatatableColumns() {
    const columns = [
        { data: 'id' },
        { data: 'category' },
        { data: 'title' },
        { data: 'qty' }
    ];
    if (window.helpSupportShowAddedBy) {
        columns.push({ data: 'added_by' });
    }
    columns.push(
        { data: 'created_at' },
        { data: 'resource', orderable: false, searchable: false },
        { data: 'actions', orderable: false, searchable: false }
    );
    return columns;
}

function initHelpSupportDatatable() {
    const $table = $('#helpSupportTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/help_support_datatable.php',
            type: 'POST'
        },
        order: [[2, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: helpSupportDatatableColumns(),
        language: {
            emptyTable: 'No Help & Support items found.',
            zeroRecords: 'No matching Help & Support items found.'
        }
    });
}

function helpSupportResourceType() {
    const selected = document.querySelector('input[name="resource_type"]:checked');
    return selected ? selected.value : 'attachment';
}

function toggleHelpSupportResourceFields() {
    const attachmentGroup = document.getElementById('helpSupportAttachmentGroup');
    const urlGroup = document.getElementById('helpSupportUrlGroup');
    const fileInput = document.getElementById('helpSupportAttachment');
    const urlInput = document.getElementById('helpSupportUrl');
    if (!attachmentGroup || !urlGroup || !fileInput || !urlInput) {
        return;
    }

    const type = helpSupportResourceType();

    attachmentGroup.style.display = type === 'attachment' ? '' : 'none';
    urlGroup.style.display = type === 'url' ? '' : 'none';
    fileInput.disabled = type !== 'attachment';
    urlInput.disabled = type !== 'url';
}

function showHelpSupportCurrentFile(record) {
    const wrap = document.getElementById('helpSupportCurrentFile');
    const hasAttachment = document.getElementById('helpSupportHasAttachment');
    const name = record && record.attachment_name ? record.attachment_name : '';
    const path = record && record.attachment_path ? record.attachment_path : '';

    if (name && path) {
        hasAttachment.value = '1';
        wrap.style.display = '';
        wrap.textContent = 'Current file: ';
        const link = document.createElement('a');
        link.href = path;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = name;
        wrap.appendChild(link);
        return;
    }

    hasAttachment.value = '0';
    wrap.style.display = 'none';
    wrap.textContent = '';
}

function setHelpSupportResourceType(type) {
    const radio = document.querySelector('input[name="resource_type"][value="' + type + '"]');
    if (radio) {
        radio.checked = true;
    }
    toggleHelpSupportResourceFields();
}

function fillHelpSupportForm(record) {
    const form = document.getElementById('helpSupportForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('helpSupportRecordId').value = record.id || '';
    document.getElementById('helpSupportFormModeLabel').textContent = record.id
        ? 'Edit Help & Support'
        : 'Add Help & Support';
    document.getElementById('submitHelpSupportBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Help & Support'
        : '<i class="bi bi-check-lg"></i> Save Help & Support';

    form.querySelector('[name="category"]').value = record.category || '';
    form.querySelector('[name="title"]').value = record.title || '';
    form.querySelector('[name="description"]').value = record.description || '';
    form.querySelector('[name="qty"]').value = record.qty !== undefined && record.qty !== null ? record.qty : '';
    form.querySelector('[name="resource_url"]').value = record.resource_url || '';
    form.querySelector('[name="attachment"]').value = '';
    const hasFile = record.attachment_name && record.attachment_path;
    setHelpSupportResourceType(hasFile ? 'attachment' : (record.resource_url ? 'url' : 'attachment'));
    showHelpSupportCurrentFile(record);
}

function resetHelpSupportForm() {
    const form = document.getElementById('helpSupportForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('helpSupportRecordId').value = '';
    document.getElementById('helpSupportFormModeLabel').textContent = 'Add Help & Support';
    document.getElementById('submitHelpSupportBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Help & Support';
    setHelpSupportResourceType('attachment');
    showHelpSupportCurrentFile(null);
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openHelpSupportFormPanel() {
    document.getElementById('helpSupportFormCard').classList.add('show');
    document.getElementById('openHelpSupportForm').style.display = 'none';
    document.getElementById('closeHelpSupportForm').classList.add('show');
}

function closeHelpSupportFormPanel() {
    document.getElementById('helpSupportFormCard').classList.remove('show');
    document.getElementById('openHelpSupportForm').style.display = 'flex';
    document.getElementById('closeHelpSupportForm').classList.remove('show');
    resetHelpSupportForm();
}

function bootHelpSupportPage() {
    initHelpSupportFormValidation();
    initHelpSupportDatatable();
    toggleHelpSupportResourceFields();

    document.querySelectorAll('input[name="resource_type"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            toggleHelpSupportResourceFields();
            ['attachment', 'resource_url', 'resource_type'].forEach(function (field) {
                const msg = document.querySelector('.validation-msg[data-field="' + field + '"]');
                const input = document.querySelector('[name="' + field + '"]');
                if (msg) {
                    msg.textContent = '';
                }
                if (input) {
                    input.classList.remove('is-invalid');
                }
            });
        });
    });

    document.getElementById('cancelHelpSupportForm')?.addEventListener('click', closeHelpSupportFormPanel);
    document.getElementById('closeHelpSupportForm')?.addEventListener('click', closeHelpSupportFormPanel);
    document.getElementById('openHelpSupportForm')?.addEventListener('click', function () {
        resetHelpSupportForm();
        openHelpSupportFormPanel();
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-help-support-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/help_support_get.php', { id: id })
            .done(function (record) {
                resetHelpSupportForm();
                fillHelpSupportForm(record);
                openHelpSupportFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load record details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootHelpSupportPage);
} else {
    bootHelpSupportPage();
}
