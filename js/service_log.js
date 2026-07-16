let slPartReplacementModule = null;
let serviceLogFeedbackRating = null;

function initServiceLogFeedbackRating() {
    serviceLogFeedbackRating = createCustomerFeedbackRating({
        wrapId: 'serviceLogCustomerFeedbackRating',
        inputId: 'serviceLogCustomerFeedbackInput',
        selectedId: 'serviceLogCustomerFeedbackSelected',
        formSelector: '#serviceLogForm'
    });
    serviceLogFeedbackRating.init();
    window.serviceLogFeedbackRating = serviceLogFeedbackRating;
}

function initServiceLogPartReplacementModule() {
    slPartReplacementModule = createServiceLogPartReplacementModule({
        formId: 'serviceLogForm',
        partReplacedSelectId: 'serviceLogPartReplacedSelect',
        commonHoursWrapperId: 'serviceLogPartReplacedCommonHoursWrapper',
        wrapperId: 'serviceLogPartReplacementWrapper',
        entriesContainerId: 'serviceLogPartReplacementEntries',
        addBtnId: 'serviceLogAddPartReplacementBtn',
        feedbackRatingGlobal: 'serviceLogFeedbackRating',
        partModelSelectPrefix: 'serviceLogPartModelSelect',
        entryClass: 'sl-part-replacement-entry',
        dropdownParent: '#serviceLogFormCard'
    });

    slPartReplacementModule.initControls();
    window.slPartReplacementModule = slPartReplacementModule;
}

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
            {
                data: 'id',
                orderable: true,
                searchable: true,
                render: function (data) {
                    return data;
                }
            },
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
        },
        rowCallback: function (row, data) {
            if (Number(data.is_draft) === 1) {
                row.classList.add('service-log-draft-row');
            } else {
                row.classList.remove('service-log-draft-row');
            }
        }
    });
}

function loadNextServiceLogSerialNumber(form) {
    if (!form) {
        return;
    }

    const input = form.querySelector('[name="serial_number"]');
    if (!input) {
        return;
    }

    $.getJSON('api/service_log_next_serial.php')
        .done(function (res) {
            if (res && res.serial_number) {
                input.value = res.serial_number;
            }
        });
}

function fillServiceLogForm(record, options) {
    options = options || {};
    const form = document.getElementById('serviceLogForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('serviceLogId').value = record.id || '';
    const isDraft = Number(record.is_draft) === 1;
    document.getElementById('formModeLabel').textContent = record.id
        ? (isDraft ? 'Edit Draft Service Log' : 'Edit Service Log')
        : 'New Service Log';
    document.getElementById('submitServiceLogBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Service Log'
        : '<i class="bi bi-check-lg"></i> Submit Service Log';

    updateServiceLogDraftButtonState(record);

    const $select = $('#installedBaseLinkSelect');
    if ($select.length && record.installed_base_id) {
        const label = '#' + record.installed_base_id + ' - ' + record.order_id;
        const option = new Option(label, record.installed_base_id, true, true);
        $select.append(option).trigger('change');
    }

    const fields = [
        'order_id', 'fab_number', 'serial_number', 'machine_model', 'warranty_chargeable',
        'complaint_date', 'issue_description', 'engineer_name', 'visit_date',
        'action_taken', 'closure_date',
        'separator_remaining_date', 'separator_remaining_hours',
        'air_filter_remaining_date', 'air_filter_remaining_hours',
        'oil_filter_remaining_date', 'oil_filter_remaining_hours',
        'oil_remaining_date', 'oil_remaining_hours',
        'valve_kit_remaining_date', 'valve_kit_remaining_hours',
        'grease_remaining_date', 'grease_remaining_hours'
    ];

    fields.forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = record[field] ?? '';
        }
    });

    setStaticSelect2Value('serviceLogWarrantySelect', record.warranty_chargeable || '');
    setStaticSelect2Value('serviceLogPartReplacedSelect', record.part_replaced || '');

    const runningHoursInput = form.querySelector('[name="running_hours"]');
    if (runningHoursInput) {
        runningHoursInput.value = record.running_hours != null ? String(record.running_hours) : '';
    }

    if (slPartReplacementModule) {
        slPartReplacementModule.toggle(record.part_replaced || '');

        const entries = Array.isArray(record.part_replacement_entries) ? record.part_replacement_entries : [];
        if (entries.length) {
            slPartReplacementModule.loadEntries(entries);
        } else if (String(record.part_replaced || '').trim().toLowerCase() === 'yes') {
            slPartReplacementModule.loadEntries([]);
        }
    }

    if (serviceLogFeedbackRating) {
        serviceLogFeedbackRating.set(record.customer_feedback || '');
    }

    const remarksInput = form.querySelector('[name="remarks"]');
    if (remarksInput) {
        remarksInput.value = record.remarks ?? '';
    }

    const returnInstalledBaseInput = document.getElementById('serviceLogReturnInstalledBaseId');
    if (returnInstalledBaseInput) {
        returnInstalledBaseInput.value = options.returnInstalledBaseId
            ? String(options.returnInstalledBaseId)
            : '';
    }
}

