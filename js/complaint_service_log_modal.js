let ibServiceLogPartReplacementIndex = 0;
let ibServiceLogFeedbackRating = null;

function initInstalledBaseServiceLogFeedbackRating() {
    if (typeof createCustomerFeedbackRating !== 'function') {
        return;
    }
    if (!ibServiceLogFeedbackRating) {
        ibServiceLogFeedbackRating = createCustomerFeedbackRating({
            wrapId: 'ibServiceLogCustomerFeedbackRating',
            inputId: 'ibServiceLogCustomerFeedbackInput',
            selectedId: 'ibServiceLogCustomerFeedbackSelected',
            formSelector: '#installedBaseServiceLogForm'
        });
        window.ibServiceLogFeedbackRating = ibServiceLogFeedbackRating;
    }
    ibServiceLogFeedbackRating.init();
}

function complaintServiceLogInitSelect2() {
    if (typeof $.fn.select2 === 'undefined') {
        return;
    }

    const $modal = $('#installedBaseServiceLogModal');
    const dropdownParent = $modal.length ? $modal : null;
    const select2Options = dropdownParent ? { dropdownParent: dropdownParent } : {};

    $('#ibServiceLogWarrantySelect').off('select2:select select2:clear');
    $('#ibServiceLogPartReplacedSelect').off('select2:select select2:clear change.complaintServiceLog');

    initStaticSelect2Fields('installedBaseServiceLogForm', [
        Object.assign({
            selectId: 'ibServiceLogWarrantySelect',
            validationField: 'warranty_chargeable',
            allowClear: false,
            noResultsText: 'No service type found'
        }, select2Options),
        Object.assign({
            selectId: 'ibServiceLogPartReplacedSelect',
            validationField: 'part_replaced',
            allowClear: false,
            noResultsText: 'No option found'
        }, select2Options)
    ]);

    $('#ibServiceLogPartReplacedSelect').on('change.complaintServiceLog', function () {
        ibServiceLogTogglePartReplacementSection($(this).val());
    });
    ibServiceLogTogglePartReplacementSection($('#ibServiceLogPartReplacedSelect').val());

    const form = document.getElementById('installedBaseServiceLogForm');
    if (form) {
        ['ibServiceLogWarrantySelect', 'ibServiceLogPartReplacedSelect'].forEach(function (selectId) {
            const $select = $('#' + selectId);
            const fieldName = $select.attr('name');
            $select.off('select2:select.complaintClear select2:clear.complaintClear')
                .on('select2:select.complaintClear select2:clear.complaintClear', function () {
                    if (String($select.val() || '').trim()) {
                        complaintServiceLogClearFieldError(form, fieldName);
                    }
                });
        });
    }
}

function complaintServiceLogReinitControls() {
    complaintServiceLogInitSelect2();
    initInstalledBaseServiceLogFeedbackRating();
}

window.complaintServiceLogReinitControls = complaintServiceLogReinitControls;

function ibServiceLogIsPartReplacedYes(value) {
    return String(value || '').trim().toLowerCase() === 'yes';
}

function ibServiceLogSetModalMode(mode) {
    const titleEl = document.getElementById('ibServiceLogModalTitle');
    const submitLabel = document.getElementById('submitIbServiceLogBtnLabel');
    const isEdit = mode === 'edit';
    if (titleEl) {
        titleEl.textContent = isEdit ? 'Edit Service Log' : 'Add Service Log';
    }
    if (submitLabel) {
        submitLabel.textContent = isEdit ? 'Update Service Log' : 'Submit Service Log';
    }
}

function ibServiceLogClearErrors(form) {
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
    form.querySelectorAll('.select2-selection.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    if (window.ibServiceLogFeedbackRating) {
        window.ibServiceLogFeedbackRating.setError('');
    }
}

