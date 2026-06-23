function initAssignToSelect2(formId, selectId, options) {
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
        placeholder: $select.data('placeholder') || 'Search assignee',
        allowClear: true,
        minimumResultsForSearch: 0,
        language: {
            noResults: function () {
                return 'No assignee found';
            }
        }
    };

    if (options.dropdownParent) {
        select2Options.dropdownParent = options.dropdownParent;
    }

    $select.select2(select2Options);

    const validationField = options.validationField || 'assign_complaint';

    $select.on('select2:select select2:clear', function () {
        $select.removeClass('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="' + validationField + '"]');
        if (msg) {
            msg.textContent = '';
        }
    });
}

function resetAssignToSelect2(selectId) {
    const $select = $('#' + selectId);

    if (!$select.length) {
        return;
    }

    $select.val(null).trigger('change');
    $select.removeClass('is-invalid');
}