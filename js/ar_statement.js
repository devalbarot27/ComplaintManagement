$(function () {
    const $table = $('#ledgerTable');
    if (!$table.length || typeof $.fn.DataTable === 'undefined') {
        return;
    }

    $.fn.dataTable.ext.search.push(function (settings, _data, dataIndex) {
        if (settings.nTable.id !== 'ledgerTable') {
            return true;
        }

        const selected = String($('#ledgerStatusFilter').val() || '');
        if (selected === '') {
            return true;
        }

        const rowNode = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
        return String($(rowNode).attr('data-status') || '') === selected;
    });

    const table = $table.DataTable({
        order: [[1, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        scrollX: true,
        autoWidth: false,
        language: {
            emptyTable: 'No AR records found.',
            zeroRecords: 'No matching AR records found.'
        }
    });
    table.columns.adjust();

    $('#ledgerStatusFilter').on('change', function () {
        table.draw();
    });

    $('#downloadStatementBtn').on('click', function () {
        const csvEscape = function (value) {
            return '"' + String(value || '').replace(/"/g, '""') + '"';
        };
        const header = table.columns().header().toArray().map(function (th) {
            return csvEscape($(th).text().replace(/\s+/g, ' ').trim());
        });
        const body = table.rows({ search: 'applied' }).nodes().toArray().map(function (tr) {
            return [...tr.querySelectorAll('td')].map(function (td) {
                return csvEscape(td.textContent.replace(/\s+/g, ' ').trim());
            }).join(',');
        });
        const footerRow = $table.find('tfoot tr').get(0);
        const footer = footerRow
            ? [...footerRow.querySelectorAll('th,td')].map(function (cell) {
                return csvEscape(cell.textContent.replace(/\s+/g, ' ').trim());
            }).join(',')
            : '';
        const csv = [header.join(',')].concat(body);
        if (footer) {
            csv.push(footer);
        }

        const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'AR_Statement.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });
});