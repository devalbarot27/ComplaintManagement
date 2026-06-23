function showServiceLogPageAlert(type, message) {
    const content = document.querySelector('.content');
    if (!content || !message) {
        return;
    }

    content.querySelectorAll('.service-log-ajax-alert').forEach(function (el) {
        el.remove();
    });

    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const wrapper = document.createElement('div');
    wrapper.className = 'alert ' + alertClass + ' alert-dismissible fade show mb-3 service-log-ajax-alert';
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

function formatSparePartsDisplayDate(value) {
    if (!value) {
        return '';
    }

    const parts = String(value).split('-');
    if (parts.length !== 3) {
        return value;
    }

    const date = new Date(parts[0], parts[1] - 1, parts[2]);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

function resetServiceLogSparePartsForm() {
    const form = document.getElementById('serviceLogSparePartsForm');
    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('slSparePartsServiceLogId').value = '';
    document.getElementById('slSparePartsServiceLogLabel').value = '';
    document.getElementById('slSparePartsInstalledBaseId').value = '';
    document.getElementById('slSparePartsInstalledBaseLabel').value = '';
    document.getElementById('slSparePartsCustomerName').value = '';
    document.getElementById('slSparePartsMachineModel').value = '';
    document.getElementById('slSparePartsComplaintDate').value = '';
    document.getElementById('slSparePartsVisitDate').value = '';
    document.getElementById('slSparePartsClosureDate').value = '';
    document.getElementById('slSparePartsEngineerName').value = '';
    document.getElementById('slSparePartsIssueDescription').value = '';
    document.getElementById('slSparePartsActionTaken').value = '';

    resetStaticSelect2Fields([
        'slSparePartsWarrantySelect',
        'slSparePartsReasonSelect'
    ]);

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function fillServiceLogSparePartsForm(data) {
    const form = document.getElementById('serviceLogSparePartsForm');
    if (!form || !data) {
        return;
    }

    document.getElementById('slSparePartsServiceLogId').value = data.service_log_id || '';
    document.getElementById('slSparePartsServiceLogLabel').value = data.service_log_label || '';
    document.getElementById('slSparePartsInstalledBaseId').value = data.installed_base_id || '';
    document.getElementById('slSparePartsInstalledBaseLabel').value = data.installed_base_label || '';
    document.getElementById('slSparePartsCustomerName').value = data.customer_name || '';
    document.getElementById('slSparePartsMachineModel').value = data.machine_model || '';
    document.getElementById('slSparePartsComplaintDate').value = formatSparePartsDisplayDate(data.complaint_date);
    document.getElementById('slSparePartsVisitDate').value = formatSparePartsDisplayDate(data.visit_date);
    document.getElementById('slSparePartsClosureDate').value = formatSparePartsDisplayDate(data.closure_date);
    document.getElementById('slSparePartsEngineerName').value = data.engineer_name || '';
    document.getElementById('slSparePartsIssueDescription').value = data.issue_description || '';
    document.getElementById('slSparePartsActionTaken').value = data.action_taken || '';

    ['order_id', 'fab_number', 'serial_number', 'running_hours'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = data[field] ?? '';
        }
    });

    if (data.warranty_chargeable) {
        setStaticSelect2Value('slSparePartsWarrantySelect', data.warranty_chargeable);
    }
}

function initServiceLogSparePartsSelect2() {
    const $modal = $('#serviceLogSparePartsModal');
    const dropdownParent = $modal.length ? $modal : null;
    const select2Options = dropdownParent ? { dropdownParent: dropdownParent } : {};

    initStaticSelect2Fields('serviceLogSparePartsForm', [
        Object.assign({
            selectId: 'slSparePartsWarrantySelect',
            validationField: 'warranty_chargeable',
            allowClear: false,
            noResultsText: 'No warranty type found'
        }, select2Options),
        Object.assign({
            selectId: 'slSparePartsReasonSelect',
            validationField: 'reason',
            allowClear: false,
            noResultsText: 'No reason found'
        }, select2Options)
    ]);
}

function initServiceLogSparePartsValidation() {
    const form = document.getElementById('serviceLogSparePartsForm');

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
        spare_kit_number: {
            presence: {
                allowEmpty: false,
                message: '^Spare Kit Number is required'
            }
        },
        quantity: {
            presence: {
                allowEmpty: false,
                message: '^Quantity is required'
            },
            numericality: {
                greaterThan: 0,
                message: '^Quantity must be greater than zero'
            }
        },
        order_value: {
            presence: {
                allowEmpty: false,
                message: '^Order Value is required'
            },
            numericality: {
                greaterThanOrEqualTo: 0,
                message: '^Order Value must be a valid number'
            }
        },
        reason: {
            presence: {
                allowEmpty: false,
                message: '^Reason is required'
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
        e.preventDefault();

        if (isSubmitting) {
            return;
        }

        const errors = validate(form, constraints);
        showErrors(errors);

        if (errors) {
            return;
        }

        isSubmitting = true;
        const submitButton = document.getElementById('submitSlSparePartsBtn');
        if (submitButton) {
            submitButton.classList.add('disabled_btn');
        }

        $.ajax({
            url: 'api/spare_parts_create.php',
            type: 'POST',
            data: $(form).serialize(),
            dataType: 'json'
        })
            .done(function (response) {
                const modalEl = document.getElementById('serviceLogSparePartsModal');
                const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;

                if (modal) {
                    modal.hide();
                }

                resetServiceLogSparePartsForm();

                if (window.serviceLogTable) {
                    window.serviceLogTable.ajax.reload(null, false);
                }

                if (response && response.message) {
                    showServiceLogPageAlert('success', response.message);
                }
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Failed to save spare parts record.';
                showServiceLogPageAlert('error', message);
            })
            .always(function () {
                isSubmitting = false;
                if (submitButton) {
                    submitButton.classList.remove('disabled_btn');
                }
            });
    });
}

function initServiceLogSparePartsModal() {
    const modalEl = document.getElementById('serviceLogSparePartsModal');
    if (!modalEl) {
        return;
    }

    initServiceLogSparePartsSelect2();
    initServiceLogSparePartsValidation();

    modalEl.addEventListener('hidden.bs.modal', function () {
        resetServiceLogSparePartsForm();
    });

    $(document).on('click', '.add-spare-parts-btn', function () {
        const id = $(this).data('id');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        resetServiceLogSparePartsForm();

        $.getJSON('api/service_log_spare_parts_prefill.php', { id: id })
            .done(function (data) {
                fillServiceLogSparePartsForm(data);
                modal.show();
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Unable to load service log details.';
                showServiceLogPageAlert('error', message);
            });
    });
}