function complaintServiceLogClearFieldError(form, fieldKey) {
    if (!form || !fieldKey) {
        return;
    }

    let input = null;
    if (fieldKey.indexOf('part_replacement_entries.') === 0) {
        const match = fieldKey.match(/^part_replacement_entries\.(\d+)\.(\w+)$/);
        if (match) {
            input = form.querySelector('[name="part_replacement_entries[' + match[1] + '][' + match[2] + ']"]');
        }
    } else {
        input = form.querySelector('[name="' + fieldKey + '"]');
    }

    const msg = form.querySelector('.validation-msg[data-field="' + fieldKey + '"]');
    if (input) {
        input.classList.remove('is-invalid');
        const $input = $(input);
        if ($input.hasClass('select2-hidden-accessible')) {
            $input.next('.select2-container').find('.select2-selection').removeClass('is-invalid');
        }
    }

    if (fieldKey === 'customer_feedback' && window.ibServiceLogFeedbackRating) {
        window.ibServiceLogFeedbackRating.setError('');
    }

    if (msg) {
        msg.textContent = '';
    }
}

function complaintServiceLogMaybeClearPartReplacementEntriesError(form) {
    if (!form) {
        return;
    }

    const entries = form.querySelectorAll('.ib-part-replacement-entry');
    if (entries.length > 0) {
        const msg = form.querySelector('.validation-msg[data-field="part_replacement_entries"]');
        if (msg) {
            msg.textContent = '';
        }
    }
}

function complaintServiceLogInitLiveValidation(form) {
    if (!form || form.dataset.complaintLiveValidation === '1') {
        return;
    }

    form.dataset.complaintLiveValidation = '1';

    form.addEventListener('input', function (event) {
        const target = event.target;
        if (!target || !target.getAttribute('name')) {
            return;
        }

        const name = target.getAttribute('name');
        const value = String(target.value || '').trim();

        if (name === 'complaint_date' || name === 'visit_date' || name === 'closure_date') {
            if (value) {
                complaintServiceLogClearFieldError(form, name);
                complaintServiceLogApplyDateOrderValidation(form);
            }
            return;
        }

        if (target.tagName === 'TEXTAREA' || target.type === 'text' || target.type === 'date') {
            if (value) {
                complaintServiceLogClearFieldError(form, name);
            }
            return;
        }

        if (target.type === 'number' && name === 'running_hours') {
            if (value && /^-?\d+(\.\d+)?$/.test(value) && parseFloat(value) > 0) {
                complaintServiceLogClearFieldError(form, 'running_hours');
            }
            return;
        }

        if (target.type === 'number' && name.indexOf('_remaining_hours') !== -1) {
            if (value && /^-?\d+(\.\d+)?$/.test(value) && parseFloat(value) >= 0) {
                complaintServiceLogClearFieldError(form, name);
            }
            return;
        }

        const quantityMatch = name.match(/^part_replacement_entries\[(\d+)\]\[quantity\]$/);
        if (target.type === 'number' && quantityMatch) {
            if (value && /^\d+$/.test(value) && parseInt(value, 10) >= 1) {
                complaintServiceLogClearFieldError(form, 'part_replacement_entries.' + quantityMatch[1] + '.quantity');
                complaintServiceLogMaybeClearPartReplacementEntriesError(form);
            }
        }
    });

    form.addEventListener('change', function (event) {
        const target = event.target;
        if (!target || !target.getAttribute('name')) {
            return;
        }

        const name = target.getAttribute('name');
        if (name === 'complaint_date' || name === 'visit_date' || name === 'closure_date') {
            if (String(target.value || '').trim()) {
                complaintServiceLogClearFieldError(form, name);
            }
            complaintServiceLogApplyDateOrderValidation(form);
            return;
        }

        if (String(target.value || '').trim()) {
            complaintServiceLogClearFieldError(form, name);
        }
    });
}

function resetInstalledBaseServiceLogForm() {
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('ibServiceLogRecordId').value = '';
    document.getElementById('ibServiceLogComplaintId').value = '';
    document.getElementById('ibServiceLogInstalledBaseId').value = '';
    document.getElementById('ibServiceLogInstalledBaseLabel').value = '';
    resetStaticSelect2Fields(['ibServiceLogWarrantySelect', 'ibServiceLogPartReplacedSelect']);
    ibServiceLogSetModalMode('add');
    ibServiceLogClearPartReplacementEntries();
    document.getElementById('ibServiceLogPartReplacementWrapper').classList.add('d-none');
    document.getElementById('ibServiceLogPartReplacedCommonHoursWrapper').classList.add('d-none');
    if (window.ibServiceLogFeedbackRating) {
        window.ibServiceLogFeedbackRating.reset();
    }
    ibServiceLogClearErrors(form);
}

