$(function () {
    const $dealerFilter = $('#arDealerFilter');
    if ($dealerFilter.length) {
        const applyDealerFilter = function (cuno) {
            const url = new URL(window.location.href);
            const dealerCode = String(cuno || '').trim();
            if (dealerCode) {
                url.searchParams.set('dealer', dealerCode);
            } else {
                url.searchParams.delete('dealer');
            }
            ['download', 'status', 'search', 'order_column', 'order_dir', 'start', 'length']
                .forEach(function (parameter) {
                    url.searchParams.delete(parameter);
                });
            window.location.assign(url.pathname + url.search + url.hash);
        };

        if (typeof $.fn.select2 !== 'undefined') {
            $dealerFilter.select2({
                width: '260px',
                placeholder: $dealerFilter.data('placeholder') || 'Search dealer',
                allowClear: true,
                minimumResultsForSearch: 0,
                dropdownParent: $dealerFilter.parent(),
                language: {
                    noResults: function () {
                        return 'No dealer found';
                    }
                }
            });
        }

        $dealerFilter.on('change', function () {
            applyDealerFilter($(this).val());
        });
    }

    const $table = $('#ledgerTable');
    if (!$table.length || typeof $.fn.DataTable === 'undefined') {
        return;
    }

    const table = $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.location.pathname + window.location.search,
            type: 'POST',
            data: function (request) {
                request.ledger_datatable = 1;
                request.status = String($('#ledgerStatusFilter').val() || '');
            }
        },
        columns: [
            { data: 'dpst', render: $.fn.dataTable.render.text() },
            { data: 'docdt', render: $.fn.dataTable.render.text() },
            { data: 'invoiceNo', render: $.fn.dataTable.render.text() },
            { data: 'currency', render: $.fn.dataTable.render.text() },
            { data: 'invamt', className: 'debit-text text-end', render: renderRupees },
            { data: 'recvamt', className: 'credit-text text-end', render: renderRupees },
            { data: 'amtout', className: 'fw-semibold text-end', render: renderRupees },
            { data: 'less30', className: 'text-end', render: renderRupees },
            { data: 'less40', className: 'text-end', render: renderRupees },
            { data: 'less45', className: 'text-end', render: renderRupees },
            { data: 'less50', className: 'text-end', render: renderRupees },
            { data: 'less60', className: 'text-end', render: renderRupees },
            { data: 'less90', className: 'text-end', render: renderRupees },
            { data: 'more90', className: 'text-end', render: renderRupees },
            { data: 'duedate', render: $.fn.dataTable.render.text() }
        ],
        order: [[1, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        scrollX: true,
        autoWidth: false,
        language: {
            processing: 'Loading AR records...',
            emptyTable: 'No AR records found.',
            zeroRecords: 'No matching AR records found.'
        }
    });

    $('#ledgerStatusFilter').on('change', function () {
        table.ajax.reload();
    });

    $('#downloadStatementBtn').on('click', function () {
        const url = new URL(window.location.href);
        const order = table.order()[0] || [1, 'desc'];
        url.searchParams.set('download', 'csv');
        url.searchParams.set('status', String($('#ledgerStatusFilter').val() || ''));
        url.searchParams.set('search', table.search());
        url.searchParams.set('order_column', String(order[0]));
        url.searchParams.set('order_dir', String(order[1]));
        window.location.href = url.toString();
    });

    function renderRupees(value, type) {
        if (type !== 'display') return value;
        return '&#8377;' + Math.round(Number(value) || 0).toLocaleString('en-IN');
    }
});