function resetServiceLogForm() {
    const form = document.getElementById('serviceLogForm');
    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('serviceLogId').value = '';
    const returnInstalledBaseInput = document.getElementById('serviceLogReturnInstalledBaseId');
    if (returnInstalledBaseInput) {
        returnInstalledBaseInput.value = '';
    }
    document.getElementById('formModeLabel').textContent = 'New Service Log';
    document.getElementById('submitServiceLogBtn').innerHTML = '<i class="bi bi-check-lg"></i> Submit Service Log';
    updateServiceLogDraftButtonState(null);

    resetInstalledBaseLinkSelect2(form);
    resetStaticSelect2Fields([
        'serviceLogWarrantySelect',
        'serviceLogPartReplacedSelect'
    ]);

    if (serviceLogFeedbackRating) {
        serviceLogFeedbackRating.reset();
    }

    if (slPartReplacementModule) {
        slPartReplacementModule.reset();
    }

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
    form.querySelectorAll('.select2-selection.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });

    const draftFlag = document.getElementById('submitServiceLogDraftFlag');
    if (draftFlag) {
        draftFlag.disabled = true;
        draftFlag.value = '';
    }

    const draftBtn = document.getElementById('saveServiceLogDraftBtn');
    if (draftBtn) {
        draftBtn.classList.remove('disabled_btn');
        draftBtn.style.display = '';
    }
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

function initServiceLogStaticSelect2() {
    initStaticSelect2Fields('serviceLogForm', [
        {
            selectId: 'serviceLogWarrantySelect',
            validationField: 'warranty_chargeable',
            allowClear: false,
            noResultsText: 'No service type found'
        },
        {
            selectId: 'serviceLogPartReplacedSelect',
            validationField: 'part_replaced',
            allowClear: false,
            noResultsText: 'No option found'
        }
    ]);
}

function decodeServiceLogBase64Id(value) {
    if (!value) {
        return 0;
    }

    try {
        const normalized = String(value).replace(/-/g, '+').replace(/_/g, '/');
        const decoded = window.atob(normalized);
        const id = parseInt(decoded, 10);

        return Number.isFinite(id) && id > 0 ? id : 0;
    } catch (error) {
        return 0;
    }
}

function getServiceLogDraftEditConfig() {
    const pageConfig = window.serviceLogPageConfig || {};
    let editDraftId = parseInt(pageConfig.editDraftId, 10);
    let returnInstalledBaseId = parseInt(pageConfig.returnInstalledBaseId, 10);

    if (editDraftId > 0 && returnInstalledBaseId > 0) {
        return {
            editDraftId: editDraftId,
            returnInstalledBaseId: returnInstalledBaseId
        };
    }

    const params = new URLSearchParams(window.location.search);
    editDraftId = decodeServiceLogBase64Id(params.get('edit_draft'));
    returnInstalledBaseId = decodeServiceLogBase64Id(params.get('return_ib'));

    return {
        editDraftId: editDraftId,
        returnInstalledBaseId: returnInstalledBaseId
    };
}

function initServiceLogInstalledBaseDraftEdit() {
    const config = getServiceLogDraftEditConfig();
    const editDraftId = parseInt(config.editDraftId, 10);
    const returnInstalledBaseId = parseInt(config.returnInstalledBaseId, 10);

    if (!editDraftId) {
        return;
    }

    $.getJSON('api/service_log_get.php', { id: editDraftId })
        .done(function (record) {
            if (Number(record.is_draft) !== 1) {
                alert('Only draft service logs can be edited from Installed Base Details.');
                return;
            }

            resetServiceLogForm();
            fillServiceLogForm(record, {
                returnInstalledBaseId: returnInstalledBaseId > 0 ? returnInstalledBaseId : 0
            });
            openServiceLogForm();

            const card = document.getElementById('serviceLogFormCard');
            if (card && typeof card.scrollIntoView === 'function') {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        })
        .fail(function (xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.error
                ? xhr.responseJSON.error
                : 'Unable to load draft service log.';
            alert(message);
        });
}

function initServiceLogPage() {
    initServiceLogFeedbackRating();
    initServiceLogPartReplacementModule();
    const table = initServiceLogDatatable();
    initServiceLogInstalledBaseSelect2();
    initServiceLogStaticSelect2();
    initServiceLogFormValidation();
    initServiceLogDraftSave();

    const openBtn = document.getElementById('openServiceLogForm');
    const closeBtn = document.getElementById('closeServiceLogForm');

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            const form = document.getElementById('serviceLogForm');
            resetServiceLogForm();
            loadNextServiceLogSerialNumber(form);
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

    initServiceLogInstalledBaseDraftEdit();
}