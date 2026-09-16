let activeComplaintIdForServiceLog = null;
let pendingServiceLogAction = null;
let pendingServiceLogId = null;
let pendingReopenServiceUpdate = null;
let skipServiceLogModalReset = false;
let openedServiceLogFromServiceUpdate = false;

window.serviceUpdateServiceLogState = {
    loaded: false,
    hasInstalledBase: false,
    hasServiceLog: false,
    isDraft: false,
    currentCycle: '',
    currentCycleLabel: ''
};

function resetServiceUpdateServiceLogState() {
    window.serviceUpdateServiceLogState = {
        loaded: false,
        hasInstalledBase: false,
        hasServiceLog: false,
        isDraft: false,
        currentCycle: '',
        currentCycleLabel: ''
    };
}

function updateServiceUpdateServiceLogState(data) {
    if (!data) {
        resetServiceUpdateServiceLogState();
        return;
    }

    const log = data.current_cycle_service_log || data.service_log || null;
    window.serviceUpdateServiceLogState = {
        loaded: true,
        hasInstalledBase: !!data.has_installed_base,
        hasServiceLog: !!(data.has_service_log && log),
        isDraft: !!(log && log.is_draft),
        currentCycle: data.current_cycle || '',
        currentCycleLabel: data.current_cycle_label || ''
    };
}

function clearServiceUpdateServiceLogValidation() {
    const section = document.getElementById('serviceUpdateServiceLogSection');
    const msg = document.getElementById('serviceUpdateServiceLogValidation');

    if (section) {
        section.classList.remove('is-invalid');
    }

    if (msg) {
        msg.textContent = '';
    }
}

function showServiceUpdateServiceLogValidation(message) {
    const section = document.getElementById('serviceUpdateServiceLogSection');
    const msg = document.getElementById('serviceUpdateServiceLogValidation');

    if (section) {
        section.classList.toggle('is-invalid', !!message);
    }

    if (msg) {
        msg.textContent = message || '';
    }
}

function validateServiceUpdateServiceLogRequired() {
    const section = document.getElementById('serviceUpdateServiceLogSection');
    if (!section) {
        return null;
    }

    const state = window.serviceUpdateServiceLogState || {};

    if (!state.loaded) {
        return 'Service log details are still loading. Please wait.';
    }

    if (!state.hasInstalledBase) {
        return 'A matching installed base is required before a service log can be added.';
    }

    
    // TODO: Uncomment this when we have a way to add service logs to complaints that don't have them yet.
    // 09-07-2026
    if (!state.hasServiceLog) {
        const cycleLabel = state.currentCycleLabel
            || (state.currentCycle === 'reopen' ? 'Re-Open' : 'In Progress');
        return 'A service log is required for the ' + cycleLabel + ' cycle before submitting the service update.';
    }
    
    if (state.isDraft) {
        return 'Please complete the service log before submitting the service update.';
    }

    return null;
}

function showComplaintServiceLogAlert(type, message) {
    const content = document.querySelector('.content');
    if (!content || !message) {
        return;
    }

    content.querySelectorAll('.complaint-service-log-ajax-alert').forEach(function (el) {
        el.remove();
    });

    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const wrapper = document.createElement('div');
    wrapper.className = 'alert ' + alertClass + ' alert-dismissible fade show mb-3 complaint-service-log-ajax-alert';
    wrapper.setAttribute('role', 'alert');

    const text = document.createElement('span');
    text.textContent = message;
    wrapper.appendChild(text);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('data-bs-dismiss', 'alert');
    wrapper.appendChild(closeBtn);

    content.insertBefore(wrapper, content.firstChild);

    if (type === 'success') {
        setTimeout(function () {
            $(wrapper).fadeOut(function () {
                wrapper.remove();
            });
        }, 3000);
    }
}

function escapeComplaintServiceLogHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
}

