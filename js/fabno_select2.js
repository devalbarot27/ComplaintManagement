function initFabnoSelect2(formId, selectId, options) {
    options = options || {};

    const form = document.getElementById(formId);
    const $fab = $('#' + selectId);

    if (!form || !$fab.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    $fab.select2({
        width: '100%',
        placeholder: 'Search fab number',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: 'api/ln_invoice_fabno_search.php',
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
                return 'Type to search fab number';
            },
            noResults: function () {
                return 'No fab number found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $fab.on('select2:select', function (e) {
        $fab.removeClass('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="fab_number"]');
        if (msg) {
            msg.textContent = '';
        }

        if (typeof options.onSelect === 'function') {
            options.onSelect(e.params.data, form);
        }
    });

    $fab.on('select2:clear', function () {
        if (typeof options.onClear === 'function') {
            options.onClear(form);
        }
    });
}

function setFabNumberSelect2(selectId, formId, fabNumber) {
    const $fab = $('#' + selectId);
    const form = document.getElementById(formId);

    if (!$fab.length) {
        return;
    }

    $fab.val(null).trigger('change');

    if (fabNumber) {
        const option = new Option(fabNumber, fabNumber, true, true);
        $fab.append(option).trigger('change');
    }

    $fab.removeClass('is-invalid');

    if (form) {
        const msg = form.querySelector('.validation-msg[data-field="fab_number"]');
        if (msg) {
            msg.textContent = '';
        }
    }
}

function resetFabNumberSelect2ById(selectId) {
    const $fab = $('#' + selectId);
    if (!$fab.length) {
        return;
    }

    $fab.val(null).trigger('change');
}
