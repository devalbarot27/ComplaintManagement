function clearAddressAutoFields(form) {
    if (!form) {
        return;
    }

    ['city', 'district', 'state'].forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = '';
        }
    });
}

function setAddressAutoFields(form, data) {
    if (!form || !data) {
        return;
    }

    const mapping = {
        city: data.city || '',
        district: data.district || '',
        state: data.state || '',
    };

    Object.keys(mapping).forEach(function (field) {
        const input = form.querySelector('[name="' + field + '"]');
        if (input) {
            input.value = mapping[field];
            input.classList.remove('is-invalid');
        }

        const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
        if (msg) {
            msg.textContent = '';
        }
    });
}

function resetPincodeSelect2(form) {
    const $pincode = $('#pincodeSelect');
    if (!$pincode.length) {
        return;
    }

    $pincode.val(null).trigger('change');
    clearAddressAutoFields(form);
}

function initPincodeSelect2() {
    const form = document.getElementById('complaintForm');
    const $pincode = $('#pincodeSelect');

    if (!form || !$pincode.length || typeof $.fn.select2 === 'undefined') {
        return;
    }

    $pincode.select2({
        width: '100%',
        placeholder: 'Search pincode',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: 'api/postcode_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: (params.term || '').replace(/\D/g, '')
                };
            },
            processResults: function (data) {
                return data;
            },
            cache: true
        },
        language: {
            inputTooShort: function () {
                return 'Type at least 2 digits';
            },
            noResults: function () {
                return 'No pincode found';
            },
            searching: function () {
                return 'Searching...';
            }
        }
    });

    $pincode.on('select2:select', function (e) {
        setAddressAutoFields(form, e.params.data);
        $pincode.removeClass('is-invalid');

        const msg = form.querySelector('.validation-msg[data-field="pincode"]');
        if (msg) {
            msg.textContent = '';
        }
    });

    $pincode.on('select2:clear', function () {
        clearAddressAutoFields(form);
    });
}