function fillInstalledBaseServiceLogForm(data) {
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!form || !data) {
        return;
    }
    document.getElementById('ibServiceLogRecordId').value = data.record_id || data.id || '';
    document.getElementById('ibServiceLogComplaintId').value = data.complaint_id || '';
    document.getElementById('ibServiceLogInstalledBaseId').value = data.installed_base_id || '';
    document.getElementById('ibServiceLogInstalledBaseLabel').value = data.installed_base_label || '';
    ['fab_number', 'machine_model', 'serial_number'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = data[field] || '';
        }
    });
}

function fillInstalledBaseServiceLogFormForEdit(record) {
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!form || !record) {
        return;
    }
    fillInstalledBaseServiceLogForm(record);
    [
        'complaint_date', 'issue_description', 'engineer_name', 'visit_date', 'action_taken', 'closure_date',
        'separator_remaining_date', 'separator_remaining_hours',
        'air_filter_remaining_date', 'air_filter_remaining_hours',
        'oil_filter_remaining_date', 'oil_filter_remaining_hours',
        'oil_remaining_date', 'oil_remaining_hours',
        'valve_kit_remaining_date', 'valve_kit_remaining_hours',
        'grease_remaining_date', 'grease_remaining_hours', 'running_hours', 'remarks'
    ].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = record[field] ?? '';
        }
    });
    setStaticSelect2Value('ibServiceLogWarrantySelect', record.warranty_chargeable || '');
    setStaticSelect2Value('ibServiceLogPartReplacedSelect', record.part_replaced || '');
    ibServiceLogTogglePartReplacementSection(record.part_replaced || '');
    ibServiceLogClearPartReplacementEntries();
    const entries = Array.isArray(record.part_replacement_entries) ? record.part_replacement_entries : [];
    if (ibServiceLogIsPartReplacedYes(record.part_replaced)) {
        if (entries.length) {
            entries.forEach(function (entry) {
                ibServiceLogCreatePartReplacementEntry({
                    machine_model_code: entry.machine_model_code || '',
                    machine_model_desc: entry.machine_model || '',
                    quantity: entry.quantity || ''
                });
            });
        } else {
            ibServiceLogCreatePartReplacementEntry({});
        }
    }
    complaintServiceLogApplyFeedbackRating(record.customer_feedback || '');
    ibServiceLogSetModalMode('edit');
}

