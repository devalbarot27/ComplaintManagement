let serviceUpdateSubmitting = false;

function initServiceUpdateValidation() {
    const form = document.getElementById('serviceUpdateForm');

    if (!form) {
        return;
    }

    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    const maxFileSize = 2 * 1024 * 1024;

    function clearValidationState() {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.validation-msg').forEach(function (el) {
            el.textContent = '';
        });
    }

    function setFieldError(fieldName, message) {
        const input = fieldName === 'service_report'
            ? form.querySelector('input[name="service_report[]"]')
            : form.querySelector('[name="' + fieldName + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + fieldName + '"]');

        if (input) {
            input.classList.add('is-invalid');
        }

        if (msg) {
            msg.textContent = message;
        }
    }

    function validateVisitDate(value) {
        const maxDate = getCurrentDateLocal();

        if (!value) {
            return 'Customer visit date is required';
        }

        if (value > maxDate) {
            return 'Customer visit date cannot be in the future';
        }

        return null;
    }

    function validateActionTaken(value) {
        if (!value || !value.trim()) {
            return 'Complaint action taken is required';
        }

        return null;
    }

    function validateServiceReport() {
        const fileInput = form.querySelector('input[name="service_report[]"]');

        if (!fileInput || !fileInput.files.length) {
            return 'At least one service report file is required';
        }

        for (let i = 0; i < fileInput.files.length; i++) {
            const file = fileInput.files[i];
            const extension = file.name.split('.').pop().toLowerCase();

            if (!allowedExtensions.includes(extension)) {
                return 'Invalid file type for "' + file.name + '". Allowed: PDF, JPG, PNG, DOC, DOCX';
            }

            if (file.size > maxFileSize) {
                return 'File "' + file.name + '" must be 2 MB or smaller';
            }
        }

        return null;
    }

    function validateServiceUpdateForm() {
        const errors = {};
        const visitDateError = validateVisitDate(
            (form.querySelector('[name="customer_visit_date"]') || {}).value || ''
        );
        const actionTakenError = validateActionTaken(
            (form.querySelector('[name="complaint_action_taken"]') || {}).value || ''
        );
        const fileError = validateServiceReport();

        if (visitDateError) {
            errors.customer_visit_date = [visitDateError];
        }

        if (actionTakenError) {
            errors.complaint_action_taken = [actionTakenError];
        }

        if (fileError) {
            errors.service_report = [fileError];
        }

        return Object.keys(errors).length ? errors : null;
    }

    function showErrors(errors) {
        clearValidationState();

        if (!errors) {
            return;
        }

        Object.keys(errors).forEach(function (field) {
            if (errors[field] && errors[field].length) {
                setFieldError(field, errors[field][0]);
            }
        });
    }

    function showFieldValidation(fieldName, message) {
        const input = fieldName === 'service_report'
            ? form.querySelector('input[name="service_report[]"]')
            : form.querySelector('[name="' + fieldName + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + fieldName + '"]');

        if (input) {
            input.classList.toggle('is-invalid', !!message);
        }

        if (msg) {
            msg.textContent = message || '';
        }
    }

    const visitDateInput = form.querySelector('[name="customer_visit_date"]');
    if (visitDateInput) {
        setCurrentDateInput(visitDateInput);

        visitDateInput.addEventListener('change', function () {
            const maxDate = getCurrentDateLocal();
            this.max = maxDate;
            if (this.value && this.value > maxDate) {
                this.value = maxDate;
            }
            showFieldValidation('customer_visit_date', validateVisitDate(this.value));
        });
    }

    const actionTakenInput = form.querySelector('[name="complaint_action_taken"]');
    if (actionTakenInput) {
        actionTakenInput.addEventListener('input', function () {
            showFieldValidation('complaint_action_taken', validateActionTaken(this.value));
        });
    }

    const serviceReportInput = form.querySelector('input[name="service_report[]"]');
    if (serviceReportInput) {
        serviceReportInput.addEventListener('change', function () {
            showFieldValidation('service_report', validateServiceReport());
        });
    }

    form.addEventListener('submit', function (e) {
        if (serviceUpdateSubmitting) {
            e.preventDefault();
            return;
        }

        const errors = validateServiceUpdateForm();
        showErrors(errors);

        if (errors) {
            e.preventDefault();
            return;
        }

        serviceUpdateSubmitting = true;

        const submitButton = form.querySelector('[name="save_update"]');
        if (submitButton) {
            submitButton.classList.add('disabled_btn');
        }
    });

    form.addEventListener('reset', function () {
        clearValidationState();
        serviceUpdateSubmitting = false;
    });
}

function resetServiceUpdateForm(complaintId) {
    const form = document.getElementById('serviceUpdateForm');

    if (!form) {
        return;
    }

    serviceUpdateSubmitting = false;

    form.reset();
    document.getElementById('serviceComplaintId').value = complaintId;
    setCurrentDateInput(form.querySelector('[name="customer_visit_date"]'));

    const submitButton = form.querySelector('[name="save_update"]');
    if (submitButton) {
        submitButton.classList.remove('disabled_btn');
    }

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}