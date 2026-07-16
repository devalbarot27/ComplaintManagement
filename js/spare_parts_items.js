(function (window, $) {
    'use strict';

    function createSparePartsItemsModule(config) {
        config = config || {};
        let entryIndex = 0;
        const entryClass = config.entryClass || 'spare-parts-item-entry';
        const reasonOptions = Array.isArray(config.reasonOptions) ? config.reasonOptions : [];

        function getForm() {
            return document.getElementById(config.formId || '');
        }

        function getContainer() {
            return document.getElementById(config.entriesContainerId || '');
        }

        function destroyEntrySelect2(entry) {
            if (!entry) {
                return;
            }

            entry.querySelectorAll('select.spare-parts-reason-select').forEach(function (select) {
                const $select = $(select);
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
            });
        }

        function buildReasonOptionsHtml(selectedValue) {
            let html = '<option value=""></option>';
            reasonOptions.forEach(function (reason) {
                const selected = selectedValue === reason ? ' selected' : '';
                html += '<option value="' + $('<div>').text(reason).html() + '"' + selected + '>'
                    + $('<div>').text(reason).html()
                    + '</option>';
            });
            return html;
        }

        function initReasonSelect2(entry, index) {
            const select = entry.querySelector('.spare-parts-reason-select');
            if (!select || typeof $.fn.select2 === 'undefined') {
                return;
            }

            const $select = $(select);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }

            const select2Options = {
                width: '100%',
                placeholder: 'Search reason',
                allowClear: false
            };

            if (config.dropdownParent) {
                select2Options.dropdownParent = $(config.dropdownParent);
            }

            $select.select2(select2Options);

            $select.on('change select2:select select2:clear', function () {
                $select.removeClass('is-invalid');
                const msg = entry.querySelector('.validation-msg[data-field="spare_parts_items.' + index + '.reason"]');
                if (msg) {
                    msg.textContent = '';
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
                const label = entry.querySelector('.spare-parts-item-number');
                if (label) {
                    label.textContent = String(index + 1);
                }

                const removeBtn = entry.querySelector('.remove-spare-parts-item');
                if (removeBtn) {
                    removeBtn.classList.toggle('d-none', entries.length <= 1);
                }
            });
        }

        function createEntry(defaults) {
            defaults = defaults || {};
            const container = getContainer();
            if (!container) {
                return null;
            }

            const index = entryIndex++;
            const existingId = defaults.id != null && String(defaults.id) !== '' ? String(defaults.id) : '';
            const entry = document.createElement('div');
            entry.className = entryClass + ' border rounded p-3 mb-3';
            entry.setAttribute('data-entry-index', String(index));

            entry.innerHTML = ''
                + (existingId
                    ? '<input type="hidden" name="spare_parts_items[' + index + '][id]" value="' + $('<div>').text(existingId).html() + '">'
                    : '')
                + '<div class="d-flex justify-content-between align-items-center mb-3">'
                + '  <strong>Spare Part <span class="spare-parts-item-number"></span></strong>'
                + '  <button type="button" class="btn btn-sm btn-outline-danger remove-spare-parts-item">'
                + '    <i class="bi bi-trash"></i> Remove'
                + '  </button>'
                + '</div>'
                + '<div class="row g-3">'
                + '  <div class="col-md-3 form-group">'
                + '    <label class="form-label"><i class="bi bi-box-seam"></i> Spare Kit Number <span class="text-danger">*</span></label>'
                + '    <input type="text" class="form-control" name="spare_parts_items[' + index + '][spare_kit_number]" maxlength="100"'
                + '      placeholder="Kit reference" value="' + $('<div>').text(defaults.spare_kit_number || '').html() + '">'
                + '    <div class="text-danger validation-msg" data-field="spare_parts_items.' + index + '.spare_kit_number"></div>'
                + '  </div>'
                + '  <div class="col-md-3 form-group">'
                + '    <label class="form-label"><i class="bi bi-signpost-split"></i> Reason <span class="text-danger">*</span></label>'
                + '    <select class="form-control spare-parts-reason-select" name="spare_parts_items[' + index + '][reason]"'
                + '      data-placeholder="Search reason">'
                + buildReasonOptionsHtml(defaults.reason || '')
                + '    </select>'
                + '    <div class="text-danger validation-msg" data-field="spare_parts_items.' + index + '.reason"></div>'
                + '  </div>'
                + '  <div class="col-md-3 form-group">'
                + '    <label class="form-label"><i class="bi bi-123"></i> Quantity <span class="text-danger">*</span></label>'
                + '    <input type="number" class="form-control" name="spare_parts_items[' + index + '][quantity]" min="0.01" step="0.01"'
                + '      placeholder="Quantity used" value="' + $('<div>').text(defaults.quantity || '').html() + '">'
                + '    <div class="text-danger validation-msg" data-field="spare_parts_items.' + index + '.quantity"></div>'
                + '  </div>'
                + '  <div class="col-md-3 form-group">'
                + '    <label class="form-label"><i class="bi bi-currency-rupee"></i> Order Value <span class="text-danger">*</span></label>'
                + '    <div class="input-group">'
                + '      <span class="input-group-text">₹</span>'
                + '      <input type="number" class="form-control" name="spare_parts_items[' + index + '][order_value]" min="0" step="0.01"'
                + '        placeholder="Cost" value="' + $('<div>').text(defaults.order_value || '').html() + '">'
                + '    </div>'
                + '    <div class="text-danger validation-msg" data-field="spare_parts_items.' + index + '.order_value"></div>'
                + '  </div>'
                + '</div>';

            container.appendChild(entry);
            initReasonSelect2(entry, index);
            updateEntryNumbers();

            return entry;
        }

        function ensureEntry() {
            const container = getContainer();
            if (!container || container.querySelector('.' + entryClass)) {
                return;
            }

            createEntry();
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

        function validate(form) {
            form = form || getForm();
            if (!form) {
                return null;
            }

            const entries = form.querySelectorAll('.' + entryClass);
            const errors = {};

            if (!entries.length) {
                errors.spare_parts_items = ['At least one spare part item is required'];
                return errors;
            }

            entries.forEach(function (entry) {
                const index = entry.getAttribute('data-entry-index');
                const kitInput = entry.querySelector('[name="spare_parts_items[' + index + '][spare_kit_number]"]');
                const reasonSelect = entry.querySelector('[name="spare_parts_items[' + index + '][reason]"]');
                const quantityInput = entry.querySelector('[name="spare_parts_items[' + index + '][quantity]"]');
                const orderValueInput = entry.querySelector('[name="spare_parts_items[' + index + '][order_value]"]');

                if (!kitInput || !kitInput.value.trim()) {
                    errors['spare_parts_items.' + index + '.spare_kit_number'] = ['Spare Kit Number is required'];
                }

                if (!reasonSelect || !reasonSelect.value) {
                    errors['spare_parts_items.' + index + '.reason'] = ['Reason is required'];
                }

                if (quantityInput) {
                    const quantityValue = quantityInput.value.trim();
                    if (!quantityValue) {
                        errors['spare_parts_items.' + index + '.quantity'] = ['Quantity is required'];
                    } else if (!/^-?\d+(\.\d+)?$/.test(quantityValue) || parseFloat(quantityValue) <= 0) {
                        errors['spare_parts_items.' + index + '.quantity'] = ['Quantity must be greater than zero'];
                    }
                }

                if (orderValueInput) {
                    const orderValue = orderValueInput.value.trim();
                    if (!orderValue) {
                        errors['spare_parts_items.' + index + '.order_value'] = ['Order Value is required'];
                    } else if (!/^-?\d+(\.\d+)?$/.test(orderValue) || parseFloat(orderValue) < 0) {
                        errors['spare_parts_items.' + index + '.order_value'] = ['Order Value must be a valid number'];
                    }
                }
            });

            return Object.keys(errors).length ? errors : null;
        }

        function showErrors(form, errors) {
            form = form || getForm();
            if (!form || !errors) {
                return;
            }

            Object.keys(errors).forEach(function (field) {
                if (field === 'spare_parts_items') {
                    const msg = form.querySelector('.validation-msg[data-field="spare_parts_items"]');
                    if (msg && errors[field].length) {
                        msg.textContent = errors[field][0];
                    }
                    return;
                }

                const inputName = field.replace(
                    /^spare_parts_items\.(\d+)\.(\w+)$/,
                    function (_match, index, key) {
                        return 'spare_parts_items[' + index + '][' + key + ']';
                    }
                );
                const input = form.querySelector('[name="' + inputName + '"]');
                const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');

                if (input) {
                    input.classList.add('is-invalid');
                    if ($(input).hasClass('spare-parts-reason-select')) {
                        $(input).next('.select2-container').find('.select2-selection').addClass('is-invalid');
                    }
                }

                if (msg && errors[field] && errors[field].length) {
                    msg.textContent = errors[field][0].replace(/^\^/, '');
                }
            });
        }

        function loadEntries(items) {
            clearEntries();

            if (!items || !items.length) {
                createEntry();
                return;
            }

            items.forEach(function (item) {
                createEntry({
                    id: item.id || '',
                    spare_kit_number: item.spare_kit_number || '',
                    reason: item.reason || '',
                    quantity: item.quantity != null ? String(item.quantity) : '',
                    order_value: item.order_value != null ? String(item.order_value) : ''
                });
            });
        }

        function setAddEnabled(enabled) {
            const wrapper = document.getElementById(config.addButtonWrapperId || '');
            const addBtn = document.getElementById(config.addBtnId || '');
            if (wrapper) {
                wrapper.classList.toggle('d-none', !enabled);
            }
            if (addBtn) {
                addBtn.disabled = !enabled;
            }
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
                    const removeBtn = event.target.closest('.remove-spare-parts-item');
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
        }

        return {
            reset: function () {
                clearEntries();
                setAddEnabled(true);
            },
            ensureEntry: ensureEntry,
            loadEntries: loadEntries,
            validate: validate,
            showErrors: showErrors,
            initControls: initControls,
            setAddEnabled: setAddEnabled
        };
    }

    window.createSparePartsItemsModule = createSparePartsItemsModule;
})(window, jQuery);
