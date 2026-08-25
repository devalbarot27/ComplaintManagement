function getRolesRequiringSalesCoordinator() {
    return Array.isArray(window.USER_ROLES_REQUIRING_SALES_COORDINATOR)
        ? window.USER_ROLES_REQUIRING_SALES_COORDINATOR.map(function (roleId) {
            return parseInt(roleId, 10);
        })
        : [];
}

function roleRequiresSalesCoordinator(roleId) {
    const role = parseInt(roleId, 10);
    return getRolesRequiringSalesCoordinator().indexOf(role) !== -1;
}

function toggleSalesCoordinatorField(roleId, selectedSalesCoordinatorId) {
    const wrap = document.getElementById('salesCoordinatorFieldWrap');
    const select = document.getElementById('salesCoordinatorSelect');
    if (!wrap || !select) {
        return;
    }

    const isRequired = roleRequiresSalesCoordinator(roleId);
    wrap.style.display = isRequired ? '' : 'none';

    if (!isRequired) {
        select.value = '';
        select.classList.remove('is-invalid');
        const msg = document.querySelector('.validation-msg[data-field="sales_coordinator_id"]');
        if (msg) {
            msg.textContent = '';
        }
        return;
    }

    if (selectedSalesCoordinatorId !== undefined && selectedSalesCoordinatorId !== null && String(selectedSalesCoordinatorId) !== '') {
        select.value = String(selectedSalesCoordinatorId);
    }
}

