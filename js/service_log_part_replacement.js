(function (window, $) {
    'use strict';

    function createServiceLogPartReplacementModule(config) {
        config = config || {};
        let entryIndex = 0;
        const entryClass = config.entryClass || 'service-log-part-replacement-entry';
        function getForm() {
            return document.getElementById(config.formId || '');
        }

        function getContainer() {
            return document.getElementById(config.entriesContainerId || '');
        }

        function getCommonHoursWrapper() {
            return config.commonHoursWrapperId
                ? document.getElementById(config.commonHoursWrapperId)
                : null;
        }

        function isPartReplacedYes(value) {
            return String(value || '').trim().toLowerCase() === 'yes';
        }

        function destroyEntrySelect2(entry) {
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

        function updateEntryNumbers() {
            const container = getContainer();
            if (!container) {
                return;
            }

            const entries = container.querySelectorAll('.' + entryClass);
            entries.forEach(function (entry, index) {
                const label = entry.querySelector('.part-entry-number');
                if (label) {
                    label.textContent = String(index + 1);
                }

                const removeBtn = entry.querySelector('.remove-part-replacement-entry');
                if (removeBtn) {
                    removeBtn.classList.toggle('d-none', entries.length <= 1);
                }
            });
        }

        function initPartModelSelect2(entry, index) {
            const select = entry.querySelector('.part-model-select');
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

            if (config.dropdownParent) {
                select2Options.dropdownParent = $(config.dropdownParent);
            }

            $select.select2(select2Options);

            $select.on('select2:select', function (e) {
                const data = e.params.data || {};
                const hiddenDesc = entry.querySelector('.part-model-desc');
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
                const hiddenDesc = entry.querySelector('.part-model-desc');
                if (hiddenDesc) {
                    hiddenDesc.value = '';
                }
            });
        }

        function setPartModelSelect2(entry, index, code, description) {
            const select = entry.querySelector('.part-model-select');
            const hiddenDesc = entry.querySelector('.part-model-desc');
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

        function createEntry(defaults) {
            defaults = defaults || {};
            const container = getContainer();
            if (!container) {
                return null;
            }

            const index = entryIndex++;
            const entry = document.createElement('div');
            entry.className = entryClass + ' border rounded p-3 mb-3';
            entry.setAttribute('data-entry-index', String(index));

            const existingId = defaults.id ? String(defaults.id) : '';
            const idInput = existingId
                ? '<input type="hidden" name="part_replacement_entries[' + index + '][id]" value="' + $('<div>').text(existingId).html() + '">'
                : '';

            entry.innerHTML = ''
                + idInput
                + '<div class="d-flex justify-content-between align-items-center mb-3">'
                + '  <strong>Entry <span class="part-entry-number"></span></strong>'
                + '  <button type="button" class="btn btn-sm btn-outline-danger remove-part-replacement-entry">'
                + '    <i class="bi bi-trash"></i> Remove'
                + '  </button>'
                + '</div>'
                + '<div class="row g-3">'
                + '  <div class="col-md-6 form-group">'
                + '    <label class="form-label"><i class="bi bi-cpu"></i> Machine Model / Part <span class="text-danger">*</span></label>'
                + '    <select class="form-control part-model-select" id="' + (config.partModelSelectPrefix || 'partModelSelect') + '_' + index + '"'
                + '      name="part_replacement_entries[' + index + '][machine_model_code]" data-placeholder="Search machine model">'
                + '      <option value=""></option>'
                + '    </select>'
                + '    <input type="hidden" class="part-model-desc" name="part_replacement_entries[' + index + '][machine_model]">'
                + '    <div class="text-danger validation-msg" data-field="part_replacement_entries.' + index + '.machine_model_code"></div>'
                + '  </div>'
                + '  <div class="col-md-6 form-group">'
                + '    <label class="form-label"><i class="bi bi-123"></i> Quantity <span class="text-danger">*</span></label>'
                + '    <input type="number" class="form-control" name="part_replacement_entries[' + index + '][quantity]"'
                + '      min="1" step="1" placeholder="Enter quantity"'
                + '      value="' + $('<div>').text(defaults.quantity || '').html() + '">'
                + '    <div class="text-danger validation-msg" data-field="part_replacement_entries.' + index + '.quantity"></div>'
                + '  </div>'
                + '</div>';

            container.appendChild(entry);
            initPartModelSelect2(entry, index);
            setPartModelSelect2(
                entry,
                index,
                defaults.machine_model_code || '',
                defaults.machine_model_desc || defaults.machine_model || ''
            );
            updateEntryNumbers();

            return entry;
        }

        function ensureEntry() {
            const container = getContainer();
            if (!container || container.querySelector('.' + entryClass)) {
                return;
            }

            createEntry({});
        }

        function clearCommonHoursFields() {
            const form = getForm();
            const wrapper = getCommonHoursWrapper();
            if (wrapper) {
                wrapper.classList.add('d-none');
            }

            if (!form) {
                return;
            }

            ['running_hours'].forEach(function (fieldName) {
                const input = form.querySelector('[name="' + fieldName + '"]');
                const msg = form.querySelector('.validation-msg[data-field="' + fieldName + '"]');
                if (input) {
                    input.value = '';
                    input.classList.remove('is-invalid');
                }
                if (msg) {
                    msg.textContent = '';
                }
            });
        }

        function showCommonHoursFields() {
            const wrapper = getCommonHoursWrapper();
            const form = getForm();
            if (wrapper) {
                wrapper.classList.remove('d-none');
            }

            if (!form) {
                return;
            }

        }

        function validateRunningHoursValue(value, required) {
            const runningValue = String(value || '').trim();
            if (!runningValue) {
                return required ? 'Running Hours is required' : null;
            }

            if (!/^-?\d+(\.\d+)?$/.test(runningValue) || parseFloat(runningValue) <= 0) {
                return 'Running Hours must be greater than 0';
            }

            return null;
        }

        function validateQuantityValue(value) {
            const quantityValue = String(value || '').trim();
            if (!quantityValue) {
                return 'Quantity is required';
            }

            if (!/^\d+$/.test(quantityValue) || parseInt(quantityValue, 10) < 1) {
                return 'Quantity must be a positive whole number (minimum 1)';
            }

            return null;
        }

        function clearFieldError(form, field) {
            form = form || getForm();
            if (!form || !field) {
                return;
            }

            if (field === 'running_hours') {
                const input = form.querySelector('[name="running_hours"]');
                const msg = form.querySelector('.validation-msg[data-field="running_hours"]');
                if (input) {
                    input.classList.remove('is-invalid');
                }
                if (msg) {
                    msg.textContent = '';
                }
                return;
            }

            if (field.indexOf('part_replacement_entries.') !== 0) {
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
                input.classList.remove('is-invalid');
                if ($(input).hasClass('part-model-select')) {
                    $(input).next('.select2-container').find('.select2-selection').removeClass('is-invalid');
                }
            }

            if (msg) {
                msg.textContent = '';
            }
        }

        function validateCommonHours(errors) {
            const form = getForm();
            if (!form) {
                return;
            }

            const runningInput = form.querySelector('[name="running_hours"]');
            if (!runningInput) {
                return;
            }

            const runningError = validateRunningHoursValue(runningInput.value, true);
            if (runningError) {
                errors.running_hours = [runningError];
            }
        }

        function initLiveValidation() {
            const form = getForm();
            const container = getContainer();
            if (!form) {
                return;
            }

            const runningInput = form.querySelector('[name="running_hours"]');
            if (runningInput && runningInput.dataset.liveValidationBound !== '1') {
                runningInput.dataset.liveValidationBound = '1';
                runningInput.addEventListener('input', onRunningHoursInput);
                runningInput.addEventListener('change', onRunningHoursInput);

                function onRunningHoursInput() {
                    if (validateRunningHoursValue(runningInput.value, true) === null) {
                        clearFieldError(form, 'running_hours');
                    }
                }
            }

            if (container && container.dataset.quantityValidationBound !== '1') {
                container.dataset.quantityValidationBound = '1';
                function onQuantityInput(event) {
                    const input = event.target;
                    if (!input || !input.matches('input[name*="[quantity]"]')) {
                        return;
                    }

                    const entry = input.closest('.' + entryClass);
                    if (!entry) {
                        return;
                    }

                    const index = entry.getAttribute('data-entry-index');
                    if (index === null || index === '') {
                        return;
                    }

                    const field = 'part_replacement_entries.' + index + '.quantity';
                    if (validateQuantityValue(input.value) === null) {
                        clearFieldError(form, field);
                    }
                }

                container.addEventListener('input', onQuantityInput);
                container.addEventListener('change', onQuantityInput);
            }
        }

        function clearEntries() {
            const container = getContainer();
            if (!container) {
                return;
            }

            container.querySelectorAll('.' + entryClass).forEach(function (entry) {
                destroyEntrySelect2(entry);
                entry.remove();
            });

            entryIndex = 0;
        }

        function clearFeedbackFields() {
            const form = getForm();
            if (!form) {
                return;
            }

            if (config.feedbackRatingGlobal && window[config.feedbackRatingGlobal]) {
                window[config.feedbackRatingGlobal].reset();
            }

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

        function toggle(partReplacedValue) {
            const wrapper = document.getElementById(config.wrapperId || '');
            const form = getForm();
            if (!wrapper || !form) {
                return;
            }

            const entriesMsg = form.querySelector('.validation-msg[data-field="part_replacement_entries"]');
            if (entriesMsg) {
                entriesMsg.textContent = '';
            }

            if (isPartReplacedYes(partReplacedValue)) {
                showCommonHoursFields();
                wrapper.classList.remove('d-none');
                ensureEntry();
                return;
            }

            wrapper.classList.add('d-none');
            clearCommonHoursFields();
            clearEntries();
            clearFeedbackFields();
            wrapper.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
            wrapper.querySelectorAll('.validation-msg').forEach(function (el) {
                el.textContent = '';
            });
        }

        function validate(form) {
            form = form || getForm();
            if (!form || !config.partReplacedSelectId) {
                return null;
            }

            const partReplaced = $('#' + config.partReplacedSelectId).val();
            if (!isPartReplacedYes(partReplaced)) {
                return null;
            }

            const entries = form.querySelectorAll('.' + entryClass);
            const errors = {};

            if (!entries.length) {
                errors.part_replacement_entries = ['At least one Machine Model / Part entry is required when Part Replaced is Yes'];
                validateCommonHours(errors);
                return errors;
            }

            validateCommonHours(errors);

            entries.forEach(function (entry) {
                const index = entry.getAttribute('data-entry-index');
                const modelSelect = entry.querySelector('[name="part_replacement_entries[' + index + '][machine_model_code]"]');
                const modelDesc = entry.querySelector('[name="part_replacement_entries[' + index + '][machine_model]"]');
                const quantity = entry.querySelector('[name="part_replacement_entries[' + index + '][quantity]"]');

                if (!modelSelect || !modelSelect.value || !modelDesc || !modelDesc.value.trim()) {
                    errors['part_replacement_entries.' + index + '.machine_model_code'] = ['Machine Model / Part is required'];
                }

                if (quantity) {
                    const quantityError = validateQuantityValue(quantity.value);
                    if (quantityError) {
                        errors['part_replacement_entries.' + index + '.quantity'] = [quantityError];
                    }
                }
            });

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

        function showErrors(form, errors) {
            form = form || getForm();
            if (!form || !errors) {
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
                    if ($(input).hasClass('part-model-select')) {
                        $(input).next('.select2-container').find('.select2-selection').addClass('is-invalid');
                    }
                }

                if (msg && errors[field] && errors[field].length) {
                    msg.textContent = errors[field][0].replace(/^\^/, '');
                }
            });

            if (errors.customer_feedback) {
                if (config.feedbackRatingGlobal && window[config.feedbackRatingGlobal]) {
                    window[config.feedbackRatingGlobal].setError(errors.customer_feedback[0].replace(/^\^/, ''));
                } else {
                    const feedbackMsg = form.querySelector('.validation-msg[data-field="customer_feedback"]');
                    if (feedbackMsg && errors.customer_feedback.length) {
                        feedbackMsg.textContent = errors.customer_feedback[0].replace(/^\^/, '');
                    }
                }
            }
        }

        function loadEntries(entries, defaults) {
            clearEntries();
            defaults = defaults || {};

            if (!entries || !entries.length) {
                if (isPartReplacedYes($('#' + config.partReplacedSelectId).val())) {
                    createEntry(defaults);
                }
                return;
            }

            entries.forEach(function (entry) {
                createEntry({
                    id: entry.id || '',
                    machine_model_code: entry.machine_model_code || '',
                    machine_model_desc: entry.machine_model || '',
                    quantity: entry.quantity != null ? String(entry.quantity) : ''
                });
            });
        }

        function initControls() {
            const addBtn = document.getElementById(config.addBtnId || '');
            const container = getContainer();

            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    createEntry();
                });
            }

            if (container) {
                container.addEventListener('click', function (event) {
                    const removeBtn = event.target.closest('.remove-part-replacement-entry');
                    if (!removeBtn) {
                        return;
                    }

                    const entry = removeBtn.closest('.' + entryClass);
                    const entries = container.querySelectorAll('.' + entryClass);
                    if (!entry || entries.length <= 1) {
                        return;
                    }

                    destroyEntrySelect2(entry);
                    entry.remove();
                    updateEntryNumbers();
                });
            }

            if (config.partReplacedSelectId) {
                $('#' + config.partReplacedSelectId).on('change select2:select select2:clear', function () {
                    toggle($(this).val());
                });
            }

            initLiveValidation();
        }

        return {
            reset: function () {
                clearEntries();
                const wrapper = document.getElementById(config.wrapperId || '');
                if (wrapper) {
                    wrapper.classList.add('d-none');
                }
                clearCommonHoursFields();
                clearFeedbackFields();
            },
            toggle: toggle,
            validate: validate,
            showErrors: showErrors,
            loadEntries: loadEntries,
            initControls: initControls,
            ensureEntry: ensureEntry
        };
    }

    window.createServiceLogPartReplacementModule = createServiceLogPartReplacementModule;
})(window, jQuery);