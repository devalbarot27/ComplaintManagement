function setMachineModelDesc(value) {
    const input = document.getElementById('machineModelDesc');

    if (input) {
        input.value = value || '';
    }
}

function ensureMachineModelCodeHidden(code) {
    let hidden = document.getElementById('machineModelCodeLocked');

    if (!hidden) {
        const select = document.getElementById('machineModelSelect');
        if (!select || !select.parentNode) {
            return;
        }

        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'machine_model_code';
        hidden.id = 'machineModelCodeLocked';
        select.parentNode.insertBefore(hidden, select.nextSibling);
    }

    hidden.value = code || '';
}

function removeMachineModelCodeHidden() {
    const hidden = document.getElementById('machineModelCodeLocked');
    if (hidden) {
        hidden.remove();
    }
}

function setMachineModelSelect2Locked(locked) {
    const $select = $('#machineModelSelect');

    if (!$select.length) {
        return;
    }

    const code = String($select.val() || '').trim();

    if (locked && code !== '') {
        ensureMachineModelCodeHidden(code);
        $select.prop('disabled', true);
        $select.data('locked', true);
    } else {
        removeMachineModelCodeHidden();
        $select.prop('disabled', false);
        $select.data('locked', false);
    }
}

function setMachineModelSelect2(code, description, options) {
    const $select = $('#machineModelSelect');
    options = options || {};

    if (!$select.length) {
        return;
    }

    if (!code) {
        setMachineModelSelect2Locked(false);
        $select.val(null).trigger('change');
        setMachineModelDesc('');
        $select.removeClass('is-invalid');
        return;
    }

    const label = description ? code + ' - ' + description : code;

    if ($select.find('option[value="' + code.replace(/"/g, '\\"') + '"]').length === 0) {
        const option = new Option(label, code, true, true);
        $select.append(option);
    }

    $select.val(code).trigger('change');
    setMachineModelDesc(description || '');
    $select.removeClass('is-invalid');
    setMachineModelSelect2Locked(!!options.locked);
}

function resetMachineModelSelect2() {
    const $select = $('#machineModelSelect');

    if (!$select.length) {
        return;
    }

    setMachineModelSelect2Locked(false);
    $select.val(null).trigger('change');
    setMachineModelDesc('');
    $select.removeClass('is-invalid');
}

function initInstalledBaseMachineModelSelect2() {
    const form = document.getElementById('installedBaseForm');
    const $select = $('#machineModelSelect');

    if (!form || !$select.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }

    $select.select2({
        width: '100%',
        placeholder: $select.data('placeholder') || 'Search machine model',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: 'api/machine_model_search.php',
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
                return 'Type to search machine model';
            },
            noResults: function () {
                return 'No machine model found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $select.on('select2:opening select2:unselecting', function (e) {
        if ($select.data('locked')) {
            e.preventDefault();
        }
    });

    $select.on('select2:select', function (e) {
        const data = e.params.data || {};
        setMachineModelDesc(data.tpldesc || '');
        $select.removeClass('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="machine_model_code"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    $select.on('select2:clear', function () {
        setMachineModelDesc('');
        $select.removeClass('is-invalid');
    });
}
