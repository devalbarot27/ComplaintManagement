function setInstalledBaseOrderFields(form, data) {
    if (!form || !data) {
        return;
    }

    const mapping = {
        fab_number: data.fab_number || '',
        customer_name: data.customer_name || '',
        dealer_name: data.dealer_name || '',
        machine_model: data.machine_model || '',
        invoice_date: data.invoice_date || '',
    };

    Object.keys(mapping).forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (!input) {
            return;
        }

        input.value = mapping[field];
        input.classList.remove('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
        if (msg) {
            msg.textContent = '';
        }
    });
}

function clearInstalledBaseOrderFields(form) {
    if (!form) {
        return;
    }

    ['fab_number', 'customer_name', 'dealer_name', 'machine_model', 'invoice_date'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = '';
        }
    });
}

function resetOrderSelect2(form) {
    const $order = $('#orderIdSelect');
    if (!$order.length) {
        return;
    }

    $order.val(null).trigger('change');
    clearInstalledBaseOrderFields(form);
}

function initInstalledBaseOrderSelect2() {
    const form = document.getElementById('installedBaseForm');
    const $order = $('#orderIdSelect');

    if (!form || !$order.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    $order.select2({
        width: '100%',
        placeholder: 'Search order ID',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: 'api/order_search.php',
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
            inputTooShort: function () {
                return 'Type to search order';
            },
            noResults: function () {
                return 'No order found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $order.on('select2:select', function (e) {
        setInstalledBaseOrderFields(form, e.params.data);
        $order.removeClass('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="order_id"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    $order.on('select2:clear', function () {
        clearInstalledBaseOrderFields(form);
    });
}
