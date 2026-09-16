function resetComplaintFabAutoFields(form) {
    if (typeof setComplaintCustomerSelect2Locked === 'function') {
        setComplaintCustomerSelect2Locked(false);
    }

    if (typeof resetComplaintCustomerSelect2 === 'function') {
        resetComplaintCustomerSelect2();
    }
}

function prefillComplaintFromFab(form, fabNumber) {
    if (!form) {
        return $.Deferred().resolve(null).promise();
    }

    fabNumber = String(fabNumber || '').trim();
    resetComplaintFabAutoFields(form);

    if (!fabNumber) {
        return $.Deferred().resolve(null).promise();
    }

    return $.ajax({
        url: 'api/complaint_fab_prefill.php',
        data: { fab_number: fabNumber },
        dataType: 'json'
    }).then(function (response) {
        if (!response || !response.found || !response.customer_id) {
            return response || null;
        }

        if (typeof setComplaintCustomerSelect2 === 'function') {
            setComplaintCustomerSelect2(
                response.customer_id,
                response.customer_label || response.customer_name || ''
            );
        }

        if (response.lock_customer && typeof setComplaintCustomerSelect2Locked === 'function') {
            setComplaintCustomerSelect2Locked(true);
        }

        return response;
    }, function () {
        resetComplaintFabAutoFields(form);
        return null;
    });
}