function ibServiceLogCreatePartReplacementEntry(defaults) {
    defaults = defaults || {};
    const container = document.getElementById('ibServiceLogPartReplacementEntries');
    if (!container) {
        return null;
    }
    const index = ibServiceLogPartReplacementIndex++;
    const entry = document.createElement('div');
    entry.className = 'ib-part-replacement-entry border rounded p-3 mb-3';
    entry.setAttribute('data-entry-index', String(index));
    entry.innerHTML = ''
        + '<div class="d-flex justify-content-between align-items-center mb-2"><strong>Entry</strong>'
        + '<button type="button" class="btn btn-sm btn-outline-danger ib-remove-part-replacement-entry">Remove</button></div>'
        + '<div class="row g-2">'
        + '<div class="col-md-8 form-group">'
        + '<label class="form-label">Machine Model / Part <span class="text-danger">*</span></label>'
        + '<select class="form-control ib-part-model-select" name="part_replacement_entries[' + index + '][machine_model_code]" data-placeholder="Search machine model"><option value=""></option></select>'
        + '<input type="hidden" class="ib-part-model-desc" name="part_replacement_entries[' + index + '][machine_model]">'
        + '<div class="text-danger validation-msg" data-field="part_replacement_entries.' + index + '.machine_model_code"></div>'
        + '</div>'
        + '<div class="col-md-4 form-group">'
        + '<label class="form-label">Quantity <span class="text-danger">*</span></label>'
        + '<input type="number" class="form-control" min="1" step="1" name="part_replacement_entries[' + index + '][quantity]" value="' + $('<div>').text(defaults.quantity || '').html() + '">'
        + '<div class="text-danger validation-msg" data-field="part_replacement_entries.' + index + '.quantity"></div>'
        + '</div>'
        + '</div>';
    container.appendChild(entry);
    const $select = $(entry.querySelector('.ib-part-model-select'));
    $select.select2({
        width: '100%',
        dropdownParent: $('#installedBaseServiceLogModal'),
        placeholder: 'Search machine model',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: 'api/machine_model_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term || '' }; },
            processResults: function (data) { return data; }
        }
    });
    if (defaults.machine_model_code) {
        const label = defaults.machine_model_desc
            ? defaults.machine_model_code + ' - ' + defaults.machine_model_desc
            : defaults.machine_model_code;
        const option = new Option(label, defaults.machine_model_code, true, true);
        $select.append(option).trigger('change');
        entry.querySelector('.ib-part-model-desc').value = defaults.machine_model_desc || '';
    }
    $select.on('select2:select', function (e) {
        const data = e.params.data || {};
        entry.querySelector('.ib-part-model-desc').value = data.tpldesc || '';
        const form = document.getElementById('installedBaseServiceLogForm');
        if (form) {
            complaintServiceLogClearFieldError(form, 'part_replacement_entries.' + index + '.machine_model_code');
            complaintServiceLogMaybeClearPartReplacementEntriesError(form);
        }
    });
    $select.on('select2:clear', function () {
        const hiddenDesc = entry.querySelector('.ib-part-model-desc');
        if (hiddenDesc) {
            hiddenDesc.value = '';
        }
    });
    return entry;
}

function ibServiceLogClearPartReplacementEntries() {
    const container = document.getElementById('ibServiceLogPartReplacementEntries');
    if (!container) {
        return;
    }
    container.querySelectorAll('.ib-part-model-select').forEach(function (el) {
        const $el = $(el);
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
    });
    container.innerHTML = '';
    ibServiceLogPartReplacementIndex = 0;
}

function ibServiceLogTogglePartReplacementSection(value) {
    const isYes = ibServiceLogIsPartReplacedYes(value);
    const wrapper = document.getElementById('ibServiceLogPartReplacementWrapper');
    const hours = document.getElementById('ibServiceLogPartReplacedCommonHoursWrapper');
    if (wrapper) {
        wrapper.classList.toggle('d-none', !isYes);
    }
    if (hours) {
        hours.classList.toggle('d-none', !isYes);
    }
    if (isYes && !document.querySelector('#ibServiceLogPartReplacementEntries .ib-part-replacement-entry')) {
        ibServiceLogCreatePartReplacementEntry({});
    }
    if (!isYes) {
        ibServiceLogClearPartReplacementEntries();
    }
}

function complaintServiceLogGetCustomerFeedbackValue() {
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!form) {
        return '';
    }

    const input = form.querySelector('[name="customer_feedback"]');
    if (input) {
        const inputValue = input.value.trim();
        const inputRating = parseInt(inputValue, 10);
        if (inputValue && Number.isInteger(inputRating) && inputRating >= 1 && inputRating <= 10) {
            return String(inputRating);
        }
    }

    if (window.ibServiceLogFeedbackRating) {
        const rating = window.ibServiceLogFeedbackRating.get();
        const parsed = parseInt(rating, 10);
        if (rating && Number.isInteger(parsed) && parsed >= 1 && parsed <= 10) {
            return String(parsed);
        }
    }

    const wrap = document.getElementById('ibServiceLogCustomerFeedbackRating');
    if (!wrap) {
        return '';
    }

    let highestRating = 0;
    wrap.querySelectorAll('.closure-rating__item.is-active, .closure-rating__item.is-selected').forEach(function (button) {
        const rating = parseInt(button.getAttribute('data-rating'), 10);
        if (Number.isInteger(rating) && rating > highestRating) {
            highestRating = rating;
        }
    });

    return highestRating >= 1 ? String(highestRating) : '';
}

