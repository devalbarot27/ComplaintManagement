function resetComplaintFabAutoFields(form) {
    if (!form) {
        return;
    }

    if (typeof resetComplaintCustomerSelect2 === 'function') {
        resetComplaintCustomerSelect2();
    }
}

function setComplaintCustomerFields(form, data) {
    if (!form || !data) {
        return;
    }

    if (data.customer_id && typeof setComplaintCustomerSelect2 === 'function') {
        setComplaintCustomerSelect2(data.customer_id, data.customer_label || data.customer_name || '');
    }
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
    }).fail(function () {
        resetComplaintFabAutoFields(form);
    });
}
