$(function () {
    const $table = $('#warrantyClaimsTable');
    if (!$table.length) {
        return;
    }

    $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/warranty_claims_datatable.php',
            type: 'POST'
        },
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'fab_number' },
            { data: 'customer_name' },
            { data: 'machine_model' },
            { data: 'commissioning_date' },
            { data: 'warranty_status', orderable: false },
            // { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No installed base records found.',
            zeroRecords: 'No matching records found.'
        }
    });

    initCommissionUpdateModal();
});

function initCommissionUpdateModal() {
    const $modal = $('#updateCommissionModal');
    const $fabSelect = $('#commissionFabSelect');
    if (!$modal.length || !$fabSelect.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    $fabSelect.select2({
        width: '100%',
        dropdownParent: $modal,
        placeholder: $fabSelect.data('placeholder') || 'Search by ID or fab number',
        allowClear: true,
        minimumInputLength: 0,
        ajax: {
            url: 'api/warranty_claims_fab_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (data) {
                return data;
            },
            cache: true
        },
        language: {
            noResults: function () {
                return 'No fab number found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $fabSelect.on('select2:select', function (e) {
        $('#commissionRecordId').val(e.params.data.id || '');
        $modal.find('.validation-msg[data-field="record_id"]').text('');

        const commissioningDate = e.params.data.commissioning_date || '';
        if (commissioningDate) {
            $('#commissionDateInput').val(commissioningDate);
        }
    });

    $fabSelect.on('select2:clear', function () {
        $('#commissionRecordId').val('');
    });

    $modal.on('hidden.bs.modal', function () {
        $('#updateCommissionForm')[0].reset();
        $('#commissionRecordId').val('');
        $fabSelect.val(null).trigger('change');
        $modal.find('.validation-msg').text('');
    });

    $('#updateCommissionForm').on('submit', function (e) {
        let valid = true;
        $modal.find('.validation-msg').text('');

        if (!$('#commissionRecordId').val()) {
            $modal.find('.validation-msg[data-field="record_id"]').text('Please select a fab number.');
            valid = false;
        }
        if (!$('#commissionDateInput').val()) {
            $modal.find('.validation-msg[data-field="commissioning_date"]').text('Please select a commissioning date.');
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
        }
    });
}