function complaintServiceLogApplyFeedbackRating(rating) {
    const normalized = String(rating || '').trim();
    const parsed = parseInt(normalized, 10);
    const hasRating = normalized !== '' && Number.isInteger(parsed) && parsed >= 1 && parsed <= 10;
    const value = hasRating ? String(parsed) : '';

    const form = document.getElementById('installedBaseServiceLogForm');
    const input = form ? form.querySelector('[name="customer_feedback"]') : null;
    if (input) {
        input.value = value;
    }

    if (window.ibServiceLogFeedbackRating) {
        window.ibServiceLogFeedbackRating.set(value);
    }
}

function complaintServiceLogSyncCustomerFeedbackInput() {
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!form) {
        return;
    }

    const input = form.querySelector('[name="customer_feedback"]');
    if (!input) {
        return;
    }

    const rating = complaintServiceLogGetCustomerFeedbackValue();
    input.value = rating;
}

function complaintServiceLogRemainingConsumableFields() {
    return [
        { key: 'separator', label: 'Separator' },
        { key: 'air_filter', label: 'Air Filter' },
        { key: 'oil_filter', label: 'Oil Filter' },
        { key: 'oil', label: 'Oil' },
        { key: 'valve_kit', label: 'Valve Kit' },
        { key: 'grease', label: 'Grease' }
    ];
}

function complaintServiceLogValidateRemainingConsumables(form) {
    const errors = {};

    complaintServiceLogRemainingConsumableFields().forEach(function (item) {
        const dateKey = item.key + '_remaining_date';
        const hoursKey = item.key + '_remaining_hours';
        const dateInput = form.querySelector('[name="' + dateKey + '"]');
        const hoursInput = form.querySelector('[name="' + hoursKey + '"]');

        if (!dateInput || !String(dateInput.value || '').trim()) {
            errors[dateKey] = [item.label + ' Remaining Date is required'];
        }

        if (!hoursInput || !String(hoursInput.value || '').trim()) {
            errors[hoursKey] = [item.label + ' Remaining Hours is required'];
            return;
        }

        const hoursValue = hoursInput.value.trim();
        if (!/^-?\d+(\.\d+)?$/.test(hoursValue) || parseFloat(hoursValue) < 0) {
            errors[hoursKey] = [item.label + ' Remaining Hours must be a valid non-negative number'];
        }
    });

    return Object.keys(errors).length ? errors : null;
}

function complaintServiceLogValidateDateOrder(form) {
    if (!form) {
        return null;
    }

    const complaintDate = (form.querySelector('[name="complaint_date"]') || {}).value || '';
    const visitDate = (form.querySelector('[name="visit_date"]') || {}).value || '';
    const closureDate = (form.querySelector('[name="closure_date"]') || {}).value || '';
    const errors = {};

    if (visitDate && complaintDate && visitDate < complaintDate) {
        errors.visit_date = ['Visit Date cannot be earlier than Log Date'];
    }

    if (closureDate && visitDate && closureDate < visitDate) {
        errors.closure_date = ['Closure Date cannot be earlier than Visit Date'];
    }

    return Object.keys(errors).length ? errors : null;
}

