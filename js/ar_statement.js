$(function () {
    const $dealerFilter = $('#arDealerFilter');
    if ($dealerFilter.length) {
        const applyDealerFilter = function (cuno) {
            const url = new URL(window.location.href);
            url.searchParams.set('dealer', cuno);
            window.location.href = url.pathname + url.search + url.hash;
        };

        if (typeof $.fn.select2 !== 'undefined') {
            let localResults = [];
            try {
                localResults = JSON.parse($dealerFilter.attr('data-options') || '[]');
            } catch (e) {
                localResults = [];
            }
            if (!Array.isArray(localResults)) {
                localResults = [];
            }

            const select2Options = {
                width: '260px',
                placeholder: $dealerFilter.data('placeholder') || 'Dealer Name',
                allowClear: true,
                minimumInputLength: 0,
                minimumResultsForSearch: 0,
                dropdownParent: $dealerFilter.parent(),
                ajax: {
                    url: 'api/ar_statement_dealers_search.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (data, params) {
                        const results = (data && Array.isArray(data.results)) ? data.results : [];
                        if (results.length) {
                            return { results: results };
                        }
                        const term = String((params && params.term) || '').toLowerCase();
                        const filtered = localResults.filter(function (item) {
                            if (!term) {
                                return true;
                            }
                            return String(item.text || '').toLowerCase().indexOf(term) !== -1
                                || String(item.id || '').toLowerCase().indexOf(term) !== -1;
                        });
                        return { results: filtered };
                    },
                    transport: function (params, success, failure) {
                        const request = $.ajax(params);
                        request.then(success).fail(function () {
                            success({ results: localResults });
                        });
                        return request;
                    },
                    cache: true
                },
                language: {
                    noResults: function () {
                        return 'No dealer found';
                    },
                    searching: function () {
                        return 'Searching...';
                    }
                }
            };

            $dealerFilter.select2(select2Options);
            $dealerFilter.on('select2:select select2:clear', function () {
                applyDealerFilter(String($(this).val() || '').trim());
            });
        } else {
            $dealerFilter.on('change', function () {
                applyDealerFilter(String($(this).val() || '').trim());
            });
        }
    }

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
        const selectedDealer = String($('#arDealerFilter').val() || '').trim().replace(/[^\w.-]+/g, '_');
        link.download = selectedDealer ? ('AR_Statement_' + selectedDealer + '.csv') : 'AR_Statement.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });
});