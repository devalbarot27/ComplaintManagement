function initInstalledBaseDatatable() {
    const $table = $('#installedBaseTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/installed_base_datatable.php',
            type: 'POST'
        },
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'order_id' },
            { data: 'fab_number' },
            { data: 'customer_name' },
            { data: 'dealer_name' },
            { data: 'machine_model' },
            { data: 'commissioning_date' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No installed base records found.',
            zeroRecords: 'No matching records found.'
        }
    });
}

function fillInstalledBaseForm(record) {
    const form = document.getElementById('installedBaseForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('installedBaseId').value = record.id || '';
    document.getElementById('formModeLabel').textContent = record.id ? 'Edit Installed Base' : 'New Installed Base';
    document.getElementById('submitInstalledBaseBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Record'
        : '<i class="bi bi-check-lg"></i> Save Record';

    const $order = $('#orderIdSelect');
    if ($order.length && record.order_ref_id) {
        const label = record.order_id + (record.customer_name ? ' — ' + record.customer_name : '');
        const option = new Option(label, record.order_ref_id, true, true);
        $order.append(option).trigger('change');
    }

    const orderIdDisplay = form.querySelector('#orderIdDisplay');
    if (orderIdDisplay) {
        orderIdDisplay.value = record.order_id || '';
    }

    const fields = [
        'fab_number', 'customer_name', 'address', 'mobile', 'email',
        'dealer_name', 'machine_model', 'invoice_date', 'commissioning_date',
        'running_hours', 'industry_segment', 'remarks'
    ];

    fields.forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = record[field] ?? '';
        }
    });

    setInstalledBaseOrderFields(form, record);
}

function resetInstalledBaseForm() {
    const form = document.getElementById('installedBaseForm');
    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('installedBaseId').value = '';
    document.getElementById('formModeLabel').textContent = 'New Installed Base';
    document.getElementById('submitInstalledBaseBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Record';

    resetOrderSelect2(form);

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openInstalledBaseForm() {
    const card = document.getElementById('installedBaseFormCard');
    const openBtn = document.getElementById('openInstalledBaseForm');
    const closeBtn = document.getElementById('closeInstalledBaseForm');

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

function closeInstalledBaseFormPanel() {
    const card = document.getElementById('installedBaseFormCard');
    const openBtn = document.getElementById('openInstalledBaseForm');
    const closeBtn = document.getElementById('closeInstalledBaseForm');

    if (card) {
        card.classList.remove('show');
    }
    if (openBtn) {
        openBtn.style.display = 'flex';
    }
    if (closeBtn) {
        closeBtn.classList.remove('show');
    }

    resetInstalledBaseForm();
}

function initInstalledBasePage() {
    const table = initInstalledBaseDatatable();
    initInstalledBaseOrderSelect2();
    initInstalledBaseFormValidation();

    const openBtn = document.getElementById('openInstalledBaseForm');
    const closeBtn = document.getElementById('closeInstalledBaseForm');

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            resetInstalledBaseForm();
            openInstalledBaseForm();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeInstalledBaseFormPanel);
    }

    $(document).on('click', '.edit-installed-base-btn', function () {
        const id = $(this).data('id');

        $.getJSON('api/installed_base_get.php', { id: id })
            .done(function (record) {
                resetInstalledBaseForm();
                fillInstalledBaseForm(record);
                openInstalledBaseForm();
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Unable to load record.';
                alert(message);
            });
    });

    if (table) {
        window.installedBaseTable = table;
    }
}
