function applyServiceLogWarrantyAmcFields(form, data) {
    if (!form) {
        return;
    }

    data = data || {};
    const warranty = String(data.warranty_status || '').trim();
    const endHeading = String(data.warranty_end_date_heading || 'Warranty Ended On').trim()
        || 'Warranty Ended On';
    const endLabel = String(data.warranty_end_date_label || '').trim();
    const underRaw = String(data.under_amc || '').trim();
    const underAmc = underRaw === 'Yes' ? 'Yes' : (underRaw === 'No' ? 'No' : '');
    const amcEnd = String(data.amc_end_date_label || '').trim();

    const warrantyInput = form.querySelector('[name="machine_warranty"]');
    if (warrantyInput) {
        warrantyInput.value = warranty;
    }

    const endInput = form.querySelector('[name="warranty_ended_on"]');
    const endWrap = form.querySelector('.service-log-warranty-end-wrap');
    const endLabelEl = form.querySelector('.service-log-warranty-end-label');
    if (endLabelEl) {
        endLabelEl.innerHTML = '<i class="bi bi-calendar-x"></i> ';
        endLabelEl.appendChild(document.createTextNode(' ' + endHeading));
    }
    if (endInput) {
        endInput.value = endLabel;
    }
    if (endWrap) {
        endWrap.classList.toggle('d-none', endLabel === '');
    }

    const underInput = form.querySelector('[name="under_amc"]');
    if (underInput) {
        underInput.value = underAmc;
    }

    const amcInput = form.querySelector('[name="amc_end_date"]');
    const amcWrap = form.querySelector('.service-log-amc-end-wrap');
    const showAmcEnd = underAmc === 'Yes' && amcEnd !== '';
    if (amcInput) {
        amcInput.value = showAmcEnd ? amcEnd : '';
    }
    if (amcWrap) {
        amcWrap.classList.toggle('d-none', !showAmcEnd);
    }
}

function clearServiceLogWarrantyAmcFields(form) {
    if (!form) {
        return;
    }

    applyServiceLogWarrantyAmcFields(form, {
        warranty_status: '',
        warranty_end_date_heading: 'Warranty Ended On',
        warranty_end_date_label: '',
        under_amc: '',
        amc_end_date_label: ''
    });
}