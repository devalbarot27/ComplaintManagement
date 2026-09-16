function setInstalledBaseOrderFields(form, data) {
    if (!form || !data) {
        return;
    }

    const orderIdDisplay = form.querySelector('#orderIdDisplay');
    if (orderIdDisplay) {
        orderIdDisplay.value = data.order_id || '';
    }
}

function clearInstalledBaseOrderFields(form) {
    if (!form) {
        return;
    }

    const orderIdDisplay = form.querySelector('#orderIdDisplay');
    if (orderIdDisplay) {
        orderIdDisplay.value = '';
    }
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
        placeholder: 'Search order number',
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
                return 'Type to search order number';
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

        const msg = form.querySelector('.validation-msg[data-field="order_ref_id"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    $order.on('select2:clear', function () {
        clearInstalledBaseOrderFields(form);
    });
}