function buildInstalledBaseAddUrl(complaintId, fabNumber) {
    const params = new URLSearchParams();
    params.set('open_form', '1');
    if (complaintId) {
        params.set('complaint_id', String(complaintId));
    }
    fabNumber = String(fabNumber || '').trim();
    if (fabNumber !== '') {
        params.set('fab_number', fabNumber);
    }
    return 'installed_base.php?' + params.toString();
}

function normalizeInstalledBaseAddUrl(url) {
    return String(url || '').replace(/&amp;/gi, '&');
}

function buildServiceLogSummaryCardHtml(log) {
    const draftBadge = log.is_draft
        ? '<span class="badge service-log-draft-badge ms-2">Draft</span>'
        : '';

    return ''
        + '<div class="service-update-service-log-card">'
        + (draftBadge ? '<div class="mb-2">' + draftBadge + '</div>' : '')
        + '  <div class="row g-3 small">'
        + '    <div class="col-md-4"><strong>Service Log Number:</strong> ' + escapeComplaintServiceLogHtml(log.service_log_number) + '</div>'
        + '    <div class="col-md-4"><strong>Log Date:</strong> ' + escapeComplaintServiceLogHtml(log.complaint_date) + '</div>'
        + '    <div class="col-md-4"><strong>Engineer Name:</strong> ' + escapeComplaintServiceLogHtml(log.engineer_name) + '</div>'
        + '    <div class="col-md-6"><strong>Machine Model / Part:</strong> ' + escapeComplaintServiceLogHtml(log.machine_model_part) + '</div>'
        + '    <div class="col-md-6"><strong>Running Hours / Loaded Hours:</strong> ' + escapeComplaintServiceLogHtml(log.running_hours) + '</div>'
        + '  </div>'
        + '</div>';
}

function buildServiceLogCycleSectionHtml(options) {
    const title = options.title || 'Service Log';
    const log = options.log || null;
    const canAdd = !!options.canAdd;
    const canEdit = !!options.canEdit;
    const isCurrentCycle = !!options.isCurrentCycle;
    const emptyMessage = options.emptyMessage || 'No service log added yet.';

    let actionsHtml = '';
    if (log && canEdit) {
        actionsHtml = '<button type="button" class="btn btn-sm btn-outline-dark complaint-edit-service-log-btn" data-service-log-id="' + parseInt(log.id, 10) + '">'
            + '<i class="bi bi-pencil"></i> Edit</button>';
    } else if (!log && canAdd && isCurrentCycle) {
        actionsHtml = '<button type="button" class="btn btn-sm btn-outline-dark complaint-add-service-log-btn">'
            + '<i class="bi bi-plus-lg"></i> Add Service Log</button>';
    }

    let bodyHtml = '';
    if (log) {
        bodyHtml = buildServiceLogSummaryCardHtml(log);
    } else {
        bodyHtml = '<p class="service-update-service-log-cycle__empty mb-0">' + escapeComplaintServiceLogHtml(emptyMessage) + '</p>';
    }

    return ''
        + '<div class="service-update-service-log-cycle' + (isCurrentCycle ? ' service-update-service-log-cycle--current' : '') + '" data-cycle="' + escapeComplaintServiceLogHtml(options.cycleKey || '') + '">'
        + '  <div class="service-update-service-log-cycle__head">'
        + '    <h4 class="service-update-service-log-cycle__title">' + escapeComplaintServiceLogHtml(title) + '</h4>'
        + (actionsHtml ? '<div class="d-flex gap-2">' + actionsHtml + '</div>' : '')
        + '  </div>'
        + bodyHtml
        + '</div>';
}

