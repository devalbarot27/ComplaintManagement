function initComplaintEntryDatatable() {
    const $table = $('#complaintTable');
    if (!$table.length) {
        return null;
    }

    const table = $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/complaints_datatable.php',
            type: 'POST',
            data: function (payload) {
                const statusFilter = document.getElementById('complaintHistoryStatusFilter');
                if (statusFilter) {
                    payload.status_filter = statusFilter.value;
                }
            }
        },
        order: [[5, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'fab_number' },
            { data: 'customer_name' },
            { data: 'customer_address' },
            { data: 'status', orderable: false },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No complaints found.',
            zeroRecords: 'No matching complaints found.'
        }
    });

    const statusFilter = document.getElementById('complaintHistoryStatusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            table.ajax.reload();
        });
    }

    return table;
}

function initAssignedComplaintDatatable() {
    const $table = $('#dscComplaintTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/assigned_complaints_datatable.php',
            type: 'POST'
        },
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'ca_id' },
            { data: 'fab_number' },
            { data: 'customer_name' },
            { data: 'complaint_category' },
            { data: 'assign_complaint' },
            { data: 'assign_complaint_datetime' },
            { data: 'remarks' },
            { data: 'status', orderable: false },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No assigned complaints found.',
            zeroRecords: 'No matching assigned complaints found.'
        }
    });
}