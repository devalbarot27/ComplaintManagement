function validateComplaintServiceLogDraftForm(form) {
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

    const installedBaseId = document.getElementById('ibServiceLogInstalledBaseId');
    if (!installedBaseId || !String(installedBaseId.value || '').trim()) {
        addError('installed_base_id', 'Installed base record is required');
    }

    requireField('fab_number', 'Fab Number is required');
    requireField('serial_number', 'Serial Number is required');
    requireField('machine_model', 'Machine Model is required');
    requireField('warranty_chargeable', 'Warranty / Chargeable is required');
    requireField('complaint_date', 'Log Date is required');
    requireField('issue_description', 'Issue / Service Description is required');
    requireField('engineer_name', 'Engineer Name is required');
    requireField('visit_date', 'Visit Date is required');
    requireField('action_taken', 'Action Taken is required');

    const partReplacedSelect = document.getElementById('ibServiceLogPartReplacedSelect');
    const partReplaced = partReplacedSelect ? String(partReplacedSelect.value || '').trim() : '';
    if (!partReplaced) {
        addError('part_replaced', 'Part Replaced is required');
    }

    return Object.keys(errors).length ? errors : null;
}

function showComplaintServiceLogDraftErrors(form, errors) {
    if (typeof ibServiceLogClearErrors === 'function') {
        ibServiceLogClearErrors(form);
    }

    if (!errors) {
        return;
    }

    Object.keys(errors).forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');

        if (input) {
            input.classList.add('is-invalid');
        }

        if (field === 'warranty_chargeable') {
            $('#ibServiceLogWarrantySelect').next('.select2-container').find('.select2-selection').addClass('is-invalid');
        }

        if (field === 'part_replaced') {
            $('#ibServiceLogPartReplacedSelect').next('.select2-container').find('.select2-selection').addClass('is-invalid');
        }

        if (msg && errors[field] && errors[field].length) {
            msg.textContent = errors[field][0];
        }
    });
}

function updateComplaintServiceLogDraftButtonState() {
    const draftBtn = document.getElementById('saveComplaintServiceLogDraftBtn');
    if (!draftBtn) {
        return;
    }

    const recordId = parseInt((document.getElementById('ibServiceLogRecordId') || {}).value || '0', 10);
    const modalEl = document.getElementById('installedBaseServiceLogModal');
    const editingDraft = modalEl ? modalEl.getAttribute('data-editing-draft') : null;

    if (recordId > 0 && editingDraft === '0') {
        draftBtn.style.display = 'none';
    } else {
        draftBtn.style.display = '';
        draftBtn.classList.remove('disabled_btn');
    }
}

function initComplaintServiceLogDraftSave() {
    const form = document.getElementById('installedBaseServiceLogForm');
    const draftBtn = document.getElementById('saveComplaintServiceLogDraftBtn');
    const modalEl = document.getElementById('installedBaseServiceLogModal');

    if (!form || !draftBtn || !modalEl || modalEl.getAttribute('data-context') !== 'complaint') {
        return;
    }

    let isSubmittingDraft = false;

    if (modalEl.dataset.complaintDraftInit === '1') {
        return;
    }
    modalEl.dataset.complaintDraftInit = '1';

    modalEl.addEventListener('shown.bs.modal', function () {
        updateComplaintServiceLogDraftButtonState();
    });

    draftBtn.addEventListener('click', function () {
        if (isSubmittingDraft) {
            return;
        }

        if (typeof complaintServiceLogSyncCustomerFeedbackInput === 'function') {
            complaintServiceLogSyncCustomerFeedbackInput();
        }

        const errors = validateComplaintServiceLogDraftForm(form);
        showComplaintServiceLogDraftErrors(form, errors);

        if (errors) {
            return;
        }

        const complaintId = parseInt((document.getElementById('ibServiceLogComplaintId') || {}).value || '0', 10);
        if (!complaintId) {
            showComplaintServiceLogDraftErrors(form, { complaint_id: ['Complaint reference is required'] });
            return;
        }

        const installedBaseId = document.getElementById('ibServiceLogInstalledBaseId').value;
        if (!installedBaseId || parseInt(installedBaseId, 10) <= 0) {
            showComplaintServiceLogDraftErrors(form, { installed_base_id: ['Installed base record is required'] });
            return;
        }

        isSubmittingDraft = true;
        draftBtn.classList.add('disabled_btn');

        $.ajax({
            url: 'api/complaint_service_log_draft_save.php',
            type: 'POST',
            data: $(form).serialize(),
            dataType: 'json'
        })
            .done(function (response) {
                const recordId = parseInt((response && response.service_log_id) || '0', 10);
                if (recordId > 0) {
                    document.getElementById('ibServiceLogRecordId').value = String(recordId);
                    modalEl.setAttribute('data-editing-draft', '1');
                }

                if (typeof window.complaintServiceLogAfterSave === 'function') {
                    window.complaintServiceLogAfterSave(response || {});
                } else if (response && response.message && typeof showComplaintServiceLogAlert === 'function') {
                    showComplaintServiceLogAlert('success', response.message);
                }
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Failed to save service log draft.';

                if (/customer feedback/i.test(message) && window.ibServiceLogFeedbackRating) {
                    window.ibServiceLogFeedbackRating.setError(message);
                }

                if (typeof showComplaintServiceLogAlert === 'function') {
                    showComplaintServiceLogAlert('error', message);
                }
            })
            .always(function () {
                isSubmittingDraft = false;
                draftBtn.classList.remove('disabled_btn');
            });
    });
}

window.updateComplaintServiceLogDraftButtonState = updateComplaintServiceLogDraftButtonState;