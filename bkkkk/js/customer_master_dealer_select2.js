function customerMasterDealerSelectConfigs() {
    return [
        {
            formId: 'customerMasterForm',
            selectId: 'customerMasterDealerSelect',
            nameHiddenId: 'customerMasterDealerName',
            codeLockedId: 'customerMasterDealerCodeLocked'
        },
        {
            formId: 'installedBaseAddCustomerForm',
            selectId: 'installedBaseCustomerModalDealerSelect',
            nameHiddenId: 'installedBaseCustomerModalDealerName',
            codeLockedId: 'installedBaseCustomerModalDealerCodeLocked'
        }
    ];
}

function getCustomerMasterDealerContext() {
    const ctx = window.customerMasterDealerContext;
    if (!ctx || !ctx.code) {
        return null;
    }
    return {
        code: String(ctx.code || '').trim(),
        name: String(ctx.name || ctx.code || '').trim(),
        text: String(ctx.text || ((ctx.name || ctx.code) + ' - [' + ctx.code + ']')).trim(),
        locked: ctx.locked !== false
    };
}

function ensureCustomerMasterDealerCodeHidden(selectId, codeLockedId, code) {
    const select = document.getElementById(selectId);
    if (!select || !select.parentNode) {
        return;
    }

    let hidden = document.getElementById(codeLockedId);
    if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'dealer_code';
        hidden.id = codeLockedId;
        select.parentNode.insertBefore(hidden, select.nextSibling);
    }
    hidden.value = code || '';
}

function removeCustomerMasterDealerCodeHidden(codeLockedId) {
    const hidden = document.getElementById(codeLockedId);
    if (hidden) {
        hidden.remove();
    }
}

function setCustomerMasterDealerName(nameHiddenId, value) {
    const input = document.getElementById(nameHiddenId);
    if (input) {
        input.value = value || '';
    }
}

function setCustomerMasterDealerSelect2Locked(selectId, codeLockedId, locked) {
    const $select = $('#' + selectId);
    if (!$select.length) {
        return;
    }

    const code = String($select.val() || '').trim();

    if (locked && code !== '') {
        ensureCustomerMasterDealerCodeHidden(selectId, codeLockedId, code);
        $select.prop('disabled', true);
        $select.data('locked', true);
    } else {
        removeCustomerMasterDealerCodeHidden(codeLockedId);
        $select.prop('disabled', false);
        $select.data('locked', false);
    }
}

function setCustomerMasterDealerSelect2(selectId, nameHiddenId, codeLockedId, code, name, text, options) {
    const $select = $('#' + selectId);
    options = options || {};

    if (!$select.length) {
        return;
    }

    if (!code) {
        setCustomerMasterDealerSelect2Locked(selectId, codeLockedId, false);
        $select.val(null).trigger('change');
        setCustomerMasterDealerName(nameHiddenId, '');
        $select.removeClass('is-invalid');
        return;
    }

    const label = text || ((name || code) + ' - [' + code + ']');
    const escaped = String(code).replace(/"/g, '\\"');
    if ($select.find('option[value="' + escaped + '"]').length === 0) {
        $select.append(new Option(label, code, true, true));
    }

    $select.val(code).trigger('change');
    setCustomerMasterDealerName(nameHiddenId, name || code);
    $select.removeClass('is-invalid');
    setCustomerMasterDealerSelect2Locked(selectId, codeLockedId, !!options.locked);
}

function resetCustomerMasterDealerSelect2(selectId, nameHiddenId, codeLockedId) {
    const ctx = getCustomerMasterDealerContext();
    if (ctx && ctx.locked) {
        setCustomerMasterDealerSelect2(
            selectId,
            nameHiddenId,
            codeLockedId,
            ctx.code,
            ctx.name,
            ctx.text,
            { locked: true }
        );
        return;
    }

    setCustomerMasterDealerSelect2(selectId, nameHiddenId, codeLockedId, '', '', '', { locked: false });
}

function applyCustomerMasterDealerContextToSelect(selectId, nameHiddenId, codeLockedId) {
    const ctx = getCustomerMasterDealerContext();
    if (!ctx) {
        return;
    }
    setCustomerMasterDealerSelect2(
        selectId,
        nameHiddenId,
        codeLockedId,
        ctx.code,
        ctx.name,
        ctx.text,
        { locked: !!ctx.locked }
    );
}

function initCustomerMasterDealerSelect2(selectId, nameHiddenId, codeLockedId, options) {
    const $select = $('#' + selectId);
    options = options || {};
    if (!$select.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }

    const select2Options = {
        width: '100%',
        placeholder: $select.data('placeholder') || 'Search dealer',
        allowClear: !getCustomerMasterDealerContext(),
        minimumInputLength: 0,
        ajax: {
            url: 'api/customer_master_dealers_search.php',
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
                return 'No dealer found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    };

    if (options.dropdownParent) {
        select2Options.dropdownParent = options.dropdownParent;
    }

    $select.select2(select2Options);

    $select.off('select2:opening.cmDealer select2:unselecting.cmDealer select2:select.cmDealer select2:clear.cmDealer');

    $select.on('select2:opening.cmDealer select2:unselecting.cmDealer', function (e) {
        if ($select.data('locked')) {
            e.preventDefault();
        }
    });

    $select.on('select2:select.cmDealer', function (e) {
        const data = e.params.data || {};
        setCustomerMasterDealerName(nameHiddenId, data.name || data.text || '');
        $select.removeClass('is-invalid');

        const form = $select.closest('form').get(0);
        if (form) {
            const msg = form.querySelector('.validation-msg[data-field="dealer_code"]');
            if (msg) {
                msg.textContent = '';
            }
        }
    });

    $select.on('select2:clear.cmDealer', function () {
        setCustomerMasterDealerName(nameHiddenId, '');
        $select.removeClass('is-invalid');
    });

    applyCustomerMasterDealerContextToSelect(selectId, nameHiddenId, codeLockedId);
}

function initAllCustomerMasterDealerSelect2() {
    customerMasterDealerSelectConfigs().forEach(function (cfg) {
        if (document.getElementById(cfg.selectId)) {
            initCustomerMasterDealerSelect2(cfg.selectId, cfg.nameHiddenId, cfg.codeLockedId);
        }
    });
}

function getCustomerMasterDealerFormValue(form) {
    if (!form) {
        return '';
    }
    const locked = form.querySelector('[id$="DealerCodeLocked"]');
    if (locked && String(locked.value || '').trim() !== '') {
        return String(locked.value).trim();
    }
    const select = form.querySelector('[name="dealer_code"]');
    return select ? String(select.value || '').trim() : '';
}
