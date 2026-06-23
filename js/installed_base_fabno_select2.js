function setInstalledBaseInvoiceDate(form, invoiceDate) {
    if (!form) {
        return;
    }

    const input = form.querySelector('[name="invoice_date"]');
    if (!input) {
        return;
    }

    input.value = invoiceDate || '';
    input.classList.remove('is-invalid');

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
    }
}

function initInstalledBaseFabnoSelect2() {
    initFabnoSelect2('installedBaseForm', 'fabNumberSelect', {
        onSelect: function (data, form) {
            setInstalledBaseInvoiceDate(form, data.invoice_date || '');
        },
        onClear: function (form) {
            setInstalledBaseInvoiceDate(form, '');
        }
    });
}