function complaintServiceLogApplyDateOrderValidation(form) {
    const dateErrors = complaintServiceLogValidateDateOrder(form);

    ['visit_date', 'closure_date'].forEach(function (field) {
        if (dateErrors && dateErrors[field]) {
            const input = form.querySelector('[name="' + field + '"]');
            const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
            if (input) {
                input.classList.add('is-invalid');
            }
            if (msg) {
                msg.textContent = dateErrors[field][0];
            }
            return;
        }

        const input = form.querySelector('[name="' + field + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
        if (!input || !msg) {
            return;
        }

        const currentMessage = String(msg.textContent || '');
        if (
            currentMessage === 'Visit Date cannot be earlier than Log Date'
            || currentMessage === 'Closure Date cannot be earlier than Visit Date'
        ) {
            complaintServiceLogClearFieldError(form, field);
        }
    });
}

function complaintServiceLogValidateCommonHours(form, errors) {
    const runningInput = form.querySelector('[name="running_hours"]');

    if (!runningInput) {
        return;
    }

    const runningValue = runningInput.value.trim();
    if (!runningValue) {
        errors.running_hours = ['Running Hours is required'];
    } else if (!/^-?\d+(\.\d+)?$/.test(runningValue) || parseFloat(runningValue) <= 0) {
        errors.running_hours = ['Running Hours must be greater than 0'];
    }
}

function complaintServiceLogValidatePartReplacementEntries(form) {
    const partReplaced = $('#ibServiceLogPartReplacedSelect').val();
    if (!ibServiceLogIsPartReplacedYes(partReplaced)) {
        return null;
    }

    const entries = form.querySelectorAll('.ib-part-replacement-entry');
    const errors = {};

    complaintServiceLogValidateCommonHours(form, errors);

    if (!entries.length) {
        errors.part_replacement_entries = ['At least one Machine Model / Part entry is required when Part Replaced is Yes'];
        return Object.keys(errors).length ? errors : null;
    }

    entries.forEach(function (entry) {
        const index = entry.getAttribute('data-entry-index');
        const modelSelect = entry.querySelector('[name="part_replacement_entries[' + index + '][machine_model_code]"]');
        const modelDesc = entry.querySelector('[name="part_replacement_entries[' + index + '][machine_model]"]');
        const quantity = entry.querySelector('[name="part_replacement_entries[' + index + '][quantity]"]');

        if (!modelSelect || !modelSelect.value || !modelDesc || !modelDesc.value.trim()) {
            errors['part_replacement_entries.' + index + '.machine_model_code'] = ['Machine Model / Part is required'];
        }

        if (quantity) {
            const quantityValue = quantity.value.trim();
            if (!quantityValue) {
                errors['part_replacement_entries.' + index + '.quantity'] = ['Quantity is required'];
            } else if (!/^\d+$/.test(quantityValue) || parseInt(quantityValue, 10) < 1) {
                errors['part_replacement_entries.' + index + '.quantity'] = ['Quantity must be a positive whole number (minimum 1)'];
            }
        }
    });

    complaintServiceLogSyncCustomerFeedbackInput();
    const feedbackInput = form.querySelector('[name="customer_feedback"]');
    if (feedbackInput) {
        const customerFeedback = feedbackInput.value.trim();
        const rating = parseInt(customerFeedback, 10);
        if (!customerFeedback || !Number.isInteger(rating) || rating < 1 || rating > 10) {
            errors.customer_feedback = ['Please select a customer feedback rating between 1 and 10'];
        }
    }

    const remarks = form.querySelector('[name="remarks"]');
    if (remarks && remarks.value.length > 1000) {
        errors.remarks = ['Remarks cannot exceed 1000 characters'];
    }

    return Object.keys(errors).length ? errors : null;
}

function complaintServiceLogShowPartReplacementErrors(form, errors) {
    if (!errors) {
        return;
    }

    Object.keys(errors).forEach(function (field) {
        if (field === 'part_replacement_entries') {
            const msg = form.querySelector('.validation-msg[data-field="part_replacement_entries"]');
            if (msg && errors[field].length) {
                msg.textContent = errors[field][0];
            }
            return;
        }

        if (field === 'customer_feedback') {
            return;
        }

        const inputName = field.replace(
            /^part_replacement_entries\.(\d+)\.(\w+)$/,
            function (_match, index, key) {
                return 'part_replacement_entries[' + index + '][' + key + ']';
            }
        );
        const input = form.querySelector('[name="' + inputName + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');

        if (input) {
            input.classList.add('is-invalid');
            if ($(input).hasClass('ib-part-model-select')) {
                $(input).next('.select2-container').find('.select2-selection').addClass('is-invalid');
            }
        }

        if (msg && errors[field] && errors[field].length) {
            msg.textContent = errors[field][0].replace(/^\^/, '');
        }
    });

    if (errors.customer_feedback) {
        if (window.ibServiceLogFeedbackRating) {
            window.ibServiceLogFeedbackRating.setError(errors.customer_feedback[0].replace(/^\^/, ''));
        } else {
            const feedbackMsg = form.querySelector('.validation-msg[data-field="customer_feedback"]');
            if (feedbackMsg && errors.customer_feedback.length) {
                feedbackMsg.textContent = errors.customer_feedback[0].replace(/^\^/, '');
            }
        }
    }
}

function initInstalledBaseServiceLogModal() {
    const modalEl = document.getElementById('installedBaseServiceLogModal');
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!modalEl || !form) {
        return;
    }
    complaintServiceLogReinitControls();

    modalEl.addEventListener('shown.bs.modal', function () {
        const savedFeedback = complaintServiceLogGetCustomerFeedbackValue();
        complaintServiceLogReinitControls();
        if (savedFeedback) {
            complaintServiceLogApplyFeedbackRating(savedFeedback);
        }
    });
    complaintServiceLogInitLiveValidation(form);
    $('#ibServiceLogPartReplacementEntries').on('click', '.ib-remove-part-replacement-entry', function () {
        const entry = this.closest('.ib-part-replacement-entry');
        if (!entry) {
            return;
        }
        const select = entry.querySelector('.ib-part-model-select');
        if (select && $(select).hasClass('select2-hidden-accessible')) {
            $(select).select2('destroy');
        }
        entry.remove();
    });
    document.getElementById('ibServiceLogAddPartReplacementBtn').addEventListener('click', function () {
        ibServiceLogCreatePartReplacementEntry({});
    });

    let submitting = false;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (submitting) {
            return;
        }
        complaintServiceLogSyncCustomerFeedbackInput();
        ibServiceLogClearErrors(form);
        const required = ['installed_base_id', 'fab_number', 'machine_model', 'serial_number', 'warranty_chargeable', 'complaint_date', 'issue_description', 'engineer_name', 'visit_date', 'action_taken', 'closure_date', 'part_replaced'];
        let hasError = false;
        required.forEach(function (field) {
            const input = form.querySelector('[name="' + field + '"]');
            if (input && !String(input.value || '').trim()) {
                hasError = true;
                input.classList.add('is-invalid');
                const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
                if (msg) {
                    msg.textContent = 'This field is required';
                }
            }
        });
        const partErrors = complaintServiceLogValidatePartReplacementEntries(form);
        if (partErrors) {
            complaintServiceLogShowPartReplacementErrors(form, partErrors);
            hasError = true;
        }
        const dateErrors = complaintServiceLogValidateDateOrder(form);
        if (dateErrors) {
            complaintServiceLogShowPartReplacementErrors(form, dateErrors);
            hasError = true;
        }
        const consumableErrors = complaintServiceLogValidateRemainingConsumables(form);
        if (consumableErrors) {
            complaintServiceLogShowPartReplacementErrors(form, consumableErrors);
            hasError = true;
        }
        if (hasError) {
            return;
        }
        submitting = true;
        $('#submitIbServiceLogBtn').addClass('disabled_btn');
        const recordId = parseInt(document.getElementById('ibServiceLogRecordId').value || '0', 10);
        const url = recordId > 0 ? 'api/service_log_update.php' : 'api/service_log_create.php';
        $.ajax({
            url: url,
            type: 'POST',
            data: $(form).serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (typeof window.complaintServiceLogAfterSave === 'function') {
                window.complaintServiceLogAfterSave(response || {});
            }
        }).fail(function (xhr) {
            const message = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to save service log.';
            if (/customer feedback/i.test(message) && window.ibServiceLogFeedbackRating) {
                window.ibServiceLogFeedbackRating.setError(message);
            }
            if (typeof showComplaintServiceLogAlert === 'function') {
                showComplaintServiceLogAlert('error', message);
            }
        }).always(function () {
            submitting = false;
            $('#submitIbServiceLogBtn').removeClass('disabled_btn');
        });
    });
}