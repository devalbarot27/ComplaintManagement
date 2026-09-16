function amcAutoFieldIds() {
    return [
        'amcFabNumber',
        'amcEquipmentModel',
        'amcWarrantyStatus',
        'amcCustomerName',
        'amcCustomerMobile',
        'amcCustomerEmail',
        'amcCustomerStreet1',
        'amcCustomerStreet2',
        'amcCustomerPincode',
        'amcCustomerCity',
        'amcCustomerDistrict',
        'amcCustomerState',
        'amcUnderAmc',
        'amcExistingEndDate'
    ];
}

function setAmcWarrantyBadge(status, badgeClass) {
    const badge = document.getElementById('amcWarrantyBadge');
    if (!badge) {
        return;
    }

    badge.className = 'warranty-status-badge ' + (badgeClass || 'warranty-status--unknown');
    badge.textContent = status || '';
    badge.style.display = status ? '' : 'none';
}

function setAmcInstalledBaseFields(data) {
    const mapping = {
        amcFabNumber: data.fab_number || '',
        amcEquipmentModel: data.product_model || data.machine_model || '',
        amcWarrantyStatus: data.warranty_status || '',
        amcCustomerName: data.customer_name || '',
        amcCustomerMobile: data.telephone_number || '',
        amcCustomerEmail: data.email_id || '',
        amcCustomerStreet1: data.address_line1 || '',
        amcCustomerStreet2: data.address_line2 || '',
        amcCustomerPincode: data.post_code || '',
        amcCustomerCity: data.city_name || '',
        amcCustomerDistrict: data.district_name || '',
        amcCustomerState: data.state_name || '',
        amcUnderAmc: data.under_amc || '',
        amcExistingEndDate: data.under_amc === 'Yes' ? (data.amc_end_date_label || '') : ''
    };

    Object.keys(mapping).forEach(function (id) {
        const input = document.getElementById(id);
        if (input) {
            input.value = mapping[id];
        }
    });

    setAmcWarrantyBadge(data.warranty_status || '', data.warranty_badge_class || '');

    const msg = document.querySelector('#amcForm .validation-msg[data-field="installed_base_id"]');
    if (msg) {
        msg.textContent = '';
    }
}

function clearAmcInstalledBaseFields() {
    amcAutoFieldIds().forEach(function (id) {
        const input = document.getElementById(id);
        if (input) {
            input.value = '';
        }
    });
    setAmcWarrantyBadge('', '');
}

function resetAmcInstalledBaseSelect() {
    const $select = $('#amcInstalledBaseSelect');
    if ($select.length) {
        $select.val(null).trigger('change');
    }
    clearAmcInstalledBaseFields();
}

function initAmcInstalledBaseSelect2() {
    const $select = $('#amcInstalledBaseSelect');
    if (!$select.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    $select.select2({
        width: '100%',
        dropdownParent: $('#amcFormCard'),
        placeholder: $select.data('placeholder') || 'Search by FAB number, customer or model',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: 'api/amc_installed_base_search.php',
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
                return 'No installed base machine found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $select.on('select2:select', function (e) {
        setAmcInstalledBaseFields(e.params.data || {});
        $select.removeClass('is-invalid');
    });

    $select.on('select2:clear', function () {
        clearAmcInstalledBaseFields();
    });
}

function initAmcTypeSelect2() {
    const $select = $('#amcType');
    if (!$select.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    $select.select2({
        width: '100%',
        dropdownParent: $('#amcFormCard'),
        placeholder: $select.data('placeholder') || 'Select AMC type',
        allowClear: true
    });

    $select.on('select2:select select2:clear', function () {
        $select.removeClass('is-invalid');
        const msg = document.querySelector('#amcForm .validation-msg[data-field="amc_type"]');
        if (msg) {
            msg.textContent = '';
        }
    });
}

function resetAmcTypeSelect() {
    const $select = $('#amcType');
    if ($select.length) {
        $select.val(null).trigger('change');
    }
}

function restoreAmcInstalledBaseSelection(preselected) {
    const $select = $('#amcInstalledBaseSelect');
    if (!$select.length || !preselected || !preselected.id) {
        return;
    }

    const option = new Option(preselected.text || ('#' + preselected.id), preselected.id, true, true);
    $select.append(option).trigger('change');
    setAmcInstalledBaseFields(preselected);
}

function initAmcFormToggle() {
    const openBtn = document.getElementById('openAmcForm');
    const closeBtn = document.getElementById('closeAmcForm');
    const cancelBtn = document.getElementById('cancelAmcForm');
    const formCard = document.getElementById('amcFormCard');
    const tableCard = document.getElementById('amcTableCard');
    const form = document.getElementById('amcForm');

    function showForm() {
        if (!formCard) {
            return;
        }
        formCard.style.display = 'block';
        if (tableCard) {
            tableCard.style.display = 'none';
        }
        if (openBtn) {
            openBtn.style.display = 'none';
        }
        if (closeBtn) {
            closeBtn.style.display = '';
        }
        ['#amcInstalledBaseSelect', '#amcType'].forEach(function (selector) {
            const $select = $(selector);
            if ($select.length && $select.hasClass('select2-hidden-accessible')) {
                $select.next('.select2-container').css('width', '100%');
            }
        });
        formCard.scrollIntoView({ behavior: 'smooth' });
    }

    function hideForm() {
        if (!formCard) {
            return;
        }
        formCard.style.display = 'none';
        if (tableCard) {
            tableCard.style.display = 'block';
        }
        if (openBtn) {
            openBtn.style.display = '';
        }
        if (closeBtn) {
            closeBtn.style.display = 'none';
        }
        if (form) {
            form.reset();
        }
        resetAmcInstalledBaseSelect();
        resetAmcTypeSelect();
        document.querySelectorAll('#amcForm .validation-msg').forEach(function (msg) {
            msg.textContent = '';
        });
    }

    if (openBtn) {
        openBtn.addEventListener('click', showForm);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', hideForm);
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', hideForm);
    }
}

function initAmcFormValidation() {
    const form = document.getElementById('amcForm');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function (e) {
        let valid = true;
        form.querySelectorAll('.validation-msg').forEach(function (msg) {
            msg.textContent = '';
        });

        const installedBaseId = $('#amcInstalledBaseSelect').val();
        if (!installedBaseId) {
            const msg = form.querySelector('.validation-msg[data-field="installed_base_id"]');
            if (msg) {
                msg.textContent = 'Please search for and select an Installed Base machine.';
            }
            valid = false;
        }

        ['amc_type', 'amc_value', 'amc_start_date', 'amc_end_date', 'visit_start_date', 'no_of_visits'].forEach(function (field) {
            const input = form.querySelector('[name="' + field + '"]');
            if (!input || String(input.value || '').trim() === '') {
                const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
                if (msg) {
                    msg.textContent = 'This field is required.';
                }
                valid = false;
            }
        });

        if (!valid) {
            e.preventDefault();
        }
    });
}

$(function () {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#amcContractsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            language: {
                emptyTable: 'No AMC contracts registered yet.'
            }
        });
    }

    initAmcFormToggle();
    initAmcInstalledBaseSelect2();
    initAmcTypeSelect2();
    initAmcFormValidation();
    restoreAmcInstalledBaseSelection(window.amcPreselectedInstalledBase || null);
});