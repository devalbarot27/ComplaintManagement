let ibServiceLogPartReplacementIndex = 0;
let ibServiceLogPrefillData = {
    running_hours: '',
    machine_model_code: '',
    machine_model_desc: ''
};

function ibServiceLogIsPartReplacedYes(value) {
    return String(value || '').trim().toLowerCase() === 'yes';
}

function ibServiceLogDestroyPartReplacementSelect2(entry) {
    if (!entry) {
        return;
    }

    entry.querySelectorAll('select').forEach(function (select) {
        const $select = $(select);
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
    });
}

function ibServiceLogClearPartReplacementEntries() {
    const container = document.getElementById('ibServiceLogPartReplacementEntries');
    if (!container) {
        return;
    }

    container.querySelectorAll('.ib-part-replacement-entry').forEach(function (entry) {
        ibServiceLogDestroyPartReplacementSelect2(entry);
        entry.remove();
    });

    ibServiceLogPartReplacementIndex = 0;
}

function ibServiceLogUpdatePartReplacementEntryNumbers() {
    const container = document.getElementById('ibServiceLogPartReplacementEntries');
    if (!container) {
        return;
    }

    const entries = container.querySelectorAll('.ib-part-replacement-entry');
    entries.forEach(function (entry, index) {
        const label = entry.querySelector('.ib-part-entry-number');
        if (label) {
            label.textContent = String(index + 1);
        }

        const removeBtn = entry.querySelector('.ib-remove-part-replacement-entry');
        if (removeBtn) {
            removeBtn.classList.toggle('d-none', entries.length <= 1);
        }
    });
}