function renderComplaintServiceLogSummary(data) {
    const section = document.getElementById('serviceUpdateServiceLogSection');
    const emptyState = document.getElementById('serviceUpdateServiceLogEmpty');
    const details = document.getElementById('serviceUpdateServiceLogDetails');
    const notice = document.getElementById('serviceUpdateServiceLogNotice');

    if (!section) {
        return;
    }

    updateServiceUpdateServiceLogState(data);
    clearServiceUpdateServiceLogValidation();

    const permissions = data.permissions || {};
    const canAdd = !!permissions.add;
    const canEdit = !!permissions.edit;
    const currentCycleLog = data.current_cycle_service_log || data.service_log || null;
    const cycleTitle = data.current_cycle_label
        || (data.current_cycle === 'reopen' ? 'Re-Open Service Log' : 'In Progress Service Log');
    const emptyMessage = data.current_cycle === 'reopen'
        ? 'No service log added for the current Re-Open cycle yet.'
        : 'No In Progress service log added yet.';

    if (notice) {
        if (!data.has_installed_base) {
            notice.classList.remove('d-none');
            notice.textContent = data.message || 'No installed base record found for this complaint Fab Number.';
        } else {
            notice.classList.add('d-none');
            notice.textContent = '';
        }
    }

    if (!data.has_installed_base) {
        if (emptyState) {
            emptyState.classList.remove('d-none');
            let emptyHtml = '<p class="service-update-installed-base-empty__text mb-2">'
                + escapeComplaintServiceLogHtml('Service log cannot be added until a matching installed base exists.')
                + '</p>';
            if (data.can_add_installed_base) {
                const complaintId = parseInt(data.complaint_id || activeComplaintIdForServiceLog || 0, 10) || 0;
                const addUrl = buildInstalledBaseAddUrl(complaintId, data.fab_number)
                    || normalizeInstalledBaseAddUrl(data.installed_base_add_url);
                if (addUrl) {
                    emptyHtml += ''
                        + '<a href="' + escapeComplaintServiceLogHtml(addUrl) + '" '
                        + 'class="btn btn-sm btn-complaint-primary service-update-installed-base-add-btn">'
                        + '<i class="bi bi-plus-lg"></i> Add Installed Base Capture'
                        + '</a>';
                }
            }
            emptyState.innerHTML = emptyHtml;
        }
        if (details) {
            details.classList.add('d-none');
            details.innerHTML = '';
        }
        return;
    }

    if (emptyState) {
        emptyState.classList.add('d-none');
    }

    if (details) {
        details.classList.remove('d-none');
        details.innerHTML = buildServiceLogCycleSectionHtml({
            title: cycleTitle,
            cycleKey: data.current_cycle || 'in_progress',
            log: currentCycleLog,
            canAdd: canAdd,
            canEdit: canEdit,
            isCurrentCycle: true,
            emptyMessage: emptyMessage
        });
    }
}

function renderComplaintServiceLogSummaryError(message) {
    const emptyState = document.getElementById('serviceUpdateServiceLogEmpty');
    const details = document.getElementById('serviceUpdateServiceLogDetails');

    resetServiceUpdateServiceLogState();
    window.serviceUpdateServiceLogState.loaded = true;

    if (emptyState) {
        emptyState.classList.remove('d-none');
        emptyState.textContent = message || 'Unable to load service log details.';
    }
    if (details) {
        details.classList.add('d-none');
        details.innerHTML = '';
    }
}

function loadComplaintServiceLogSummary(complaintId) {
    if (!complaintId) {
        return;
    }

    resetServiceUpdateServiceLogState();
    clearServiceUpdateServiceLogValidation();

    const emptyState = document.getElementById('serviceUpdateServiceLogEmpty');
    if (emptyState) {
        emptyState.classList.remove('d-none');
        emptyState.textContent = 'Loading service log details...';
    }

    $.getJSON('api/complaint_service_log_summary.php', { complaint_id: complaintId })
        .done(function (data) {
            if (!data || data.success === false) {
                renderComplaintServiceLogSummaryError(
                    (data && data.error) ? data.error : 'Unable to load service log details.'
                );
                return;
            }
            renderComplaintServiceLogSummary(data);
        })
        .fail(function (xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.error
                ? xhr.responseJSON.error
                : 'Unable to load service log details.';
            renderComplaintServiceLogSummaryError(message);
            showComplaintServiceLogAlert('error', message);
        });
}

