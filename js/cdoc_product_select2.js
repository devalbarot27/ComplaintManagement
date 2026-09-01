function initCdocProductSelect2(selectId, groupSelectId, prefill) {
    const $select = $('#' + selectId);
    const $group = groupSelectId ? $('#' + groupSelectId) : $();

    if (!$select.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    let syncingGroup = false;

    $select.select2({
        width: '100%',
        placeholder: $select.data('placeholder') || 'Select a product',
        allowClear: true,
        minimumInputLength: 0,
        dropdownParent: $(document.body),
        ajax: {
            url: 'api/cdoc_product_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term || '',
                    product_group: $group.length ? String($group.val() || '') : ''
                };
            },
            processResults: function (data) {
                return data;
            },
            cache: true
        },
        language: {
            noResults: function () {
                return 'No product found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $select.on('select2:select', function (e) {
        const group = (e.params.data && e.params.data.product_group) ? String(e.params.data.product_group) : '';
        if (!group || !$group.length) {
            return;
        }

        if ($group.find('option').filter(function () {
            return String($(this).val()) === group;
        }).length === 0) {
            $group.append(new Option(group, group, true, true));
        }

        syncingGroup = true;
        $group.val(group);
        syncingGroup = false;
    });

    if ($group.length) {
        $group.on('change', function () {
            if (syncingGroup) {
                return;
            }
            $select.val(null).trigger('change');
        });
    }

    if (prefill && prefill.code) {
        const code = String(prefill.code);
        const label = prefill.label || code;
        if ($select.find('option').filter(function () {
            return String($(this).val()) === code;
        }).length === 0) {
            $select.append(new Option(label, code, true, true));
        }
        $select.val(code).trigger('change');
    }
}
