function initServiceLogFormValidation() {
    const form = document.getElementById('serviceLogForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    function consumableDateConstraint(label) {
        return {
            presence: {
                allowEmpty: false,
                message: '^' + label + ' Remaining Date is required'
            }
        };
    }

    function consumableHoursConstraint(label) {
        return {
            presence: {
                allowEmpty: false,
                message: '^' + label + ' Remaining Hours is required'
            },
            numericality: {
                greaterThanOrEqualTo: 0,
                message: '^' + label + ' Remaining Hours must be a valid number'
            }
        };
    }

    const constraints = {
        installed_base_id: {
            presence: {
                allowEmpty: false,
                message: '^Installed base record is required'
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
        machine_model: {
            presence: {
                allowEmpty: false,
                message: '^Machine Model is required'
            }
        },
        warranty_chargeable: {
            presence: {
                allowEmpty: false,
                message: '^Warranty / Chargeable is required'
            }
        },
        complaint_date: {
            presence: {
                allowEmpty: false,
                message: '^Log Date is required'
            }
        },
        issue_description: {
            presence: {
                allowEmpty: false,
                message: '^Issue / Service Description is required'
            }
        },
        engineer_name: {
            presence: {
                allowEmpty: false,
                message: '^Engineer Name is required'
            }
        },
        visit_date: {
            presence: {
                allowEmpty: false,
                message: '^Visit Date is required'
            }
        },
        action_taken: {
            presence: {
                allowEmpty: false,
                message: '^Action Taken is required'
            }
        },
        closure_date: {
            presence: {
                allowEmpty: false,
                message: '^Closure Date is required to complete the service log'
            }
        },
        part_replaced: {
            presence: {
                allowEmpty: false,
                message: '^Part Replaced is required'
            }
        },
        separator_remaining_date: consumableDateConstraint('Separator'),
        separator_remaining_hours: consumableHoursConstraint('Separator'),
        air_filter_remaining_date: consumableDateConstraint('Air Filter'),
        air_filter_remaining_hours: consumableHoursConstraint('Air Filter'),
        oil_filter_remaining_date: consumableDateConstraint('Oil Filter'),
        oil_filter_remaining_hours: consumableHoursConstraint('Oil Filter'),
        oil_remaining_date: consumableDateConstraint('Oil'),
        oil_remaining_hours: consumableHoursConstraint('Oil'),
        valve_kit_remaining_date: consumableDateConstraint('Valve Kit'),
        valve_kit_remaining_hours: consumableHoursConstraint('Valve Kit'),
        grease_remaining_date: consumableDateConstraint('Grease'),
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

            if (msg && errors[field] && errors[field].length) {
                msg.textContent = errors[field][0];
            }
        });

        if (window.slPartReplacementModule) {
            window.slPartReplacementModule.showErrors(form, errors);
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
        const partErrors = window.slPartReplacementModule
            ? (window.slPartReplacementModule.validate(form) || {})
            : {};
        const errors = Object.assign({}, baseErrors, partErrors);

        showErrors(Object.keys(errors).length ? errors : null);

        if (Object.keys(errors).length) {
            e.preventDefault();
            return;
        }

        const complaintDate = form.querySelector('[name="complaint_date"]').value;
        const visitDate = form.querySelector('[name="visit_date"]').value;
        const closureDate = form.querySelector('[name="closure_date"]').value;

        if (visitDate && complaintDate && visitDate < complaintDate) {
            e.preventDefault();
            showErrors({ visit_date: ['Visit Date cannot be earlier than Log Date'] });
            return;
        }

        if (closureDate && visitDate && closureDate < visitDate) {
            e.preventDefault();
            showErrors({ closure_date: ['Closure Date cannot be earlier than Visit Date'] });
            return;
        }

        isSubmitting = true;
        const submitButton = form.querySelector('[name="submit_service_log"]');
        if (submitButton) {
            submitButton.classList.add('disabled_btn');
        }
    });

    form.addEventListener('reset', function () {
        clearValidationState();
        resetInstalledBaseLinkSelect2(form);
        resetStaticSelect2Fields([
            'serviceLogWarrantySelect',
            'serviceLogPartReplacedSelect'
        ]);

        if (window.serviceLogFeedbackRating) {
            window.serviceLogFeedbackRating.reset();
        }
    });
}