function ibServiceLogInitPartModelSelect2(entry, index) {
    const $modal = $('#installedBaseServiceLogModal');
    const select = entry.querySelector('.ib-part-model-select');
    if (!select || typeof $.fn.select2 === 'undefined') {
        return;
    }

    const $select = $(select);
    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }

    const select2Options = {
        width: '100%',
        placeholder: 'Search machine model',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: 'api/machine_model_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (data) {
                return data;
            },
            cache: true
        },
        language: {
            inputTooShort: function () {
                return 'Type to search machine model';
            },
            noResults: function () {
                return 'No machine model found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    };

    if ($modal.length) {
        select2Options.dropdownParent = $modal;
    }

    $select.select2(select2Options);

    $select.on('select2:select', function (e) {
        const data = e.params.data || {};
        const hiddenDesc = entry.querySelector('.ib-part-model-desc');
        if (hiddenDesc) {
            hiddenDesc.value = data.tpldesc || '';
        }
        $select.removeClass('is-invalid');
        const msg = entry.querySelector('.validation-msg[data-field="part_replacement_entries.' + index + '.machine_model_code"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    $select.on('select2:clear', function () {
        const hiddenDesc = entry.querySelector('.ib-part-model-desc');
        if (hiddenDesc) {
            hiddenDesc.value = '';
        }
    });
}

function ibServiceLogSetPartModelSelect2(entry, index, code, description) {
    const select = entry.querySelector('.ib-part-model-select');
    const hiddenDesc = entry.querySelector('.ib-part-model-desc');
    if (!select) {
        return;
    }

    const $select = $(select);
    $select.val(null).trigger('change');

    if (code) {
        const label = description ? code + ' - ' + description : code;
        const option = new Option(label, code, true, true);
        $select.append(option).trigger('change');
        if (hiddenDesc) {
            hiddenDesc.value = description || '';
        }
    } else if (hiddenDesc) {
        hiddenDesc.value = '';
    }
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
        + '<div class="d-flex justify-content-between align-items-center mb-3">'
        + '  <strong>Entry <span class="ib-part-entry-number"></span></strong>'
        + '  <button type="button" class="btn btn-sm btn-outline-danger ib-remove-part-replacement-entry">'
        + '    <i class="bi bi-trash"></i> Remove'
        + '  </button>'
        + '</div>'
        + '<div class="row g-3">'
        + '  <div class="col-md-4 form-group">'
        + '    <label class="form-label"><i class="bi bi-cpu"></i> Machine Model / Part <span class="text-danger">*</span></label>'
        + '    <select class="form-control ib-part-model-select" id="ibServiceLogPartModelSelect_' + index + '"'
        + '      name="part_replacement_entries[' + index + '][machine_model_code]" data-placeholder="Search machine model">'
        + '      <option value=""></option>'
        + '    </select>'
        + '    <input type="hidden" class="ib-part-model-desc" name="part_replacement_entries[' + index + '][machine_model]">'
        + '    <div class="text-danger validation-msg" data-field="part_replacement_entries.' + index + '.machine_model_code"></div>'
        + '  </div>'
        + '  <div class="col-md-4 form-group">'
        + '    <label class="form-label"><i class="bi bi-clock-history"></i> Running Hours <span class="text-danger">*</span></label>'
        + '    <input type="number" class="form-control" name="part_replacement_entries[' + index + '][running_hours]"'
        + '      min="0.01" step="0.01" placeholder="Machine usage"'
        + '      value="' + $('<div>').text(defaults.running_hours || '').html() + '">'
        + '    <div class="text-danger validation-msg" data-field="part_replacement_entries.' + index + '.running_hours"></div>'
        + '  </div>'
        + '  <div class="col-md-4 form-group">'
        + '    <label class="form-label"><i class="bi bi-speedometer2"></i> Loaded Hours <span class="text-danger">*</span></label>'
        + '    <input type="number" class="form-control" name="part_replacement_entries[' + index + '][loaded_hours]"'
        + '      min="0" step="0.01" placeholder="Operational load">'
        + '    <div class="text-danger validation-msg" data-field="part_replacement_entries.' + index + '.loaded_hours"></div>'
        + '  </div>'
        + '</div>';

    container.appendChild(entry);
    ibServiceLogInitPartModelSelect2(entry, index);
    ibServiceLogSetPartModelSelect2(
        entry,
        index,
        defaults.machine_model_code || '',
        defaults.machine_model_desc || ''
    );
    ibServiceLogUpdatePartReplacementEntryNumbers();

    return entry;
}

function ibServiceLogEnsurePartReplacementEntry() {
    const container = document.getElementById('ibServiceLogPartReplacementEntries');
    if (!container || container.querySelector('.ib-part-replacement-entry')) {
        return;
    }

    ibServiceLogCreatePartReplacementEntry({
        running_hours: ibServiceLogPrefillData.running_hours,
        machine_model_code: ibServiceLogPrefillData.machine_model_code,
        machine_model_desc: ibServiceLogPrefillData.machine_model_desc
    });
}

function ibServiceLogClearPartReplacementFeedbackFields(form) {
    if (!form) {
        return;
    }

    resetStaticSelect2('ibServiceLogFeedbackSelect');

    const remarks = form.querySelector('[name="remarks"]');
    if (remarks) {
        remarks.value = '';
        remarks.classList.remove('is-invalid');
    }

    form.querySelectorAll('.validation-msg[data-field="customer_feedback"], .validation-msg[data-field="remarks"]')
        .forEach(function (el) {
            el.textContent = '';
        });
}

function ibServiceLogTogglePartReplacementSection(partReplacedValue) {
    const wrapper = document.getElementById('ibServiceLogPartReplacementWrapper');
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!wrapper || !form) {
        return;
    }

    const entriesMsg = form.querySelector('.validation-msg[data-field="part_replacement_entries"]');
    if (entriesMsg) {
        entriesMsg.textContent = '';
    }

    if (ibServiceLogIsPartReplacedYes(partReplacedValue)) {
        wrapper.classList.remove('d-none');
        ibServiceLogEnsurePartReplacementEntry();
        return;
    }

    wrapper.classList.add('d-none');
    ibServiceLogClearPartReplacementEntries();
    ibServiceLogClearPartReplacementFeedbackFields(form);
    wrapper.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    wrapper.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function ibServiceLogRedirectAfterSave(installedBaseId) {
    const parsedId = parseInt(installedBaseId, 10);
    if (parsedId > 0) {
        window.location.replace(
            'installed_base_details.php?id=' + btoa(String(parsedId)) + '&service_log_added=1'
        );
        return;
    }

    window.location.replace('installed_base.php?service_log_added=1');
}

function ibServiceLogValidatePartReplacementEntries(form) {
    const partReplaced = $('#ibServiceLogPartReplacedSelect').val();
    if (!ibServiceLogIsPartReplacedYes(partReplaced)) {
        return null;
    }

    const entries = form.querySelectorAll('.ib-part-replacement-entry');
    const errors = {};

    if (!entries.length) {
        errors.part_replacement_entries = ['At least one Machine Model / Part entry is required when Part Replaced is Yes'];
        return errors;
    }

    entries.forEach(function (entry) {
        const index = entry.getAttribute('data-entry-index');
        const modelSelect = entry.querySelector('[name="part_replacement_entries[' + index + '][machine_model_code]"]');
        const modelDesc = entry.querySelector('[name="part_replacement_entries[' + index + '][machine_model]"]');
        const runningHours = entry.querySelector('[name="part_replacement_entries[' + index + '][running_hours]"]');
        const loadedHours = entry.querySelector('[name="part_replacement_entries[' + index + '][loaded_hours]"]');

        if (!modelSelect || !modelSelect.value || !modelDesc || !modelDesc.value.trim()) {
            errors['part_replacement_entries.' + index + '.machine_model_code'] = ['Machine Model / Part is required'];
        }

        if (runningHours) {
            const runningValue = runningHours.value.trim();
            if (!runningValue) {
                errors['part_replacement_entries.' + index + '.running_hours'] = ['Running Hours is required'];
            } else if (!/^-?\d+(\.\d+)?$/.test(runningValue) || parseFloat(runningValue) <= 0) {
                errors['part_replacement_entries.' + index + '.running_hours'] = ['Running Hours must be greater than 0'];
            }
        }

        if (loadedHours) {
            const loadedValue = loadedHours.value.trim();
            if (!loadedValue) {
                errors['part_replacement_entries.' + index + '.loaded_hours'] = ['Loaded Hours is required'];
            } else if (!/^-?\d+(\.\d+)?$/.test(loadedValue) || parseFloat(loadedValue) < 0) {
                errors['part_replacement_entries.' + index + '.loaded_hours'] = ['Loaded Hours must be a valid number'];
            }
        }
    });

    const customerFeedback = $('#ibServiceLogFeedbackSelect').val();
    if (!customerFeedback) {
        errors.customer_feedback = ['Customer Feedback is required'];
    }

    const remarks = form.querySelector('[name="remarks"]');
    if (remarks && remarks.value.length > 1000) {
        errors.remarks = ['Remarks cannot exceed 1000 characters'];
    }

    return Object.keys(errors).length ? errors : null;
}

function ibServiceLogShowPartReplacementErrors(form, errors) {
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
        const feedbackSelect = form.querySelector('#ibServiceLogFeedbackSelect');
        const feedbackMsg = form.querySelector('.validation-msg[data-field="customer_feedback"]');
        if (feedbackSelect) {
            feedbackSelect.classList.add('is-invalid');
            $(feedbackSelect).next('.select2-container').find('.select2-selection').addClass('is-invalid');
        }
        if (feedbackMsg && errors.customer_feedback.length) {
            feedbackMsg.textContent = errors.customer_feedback[0];
        }
    }
}

function showInstalledBasePageAlert(type, message) {
    const content = document.querySelector('.content');
    if (!content || !message) {
        return;
    }

    content.querySelectorAll('.installed-base-ajax-alert').forEach(function (el) {
        el.remove();
    });

    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const wrapper = document.createElement('div');
    wrapper.className = 'alert ' + alertClass + ' alert-dismissible fade show mb-3 installed-base-ajax-alert';
    wrapper.setAttribute('role', 'alert');

    const text = document.createElement('span');
    text.textContent = message;
    wrapper.appendChild(text);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('data-bs-dismiss', 'alert');
    wrapper.appendChild(closeBtn);

    content.insertBefore(wrapper, content.firstChild);

    if (type === 'success') {
        setTimeout(function () {
            $(wrapper).fadeOut(function () {
                wrapper.remove();
            });
        }, 3000);
    }
}

function resetInstalledBaseServiceLogForm() {
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('ibServiceLogInstalledBaseId').value = '';
    document.getElementById('ibServiceLogInstalledBaseLabel').value = '';
    ibServiceLogPrefillData = {
        running_hours: '',
        machine_model_code: '',
        machine_model_desc: ''
    };

    resetStaticSelect2Fields([
        'ibServiceLogWarrantySelect',
        'ibServiceLogPartReplacedSelect'
    ]);

    ibServiceLogClearPartReplacementEntries();
    document.getElementById('ibServiceLogPartReplacementWrapper').classList.add('d-none');
    ibServiceLogClearPartReplacementFeedbackFields(form);

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
    form.querySelectorAll('.select2-selection.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
}

function fillInstalledBaseServiceLogForm(data) {
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!form || !data) {
        return;
    }

    document.getElementById('ibServiceLogInstalledBaseId').value = data.installed_base_id || '';
    document.getElementById('ibServiceLogInstalledBaseLabel').value = data.installed_base_label || '';
    ibServiceLogPrefillData = {
        running_hours: data.running_hours != null ? String(data.running_hours) : '',
        machine_model_code: data.machine_model_code != null ? String(data.machine_model_code) : '',
        machine_model_desc: data.machine_model_desc != null ? String(data.machine_model_desc) : ''
    };

    ['order_id', 'fab_number', 'machine_model'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = data[field] ?? '';
        }
    });
}

