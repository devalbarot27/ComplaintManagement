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

function getInstalledBaseDefaultDealerName() {
    const form = document.getElementById('installedBaseForm');

    return form ? (form.getAttribute('data-default-dealer-name') || '') : '';
}

function setInstalledBaseDealerName(value) {
    const form = document.getElementById('installedBaseForm');
    if (!form) {
        return;
    }

    const input = form.querySelector('[name="dealer_name"]');
    if (input) {
        input.value = value || '';
    }
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
    if ($order.length && (record.order_id || record.order_ref_id)) {
        const label = record.order_id || String(record.order_ref_id);
        const value = record.order_id || String(record.order_ref_id);
        const option = new Option(label, value, true, true);
        $order.append(option).trigger('change');
    }

    const orderIdDisplay = form.querySelector('#orderIdDisplay');
    if (orderIdDisplay) {
        orderIdDisplay.value = record.order_id || '';
    }

    const fields = [
        'customer_name', 'street_1', 'street_2', 'mobile', 'email',
        'invoice_date', 'commissioning_date',
        'running_hours', 'industry_segment', 'remarks'
    ];

    fields.forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = record[field] ?? '';
        }
    });

    setInstalledBaseDealerName(record.dealer_name || getInstalledBaseDefaultDealerName());

    setStaticSelect2Value('industrySegmentSelect', record.industry_segment || '');
    setMachineModelSelect2(record.machine_model_code || '', record.machine_model || '');

    setInstalledBaseFabSelect2(record.fab_number || '');
    setInstalledBaseInvoiceDate(form, record.invoice_date || '');

    const $pincode = $('#installedBasePincodeSelect');
    if ($pincode.length) {
        $pincode.val(null).trigger('change');
        if (record.pincode) {
            const option = new Option(record.pincode, record.pincode, true, true);
            $pincode.append(option).trigger('change');
        }
    }

    setAddressAutoFields(form, record);
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
    resetFabNumberSelect2();
    resetPincodeSelect2(form, 'installedBasePincodeSelect');
    resetStaticSelect2('industrySegmentSelect');
    resetMachineModelSelect2();

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });

    setInstalledBaseDealerName(getInstalledBaseDefaultDealerName());
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

function initInstalledBaseStaticSelect2() {
    initStaticSelect2('installedBaseForm', 'industrySegmentSelect', {
        validationField: 'industry_segment',
        allowClear: false,
        noResultsText: 'No industry segment found'
    });
}

function initInstalledBasePage() {
    const table = initInstalledBaseDatatable();
    initInstalledBaseFabnoSelect2();
    initInstalledBaseOrderSelect2();
    initInstalledBaseMachineModelSelect2();
    initInstalledBaseStaticSelect2();
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

    const params = new URLSearchParams(window.location.search);
    if (params.get('open_form') === '1' && openBtn) {
        resetInstalledBaseForm();
        const fabNumber = (params.get('fab_number') || '').trim();
        if (fabNumber) {
            setInstalledBaseFabSelect2(fabNumber);
        }
        openInstalledBaseForm();

        const card = document.getElementById('installedBaseFormCard');
        if (card && typeof card.scrollIntoView === 'function') {
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
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