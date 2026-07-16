function initSparePartsFormValidation() {
    const form = document.getElementById('sparePartsForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        installed_base_id: {
            presence: {
                allowEmpty: false,
                message: '^Machine selection is required'
            }
        },
        order_id: {
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
        serial_number: {
            presence: {
                allowEmpty: false,
                message: '^Serial Number is required'
            }
        },
        consumption_date: {
            presence: {
                allowEmpty: false,
                message: '^Consumption Date is required'
            }
        },
        warranty_chargeable: {
            presence: {
                allowEmpty: false,
                message: '^Warranty / Chargeable is required'
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
            if (field.indexOf('spare_parts_items') === 0) {
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

        if (window.sparePartsItemsModule) {
            window.sparePartsItemsModule.showErrors(form, errors);
        }
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
        if (isSubmitting) {
            e.preventDefault();
            return;
        }

        const baseErrors = validate(form, constraints) || {};
        const itemErrors = window.sparePartsItemsModule
            ? (window.sparePartsItemsModule.validate(form) || {})
            : {};
        const errors = Object.assign({}, baseErrors, itemErrors);

        showErrors(Object.keys(errors).length ? errors : null);

        if (Object.keys(errors).length) {
            e.preventDefault();
            return;
        }

        isSubmitting = true;
        const submitButton = form.querySelector('[name="submit_spare_parts"]');
        if (submitButton) {
            submitButton.classList.add('disabled_btn');
        }
    });

    form.addEventListener('reset', function () {
        clearValidationState();
        resetSparePartsMachineSelect2(form);
        resetStaticSelect2Fields(['sparePartsWarrantySelect']);
    });
}