function initInstalledBaseServiceLogSelect2() {
    const $modal = $('#installedBaseServiceLogModal');
    const dropdownParent = $modal.length ? $modal : null;
    const select2Options = dropdownParent ? { dropdownParent: dropdownParent } : {};

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

    $('#ibServiceLogPartReplacedSelect').on('change select2:select select2:clear', function () {
        ibServiceLogTogglePartReplacementSection($(this).val());
    });
}

function initInstalledBaseServiceLogPartReplacementControls() {
    const addBtn = document.getElementById('ibServiceLogAddPartReplacementBtn');
    const container = document.getElementById('ibServiceLogPartReplacementEntries');

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            ibServiceLogCreatePartReplacementEntry();
        });
    }

    if (container) {
        container.addEventListener('click', function (event) {
            const removeBtn = event.target.closest('.ib-remove-part-replacement-entry');
            if (!removeBtn) {
                return;
            }

            const entry = removeBtn.closest('.ib-part-replacement-entry');
            const entries = container.querySelectorAll('.ib-part-replacement-entry');
            if (!entry || entries.length <= 1) {
                return;
            }

            ibServiceLogDestroyPartReplacementSelect2(entry);
            entry.remove();
            ibServiceLogUpdatePartReplacementEntryNumbers();
        });
    }
}

