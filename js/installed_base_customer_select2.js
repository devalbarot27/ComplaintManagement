function setInstalledBaseCustomerSelect2(id, text) {
    const $customer = $('#installedBaseCustomerSelect');
    if (!$customer.length) {
        return;
    }

    $customer.find('option').remove();
    id = id != null ? String(id).trim() : '';
    text = text != null ? String(text).trim() : '';

    if (id !== '') {
        const option = new Option(text || id, id, true, true);
        $customer.append(option).trigger('change');
    } else {
        $customer.val(null).trigger('change');
    }
}

function resetInstalledBaseCustomerSelect2() {
    setInstalledBaseCustomerSelect2('', '');
}

function initInstalledBaseCustomerSelect2() {
    const form = document.getElementById('installedBaseForm');
    const $customer = $('#installedBaseCustomerSelect');

    if (!form || !$customer.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    $customer.select2({
        width: '100%',
        placeholder: $customer.data('placeholder') || 'Search customer',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: 'api/customer_masters_search.php',
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
            noResults: function () {
                return 'No customer found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $customer.on('select2:select select2:clear change', function () {
        $customer.removeClass('is-invalid');
        $customer.next('.select2-container').find('.select2-selection').removeClass('is-invalid');
        const msg = form.querySelector('.validation-msg[data-field="customer_id"]');
        if (msg) {
            msg.textContent = '';
        }
    });
}

function saveInstalledBaseFormDraftBeforeCustomerRedirect() {
    const form = document.getElementById('installedBaseForm');
    if (!form || typeof sessionStorage === 'undefined') {
        return;
    }

    const draft = {
        record_id: (document.getElementById('installedBaseId') || {}).value || '',
        return_complaint_id: (document.getElementById('returnComplaintId') || {}).value || '',
        fab_number: (form.querySelector('[name="fab_number"]') || {}).value || '',
        dealer_name: (form.querySelector('[name="dealer_name"]') || {}).value || '',
        machine_model_code: (form.querySelector('[name="machine_model_code"]') || {}).value || '',
        machine_model: (form.querySelector('[name="machine_model"]') || {}).value || '',
        invoice_date: (form.querySelector('[name="invoice_date"]') || {}).value || '',
        commissioning_date: (form.querySelector('[name="commissioning_date"]') || {}).value || '',
        running_hours: (form.querySelector('[name="running_hours"]') || {}).value || '',
        industry_segment: (form.querySelector('[name="industry_segment"]') || {}).value || '',
        remarks: (form.querySelector('[name="remarks"]') || {}).value || ''
    };

    sessionStorage.setItem('installed_base_form_draft', JSON.stringify(draft));
}

function restoreInstalledBaseFormDraft() {
    if (typeof sessionStorage === 'undefined') {
        return null;
    }

    const raw = sessionStorage.getItem('installed_base_form_draft');
    if (!raw) {
        return null;
    }

    try {
        const draft = JSON.parse(raw);
        sessionStorage.removeItem('installed_base_form_draft');
        return draft && typeof draft === 'object' ? draft : null;
    } catch (e) {
        sessionStorage.removeItem('installed_base_form_draft');
        return null;
    }
}

function applyInstalledBaseFormDraft(draft) {
    const form = document.getElementById('installedBaseForm');
    if (!form || !draft) {
        return;
    }

    if (draft.record_id) {
        document.getElementById('installedBaseId').value = draft.record_id;
        document.getElementById('formModeLabel').textContent = 'Edit Installed Base';
        document.getElementById('submitInstalledBaseBtn').innerHTML = '<i class="bi bi-check-lg"></i> Update Record';
    }

    const returnComplaintIdField = document.getElementById('returnComplaintId');
    if (returnComplaintIdField && draft.return_complaint_id) {
        returnComplaintIdField.value = draft.return_complaint_id;
    }

    if (draft.fab_number && typeof setInstalledBaseFabSelect2 === 'function') {
        setInstalledBaseFabSelect2(draft.fab_number);
    }

    setInstalledBaseDealerName(draft.dealer_name || getInstalledBaseDefaultDealerName());

    ['invoice_date', 'commissioning_date', 'running_hours', 'remarks'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input && draft[field] != null) {
            input.value = draft[field];
        }
    });

    if (typeof setStaticSelect2Value === 'function') {
        setStaticSelect2Value('industrySegmentSelect', draft.industry_segment || '');
    }

    if (typeof setMachineModelSelect2 === 'function') {
        setMachineModelSelect2(draft.machine_model_code || '', draft.machine_model || '', {
            locked: !!draft.record_id
        });
    }

    if (typeof setInstalledBaseInvoiceDate === 'function' && draft.invoice_date) {
        setInstalledBaseInvoiceDate(form, draft.invoice_date);
    }
}

function initInstalledBaseAddNewCustomerButton() {
    const btn = document.getElementById('addNewCustomerFromInstalledBaseBtn');
    if (!btn) {
        return;
    }

    btn.addEventListener('click', function () {
        saveInstalledBaseFormDraftBeforeCustomerRedirect();

        const returnUrl = 'installed_base.php?open_form=1';
        const target = 'customer_master.php?open_form=1&return_url=' + encodeURIComponent(returnUrl);
        window.location.href = target;
    });
}
