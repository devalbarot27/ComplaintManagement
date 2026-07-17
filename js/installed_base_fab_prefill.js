function resetInstalledBaseFabAutoFields(form) {
    if (!form) {
        return;
    }

    ['customer_name', 'street_1', 'street_2', 'mobile', 'email', 'remarks'].forEach(function (field) {
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
}

function setInstalledBaseFabAutoFields(form, data) {
    if (!form || !data) {
        return;
    }

    ['customer_name', 'street_1', 'street_2', 'mobile', 'email', 'remarks'].forEach(function (field) {
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
            return;
        }

        setInstalledBaseFabAutoFields(form, response);
    }).fail(function () {
        resetInstalledBaseFabAutoFields(form);
    });
}
