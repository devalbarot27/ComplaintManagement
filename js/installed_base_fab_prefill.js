function resetInstalledBaseFabRecordFields(form) {
    if (!form) {
        return;
    }

    ['commissioning_date', 'running_hours', 'remarks'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = '';
            input.classList.remove('is-invalid');
        }

        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    if (typeof resetStaticSelect2 === 'function') {
        resetStaticSelect2('industrySegmentSelect');
    }
}

function setInstalledBaseFabRecordFields(form, data) {
    if (!form || !data || !data.has_installed_base) {
        resetInstalledBaseFabRecordFields(form);
        return;
    }

    ['commissioning_date', 'running_hours', 'remarks'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (!input) {
            return;
        }

        input.value = data[field] != null ? String(data[field]) : '';
        input.classList.remove('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    if (typeof setStaticSelect2Value === 'function') {
        setStaticSelect2Value('industrySegmentSelect', data.industry_segment || '');
    }
}

function resetInstalledBaseFabAutoFields(form) {
    if (!form) {
        return;
    }

    ['customer_name', 'street_1', 'street_2', 'mobile', 'email'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = '';
            input.classList.remove('is-invalid');
        }

        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    resetPincodeSelect2(form, 'installedBasePincodeSelect');
    resetInstalledBaseFabRecordFields(form);

    if (typeof resetMachineModelSelect2 === 'function') {
        resetMachineModelSelect2();
    }
}

function setInstalledBaseFabAutoFields(form, data) {
    if (!form || !data) {
        return;
    }

    ['customer_name', 'street_1', 'street_2', 'mobile', 'email'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (!input) {
            return;
        }

        input.value = data[field] != null ? String(data[field]) : '';
        input.classList.remove('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    setPincodeSelect2(form, 'installedBasePincodeSelect', data);
    setInstalledBaseFabRecordFields(form, data);

    // Machine Model: only auto-fill + lock when FAB already exists in Installed Base.
    if (data.has_installed_base) {
        const machineModelCode = String(data.machine_model_code || '').trim();
        const machineModelDesc = String(data.machine_model || '').trim();
        if (typeof setMachineModelSelect2 === 'function') {
            setMachineModelSelect2(machineModelCode, machineModelDesc, { locked: true });
        }
    } else if (typeof setMachineModelSelect2Locked === 'function') {
        setMachineModelSelect2Locked(false);
    }
}

function getInstalledBaseEditingRecordId() {
    const idInput = document.getElementById('installedBaseId');
    return idInput ? (parseInt(idInput.value, 10) || 0) : 0;
}

function applyInstalledBaseFabOwnershipBlocked(form, message) {
    resetInstalledBaseFabAutoFields(form);

    if (typeof setInstalledBaseInvoiceDate === 'function') {
        setInstalledBaseInvoiceDate(form, '');
    }

    if (typeof window.setInstalledBaseFabOwnershipError === 'function') {
        window.setInstalledBaseFabOwnershipError(message);
        return;
    }

    const msg = form.querySelector('.validation-msg[data-field="fab_number"]');
    $('#fabNumberSelect').addClass('is-invalid');
    if (msg) {
        msg.textContent = message
            || 'This FAB Number is already assigned to another user and cannot be used.';
    }
}

/**
 * Prefill Installed Base fields from FAB / complaint.
 * Returns a jQuery promise resolving to { allowed: boolean, response: object|null }.
 */
function prefillInstalledBaseFromFab(form, fabNumber, complaintId) {
    if (!form) {
        return $.Deferred().resolve({ allowed: false, response: null }).promise();
    }

    fabNumber = String(fabNumber || '').trim();
    complaintId = parseInt(complaintId || 0, 10) || 0;
    resetInstalledBaseFabAutoFields(form);

    if (!fabNumber && complaintId <= 0) {
        return $.Deferred().resolve({ allowed: true, response: null }).promise();
    }

    const requestData = {
        record_id: getInstalledBaseEditingRecordId()
    };
    if (fabNumber) {
        requestData.fab_number = fabNumber;
    }
    if (complaintId > 0) {
        requestData.complaint_id = complaintId;
    }

    return $.ajax({
        url: 'api/installed_base_fab_prefill.php',
        data: requestData,
        dataType: 'json'
    }).then(function (response) {
        if (response && response.blocked) {
            applyInstalledBaseFabOwnershipBlocked(
                form,
                (response.message && String(response.message).trim())
                    ? String(response.message).trim()
                    : 'This FAB Number is already assigned to another user and cannot be used.'
            );
            return { allowed: false, response: response };
        }

        if (typeof window.clearInstalledBaseFabOwnershipError === 'function') {
            window.clearInstalledBaseFabOwnershipError();
        }

        if (!response || !response.found) {
            resetInstalledBaseFabRecordFields(form);
            return { allowed: true, response: response || null };
        }

        setInstalledBaseFabAutoFields(form, response);
        return { allowed: true, response: response };
    }, function () {
        resetInstalledBaseFabAutoFields(form);
        return { allowed: true, response: null };
    });
}