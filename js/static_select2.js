function initStaticSelect2(formId, selectId, options) {
    options = options || {};

    const form = document.getElementById(formId);
    const $select = $('#' + selectId);

    if (!form || !$select.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }

    const select2Options = {
        width: '100%',
        placeholder: $select.data('placeholder') || 'Select option',
        allowClear: options.allowClear !== false,
        minimumResultsForSearch: 0,
        language: {
            noResults: function () {
                return options.noResultsText || 'No option found';
            }
        }
    };

    if (options.dropdownParent) {
        select2Options.dropdownParent = options.dropdownParent;
    }

    $select.select2(select2Options);

    const validationField = options.validationField || $select.attr('name') || '';

    $select.on('select2:select select2:clear', function () {
        $select.removeClass('is-invalid');

        if (!validationField) {
            return;
        }

        const msg = form.querySelector('.validation-msg[data-field="' + validationField + '"]');
        if (msg) {
            msg.textContent = '';
        }
    });
}

function resetStaticSelect2(selectId) {
    const $select = $('#' + selectId);

    if (!$select.length) {
        return;
    }

    $select.val(null).trigger('change');
    $select.removeClass('is-invalid');
}

function setStaticSelect2Value(selectId, value) {
    const $select = $('#' + selectId);

    if (!$select.length) {
        return;
    }

    $select.val(value || null).trigger('change');
    $select.removeClass('is-invalid');
}

function initStaticSelect2Fields(formId, fields) {
    fields.forEach(function (field) {
        initStaticSelect2(formId, field.selectId, field);
    });
}

function resetStaticSelect2Fields(selectIds) {
    selectIds.forEach(function (selectId) {
        resetStaticSelect2(selectId);
    });
}