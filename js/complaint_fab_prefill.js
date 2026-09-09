function resetComplaintFabAutoFields(form) {
    if (!form) {
        return;
    }

    ['customer_name', 'street_1', 'street_2'].forEach(function (field) {
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

    resetPincodeSelect2(form, 'pincodeSelect');
}

function setComplaintCustomerFields(form, data) {
    if (!form || !data) {
        return;
    }

    ['customer_name', 'street_1', 'street_2'].forEach(function (field) {
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
}

function prefillComplaintFromFab(form, fabNumber) {
    if (!form) {
        return;
    }

    fabNumber = String(fabNumber || '').trim();
    resetComplaintFabAutoFields(form);

    if (!fabNumber) {
        return;
    }

    $.ajax({
        url: 'api/complaint_fab_prefill.php',
        data: { fab_number: fabNumber },
        dataType: 'json'
    }).done(function (response) {
        if (!response || !response.found) {
            return;
        }

        setComplaintCustomerFields(form, response);
        setPincodeSelect2(form, 'pincodeSelect', response);
    }).fail(function () {
        resetComplaintFabAutoFields(form);
    });
}