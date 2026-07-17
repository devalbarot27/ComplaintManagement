function validateServiceLogDraftForm(form) {
    const errors = {};

    function addError(field, message) {
        if (!errors[field]) {
            errors[field] = [];
        }
        errors[field].push(message);
    }

    function requireField(name, message) {
        const input = form.querySelector('[name="' + name + '"]');
        if (!input || !String(input.value || '').trim()) {
            addError(name, message);
        }
    }

    requireField('installed_base_id', 'Installed base record is required');
    requireField('fab_number', 'Fab Number is required');

    const recordId = form.querySelector('[name="record_id"]');
    if (recordId && recordId.value) {
        requireField('serial_number', 'Serial Number is required');
    }

    requireField('machine_model', 'Machine Model is required');
    requireField('warranty_chargeable', 'Warranty / Chargeable is required');
    requireField('complaint_date', 'Log Date is required');
    requireField('issue_description', 'Issue / Service Description is required');
    requireField('engineer_name', 'Engineer Name is required');
    requireField('visit_date', 'Visit Date is required');
    requireField('action_taken', 'Action Taken is required');
    requireField('part_replaced', 'Part Replaced is required');

    const engineerName = form.querySelector('[name="engineer_name"]');
    if (engineerName && engineerName.value.length > 150) {
        addError('engineer_name', 'Engineer Name cannot exceed 150 characters');
    }

    const partReplacedSelect = document.getElementById('serviceLogPartReplacedSelect');
    const partReplaced = partReplacedSelect ? String(partReplacedSelect.value || '').trim() : '';
    const isPartReplacedYes = partReplaced.toLowerCase() === 'yes';

    if (isPartReplacedYes) {
        const runningInput = form.querySelector('[name="running_hours"]');
        if (runningInput && runningInput.value.trim() !== '') {
            const runningValue = runningInput.value.trim();
            if (!/^-?\d+(\.\d+)?$/.test(runningValue) || parseFloat(runningValue) <= 0) {
                addError('running_hours', 'Running Hours must be greater than 0');
            }
        }

        const entries = form.querySelectorAll('.sl-part-replacement-entry');

        entries.forEach(function (entry) {
            const index = entry.getAttribute('data-entry-index');
            const quantity = entry.querySelector('[name="part_replacement_entries[' + index + '][quantity]"]');

            if (quantity) {
                const quantityValue = quantity.value.trim();
                if (quantityValue && (!/^\d+$/.test(quantityValue) || parseInt(quantityValue, 10) < 1)) {
                    addError('part_replacement_entries.' + index + '.quantity', 'Quantity must be a positive whole number (minimum 1)');
                }
            }
        });

        const feedbackInput = form.querySelector('[name="customer_feedback"]');
        const customerFeedback = feedbackInput ? feedbackInput.value.trim() : '';
        if (customerFeedback) {
            const rating = parseInt(customerFeedback, 10);
            if (!Number.isInteger(rating) || rating < 1 || rating > 10) {
                addError('customer_feedback', 'Please select a customer feedback rating between 1 and 10');
            }
        }
    }

    const remarks = form.querySelector('[name="remarks"]');
    if (remarks && remarks.value.length > 1000) {
        addError('remarks', 'Remarks cannot exceed 1000 characters');
    }

    const complaintDate = form.querySelector('[name="complaint_date"]');
    const visitDate = form.querySelector('[name="visit_date"]');
    const closureDate = form.querySelector('[name="closure_date"]');

    if (visitDate && complaintDate && visitDate.value && complaintDate.value && visitDate.value < complaintDate.value) {
        addError('visit_date', 'Visit Date cannot be earlier than Log Date');
    }

    if (closureDate && visitDate && closureDate.value && visitDate.value && closureDate.value < visitDate.value) {
        addError('closure_date', 'Closure Date cannot be earlier than Visit Date');
    }

    form.querySelectorAll('input[name$="_remaining_hours"]').forEach(function (input) {
        const value = input.value.trim();
        if (value === '') {
            return;
        }

        if (!/^-?\d+(\.\d+)?$/.test(value) || parseFloat(value) < 0) {
            addError(input.getAttribute('name'), 'Remaining Hours must be a valid non-negative number');
        }
    });

    return Object.keys(errors).length ? errors : null;
}

function showServiceLogDraftErrors(form, errors) {
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
    form.querySelectorAll('.select2-selection.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });

    if (!errors) {
        return;
    }

    Object.keys(errors).forEach(function (field) {
        if (field.indexOf('part_replacement_entries') === 0
            || field === 'remarks'
            || field === 'customer_feedback'
            || field === 'running_hours') {
            return;
        }

        const input = form.querySelector('[name="' + field + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');

        if (input) {
            input.classList.add('is-invalid');
        }

        if (field === 'warranty_chargeable') {
            $('#serviceLogWarrantySelect').next('.select2-container').find('.select2-selection').addClass('is-invalid');
        }

        if (field === 'part_replaced') {
            $('#serviceLogPartReplacedSelect').next('.select2-container').find('.select2-selection').addClass('is-invalid');
        }

        if (field === 'installed_base_id') {
            $('#installedBaseLinkSelect').next('.select2-container').find('.select2-selection').addClass('is-invalid');
        }

        if (msg && errors[field] && errors[field].length) {
            msg.textContent = errors[field][0];
        }
    });

    if (window.slPartReplacementModule) {
        window.slPartReplacementModule.showErrors(form, errors);
    }
}

function initServiceLogDraftSave() {
    const form = document.getElementById('serviceLogForm');
    const draftBtn = document.getElementById('saveServiceLogDraftBtn');
    const draftFlag = document.getElementById('submitServiceLogDraftFlag');

    if (!form || !draftBtn || !draftFlag) {
        return;
    }

    let isSubmittingDraft = false;

    draftBtn.addEventListener('click', function () {
        if (isSubmittingDraft) {
            return;
        }

        const errors = validateServiceLogDraftForm(form);
        showServiceLogDraftErrors(form, errors);

        if (errors) {
            return;
        }

        isSubmittingDraft = true;
        draftBtn.classList.add('disabled_btn');
        draftFlag.disabled = false;
        draftFlag.value = '1';
        form.submit();
    });
}

function updateServiceLogDraftButtonState(record) {
    const draftBtn = document.getElementById('saveServiceLogDraftBtn');
    if (!draftBtn) {
        return;
    }

    const isFinalRecord = record && record.id && Number(record.is_draft) !== 1;
    draftBtn.style.display = isFinalRecord ? 'none' : '';
}