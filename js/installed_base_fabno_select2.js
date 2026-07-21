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
            prefillInstalledBaseFromFab(form, data.id);

            const machineModelCode = String(data.machine_model_code || '').trim();
            const machineModelDesc = String(data.machine_model || '').trim();
            if (machineModelCode !== '') {
                setMachineModelSelect2(machineModelCode, machineModelDesc, { locked: true });
            } else {
                setMachineModelSelect2Locked(false);
            }
        },
        onClear: function (form) {
            setInstalledBaseInvoiceDate(form, '');
            resetInstalledBaseFabAutoFields(form);
        }
    });
}