function openComplaintServiceLogModal(action, complaintId, serviceLogId) {
    const modalEl = document.getElementById('installedBaseServiceLogModal');
    if (!modalEl || !complaintId) {
        return;
    }

    openedServiceLogFromServiceUpdate = true;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    resetInstalledBaseServiceLogForm();
    modalEl.setAttribute('data-editing-draft', action === 'add' ? '1' : '0');

    if (action === 'edit') {
        const resolvedServiceLogId = parseInt(serviceLogId || '0', 10);
        if (!resolvedServiceLogId) {
            showComplaintServiceLogAlert('error', 'No service log found to edit.');
            reopenServiceUpdateModal(complaintId);
            return;
        }

        $.getJSON('api/complaint_service_log_summary.php', { complaint_id: complaintId })
            .done(function (summary) {
                $.getJSON('api/service_log_get.php', { id: resolvedServiceLogId, complaint_id: complaintId })
                    .done(function (record) {
                        record.complaint_id = complaintId;
                        record.installed_base_label = summary.installed_base_label || '';
                        modalEl.setAttribute(
                            'data-editing-draft',
                            Number(record.is_draft) === 1 ? '1' : '0'
                        );
                        fillInstalledBaseServiceLogFormForEdit(record);
                        document.getElementById('ibServiceLogComplaintId').value = complaintId;
                        modal.show();
                    })
                    .fail(function (xhr) {
                        const message = xhr.responseJSON && xhr.responseJSON.error
                            ? xhr.responseJSON.error
                            : 'Unable to load service log record.';
                        showComplaintServiceLogAlert('error', message);
                        reopenServiceUpdateModal(complaintId);
                    });
            })
            .fail(function (xhr) {
                const message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'Unable to load service log details.';
                showComplaintServiceLogAlert('error', message);
                reopenServiceUpdateModal(complaintId);
            });
        return;
    }

    $.getJSON('api/complaint_service_log_prefill.php', { complaint_id: complaintId })
        .done(function (data) {
            fillInstalledBaseServiceLogForm(data);
            modalEl.setAttribute('data-editing-draft', '1');
            document.getElementById('ibServiceLogComplaintId').value = complaintId;
            if (data.complaint_description) {
                const issueInput = document.querySelector('#installedBaseServiceLogForm [name="issue_description"]');
                if (issueInput) {
                    issueInput.value = data.complaint_description;
                }
            }
            ibServiceLogSetModalMode('add');
            modal.show();
        })
        .fail(function (xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.error
                ? xhr.responseJSON.error
                : 'Unable to load complaint details for service log.';
            showComplaintServiceLogAlert('error', message);
            reopenServiceUpdateModal(complaintId);
        });
}

function closeServiceUpdateAndOpenServiceLog(action, complaintId, serviceLogId) {
    activeComplaintIdForServiceLog = complaintId;
    pendingServiceLogAction = action;
    pendingServiceLogId = serviceLogId || null;

    const serviceUpdateModalEl = document.getElementById('serviceUpdateModal');
    const serviceUpdateModal = serviceUpdateModalEl
        ? bootstrap.Modal.getInstance(serviceUpdateModalEl)
        : null;

    if (serviceUpdateModal) {
        serviceUpdateModal.hide();
        return;
    }

    openComplaintServiceLogModal(action, complaintId, serviceLogId);
}

function reopenServiceUpdateModal(complaintId) {
    openedServiceLogFromServiceUpdate = false;
    pendingServiceLogId = null;

    const complaintIdField = document.getElementById('serviceComplaintId');
    if (complaintIdField) {
        complaintIdField.value = complaintId;
    }

    loadComplaintServiceLogSummary(complaintId);

    const serviceUpdateModalEl = document.getElementById('serviceUpdateModal');
    if (serviceUpdateModalEl) {
        bootstrap.Modal.getOrCreateInstance(serviceUpdateModalEl).show();
    }
}

