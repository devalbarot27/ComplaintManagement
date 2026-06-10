function initServiceLogDatatable() {
    const $table = $('#serviceLogTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/service_log_datatable.php',
            type: 'POST'
        },
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'order_id' },
            { data: 'serial_number' },
            { data: 'machine_model' },
            { data: 'warranty_chargeable' },
            { data: 'engineer_name' },
            { data: 'visit_date' },
            { data: 'closure_date' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No service logs found.',
            zeroRecords: 'No matching service logs found.'
        }
    });
}

function fillServiceLogForm(record) {
    const form = document.getElementById('serviceLogForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('serviceLogId').value = record.id || '';
    document.getElementById('formModeLabel').textContent = record.id ? 'Edit Service Log' : 'New Service Log';
    document.getElementById('submitServiceLogBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Service Log'
        : '<i class="bi bi-check-lg"></i> Save Service Log';

    const $select = $('#installedBaseLinkSelect');
    if ($select.length && record.installed_base_id) {
        const label = '#' + record.installed_base_id + ' — ' + record.order_id;
        const option = new Option(label, record.installed_base_id, true, true);
        $select.append(option).trigger('change');
    }

    const fields = [
        'order_id', 'serial_number', 'machine_model', 'warranty_chargeable',
        'complaint_date', 'issue_description', 'engineer_name', 'visit_date',
        'action_taken', 'closure_date', 'part_replaced', 'running_hours',
        'loaded_hours', 'customer_feedback', 'remarks'
    ];

    fields.forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = record[field] ?? '';
        }
    });

}

function resetServiceLogForm() {
    const form = document.getElementById('serviceLogForm');
    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('serviceLogId').value = '';
    document.getElementById('formModeLabel').textContent = 'New Service Log';
    document.getElementById('submitServiceLogBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Service Log';

    resetInstalledBaseLinkSelect2(form);

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openServiceLogForm() {
    const card = document.getElementById('serviceLogFormCard');
    const openBtn = document.getElementById('openServiceLogForm');
    const closeBtn = document.getElementById('closeServiceLogForm');

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

function closeServiceLogFormPanel() {
    const card = document.getElementById('serviceLogFormCard');
    const openBtn = document.getElementById('openServiceLogForm');
    const closeBtn = document.getElementById('closeServiceLogForm');

    if (card) {
        card.classList.remove('show');
    }
    if (openBtn) {
        openBtn.style.display = 'flex';
    }
    if (closeBtn) {
        closeBtn.classList.remove('show');
    }

    resetServiceLogForm();
}

function initServiceLogPage() {
    const table = initServiceLogDatatable();
    initServiceLogInstalledBaseSelect2();
    initServiceLogFormValidation();

    const openBtn = document.getElementById('openServiceLogForm');
    const closeBtn = document.getElementById('closeServiceLogForm');

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            resetServiceLogForm();
            openServiceLogForm();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeServiceLogFormPanel);
    }

    $(document).on('click', '.edit-service-log-btn', function () {
        const id = $(this).data('id');

        $.getJSON('api/service_log_get.php', { id: id })
            .done(function (record) {
                resetServiceLogForm();
                fillServiceLogForm(record);
                openServiceLogForm();
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Unable to load record.';
                alert(message);
            });
    });

    if (table) {
        window.serviceLogTable = table;
    }
}
