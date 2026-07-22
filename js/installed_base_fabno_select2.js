function setInstalledBaseInvoiceDate(form, invoiceDate) {
    if (!form) {
        return;
    }

    const input = form.querySelector('[name="invoice_date"]');
    if (!input) {
        return;
    }

    const value = String(invoiceDate || '').trim();
    input.value = value;
    input.classList.remove('is-invalid');

    // Auto-filled dates stay readonly; empty date is editable for manual entry
    if (value !== '') {
        input.setAttribute('readonly', 'readonly');
    } else {
        input.removeAttribute('readonly');
    }

    const msg = form.querySelector('.validation-msg[data-field="invoice_date"]');
    if (msg) {
        msg.textContent = '';
    }
}

function setInstalledBaseFabSelect2(fabNumber) {
    setFabNumberSelect2('fabNumberSelect', 'installedBaseForm', fabNumber);
}

function resetFabNumberSelect2() {
    resetFabNumberSelect2ById('fabNumberSelect');

    const form = document.getElementById('installedBaseForm');
    if (form) {
        setInstalledBaseInvoiceDate(form, '');
        resetInstalledBaseFabAutoFields(form);
    }
}

function initInstalledBaseFabnoSelect2() {
    initFabnoSelect2('installedBaseForm', 'fabNumberSelect', {
        onSelect: function (data, form) {
            // Ownership is checked inside prefill; only fill invoice date when allowed.
            prefillInstalledBaseFromFab(form, data.id).done(function (result) {
                if (result && result.allowed) {
                    setInstalledBaseInvoiceDate(form, data.invoice_date || '');
                    return;
                }

                setInstalledBaseInvoiceDate(form, '');
            });
        },
        onClear: function (form) {
            setInstalledBaseInvoiceDate(form, '');
            resetInstalledBaseFabAutoFields(form);
            if (typeof window.clearInstalledBaseFabOwnershipError === 'function') {
                window.clearInstalledBaseFabOwnershipError();
            }
        }
    });
}