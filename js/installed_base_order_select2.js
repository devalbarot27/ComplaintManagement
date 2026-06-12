function setInstalledBaseOrderFields(form, data) {
    if (!form || !data) {
        return;
    }

    const orderIdDisplay = form.querySelector('#orderIdDisplay');
    if (orderIdDisplay) {
        orderIdDisplay.value = data.order_id || '';
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

    const orderIdDisplay = form.querySelector('#orderIdDisplay');
    if (orderIdDisplay) {
        orderIdDisplay.value = '';
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

function applyOrderToInstalledBaseForm(order) {
    const form = document.getElementById('installedBaseForm');
    const $order = $('#orderIdSelect');

    if (!form || !$order.length || !order) {
        return;
    }

    const option = new Option(order.text, order.id, true, true);
    $order.append(option).trigger('change');
    setInstalledBaseOrderFields(form, order);
}

function initCreateOrderModal() {
    const modalEl = document.getElementById('createOrderModal');
    const form = document.getElementById('createOrderForm');
    const openBtn = document.getElementById('openCreateOrderModal');

    if (!modalEl || !form || !openBtn || typeof bootstrap === 'undefined') {
        return;
    }

    const modal = new bootstrap.Modal(modalEl);

    openBtn.addEventListener('click', function () {
        form.reset();
        form.querySelectorAll('.validation-msg').forEach(function (el) {
            el.textContent = '';
        });
        modal.show();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const submitBtn = document.getElementById('submitCreateOrderBtn');
        if (submitBtn) {
            submitBtn.classList.add('disabled_btn');
        }

        $.post('api/order_create.php', $(form).serialize())
            .done(function (response) {
                if (response && response.order) {
                    applyOrderToInstalledBaseForm(response.order);
                    modal.hide();
                }
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Failed to create order.';
                alert(message);
            })
            .always(function () {
                if (submitBtn) {
                    submitBtn.classList.remove('disabled_btn');
                }
            });
    });
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

        const msg = form.querySelector('.validation-msg[data-field="order_ref_id"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    $order.on('select2:clear', function () {
        clearInstalledBaseOrderFields(form);
    });

    initCreateOrderModal();
}
