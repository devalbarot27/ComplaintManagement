function setComplaintCustomerSelect2(id, text) {
    const $customer = $('#complaintCustomerSelect');
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

function resetComplaintCustomerSelect2() {
    setComplaintCustomerSelect2('', '');
}

function initComplaintCustomerSelect2() {
    const form = document.getElementById('complaintForm');
    const $customer = $('#complaintCustomerSelect');

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

function saveComplaintFormDraftBeforeCustomerRedirect() {
    const form = document.getElementById('complaintForm');
    if (!form || typeof sessionStorage === 'undefined') {
        return;
    }

    const draft = {
        fab_number: (form.querySelector('[name="fab_number"]') || {}).value || '',
        complaint_category_id: (form.querySelector('[name="complaint_category_id"]') || {}).value || '',
        complaint_category_name: (form.querySelector('[name="complaint_category_name"]') || {}).value || '',
        complaint_description: (form.querySelector('[name="complaint_description"]') || {}).value || '',
        assign_complaint: (form.querySelector('[name="assign_complaint"]') || {}).value || '',
        remarks: (form.querySelector('[name="remarks"]') || {}).value || ''
    };

    sessionStorage.setItem('complaint_form_draft', JSON.stringify(draft));
}

function restoreComplaintFormDraft() {
    if (typeof sessionStorage === 'undefined') {
        return null;
    }

    const raw = sessionStorage.getItem('complaint_form_draft');
    if (!raw) {
        return null;
    }

    try {
        const draft = JSON.parse(raw);
        sessionStorage.removeItem('complaint_form_draft');
        return draft && typeof draft === 'object' ? draft : null;
    } catch (e) {
        sessionStorage.removeItem('complaint_form_draft');
        return null;
    }
}

function applyComplaintFormDraft(draft) {
    const form = document.getElementById('complaintForm');
    if (!form || !draft) {
        return;
    }

    if (draft.fab_number && typeof setFabNumberSelect2ById === 'function') {
        setFabNumberSelect2ById('complaintFabNumberSelect', draft.fab_number, draft.fab_number);
    } else if (draft.fab_number) {
        const $fab = $('#complaintFabNumberSelect');
        if ($fab.length) {
            const option = new Option(draft.fab_number, draft.fab_number, true, true);
            $fab.append(option).trigger('change');
        }
    }

    if (draft.complaint_category_id && typeof setStaticSelect2Value === 'function') {
        setStaticSelect2Value('complaintCategorySelect', draft.complaint_category_id);
        const nameField = document.getElementById('complaintCategoryName');
        if (nameField) {
            nameField.value = draft.complaint_category_name || '';
        }
    }

    ['complaint_description', 'remarks'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input && draft[field] != null) {
            input.value = draft[field];
        }
    });

    if (draft.assign_complaint && typeof setAssignToSelect2Value === 'function') {
        setAssignToSelect2Value('complaintAssignToSelect', draft.assign_complaint, draft.assign_complaint);
    } else if (draft.assign_complaint) {
        const assignSelect = form.querySelector('[name="assign_complaint"]');
        if (assignSelect) {
            assignSelect.value = draft.assign_complaint;
            $(assignSelect).trigger('change');
        }
    }
}

function initComplaintAddNewCustomerButton() {
    const btn = document.getElementById('addNewCustomerFromComplaintBtn');
    if (!btn) {
        return;
    }

    btn.addEventListener('click', function () {
        saveComplaintFormDraftBeforeCustomerRedirect();
        const returnUrl = 'new_complaint.php?open_form=1';
        window.location.href = 'customer_master.php?open_form=1&return_url=' + encodeURIComponent(returnUrl);
    });
}
