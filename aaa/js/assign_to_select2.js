function initAssignToSelect2(formId, selectId, options) {
    options = options || {};

    const form = document.getElementById(formId);
    const $select = $('#' + selectId);

    if (!form || !$select.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    if ($select.hasClass('select2-hidden-accessible')) {
        $select.select2('destroy');
    }

    const select2Options = {
        width: '100%',
        placeholder: $select.data('placeholder') || 'Search assignee',
        allowClear: true,
        minimumResultsForSearch: 0,
        language: {
            noResults: function () {
                return 'No assignee found';
            }
        }
    };

    if (options.dropdownParent) {
        select2Options.dropdownParent = options.dropdownParent;
    }

    $select.select2(select2Options);

    const validationField = options.validationField || 'assign_complaint';

    $select.on('select2:select select2:clear', function () {
        $select.removeClass('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="' + validationField + '"]');
        if (msg) {
            msg.textContent = '';
        }
    });
}

function resetAssignToSelect2(selectId) {
    const $select = $('#' + selectId);

    if (!$select.length) {
        return;
    }

    $select.val(null).trigger('change');
    $select.removeClass('is-invalid');
}

function setAssignToSelect2Options(selectId, assignees, preselect) {
    const $select = $('#' + selectId);

    if (!$select.length) {
        return;
    }

    const currentValue = preselect || null;
    $select.empty().append(new Option('', '', false, false));

    (assignees || []).forEach(function (assignee) {
        const value = assignee && assignee.value ? String(assignee.value) : '';
        const label = assignee && assignee.label ? String(assignee.label) : value;
        if (value === '') {
            return;
        }
        $select.append(new Option(label, value, false, false));
    });

    if (currentValue) {
        $select.val(currentValue).trigger('change');
    } else {
        $select.val(null).trigger('change');
    }
}

function loadAssignComplaintAssigneeOptions(complaintId) {
    loadComplaintAssigneeOptions('assignModalAssignToSelect', complaintId);
}

function loadClosureReassignAssigneeOptions(complaintId) {
    loadComplaintAssigneeOptions('closureReassignToSelect', complaintId);
}

function loadComplaintAssigneeOptions(selectId, complaintId) {
    const $select = $('#' + selectId);
    if (!$select.length || !complaintId) {
        return;
    }

    $.ajax({
        url: 'api/complaint_assign_options.php',
        type: 'GET',
        dataType: 'json',
        data: { complaint_id: complaintId }
    }).done(function (response) {
        if (!response || !response.success) {
            return;
        }

        setAssignToSelect2Options(
            selectId,
            response.assignees || [],
            response.preselect || null
        );
    });
}