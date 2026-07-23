function initInstalledBaseFormValidation() {
    const form = document.getElementById('installedBaseForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    const FAB_OWNERSHIP_ERROR = 'This FAB Number is already assigned to another user and cannot be used.';

    const constraints = {
        fab_number: {
            presence: {
                allowEmpty: false,
                message: '^Fab Number is required'
            }
        },
        customer_name: {
            presence: {
                allowEmpty: false,
                message: '^Customer Name is required'
            }
        },
        street_1: {
            presence: {
                allowEmpty: false,
                message: '^Street 1 is required'
            }
        },
        street_2: {
            length: {
                maximum: 255,
                message: '^Street 2 cannot exceed 255 characters'
            }
        },
        pincode: {
            presence: {
                allowEmpty: false,
                message: '^Pincode is required'
            },
            format: {
                pattern: /^\d{6}$/,
                message: '^Pincode must be a 6-digit number'
            }
        },
        city: {
            presence: {
                allowEmpty: false,
                message: '^City is required'
            }
        },
        district: {
            presence: {
                allowEmpty: false,
                message: '^District is required'
            }
        },
        state: {
            presence: {
                allowEmpty: false,
                message: '^State is required'
            }
        },
        mobile: {
            presence: {
                allowEmpty: false,
                message: '^Mobile is required'
            },
            format: {
                pattern: /^[1-9]\d{9}$/,
                message: '^Mobile must be a valid 10-digit number'
            }
        },
        email: {
            presence: {
                allowEmpty: false,
                message: '^Email is required'
            },
            email: {
                message: '^Email must be a valid email address'
            }
        },
        dealer_name: {
            presence: {
                allowEmpty: false,
                message: '^Dealer Name is required'
            }
        },
        machine_model_code: {
            presence: {
                allowEmpty: false,
                message: '^Machine Model is required'
            }
        },
        invoice_date: {
            presence: {
                allowEmpty: false,
                message: '^Invoice Date is required'
            }
        },
        commissioning_date: {
            presence: {
                allowEmpty: false,
                message: '^Commissioning Date is required'
            }
        },
        running_hours: {
            presence: {
                allowEmpty: false,
                message: '^Running Hours is required'
            },
            numericality: {
                greaterThan: 0,
                message: '^Running Hours must be greater than 0'
            }
        },
        industry_segment: {
            presence: {
                allowEmpty: false,
                message: '^Industry Segment is required'
            }
        },
        remarks: {
            length: {
                maximum: 1000,
                message: '^Remarks cannot exceed 1000 characters'
            }
        }
    };

    let isSubmitting = false;
    let fabAvailabilityXhr = null;
    let fabOwnershipError = '';

    function clearValidationState() {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.validation-msg').forEach(function (el) {
            el.textContent = '';
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

            if (field === 'pincode') {
                $('#installedBasePincodeSelect').addClass('is-invalid');
            }

            if (field === 'fab_number') {
                $('#fabNumberSelect').addClass('is-invalid');
            }

            if (field === 'machine_model_code') {
                $('#machineModelSelect').addClass('is-invalid');
            }

            if (field === 'order_ref_id') {
                $('#orderIdSelect').addClass('is-invalid');
            }

            if (field === 'industry_segment') {
                $('#industrySegmentSelect').addClass('is-invalid');
            }

            if (msg && errors[field] && errors[field].length) {
                msg.textContent = errors[field][0];
            }
        });
    }

    function mergeFabOwnershipError(errors) {
        if (!fabOwnershipError) {
            return errors || null;
        }

        const merged = errors ? Object.assign({}, errors) : {};
        merged.fab_number = [fabOwnershipError];
        return merged;
    }

    function setFabOwnershipError(message) {
        fabOwnershipError = String(message || FAB_OWNERSHIP_ERROR).trim() || FAB_OWNERSHIP_ERROR;
        showErrors({ fab_number: [fabOwnershipError] });
    }

    function clearFabOwnershipError() {
        fabOwnershipError = '';
        const msg = form.querySelector('.validation-msg[data-field="fab_number"]');
        $('#fabNumberSelect').removeClass('is-invalid');
        if (msg) {
            msg.textContent = '';
        }
    }

    // Shared with FAB prefill so blocked FABs show the same field error.
    window.setInstalledBaseFabOwnershipError = setFabOwnershipError;
    window.clearInstalledBaseFabOwnershipError = clearFabOwnershipError;

    function getEditingRecordId() {
        const idInput = document.getElementById('installedBaseId');
        return idInput ? (parseInt(idInput.value, 10) || 0) : 0;
    }

    function getSelectedFabNumber() {
        const fabInput = form.querySelector('[name="fab_number"]');
        return fabInput ? String(fabInput.value || '').trim() : '';
    }

    /**
     * Check FAB ownership (trimmed, case-insensitive on server).
     * - New FAB => allow
     * - Current user's FAB / editing same record => allow
     * - Another user's FAB => block with ownership message
     */
    function validateFabOwnership(fabNumber) {
        fabNumber = String(fabNumber || '').trim();

        if (!fabNumber) {
            clearFabOwnershipError();
            return Promise.resolve(true);
        }

        if (typeof $ === 'undefined') {
            return Promise.resolve(true);
        }

        if (fabAvailabilityXhr && typeof fabAvailabilityXhr.abort === 'function') {
            fabAvailabilityXhr.abort();
        }

        const requestData = {
            fab_number: fabNumber,
            record_id: getEditingRecordId()
        };

        return new Promise(function (resolve) {
            fabAvailabilityXhr = $.ajax({
                url: 'api/installed_base_fab_availability.php',
                data: requestData,
                dataType: 'json'
            });

            fabAvailabilityXhr.done(function (response) {
                if (response && response.available === false) {
                    const message = (response.message && String(response.message).trim())
                        ? String(response.message).trim()
                        : FAB_OWNERSHIP_ERROR;
                    setFabOwnershipError(message);
                    resolve(false);
                    return;
                }

                clearFabOwnershipError();
                resolve(true);
            });

            fabAvailabilityXhr.fail(function (_xhr, status) {
                // Aborted checks must not block a later submit/select check.
                if (status === 'abort') {
                    resolve(true);
                    return;
                }
                clearFabOwnershipError();
                resolve(true);
            });
        });
    }

    function ensureSubmitInstalledBaseFlag() {
        let flag = form.querySelector('input[type="hidden"][name="submit_installed_base"]');
        if (!flag) {
            flag = document.createElement('input');
            flag.type = 'hidden';
            flag.name = 'submit_installed_base';
            flag.value = '1';
            form.appendChild(flag);
        }
        flag.value = '1';
    }

    function collectValidationValues() {
        const values = validate.collectFormValues(form);

        // Disabled Select2 fields are skipped by validate.js; use locked hidden value.
        const lockedMachineModel = document.getElementById('machineModelCodeLocked');
        if (lockedMachineModel && String(lockedMachineModel.value || '').trim() !== '') {
            values.machine_model_code = String(lockedMachineModel.value).trim();
        }

        return values;
    }

    form.querySelectorAll('input, textarea, select').forEach(function (input) {
        if (!constraints[input.name]) {
            return;
        }

        const eventName = input.tagName === 'SELECT' ? 'change' : 'input';

        input.addEventListener(eventName, function () {
            if (input.name === 'mobile') {
                input.value = input.value.replace(/\D/g, '');
            }

            if (input.name === 'fab_number') {
                clearFabOwnershipError();
            }

            const fieldErrors = validate.single(input.value, constraints[input.name]);
            const msg = form.querySelector('.validation-msg[data-field="' + input.name + '"]');

            if (input.name === 'fab_number') {
                $('#fabNumberSelect').toggleClass('is-invalid', !!fieldErrors);
            } else {
                input.classList.toggle('is-invalid', !!fieldErrors);
            }

            if (msg) {
                msg.textContent = fieldErrors ? fieldErrors[0] : '';
            }

            if (input.name === 'fab_number' && !fieldErrors) {
                validateFabOwnership(String(input.value || '').trim());
            }
        });
    });

    $('#fabNumberSelect').on('select2:select', function (e) {
        const fabNumber = (e.params && e.params.data && e.params.data.id)
            ? String(e.params.data.id).trim()
            : getSelectedFabNumber();

        clearFabOwnershipError();
        validateFabOwnership(fabNumber);
    });

    $('#fabNumberSelect').on('change', function () {
        const fabNumber = getSelectedFabNumber();
        if (!fabNumber) {
            clearFabOwnershipError();
            return;
        }
        validateFabOwnership(fabNumber);
    });

    $('#fabNumberSelect').on('select2:clear', function () {
        clearFabOwnershipError();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (isSubmitting) {
            return;
        }

        const errors = mergeFabOwnershipError(validate(collectValidationValues(), constraints));
        showErrors(errors);

        if (errors) {
            return;
        }

        const submitButton = form.querySelector('button[name="submit_installed_base"]');
        if (submitButton) {
            submitButton.classList.add('disabled_btn');
        }

        validateFabOwnership(getSelectedFabNumber()).then(function (isAvailable) {
            if (!isAvailable) {
                if (submitButton) {
                    submitButton.classList.remove('disabled_btn');
                }
                showErrors(mergeFabOwnershipError(null));
                return;
            }

            isSubmitting = true;
            // Native form.submit() omits the clicked submit button from POST.
            ensureSubmitInstalledBaseFlag();
            HTMLFormElement.prototype.submit.call(form);
        });
    });

    form.addEventListener('reset', function () {
        fabOwnershipError = '';
        clearValidationState();
        resetOrderSelect2(form);
        resetFabNumberSelect2();
        resetPincodeSelect2(form, 'installedBasePincodeSelect');
        resetStaticSelect2('industrySegmentSelect');
        resetMachineModelSelect2();
    });
}