function userPasswordStrengthError(password) {
    if (!password || password.length < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!/[0-9]/.test(password)) {
        return 'Password must contain at least one digit (0-9).';
    }
    if (!/[A-Z]/.test(password)) {
        return 'Password must contain at least one uppercase letter (A-Z).';
    }
    if (!/[a-z]/.test(password)) {
        return 'Password must contain at least one lowercase letter (a-z).';
    }
    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?~`]/.test(password)) {
        return 'Password must contain at least one special character (!@#$%^&* etc.).';
    }
    return null;
}

function isBlockedEmailDomain(email) {
    const value = String(email || '').trim().toLowerCase();
    const atPos = value.lastIndexOf('@');
    if (atPos === -1) {
        return false;
    }
    const domain = value.slice(atPos + 1);
    const blocked = Array.isArray(window.BLOCKED_EMAIL_DOMAINS) ? window.BLOCKED_EMAIL_DOMAINS : [];
    if (blocked.indexOf(domain) !== -1) {
        return true;
    }
    for (let i = 0; i < blocked.length; i += 1) {
        const suffix = '.' + blocked[i];
        if (domain.length > suffix.length && domain.slice(-suffix.length) === suffix) {
            return true;
        }
    }
    return false;
}

function initUsersFormValidation() {
    const form = document.getElementById('userForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    validate.validators.userPasswordStrength = function (value) {
        const recordId = document.getElementById('userRecordId');
        const isEdit = recordId && recordId.value !== '' && recordId.value !== '0';
        if (isEdit && (!value || value === '')) {
            return null;
        }
        const error = userPasswordStrengthError(value);
        return error ? '^' + error : null;
    };

    validate.validators.userMobileNumber = function (value) {
        if (!/^[1-9]\d{9}$/.test(String(value || ''))) {
            return '^Mobile Number must be a valid 10-digit number';
        }
        return null;
    };

    validate.validators.userUsernameFormat = function (value) {
        if (!/^[A-Za-z0-9_]+$/.test(String(value || ''))) {
            return '^Username may only contain letters, numbers, and underscore. Special characters are not allowed';
        }
        return null;
    };

    validate.validators.userNameFormat = function (value) {
        if (!/^[A-Za-z]+(?:[ .'\-][A-Za-z]+)*$/.test(String(value || '').trim())) {
            return '^Name may only contain letters, spaces, dots, hyphens, and apostrophes. Special characters are not allowed';
        }
        return null;
    };

    validate.validators.userEmailFormat = function (value) {
        const email = String(value || '').trim();
        if (!/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(email)) {
            return '^Please enter a valid email address without special characters';
        }
        if (isBlockedEmailDomain(email)) {
            return '^Open or disposable email addresses are not allowed. Please use a valid personal or business email';
        }
        return null;
    };

    validate.validators.userSalesCoordinatorRequired = function (value, options, key, attributes) {
        if (!roleRequiresSalesCoordinator(attributes.role)) {
            return null;
        }
        if (!value || String(value).trim() === '') {
            return '^Sales Coordinator is required';
        }
        return null;
    };

    const constraints = {
        role: {
            presence: { allowEmpty: false, message: '^Role is required' }
        },
        username: {
            presence: { allowEmpty: false, message: '^Username is required' },
            length: { maximum: 100, message: '^Username cannot exceed 100 characters' },
            userUsernameFormat: true
        },
        name: {
            presence: { allowEmpty: false, message: '^Name is required' },
            length: { maximum: 255, message: '^Name cannot exceed 255 characters' },
            userNameFormat: true
        },
        email: {
            presence: { allowEmpty: false, message: '^Email is required' },
            email: { message: '^Please enter a valid email address' },
            userEmailFormat: true
        },
        mobile_number: {
            presence: { allowEmpty: false, message: '^Mobile Number is required' },
            userMobileNumber: true
        },
        customer_code: {
            presence: { allowEmpty: false, message: '^Customer Code is required' }
        },
        password: {
            presence: { allowEmpty: false, message: '^Password is required' },
            userPasswordStrength: true
        },
        sales_coordinator_id: {
            userSalesCoordinatorRequired: true
        }
    };

    function bindSpecialCharacterRestrictions() {
        const usernameInput = form.querySelector('[name="username"]');
        const nameInput = form.querySelector('[name="name"]');
        const mobileInput = form.querySelector('[name="mobile_number"]');
        const emailInput = form.querySelector('[name="email"]');

        if (usernameInput) {
            usernameInput.addEventListener('input', function () {
                usernameInput.value = usernameInput.value.replace(/[^A-Za-z0-9_]/g, '');
            });
        }

        if (nameInput) {
            nameInput.addEventListener('input', function () {
                nameInput.value = nameInput.value.replace(/[^A-Za-z .'\-]/g, '');
            });
        }

        if (mobileInput) {
            mobileInput.addEventListener('input', function () {
                mobileInput.value = mobileInput.value.replace(/\D/g, '').slice(0, 10);
            });
        }

        if (emailInput) {
            emailInput.addEventListener('input', function () {
                emailInput.value = emailInput.value.replace(/[^A-Za-z0-9._%+\-@]/g, '');
            });
        }
    }

    bindSpecialCharacterRestrictions();

    function clearValidationState() {
        form.querySelectorAll('.validation-msg').forEach(function (msg) {
            msg.textContent = '';
        });
        form.querySelectorAll('.form-control').forEach(function (input) {
            input.classList.remove('is-invalid');
        });
        if (window.jQuery) {
            window.jQuery('#userCustomerCodeSelect').next('.select2-container').find('.select2-selection').removeClass('is-invalid');
        }
    }

    function showErrors(errors) {
        clearValidationState();
        if (!errors) {
            return;
        }
        Object.keys(errors).forEach(function (field) {
            const input = form.querySelector('[name="' + field + '"]');
            const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
            if (input) {
                input.classList.add('is-invalid');
            }
            if (field === 'customer_code' && window.jQuery) {
                window.jQuery('#userCustomerCodeSelect').next('.select2-container').find('.select2-selection').addClass('is-invalid');
            }
            if (msg && errors[field]) {
                msg.textContent = errors[field][0];
            }
        });
    }

    let isSubmitting = false;

    function checkUserUniqueFields(recordId) {
        return $.ajax({
            url: 'api/users_check_unique.php',
            type: 'POST',
            dataType: 'json',
            data: {
                record_id: recordId || 0,
                email: form.querySelector('[name="email"]').value.trim(),
                mobile_number: form.querySelector('[name="mobile_number"]').value.trim(),
                customer_code: form.querySelector('[name="customer_code"]').value.trim()
            }
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const recordId = document.getElementById('userRecordId');
        const isEdit = recordId && recordId.value !== '' && recordId.value !== '0';
        const passwordRequired = document.getElementById('userPasswordRequired');
        const submitButton = form.querySelector('[name="submit_user"]');

        if (isEdit) {
            constraints.password = { userPasswordStrength: true };
            if (passwordRequired) {
                passwordRequired.style.display = 'none';
            }
        } else {
            constraints.password = {
                presence: { allowEmpty: false, message: '^Password is required' },
                userPasswordStrength: true
            };
            if (passwordRequired) {
                passwordRequired.style.display = '';
            }
        }

        if (isSubmitting) {
            return;
        }

        const errors = validate(form, constraints);
        showErrors(errors);

        if (errors) {
            return;
        }

        const excludeId = isEdit ? parseInt(recordId.value, 10) : 0;

        checkUserUniqueFields(excludeId)
            .done(function (response) {
                if (response && response.errors && Object.keys(response.errors).length > 0) {
                    showErrors(response.errors);
                    return;
                }

                isSubmitting = true;
                if (submitButton) {
                    submitButton.classList.add('disabled_btn');
                }
                form.submit();
            })
            .fail(function () {
                showErrors({
                    email: ['Unable to verify email and mobile number. Please try again.']
                });
            });
    });

    form.querySelectorAll('[data-toggle-field]').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-toggle-field');
            const input = document.getElementById(targetId);
            const icon = button.querySelector('i');
            if (!input || !icon) {
                return;
            }
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.classList.toggle('bi-eye', !isPassword);
            icon.classList.toggle('bi-eye-slash', isPassword);
        });
    });
}

function initUsersDatatable() {
    const $table = $('#usersTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: { url: 'api/users_datatable.php', type: 'POST' },
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'role' },
            { data: 'username' },
            { data: 'name' },
            { data: 'customer_code' },
            { data: 'email' },
            { data: 'mobile_number' },
            { data: 'last_login_at' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No users found.',
            zeroRecords: 'No matching users found.'
        }
    });
}

function initUserCustomerCodeSelect2() {
    const $select = $('#userCustomerCodeSelect');
    if (!$select.length || typeof $select.select2 !== 'function') {
        return;
    }

    if ($select.hasClass('select2-hidden-accessible')) {
        return;
    }

    $select.select2({
        placeholder: 'Search customer code',
        allowClear: false,
        width: '100%',
        ajax: {
            url: 'api/user_customer_search.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (data) {
                return { results: (data && data.results) ? data.results : [] };
            },
            cache: true
        }
    });
}

function setUserCustomerCodeSelect2Value(id, text) {
    const $select = $('#userCustomerCodeSelect');
    if (!$select.length) {
        return;
    }

    $select.find('option').remove();
    $select.append(new Option('', '', false, false));
    if (id) {
        const option = new Option(text || id, id, true, true);
        $select.append(option).trigger('change');
    } else {
        $select.val(null).trigger('change');
    }
}

function fillUserForm(record) {
    const form = document.getElementById('userForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('userRecordId').value = record.id || '';
    document.getElementById('userFormModeLabel').textContent = record.id ? 'Edit User' : 'Add User';
    document.getElementById('submitUserBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update User'
        : '<i class="bi bi-check-lg"></i> Save User';

    form.querySelector('[name="role"]').value = record.role || '';
    toggleSalesCoordinatorField(record.role, record.sales_coordinator_id || '');
    form.querySelector('[name="username"]').value = record.username || '';
    form.querySelector('[name="name"]').value = record.name || '';
    form.querySelector('[name="email"]').value = record.email || '';
    form.querySelector('[name="mobile_number"]').value = record.mobile_number || '';
    form.querySelector('[name="password"]').value = '';
    setUserCustomerCodeSelect2Value(
        record.customer_code || '',
        record.customer_code_text || record.customer_code || ''
    );

    const passwordHint = document.getElementById('userPasswordHint');
    const passwordRequired = document.getElementById('userPasswordRequired');
    if (passwordHint) {
        passwordHint.textContent = record.id
            ? 'Leave blank to keep the current password.'
            : 'Minimum 8 characters with digit, uppercase, lowercase, and special character.';
    }
    if (passwordRequired) {
        passwordRequired.style.display = record.id ? 'none' : '';
    }
}

function resetUserForm() {
    const form = document.getElementById('userForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('userRecordId').value = '';
    toggleSalesCoordinatorField('');
    setUserCustomerCodeSelect2Value('', '');
    document.getElementById('userFormModeLabel').textContent = 'Add User';
    document.getElementById('submitUserBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save User';
    const passwordHint = document.getElementById('userPasswordHint');
    const passwordRequired = document.getElementById('userPasswordRequired');
    if (passwordHint) {
        passwordHint.textContent = 'Minimum 8 characters with digit, uppercase, lowercase, and special character.';
    }
    if (passwordRequired) {
        passwordRequired.style.display = '';
    }
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openUserFormPanel() {
    const card = document.getElementById('userFormCard');
    const openBtn = document.getElementById('openUserForm');
    const closeBtn = document.getElementById('closeUserForm');

    if (card) {
        card.classList.add('show');
    }
    if (openBtn) {
        openBtn.style.display = 'none';
    }
    if (closeBtn) {
        closeBtn.classList.add('show');
    }
}

function closeUserFormPanel() {
    const card = document.getElementById('userFormCard');
    const openBtn = document.getElementById('openUserForm');
    const closeBtn = document.getElementById('closeUserForm');

    if (card) {
        card.classList.remove('show');
    }
    if (openBtn) {
        openBtn.style.display = 'flex';
    }
    if (closeBtn) {
        closeBtn.classList.remove('show');
    }

    resetUserForm();
}

function bootUserEditPage() {
    initUserCustomerCodeSelect2();

    const roleSelect = document.getElementById('userRoleSelect');
    if (roleSelect) {
        roleSelect.addEventListener('change', function () {
            toggleSalesCoordinatorField(roleSelect.value);
        });
        toggleSalesCoordinatorField(roleSelect.value, document.getElementById('salesCoordinatorSelect')?.value || '');
    }

    const cancelBtn = document.getElementById('cancelUserForm');
    const cancelUrl = window.USER_FORM_CANCEL_URL || 'users.php';
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            window.location.href = cancelUrl;
        });
    }
}

function bootUsersPage() {
    initUserCustomerCodeSelect2();
    initUsersFormValidation();

    if (window.USER_FORM_PAGE === 'edit') {
        bootUserEditPage();
        return;
    }

    initUsersDatatable();

    const roleSelect = document.getElementById('userRoleSelect');
    if (roleSelect) {
        roleSelect.addEventListener('change', function () {
            toggleSalesCoordinatorField(roleSelect.value);
        });
    }

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-user-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        if (!id) {
            return;
        }
        $.getJSON('api/users_get.php', { id: id })
            .done(function (record) {
                resetUserForm();
                fillUserForm(record);
                openUserFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load user details.');
            });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootUsersPage);
} else {
    bootUsersPage();
}