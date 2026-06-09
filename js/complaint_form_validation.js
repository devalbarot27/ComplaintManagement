function initComplaintFormValidation() {
    const form = document.getElementById('complaintForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        fab_number: {
            presence: {
                allowEmpty: false,
                //message: 'Fab Number is required'
            }
        },
        customer_name: {
            presence: {
                allowEmpty: false,
               // message: 'Customer Name is required'
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
        complaint_description: {
            presence: {
                allowEmpty: false,
                message: '^Complaint Description is required'
            }
        },
        remarks: {
            length: {
                maximum: 500,
                message: '^Remarks cannot exceed 500 characters'
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
            if (input.name === 'fab_number') {
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

    form.addEventListener('submit', function (e) {
        const errors = validate(form, constraints);
        showErrors(errors);

        if (errors) {
            e.preventDefault();
        }
    });

    form.addEventListener('reset', function () {
        clearValidationState();
    });
}
