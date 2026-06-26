function setServiceLogInstalledBaseFields(form, data) {
    if (!form || !data) {
        return;
    }

    
    const mapping = {
        order_id: data.order_id || '',
        fab_number: data.fab_number || '',
        running_hours: data.running_hours ?? '',
        machine_model: data.machine_model || '',
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

function clearServiceLogInstalledBaseFields(form) {
    if (!form) {
        return;
    }

    ['order_id', 'fab_number', 'running_hours', 'machine_model'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = '';
        }
    });
}

function resetInstalledBaseLinkSelect2(form) {
    const $select = $('#installedBaseLinkSelect');
    if (!$select.length) {
        return;
    }

    $select.val(null).trigger('change');
    clearServiceLogInstalledBaseFields(form);
}

function initServiceLogInstalledBaseSelect2() {
    const form = document.getElementById('serviceLogForm');
    const $select = $('#installedBaseLinkSelect');

    if (!form || !$select.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    $select.select2({
        width: '100%',
        placeholder: 'Search installed base record',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: 'api/installed_base_link_search.php',
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
                return 'Type to search installed base';
            },
            noResults: function () {
                return 'No installed base record found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $select.on('select2:select', function (e) {
        setServiceLogInstalledBaseFields(form, e.params.data);

        if (window.slPartReplacementModule) {
            window.slPartReplacementModule.setPrefillData({
                running_hours: e.params.data.running_hours != null ? String(e.params.data.running_hours) : '',
                machine_model_code: e.params.data.machine_model_code != null ? String(e.params.data.machine_model_code) : '',
                machine_model_desc: e.params.data.machine_model_desc != null
                    ? String(e.params.data.machine_model_desc)
                    : (e.params.data.machine_model != null ? String(e.params.data.machine_model) : '')
            });
        }

        $select.removeClass('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="installed_base_id"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    $select.on('select2:clear', function () {
        clearServiceLogInstalledBaseFields(form);
    });
}
