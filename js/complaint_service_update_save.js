function complaintServiceUpdateMissingServiceLogMessage() {
    return 'Please add a Service Log to proceed further.';
}

function complaintServiceUpdateDraftBlockedMessage() {
    return 'Service Log is in Draft. It will not be sent for HO Approval. Please submit the Service Log before updating the complaint.';
}

function complaintServiceUpdateValidateServiceLog() {
    const section = document.getElementById('serviceUpdateServiceLogSection');
    if (!section) {
        return null;
    }

    const state = window.serviceUpdateServiceLogState || {};

    if (!state.loaded) {
        return 'Service log details are still loading. Please wait.';
    }

    if (!state.hasInstalledBase) {
        return 'A matching installed base is required before a service log can be added.';
    }

    if (!state.hasServiceLog) {
        return complaintServiceUpdateMissingServiceLogMessage();
    }

    if (state.isDraft) {
        return complaintServiceUpdateDraftBlockedMessage();
    }

    return null;
}

function complaintServiceUpdateValidateFormFields(form) {
    const errors = {};
    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    const maxFileSize = 2 * 1024 * 1024;
    const visitDate = (form.querySelector('[name="customer_visit_date"]') || {}).value || '';
    const maxDate = typeof getCurrentDateLocal === 'function' ? getCurrentDateLocal() : '';

    if (!visitDate) {
        errors.customer_visit_date = ['Customer visit date is required'];
    } else if (maxDate && visitDate > maxDate) {
        errors.customer_visit_date = ['Customer visit date cannot be in the future'];
    }

    const actionTaken = (form.querySelector('[name="complaint_action_taken"]') || {}).value || '';
    if (!actionTaken.trim()) {
        errors.complaint_action_taken = ['Complaint action taken is required'];
    }

    const fileInput = form.querySelector('input[name="service_report[]"]');
    if (!fileInput || !fileInput.files.length) {
        errors.service_report = ['At least one service report file is required'];
    } else {
        for (let i = 0; i < fileInput.files.length; i++) {
            const file = fileInput.files[i];
            const extension = file.name.split('.').pop().toLowerCase();

            if (!allowedExtensions.includes(extension)) {
                errors.service_report = ['Invalid file type for "' + file.name + '". Allowed: PDF, JPG, PNG, DOC, DOCX'];
                break;
            }

            if (file.size > maxFileSize) {
                errors.service_report = ['File "' + file.name + '" must be 2 MB or smaller'];
                break;
            }
        }
    }

    return errors;
}

function complaintServiceUpdateValidateAll(form) {
    const errors = complaintServiceUpdateValidateFormFields(form);
    const serviceLogError = complaintServiceUpdateValidateServiceLog();

    if (serviceLogError) {
        errors.service_log = [serviceLogError];
    }

    return Object.keys(errors).length ? errors : null;
}

function complaintServiceUpdateClearValidationState(form) {
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });

    if (typeof clearServiceUpdateServiceLogValidation === 'function') {
        clearServiceUpdateServiceLogValidation();
    }
}

function complaintServiceUpdateShowAllErrors(form, errors) {
    complaintServiceUpdateClearValidationState(form);

    if (!errors) {
        return;
    }

    Object.keys(errors).forEach(function (field) {
        if (!errors[field] || !errors[field].length) {
            return;
        }

        const message = errors[field][0];

        if (field === 'service_log') {
            if (typeof showServiceUpdateServiceLogValidation === 'function') {
                showServiceUpdateServiceLogValidation(message);
            }
            return;
        }

        const input = field === 'service_report'
            ? form.querySelector('input[name="service_report[]"]')
            : form.querySelector('[name="' + field + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');

        if (input) {
            input.classList.add('is-invalid');
        }

        if (msg) {
            msg.textContent = message;
        }
    });
}

function initComplaintServiceUpdateSave() {
    const form = document.getElementById('serviceUpdateForm');
    const saveBtn = document.getElementById('saveServiceUpdateBtn');

    if (!form || !saveBtn || form.dataset.complaintServiceUpdateSaveInit === '1') {
        return;
    }

    form.dataset.complaintServiceUpdateSaveInit = '1';

    saveBtn.addEventListener('click', function () {
        if (typeof serviceUpdateSubmitting !== 'undefined' && serviceUpdateSubmitting) {
            return;
        }

        const errors = complaintServiceUpdateValidateAll(form);
        complaintServiceUpdateShowAllErrors(form, errors);

        if (errors) {
            saveBtn.classList.remove('disabled_btn');
            return;
        }

        serviceUpdateSubmitting = true;
        saveBtn.classList.add('disabled_btn');
        form.submit();
    });

    form.addEventListener('reset', function () {
        serviceUpdateSubmitting = false;
        saveBtn.classList.remove('disabled_btn');
        complaintServiceUpdateClearValidationState(form);
    });
}