function initInstalledBaseServiceLogFeedbackSelect2() {
    const $modal = $('#installedBaseServiceLogModal');
    const dropdownParent = $modal.length ? $modal : null;
    const select2Options = dropdownParent ? { dropdownParent: dropdownParent } : {};

    initStaticSelect2Fields('installedBaseServiceLogForm', [
        Object.assign({
            selectId: 'ibServiceLogFeedbackSelect',
            validationField: 'customer_feedback',
            allowClear: false,
            noResultsText: 'No feedback option found'
        }, select2Options)
    ]);
}

function initInstalledBaseServiceLogValidation() {
    const form = document.getElementById('installedBaseServiceLogForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    function consumableHoursConstraint(label) {
        return {
            numericality: {
                greaterThanOrEqualTo: 0,
                allowEmpty: true,
                message: '^' + label + ' Remaining Hours must be a valid number'
            }
        };
    }

    const constraints = {
        installed_base_id: {
            presence: { allowEmpty: false, message: '^Installed base record is required' }
        },
        order_id: {
            presence: { allowEmpty: false, message: '^Order ID is required' }
        },
        fab_number: {
            presence: { allowEmpty: false, message: '^Fab Number is required' }
        },
        serial_number: {
            presence: { allowEmpty: false, message: '^Serial Number is required' }
        },
        machine_model: {
            presence: { allowEmpty: false, message: '^Machine Model is required' }
        },
        warranty_chargeable: {
            presence: { allowEmpty: false, message: '^Warranty / Chargeable is required' }
        },
        complaint_date: {
            presence: { allowEmpty: false, message: '^Complaint Date is required' }
        },
        issue_description: {
            presence: { allowEmpty: false, message: '^Issue Description is required' }
        },
        engineer_name: {
            presence: { allowEmpty: false, message: '^Engineer Name is required' }
        },
        visit_date: {
            presence: { allowEmpty: false, message: '^Visit Date is required' }
        },
        action_taken: {
            presence: { allowEmpty: false, message: '^Action Taken is required' }
        },
        closure_date: {
            presence: { allowEmpty: false, message: '^Closure Date is required to complete the service log' }
        },
        part_replaced: {
            presence: { allowEmpty: false, message: '^Part Replaced is required' }
        },
        separator_remaining_hours: consumableHoursConstraint('Separator'),
        air_filter_remaining_hours: consumableHoursConstraint('Air Filter'),
        oil_filter_remaining_hours: consumableHoursConstraint('Oil Filter'),
        oil_remaining_hours: consumableHoursConstraint('Oil'),
        valve_kit_remaining_hours: consumableHoursConstraint('Valve Kit'),
        grease_remaining_hours: consumableHoursConstraint('Grease')
    };

    function clearValidationState() {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.validation-msg').forEach(function (el) {
            el.textContent = '';
        });
        form.querySelectorAll('.select2-selection.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
    }

    function showErrors(errors) {
        clearValidationState();

        if (!errors) {
            return;
        }

        Object.keys(errors).forEach(function (field) {
            if (field.indexOf('part_replacement_entries') === 0 || field === 'remarks' || field === 'customer_feedback') {
                return;
            }

            const input = form.querySelector('[name="' + field + '"]');
            const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');

            if (input) {
                input.classList.add('is-invalid');
            }

            if (msg && errors[field] && errors[field].length) {
                msg.textContent = errors[field][0];
            }
        });

        ibServiceLogShowPartReplacementErrors(form, errors);
    }

    form.querySelectorAll('input, textarea, select').forEach(function (input) {
        if (!constraints[input.name]) {
            return;
        }

        const eventName = input.tagName === 'SELECT' ? 'change' : 'input';
        input.addEventListener(eventName, function () {
            const fieldErrors = validate.single(input.value, constraints[input.name]);
            const msg = form.querySelector('.validation-msg[data-field="' + input.name + '"]');
            input.classList.toggle('is-invalid', !!fieldErrors);
            if (msg) {
                msg.textContent = fieldErrors ? fieldErrors[0] : '';
            }
        });
    });

    let isSubmitting = false;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (isSubmitting) {
            return;
        }

        const baseErrors = validate(form, constraints) || {};
        const partErrors = ibServiceLogValidatePartReplacementEntries(form) || {};
        const mergedErrors = Object.assign({}, baseErrors, partErrors);

        showErrors(Object.keys(mergedErrors).length ? mergedErrors : null);

        if (Object.keys(mergedErrors).length) {
            return;
        }

        const installedBaseId = document.getElementById('ibServiceLogInstalledBaseId').value;
        if (!installedBaseId || parseInt(installedBaseId, 10) <= 0) {
            showErrors({ installed_base_id: ['Installed base record is required'] });
            return;
        }

        const complaintDate = form.querySelector('[name="complaint_date"]').value;
        const visitDate = form.querySelector('[name="visit_date"]').value;
        const closureDate = form.querySelector('[name="closure_date"]').value;

        if (visitDate && complaintDate && visitDate < complaintDate) {
            showErrors({ visit_date: ['Visit Date cannot be earlier than Complaint Date'] });
            return;
        }

        if (closureDate && visitDate && closureDate < visitDate) {
            showErrors({ closure_date: ['Closure Date cannot be earlier than Visit Date'] });
            return;
        }

        isSubmitting = true;
        const submitButton = document.getElementById('submitIbServiceLogBtn');
        if (submitButton) {
            submitButton.classList.add('disabled_btn');
        }

        $.ajax({
            url: 'api/service_log_create.php',
            type: 'POST',
            data: $(form).serialize(),
            dataType: 'json'
        })
            .done(function (response) {
                const redirectInstalledBaseId = response && response.installed_base_id
                    ? response.installed_base_id
                    : installedBaseId;
                ibServiceLogRedirectAfterSave(redirectInstalledBaseId);
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Failed to save service log.';
                showInstalledBasePageAlert('error', message);
            })
            .always(function () {
                isSubmitting = false;
                if (submitButton) {
                    submitButton.classList.remove('disabled_btn');
                }
            });
    });
}

function initInstalledBaseServiceLogModal() {
    const modalEl = document.getElementById('installedBaseServiceLogModal');
    if (!modalEl) {
        return;
    }

    initInstalledBaseServiceLogSelect2();
    initInstalledBaseServiceLogFeedbackSelect2();
    initInstalledBaseServiceLogPartReplacementControls();
    initInstalledBaseServiceLogValidation();

    modalEl.addEventListener('hidden.bs.modal', function () {
        resetInstalledBaseServiceLogForm();
    });

    $(document).on('click', '.add-service-log-btn', function () {
        const id = $(this).data('id');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        resetInstalledBaseServiceLogForm();

        $.getJSON('api/installed_base_service_log_prefill.php', { id: id })
            .done(function (data) {
                fillInstalledBaseServiceLogForm(data);
                modal.show();
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Unable to load installed base details.';
                showInstalledBasePageAlert('error', message);
            });
    });
}
