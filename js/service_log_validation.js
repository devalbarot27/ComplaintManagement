function initServiceLogFormValidation() {
    const form = document.getElementById('serviceLogForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        installed_base_id: {
            presence: {
                allowEmpty: false,
                message: '^Installed base record is required'
            }
        },
        order_id: {
            presence: {
                allowEmpty: false,
                message: '^Order ID is required'
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
                message: '^Complaint Date is required'
            }
        },
        issue_description: {
            presence: {
                allowEmpty: false,
                message: '^Issue Description is required'
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
        loaded_hours: {
            presence: {
                allowEmpty: false,
                message: '^Loaded Hours is required'
            },
            numericality: {
                greaterThanOrEqualTo: 0,
                message: '^Loaded Hours must be a valid number'
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

        const complaintDate = form.querySelector('[name="complaint_date"]').value;
        const visitDate = form.querySelector('[name="visit_date"]').value;
        const closureDate = form.querySelector('[name="closure_date"]').value;

        if (visitDate && complaintDate && visitDate < complaintDate) {
            e.preventDefault();
            showErrors({ visit_date: ['Visit Date cannot be earlier than Complaint Date'] });
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
    });
}
