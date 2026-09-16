function initClosureValidation() {
    const form = document.getElementById('closureForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    const remarksWrap = document.getElementById('closureRemarksWrap');
    const reassignmentWrap = document.getElementById('reassignmentDetailsWrap');

    function getCallClosure() {
        const checked = form.querySelector('input[name="call_closure"]:checked');
        return checked ? checked.value : '';
    }

    function toggleClosureFields() {
        const value = getCallClosure();
        const isYes = value === 'Yes';
        const isNo = value === 'No';

        if (remarksWrap) {
            remarksWrap.classList.toggle('d-none', !isYes);
        }

        if (reassignmentWrap) {
            reassignmentWrap.classList.toggle('d-none', !isNo);
        }

        const remarksField = form.querySelector('[name="closure_remarks"]');
        const assignToField = form.querySelector('[name="reassign_complaint"]');
        const reassignRemarksField = form.querySelector('[name="reassign_remarks"]');

        if (remarksField) {
            remarksField.value = isYes ? remarksField.value : '';
        }

        if (!isYes && typeof resetClosureCustomerFeedbackRating === 'function') {
            resetClosureCustomerFeedbackRating();
        }

        if (assignToField && !isNo) {
            assignToField.value = '';
        }

        if (reassignRemarksField && !isNo) {
            reassignRemarksField.value = '';
        }
    }

    function clearValidationState() {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.validation-msg').forEach(function (el) {
            el.textContent = '';
        });
    }

    function setFieldError(fieldName, message) {
        const input = form.querySelector('[name="' + fieldName + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + fieldName + '"]');

        if (input) {
            input.classList.add('is-invalid');
        }

        if (fieldName === 'customer_feedback' && typeof setClosureCustomerFeedbackRatingError === 'function') {
            setClosureCustomerFeedbackRatingError(message);
            return;
        }

        if (msg) {
            msg.textContent = message;
        }
    }

    function validateClosureForm() {
        const errors = {};
        const callClosure = getCallClosure();

        if (!callClosure) {
            errors.call_closure = ['Please select Call Closure Yes or No'];
        }

        if (callClosure === 'Yes') {
            const remarks = form.querySelector('[name="closure_remarks"]').value.trim();
            if (!remarks) {
                errors.closure_remarks = ['Closure remarks are required'];
            }

            const customerFeedback = typeof getClosureCustomerFeedbackRating === 'function'
                ? getClosureCustomerFeedbackRating()
                : form.querySelector('[name="customer_feedback"]').value.trim();
            const rating = parseInt(customerFeedback, 10);
            if (!customerFeedback || !Number.isInteger(rating) || rating < 1 || rating > 10) {
                errors.customer_feedback = ['Please select a customer feedback rating between 1 and 10'];
            }
        }

        if (callClosure === 'No') {
            const assignTo = form.querySelector('[name="reassign_complaint"]').value.trim();
            if (!assignTo) {
                errors.reassign_complaint = ['Reassign to is required'];
            }

            const reassignRemarks = form.querySelector('[name="reassign_remarks"]').value.trim();
            if (reassignRemarks && reassignRemarks.length > 500) {
                errors.reassign_remarks = ['Remarks cannot exceed 500 characters'];
            }
        }

        return Object.keys(errors).length ? errors : null;
    }

    function showErrors(errors) {
        clearValidationState();

        if (!errors) {
            return;
        }

        Object.keys(errors).forEach(function (field) {
            setFieldError(field, errors[field][0]);
        });
    }

    form.querySelectorAll('input[name="call_closure"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            toggleClosureFields();
            clearValidationState();
        });
    });

    form.addEventListener('submit', function (e) {
        const errors = validateClosureForm();
        showErrors(errors);

        if (errors) {
            e.preventDefault();
        }
    });

    form.addEventListener('reset', function () {
        clearValidationState();
        toggleClosureFields();
    });

    toggleClosureFields();
}

function resetClosureForm(complaintId) {
    const form = document.getElementById('closureForm');

    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('closureComplaintId').value = complaintId;

    if (typeof resetClosureCustomerFeedbackRating === 'function') {
        resetClosureCustomerFeedbackRating();
    }

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });

    const event = new Event('reset');
    form.dispatchEvent(event);
}