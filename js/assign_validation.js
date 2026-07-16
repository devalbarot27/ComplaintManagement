function initAssignValidation() {
    const form = document.getElementById('assignComplaintForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        assign_complaint: {
            presence: {
                allowEmpty: false,
                message: '^Please select Assign To option'
            }
        },
        assign_complaint_datetime: {
            presence: {
                allowEmpty: false,
                message: '^Please select Assign Date Time'
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

function resetAssignForm(complaintId) {
    const form = document.getElementById('assignComplaintForm');

    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('assignComplaintId').value = complaintId;
    setCurrentDateTimeInput(form.querySelector('[name="assign_complaint_datetime"]'));

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}
