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
            setInstalledBaseInvoiceDate(form, data.invoice_date || '');
            // Prefill decides Machine Model lock from Installed Base existence.
            prefillInstalledBaseFromFab(form, data.id);
        },
        onClear: function (form) {
            setInstalledBaseInvoiceDate(form, '');
            resetInstalledBaseFabAutoFields(form);
        }
    });
}