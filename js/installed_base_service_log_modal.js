function showInstalledBasePageAlert(type, message) {
    const content = document.querySelector('.content');
    if (!content || !message) {
        return;
    }

    content.querySelectorAll('.installed-base-ajax-alert').forEach(function (el) {
        el.remove();
    });

    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const wrapper = document.createElement('div');
    wrapper.className = 'alert ' + alertClass + ' alert-dismissible fade show mb-3 installed-base-ajax-alert';
    wrapper.setAttribute('role', 'alert');

    const text = document.createElement('span');
    text.textContent = message;
    wrapper.appendChild(text);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('data-bs-dismiss', 'alert');
    wrapper.appendChild(closeBtn);

    content.insertBefore(wrapper, content.firstChild);

    if (type === 'success') {
        setTimeout(function () {
            $(wrapper).fadeOut(function () {
                wrapper.remove();
            });
        }, 3000);
    }
}

function resetInstalledBaseServiceLogForm() {
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('ibServiceLogInstalledBaseId').value = '';
    document.getElementById('ibServiceLogInstalledBaseLabel').value = '';

    resetStaticSelect2Fields([
        'ibServiceLogWarrantySelect',
        'ibServiceLogPartReplacedSelect',
        'ibServiceLogFeedbackSelect'
    ]);

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function fillInstalledBaseServiceLogForm(data) {
    const form = document.getElementById('installedBaseServiceLogForm');
    if (!form || !data) {
        return;
    }

    document.getElementById('ibServiceLogInstalledBaseId').value = data.installed_base_id || '';
    document.getElementById('ibServiceLogInstalledBaseLabel').value = data.installed_base_label || '';

    const fields = ['order_id', 'fab_number', 'machine_model', 'running_hours'];
    fields.forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = data[field] ?? '';
        }
    });
}

function initInstalledBaseServiceLogSelect2() {
    const $modal = $('#installedBaseServiceLogModal');
    const dropdownParent = $modal.length ? $modal : null;
    const select2Options = dropdownParent ? { dropdownParent: dropdownParent } : {};

    initStaticSelect2Fields('installedBaseServiceLogForm', [
        Object.assign({
            selectId: 'ibServiceLogWarrantySelect',
            validationField: 'warranty_chargeable',
            allowClear: false,
            noResultsText: 'No service type found'
        }, select2Options),
        Object.assign({
            selectId: 'ibServiceLogPartReplacedSelect',
            validationField: 'part_replaced',
            allowClear: false,
            noResultsText: 'No option found'
        }, select2Options),
        Object.assign({
            selectId: 'ibServiceLogFeedbackSelect',
            validationField: 'customer_feedback',
            allowClear: true,
            noResultsText: 'No feedback option found'
        }, select2Options)
    ]);
}

function initInstalledBaseServiceLogValidation() {
    const form = document.getElementById('installedBaseServiceLogForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    function consumableHoursConstraint(label) {
        return {
            numericality: {
                greaterThanOrEqualTo: 0,
                allowEmpty: true,
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
        },
        separator_remaining_hours: consumableHoursConstraint('Separator'),
        air_filter_remaining_hours: consumableHoursConstraint('Air Filter'),
        oil_filter_remaining_hours: consumableHoursConstraint('Oil Filter'),
        oil_remaining_hours: consumableHoursConstraint('Oil'),
        valve_kit_remaining_hours: consumableHoursConstraint('Valve Kit'),
        grease_remaining_hours: consumableHoursConstraint('Grease')
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
        e.preventDefault();

        if (isSubmitting) {
            return;
        }

        const errors = validate(form, constraints);
        showErrors(errors);

        if (errors) {
            return;
        }

        const complaintDate = form.querySelector('[name="complaint_date"]').value;
        const visitDate = form.querySelector('[name="visit_date"]').value;
        const closureDate = form.querySelector('[name="closure_date"]').value;

        if (visitDate && complaintDate && visitDate < complaintDate) {
            showErrors({ visit_date: ['Visit Date cannot be earlier than Complaint Date'] });
            return;
        }

        if (closureDate && visitDate && closureDate < visitDate) {
            showErrors({ closure_date: ['Closure Date cannot be earlier than Visit Date'] });
            return;
        }

        isSubmitting = true;
        const submitButton = document.getElementById('submitIbServiceLogBtn');
        if (submitButton) {
            submitButton.classList.add('disabled_btn');
        }

        $.ajax({
            url: 'api/service_log_create.php',
            type: 'POST',
            data: $(form).serialize(),
            dataType: 'json'
        })
            .done(function () {
                window.location.reload();
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Failed to save service log.';
                showInstalledBasePageAlert('error', message);
            })
            .always(function () {
                isSubmitting = false;
                if (submitButton) {
                    submitButton.classList.remove('disabled_btn');
                }
            });
    });
}

function initInstalledBaseServiceLogModal() {
    const modalEl = document.getElementById('installedBaseServiceLogModal');
    if (!modalEl) {
        return;
    }

    initInstalledBaseServiceLogSelect2();
    initInstalledBaseServiceLogValidation();

    modalEl.addEventListener('hidden.bs.modal', function () {
        resetInstalledBaseServiceLogForm();
    });

    $(document).on('click', '.add-service-log-btn', function () {
        const id = $(this).data('id');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        resetInstalledBaseServiceLogForm();

        $.getJSON('api/installed_base_service_log_prefill.php', { id: id })
            .done(function (data) {
                fillInstalledBaseServiceLogForm(data);
                modal.show();
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Unable to load installed base details.';
                showInstalledBasePageAlert('error', message);
            });
    });
}
