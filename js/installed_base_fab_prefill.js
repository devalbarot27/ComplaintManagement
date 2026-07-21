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

function prefillInstalledBaseFromFab(form, fabNumber, complaintId) {
    if (!form) {
        return;
    }

    fabNumber = String(fabNumber || '').trim();
    complaintId = parseInt(complaintId || 0, 10) || 0;
    resetInstalledBaseFabAutoFields(form);

    if (!fabNumber && complaintId <= 0) {
        return;
    }

    const requestData = {};
    if (fabNumber) {
        requestData.fab_number = fabNumber;
    }
    if (complaintId > 0) {
        requestData.complaint_id = complaintId;
    }

    $.ajax({
        url: 'api/installed_base_fab_prefill.php',
        data: requestData,
        dataType: 'json'
    }).done(function (response) {
        if (!response || !response.found) {
            resetInstalledBaseFabRecordFields(form);
            return;
        }

        setInstalledBaseFabAutoFields(form, response);
    }).fail(function () {
        resetInstalledBaseFabAutoFields(form);
    });
}
