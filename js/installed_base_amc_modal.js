function ibAmcSetInputValue(id, value) {
    const input = document.getElementById(id);
    if (input) {
        input.value = value || '';
    }
}

function ibAmcClearValidation() {
    const form = document.getElementById('installedBaseAmcForm');
    if (!form) {
        return;
    }

    form.querySelectorAll('.validation-msg').forEach(function (msg) {
        msg.textContent = '';
    });
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });

    const errorBox = document.getElementById('ibAmcFormError');
    if (errorBox) {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    }
}

function ibAmcShowFormError(message) {
    const errorBox = document.getElementById('ibAmcFormError');
    if (!errorBox) {
        return;
    }

    errorBox.textContent = message || '';
    errorBox.classList.toggle('d-none', !message);
}

function ibAmcSetWarrantyBadge(status, badgeClass) {
    const badge = document.getElementById('ibAmcWarrantyBadge');
    if (!badge) {
        return;
    }

    badge.className = 'warranty-status-badge ' + (badgeClass || 'warranty-status--unknown');
    badge.textContent = status || '';
    badge.style.display = status ? 'inline-block' : 'none';
}

function resetInstalledBaseAmcForm() {
    const form = document.getElementById('installedBaseAmcForm');
    if (!form) {
        return;
    }

    form.reset();
    ibAmcSetInputValue('ibAmcInstalledBaseId', '');
    [
        'ibAmcInstalledBaseLabel',
        'ibAmcFabNumber',
        'ibAmcEquipmentModel',
        'ibAmcWarrantyStatus',
        'ibAmcCustomerName',
        'ibAmcCustomerMobile',
        'ibAmcCustomerEmail',
        'ibAmcCustomerStreet1',
        'ibAmcCustomerStreet2',
        'ibAmcCustomerPincode',
        'ibAmcCustomerCity',
        'ibAmcCustomerDistrict',
        'ibAmcCustomerState'
    ].forEach(function (id) {
        ibAmcSetInputValue(id, '');
    });
    ibAmcSetWarrantyBadge('', '');
    ibAmcClearValidation();
}

function fillInstalledBaseAmcForm(data) {
    data = data || {};
    ibAmcSetInputValue('ibAmcInstalledBaseId', data.installed_base_id || data.id || '');
    ibAmcSetInputValue('ibAmcInstalledBaseLabel', data.text || '');
    ibAmcSetInputValue('ibAmcFabNumber', data.fab_number || '');
    ibAmcSetInputValue('ibAmcEquipmentModel', data.product_model || data.machine_model || '');
    ibAmcSetInputValue('ibAmcWarrantyStatus', data.warranty_status || '');
    ibAmcSetInputValue('ibAmcCustomerName', data.customer_name || '');
    ibAmcSetInputValue('ibAmcCustomerMobile', data.telephone_number || '');
    ibAmcSetInputValue('ibAmcCustomerEmail', data.email_id || '');
    ibAmcSetInputValue('ibAmcCustomerStreet1', data.address_line1 || '');
    ibAmcSetInputValue('ibAmcCustomerStreet2', data.address_line2 || '');
    ibAmcSetInputValue('ibAmcCustomerPincode', data.post_code || '');
    ibAmcSetInputValue('ibAmcCustomerCity', data.city_name || '');
    ibAmcSetInputValue('ibAmcCustomerDistrict', data.district_name || '');
    ibAmcSetInputValue('ibAmcCustomerState', data.state_name || '');
    ibAmcSetWarrantyBadge(data.warranty_status || '', data.warranty_badge_class || '');
}

function ibAmcParseJsonResponse(response) {
    return response.text().then(function (text) {
        let payload = {};
        if (text) {
            try {
                payload = JSON.parse(text);
            } catch (e) {
                payload = { error: text };
            }
        }
        if (!response.ok) {
            const error = new Error(payload.error || payload.message || 'Request failed.');
            error.payload = payload;
            throw error;
        }
        return payload;
    });
}

function showInstalledBaseAmcPageAlert(type, message) {
    if (typeof showInstalledBasePageAlert === 'function') {
        showInstalledBasePageAlert(type, message);
        return;
    }

    const content = document.querySelector('.content');
    if (!content || !message) {
        return;
    }

    content.querySelectorAll('.installed-base-ajax-alert').forEach(function (el) {
        el.remove();
    });

    const wrapper = document.createElement('div');
    wrapper.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-danger')
        + ' alert-dismissible fade show mb-3 installed-base-ajax-alert';
    wrapper.setAttribute('role', 'alert');
    wrapper.appendChild(document.createTextNode(message));

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('data-bs-dismiss', 'alert');
    wrapper.appendChild(closeBtn);

    content.insertBefore(wrapper, content.firstChild);

    if (type === 'success') {
        setTimeout(function () {
            wrapper.remove();
        }, 3000);
    }
}

function ibAmcValidateForm(form) {
    let valid = true;
    ibAmcClearValidation();

    const installedBaseId = (document.getElementById('ibAmcInstalledBaseId') || {}).value;
    if (!installedBaseId) {
        const msg = form.querySelector('.validation-msg[data-field="installed_base_id"]');
        if (msg) {
            msg.textContent = 'Installed base record is required.';
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
            if (input) {
                input.classList.add('is-invalid');
            }
            valid = false;
        }
    });

    return valid;
}

function openInstalledBaseAmcModal(installedBaseId) {
    const modalEl = document.getElementById('installedBaseAmcModal');
    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
    }

    const id = parseInt(installedBaseId, 10) || 0;
    if (id <= 0) {
        showInstalledBaseAmcPageAlert('error', 'Invalid installed base record.');
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    resetInstalledBaseAmcForm();

    fetch('api/amc_installed_base_prefill.php?id=' + encodeURIComponent(String(id)), {
        headers: { Accept: 'application/json' }
    })
        .then(ibAmcParseJsonResponse)
        .then(function (data) {
            fillInstalledBaseAmcForm(data);
            modal.show();
        })
        .catch(function (error) {
            showInstalledBaseAmcPageAlert('error', error.message || 'Unable to load installed base details.');
        });
}

function initInstalledBaseAmcModal() {
    const modalEl = document.getElementById('installedBaseAmcModal');
    const form = document.getElementById('installedBaseAmcForm');
    if (!modalEl || !form) {
        return;
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
        resetInstalledBaseAmcForm();
    });

    document.addEventListener('click', function (event) {
        const btn = event.target.closest('.add-amc-contract-btn');
        if (!btn) {
            return;
        }
        event.preventDefault();
        openInstalledBaseAmcModal(btn.getAttribute('data-id'));
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (!ibAmcValidateForm(form)) {
            return;
        }

        const submitButton = document.getElementById('ibAmcSubmitBtn');
        if (submitButton) {
            submitButton.classList.add('disabled_btn');
            submitButton.disabled = true;
        }

        fetch('api/amc_create.php', {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json' }
        })
            .then(ibAmcParseJsonResponse)
            .then(function (payload) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }
                if (window.location.pathname.indexOf('installed_base_details.php') !== -1) {
                    window.location.reload();
                    return;
                }
                showInstalledBaseAmcPageAlert('success', payload.message || 'AMC contract registered successfully.');
            })
            .catch(function (error) {
                ibAmcShowFormError(error.message || 'Failed to save AMC contract.');
            })
            .finally(function () {
                if (submitButton) {
                    submitButton.classList.remove('disabled_btn');
                    submitButton.disabled = false;
                }
            });
    });
}

document.addEventListener('DOMContentLoaded', initInstalledBaseAmcModal);
