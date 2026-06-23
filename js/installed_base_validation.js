function initInstalledBaseFormValidation() {
    const form = document.getElementById('installedBaseForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        order_ref_id: {
            presence: {
                allowEmpty: false,
                message: '^Order ID is required'
            }
        },
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
                greaterThanOrEqualTo: 0,
                message: '^Running Hours must be a valid number'
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

    form.querySelectorAll('input, textarea, select').forEach(function (input) {
        if (!constraints[input.name]) {
            return;
        }

        const eventName = input.tagName === 'SELECT' ? 'change' : 'input';

        input.addEventListener(eventName, function () {
            if (input.name === 'mobile') {
                input.value = input.value.replace(/\D/g, '');
            }

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
        if (isSubmitting) {
            e.preventDefault();
            return;
        }

        const errors = validate(form, constraints);
        showErrors(errors);

        if (errors) {
            e.preventDefault();
            return;
        }

        isSubmitting = true;
        const submitButton = form.querySelector('[name="submit_installed_base"]');
        if (submitButton) {
            submitButton.classList.add('disabled_btn');
        }
    });

    form.addEventListener('reset', function () {
        clearValidationState();
        resetOrderSelect2(form);
        resetFabNumberSelect2();
        resetPincodeSelect2(form, 'installedBasePincodeSelect');
        resetStaticSelect2('industrySegmentSelect');
        resetMachineModelSelect2();
    });
}
