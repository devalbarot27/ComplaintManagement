function setOrderBookingEndCustomerSelect2(id, text) {
    const $customer = $('#orderBookingEndCustomerSelect');
    if (!$customer.length) {
        return;
    }

    $customer.find('option').remove();
    id = id != null ? String(id).trim() : '';
    text = text != null ? String(text).trim() : '';

    if (id !== '') {
        const option = new Option(text || id, id, true, true);
        $customer.append(option).trigger('change');
    } else {
        $customer.val(null).trigger('change');
    }
}

function resetOrderBookingEndCustomerSelect2() {
    setOrderBookingEndCustomerSelect2('', '');
}

function clearOrderBookingEndCustomerFields() {
    const form = document.getElementById('orderBookingForm');
    ['endCustomerName', 'endCustomerEmail', 'endCustomerStreet1', 'endCustomerStreet2'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.value = '';
        }
    });

    if (form && typeof resetPincodeSelect2 === 'function') {
        resetPincodeSelect2(form, 'orderBookingPincodeSelect');
    } else {
        ['endCustomerCity', 'endCustomerDistrict', 'endCustomerState'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.value = '';
            }
        });
        const stateCode = document.getElementById('state_code');
        if (stateCode) {
            stateCode.value = '';
        }
    }
}

function fillOrderBookingEndCustomerFields(data) {
    if (!data) {
        return;
    }

    const form = document.getElementById('orderBookingForm');
    const nameEl = document.getElementById('endCustomerName');
    const emailEl = document.getElementById('endCustomerEmail');
    const street1El = document.getElementById('endCustomerStreet1');
    const street2El = document.getElementById('endCustomerStreet2');

    if (nameEl) {
        nameEl.value = data.customer_name != null ? String(data.customer_name) : '';
    }
    if (emailEl) {
        emailEl.value = data.email != null ? String(data.email) : '';
    }
    if (street1El) {
        street1El.value = data.street_1 != null ? String(data.street_1) : '';
    }
    if (street2El) {
        street2El.value = data.street_2 != null ? String(data.street_2) : '';
    }

    if (form && typeof setPincodeSelect2 === 'function') {
        setPincodeSelect2(form, 'orderBookingPincodeSelect', {
            pincode: data.pincode || '',
            city: data.city || '',
            district: data.district || '',
            state: data.state || '',
            state_code: data.state_code || ''
        });
    } else {
        const cityEl = document.getElementById('endCustomerCity');
        const districtEl = document.getElementById('endCustomerDistrict');
        const stateEl = document.getElementById('endCustomerState');
        if (cityEl) {
            cityEl.value = data.city != null ? String(data.city) : '';
        }
        if (districtEl) {
            districtEl.value = data.district != null ? String(data.district) : '';
        }
        if (stateEl) {
            stateEl.value = data.state != null ? String(data.state) : '';
        }
    }
}

function applyOrderBookingEndCustomer(data) {
    if (!data || !data.id) {
        return;
    }

    const label = data.text || data.customer_name || String(data.id);
    setOrderBookingEndCustomerSelect2(data.id, label);
    fillOrderBookingEndCustomerFields(data);
}

function initOrderBookingEndCustomerSelect2() {
    const $customer = $('#orderBookingEndCustomerSelect');
    if (!$customer.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    if ($customer.hasClass('select2-hidden-accessible')) {
        $customer.select2('destroy');
    }

    $customer.select2({
        width: '100%',
        placeholder: $customer.data('placeholder') || 'Search customer',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: 'api/customer_masters_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (data) {
                return data;
            },
            cache: true
        },
        language: {
            noResults: function () {
                return 'No customer found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $customer.off('select2:select.orderBookingEndCustomer select2:clear.orderBookingEndCustomer');
    $customer.on('select2:select.orderBookingEndCustomer', function (e) {
        const data = (e.params && e.params.data) ? e.params.data : null;
        if (data) {
            fillOrderBookingEndCustomerFields(data);
            return;
        }

        const id = $customer.val();
        if (!id) {
            return;
        }

        $.getJSON('api/customer_masters_search.php', { id: id })
            .done(function (payload) {
                const row = payload && payload.results && payload.results[0] ? payload.results[0] : null;
                if (row) {
                    fillOrderBookingEndCustomerFields(row);
                }
            });
    });

    $customer.on('select2:clear.orderBookingEndCustomer', function () {
        clearOrderBookingEndCustomerFields();
    });
}

function initOrderBookingAddNewCustomerButton() {
    const btn = document.getElementById('addNewCustomerFromOrderBookingBtn');
    if (!btn) {
        return;
    }

    btn.addEventListener('click', function () {
        if (typeof openInstalledBaseAddCustomerModal === 'function') {
            openInstalledBaseAddCustomerModal();
        }
    });
}

function initOrderBookingEndCustomerCustomerUi() {
    initOrderBookingEndCustomerSelect2();
    initOrderBookingAddNewCustomerButton();
    if (typeof initInstalledBaseAddCustomerModal === 'function') {
        initInstalledBaseAddCustomerModal();
    }
}