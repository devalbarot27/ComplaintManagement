function clearClosureDistanceFields() {
    const km = document.getElementById('closureKmTravelled');
    const kmValue = document.getElementById('closureKmTravelledValue');
    const price = document.getElementById('closureVisitCharge');
    const priceValue = document.getElementById('closureVisitChargeValue');
    const serviceDate = document.getElementById('closureServiceDate');
    const serviceDateValue = document.getElementById('closureServiceDateValue');
    const emptyHint = document.getElementById('closureDistanceEmpty');
    const fieldsWrap = document.getElementById('closureDistanceFields');

    if (km) {
        km.value = '';
    }
    if (kmValue) {
        kmValue.value = '';
    }
    if (price) {
        price.value = '';
    }
    if (priceValue) {
        priceValue.value = '';
    }
    if (serviceDate) {
        serviceDate.value = '';
    }
    if (serviceDateValue) {
        serviceDateValue.value = '';
    }
    if (emptyHint) {
        emptyHint.classList.add('d-none');
        emptyHint.textContent = '';
    }
    if (fieldsWrap) {
        fieldsWrap.classList.add('d-none');
    }
}

function applyClosureDistanceDetails(data) {
    const km = document.getElementById('closureKmTravelled');
    const kmValue = document.getElementById('closureKmTravelledValue');
    const price = document.getElementById('closureVisitCharge');
    const priceValue = document.getElementById('closureVisitChargeValue');
    const serviceDate = document.getElementById('closureServiceDate');
    const serviceDateValue = document.getElementById('closureServiceDateValue');
    const emptyHint = document.getElementById('closureDistanceEmpty');
    const fieldsWrap = document.getElementById('closureDistanceFields');

    if (!data || !data.found) {
        if (fieldsWrap) {
            fieldsWrap.classList.add('d-none');
        }
        if (emptyHint) {
            emptyHint.textContent = 'No Warranty Service Claim found for this ticket.';
            emptyHint.classList.remove('d-none');
        }
        return;
    }

    if (fieldsWrap) {
        fieldsWrap.classList.remove('d-none');
    }
    if (emptyHint) {
        emptyHint.classList.add('d-none');
        emptyHint.textContent = '';
    }

    if (km) {
        km.value = data.km_travelled_label || data.km_travelled || '';
    }
    if (kmValue) {
        kmValue.value = data.km_travelled || '';
    }
    if (price) {
        price.value = data.visit_charge_label || '';
    }
    if (priceValue) {
        priceValue.value = data.visit_charge_price || '';
    }
    if (serviceDate) {
        serviceDate.value = data.service_date_label || '';
    }
    if (serviceDateValue) {
        serviceDateValue.value = data.service_date || '';
    }
}

function loadClosureDistanceFromServiceClaim(complaintId) {
    clearClosureDistanceFields();

    const id = String(complaintId || '').trim();
    if (id === '' || !/^\d+$/.test(id)) {
        return;
    }

    const emptyHint = document.getElementById('closureDistanceEmpty');
    if (emptyHint) {
        emptyHint.textContent = 'Loading distance travelled from Warranty Service Claims...';
        emptyHint.classList.remove('d-none');
    }

    fetch('api/complaint_closure_distance.php?complaint_id=' + encodeURIComponent(id), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
        .then(function (response) {
            return response.json().then(function (payload) {
                return { ok: response.ok, payload: payload };
            });
        })
        .then(function (result) {
            if (!result.ok || !result.payload || result.payload.success === false) {
                applyClosureDistanceDetails(null);
                return;
            }
            applyClosureDistanceDetails(result.payload);
        })
        .catch(function () {
            applyClosureDistanceDetails(null);
        });
}
