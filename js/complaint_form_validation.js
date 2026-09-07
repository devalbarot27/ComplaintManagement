function initComplaintFormValidation() {
    const form = document.getElementById('complaintForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        fab_number: {
            presence: {
                allowEmpty: false,
                message: '^Fab Number is required'
            }
        },
        customer_id: {
            presence: {
                allowEmpty: false,
                message: '^Customer is required'
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
            if (field === 'customer_id') {
                $('#complaintCustomerSelect').addClass('is-invalid');
                $('#complaintCustomerSelect').next('.select2-container').find('.select2-selection').addClass('is-invalid');
            }
            if (msg && errors[field] && errors[field][0]) {
                msg.textContent = errors[field][0];
            }
        });
    }

    form.addEventListener('submit', function (event) {
        const errors = validate(form, constraints);
        if (errors) {
            event.preventDefault();
            showErrors(errors);
        }
    });
}
