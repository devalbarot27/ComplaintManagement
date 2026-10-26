function initComplaintApprovalsDatatable() {
    const $table = $('#complaintApprovalsTable');
    if (!$table.length || !$.fn.DataTable) {
        return null;
    }

    const table = $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/complaint_closure_approvals_datatable.php',
            type: 'POST'
        },
        order: [[6, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'fab_number' },
            { data: 'customer_name' },
            { data: 'status' },
            { data: 'approval_status' },
            { data: 'approver_name' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No pending closure approval requests.',
            zeroRecords: 'No matching pending closure approval requests found.'
        }
    });

    return table;
}

$(function () {
    initComplaintApprovalsDatatable();
});
