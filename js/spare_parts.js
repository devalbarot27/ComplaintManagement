function initSparePartsDatatable() {
    const $table = $('#sparePartsTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/spare_parts_datatable.php',
            type: 'POST'
        },
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'serial_number' },
            { data: 'consumption_date' },
            { data: 'warranty_chargeable' },
            { data: 'spare_kit_number' },
            { data: 'quantity' },
            { data: 'order_value' },
            { data: 'reason' },
            { data: 'service_log_id' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No spare parts records found.',
            zeroRecords: 'No matching records found.'
        }
    });
}

function fillSparePartsForm(record) {
    const form = document.getElementById('sparePartsForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('sparePartsId').value = record.id || '';
    document.getElementById('formModeLabel').textContent = record.id
        ? 'Edit Spare Parts Consumption'
        : 'New Spare Parts Consumption';
    document.getElementById('submitSparePartsBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Record'
        : '<i class="bi bi-check-lg"></i> Save Record';

    const $machine = $('#sparePartsMachineSelect');
    if ($machine.length && record.installed_base_id) {
        const label = '#' + record.installed_base_id + ' — ' + record.serial_number;
        const option = new Option(label, record.installed_base_id, true, true);
        $machine.append(option).trigger('change');
        window.sparePartsSelectedInstalledBaseId = record.installed_base_id;
        initSparePartsServiceLogSelect2(record.installed_base_id);
    }

    const $serviceLog = $('#sparePartsServiceLogSelect');
    if ($serviceLog.length && record.service_log_id) {
        const serviceLabel = '#' + record.service_log_id;
        const serviceOption = new Option(serviceLabel, record.service_log_id, true, true);
        $serviceLog.append(serviceOption).trigger('change');
    }

    const fields = [
        'serial_number', 'consumption_date', 'warranty_chargeable', 'spare_kit_number',
        'quantity', 'order_value', 'reason', 'running_hours', 'remarks'
    ];

    fields.forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = record[field] ?? '';
        }
    });
}

function resetSparePartsForm() {
    const form = document.getElementById('sparePartsForm');
    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('sparePartsId').value = '';
    document.getElementById('formModeLabel').textContent = 'New Spare Parts Consumption';
    document.getElementById('submitSparePartsBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Record';

    resetSparePartsMachineSelect2(form);

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openSparePartsForm() {
    const card = document.getElementById('sparePartsFormCard');
    const openBtn = document.getElementById('openSparePartsForm');
    const closeBtn = document.getElementById('closeSparePartsForm');

    if (card) {
        card.classList.add('show');
    }
    if (openBtn) {
        openBtn.style.display = 'none';
    }
    if (closeBtn) {
        closeBtn.classList.add('show');
    }
}

function closeSparePartsFormPanel() {
    const card = document.getElementById('sparePartsFormCard');
    const openBtn = document.getElementById('openSparePartsForm');
    const closeBtn = document.getElementById('closeSparePartsForm');

    if (card) {
        card.classList.remove('show');
    }
    if (openBtn) {
        openBtn.style.display = 'flex';
    }
    if (closeBtn) {
        closeBtn.classList.remove('show');
    }

    resetSparePartsForm();
}

function initSparePartsPage() {
    const table = initSparePartsDatatable();
    initSparePartsMachineSelect2();
    initSparePartsFormValidation();

    const openBtn = document.getElementById('openSparePartsForm');
    const closeBtn = document.getElementById('closeSparePartsForm');

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            resetSparePartsForm();
            openSparePartsForm();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeSparePartsFormPanel);
    }

    $(document).on('click', '.edit-spare-parts-btn', function () {
        const id = $(this).data('id');

        $.getJSON('api/spare_parts_get.php', { id: id })
            .done(function (record) {
                resetSparePartsForm();
                fillSparePartsForm(record);
                openSparePartsForm();
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Unable to load record.';
                alert(message);
            });
    });

    if (table) {
        window.sparePartsTable = table;
    }
}