window.complaintServiceLogAfterSave = function (response) {
    const complaintId = activeComplaintIdForServiceLog
        || parseInt((document.getElementById('serviceComplaintId') || {}).value || '0', 10);

    if (response && response.message) {
        showComplaintServiceLogAlert('success', response.message);
    }

    skipServiceLogModalReset = true;
    pendingReopenServiceUpdate = complaintId;

    const serviceLogModalEl = document.getElementById('installedBaseServiceLogModal');
    const serviceLogModal = serviceLogModalEl
        ? bootstrap.Modal.getInstance(serviceLogModalEl)
        : null;

    if (serviceLogModal) {
        serviceLogModal.hide();
    } else {
        reopenServiceUpdateModal(complaintId);
    }
};

function initComplaintServiceUpdateServiceLog() {
    const serviceUpdateModalEl = document.getElementById('serviceUpdateModal');
    const serviceLogSection = document.getElementById('serviceUpdateServiceLogSection');

    if (!serviceUpdateModalEl || !serviceLogSection) {
        return;
    }

    const serviceLogModalEl = document.getElementById('installedBaseServiceLogModal');

    $(document).on('click', '.service-update-btn', function () {
        const complaintId = $(this).data('id');
        activeComplaintIdForServiceLog = complaintId;
        loadComplaintServiceLogSummary(complaintId);
    });

    serviceUpdateModalEl.addEventListener('shown.bs.modal', function () {
        const complaintId = parseInt((document.getElementById('serviceComplaintId') || {}).value || '0', 10)
            || activeComplaintIdForServiceLog;
        if (complaintId) {
            loadComplaintServiceLogSummary(complaintId);
        }
    });

    if (!serviceLogModalEl) {
        return;
    }

    $(document).on('click', '.complaint-add-service-log-btn', function () {
        const complaintId = parseInt((document.getElementById('serviceComplaintId') || {}).value || '0', 10);
        if (!complaintId || $(this).closest('.service-update-service-log-cycle--current').length === 0) {
            return;
        }
        closeServiceUpdateAndOpenServiceLog('add', complaintId);
    });

    $(document).on('click', '.complaint-edit-service-log-btn', function () {
        const complaintId = parseInt((document.getElementById('serviceComplaintId') || {}).value || '0', 10);
        const serviceLogId = parseInt($(this).data('service-log-id') || '0', 10);
        if (!complaintId || !serviceLogId) {
            return;
        }
        closeServiceUpdateAndOpenServiceLog('edit', complaintId, serviceLogId);
    });

    serviceUpdateModalEl.addEventListener('hidden.bs.modal', function () {
        if (pendingServiceLogAction) {
            const action = pendingServiceLogAction;
            const complaintId = activeComplaintIdForServiceLog;
            const serviceLogId = pendingServiceLogId;
            pendingServiceLogAction = null;
            pendingServiceLogId = null;
            openComplaintServiceLogModal(action, complaintId, serviceLogId);
            return;
        }

        if (pendingReopenServiceUpdate) {
            return;
        }

        resetServiceUpdateForm(document.getElementById('serviceComplaintId').value || '');
    });

    serviceLogModalEl.addEventListener('hidden.bs.modal', function () {
        if (pendingReopenServiceUpdate) {
            const complaintId = pendingReopenServiceUpdate;
            pendingReopenServiceUpdate = null;
            openedServiceLogFromServiceUpdate = false;
            reopenServiceUpdateModal(complaintId);
            return;
        }

        if (openedServiceLogFromServiceUpdate) {
            const complaintId = activeComplaintIdForServiceLog
                || parseInt((document.getElementById('serviceComplaintId') || {}).value || '0', 10);
            openedServiceLogFromServiceUpdate = false;
            if (complaintId) {
                reopenServiceUpdateModal(complaintId);
            }
            return;
        }

        if (!skipServiceLogModalReset) {
            resetInstalledBaseServiceLogForm();
        }

        skipServiceLogModalReset = false;
    });
}