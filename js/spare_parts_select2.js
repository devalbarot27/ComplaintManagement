function setSparePartsMachineFields(form, data) {
    if (!form || !data) {
        return;
    }

    const mapping = {
        serial_number: data.serial_number || data.fab_number || '',
        running_hours: data.running_hours ?? '',
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

    window.sparePartsSelectedInstalledBaseId = data.installed_base_id || data.id || null;
    initSparePartsServiceLogSelect2(window.sparePartsSelectedInstalledBaseId);
}

function clearSparePartsMachineFields(form) {
    if (!form) {
        return;
    }

    ['serial_number', 'running_hours'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = '';
        }
    });

    window.sparePartsSelectedInstalledBaseId = null;
    resetSparePartsServiceLogSelect2(form);
}

function resetSparePartsMachineSelect2(form) {
    const $select = $('#sparePartsMachineSelect');
    if ($select.length) {
        $select.val(null).trigger('change');
    }
    clearSparePartsMachineFields(form);
}

function resetSparePartsServiceLogSelect2(form) {
    const $select = $('#sparePartsServiceLogSelect');
    if (!$select.length) {
        return;
    }

    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }

    $select.val(null);
    $select.prop('disabled', true);
}

function initSparePartsServiceLogSelect2(installedBaseId) {
    const form = document.getElementById('sparePartsForm');
    const $select = $('#sparePartsServiceLogSelect');

    if (!form || !$select.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }

    $select.val(null);
    $select.prop('disabled', !installedBaseId);

    if (!installedBaseId) {
        return;
    }

    $select.select2({
        width: '100%',
        placeholder: 'Link service record (optional)',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: 'api/service_log_link_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term || '',
                    installed_base_id: installedBaseId
                };
            },
            processResults: function (data) {
                return data;
            },
            cache: true
        },
        language: {
            noResults: function () {
                return 'No service log found for this machine';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });
}

function initSparePartsMachineSelect2() {
    const form = document.getElementById('sparePartsForm');
    const $select = $('#sparePartsMachineSelect');

    if (!form || !$select.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    $select.select2({
        width: '100%',
        placeholder: 'Search machine by serial / order / customer',
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
                return 'Type to search machine';
            },
            noResults: function () {
                return 'No machine found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $select.on('select2:select', function (e) {
        setSparePartsMachineFields(form, e.params.data);
        $select.removeClass('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="installed_base_id"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    $select.on('select2:clear', function () {
        clearSparePartsMachineFields(form);
    });

    initSparePartsServiceLogSelect2(null);
}
