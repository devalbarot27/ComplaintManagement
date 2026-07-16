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
            return '^Username may only contain letters, numbers, and underscore';
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
            presence: { allowEmpty: false, message: '^Name is required' }
        },
        email: {
            presence: { allowEmpty: false, message: '^Email is required' },
            email: { message: '^Please enter a valid email address' }
        },
        mobile_number: {
            presence: { allowEmpty: false, message: '^Mobile Number is required' },
            userMobileNumber: true
        },
        password: {
            presence: { allowEmpty: false, message: '^Password is required' },
            userPasswordStrength: true
        },
        sales_coordinator_id: {
            userSalesCoordinatorRequired: true
        }
    };

    function clearValidationState() {
        form.querySelectorAll('.validation-msg').forEach(function (msg) {
            msg.textContent = '';
        });
        form.querySelectorAll('.form-control').forEach(function (input) {
            input.classList.remove('is-invalid');
        });
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
            if (msg && errors[field]) {
                msg.textContent = errors[field][0];
            }
        });
    }

    let isSubmitting = false;

    function checkUserUniqueFields(recordId) {
        return $.ajax({
            url: 'api/users_check_unique.php',
            type: 'GET',
            dataType: 'json',
            data: {
                record_id: recordId || 0,
                email: form.querySelector('[name="email"]').value.trim(),
                mobile_number: form.querySelector('[name="mobile_number"]').value.trim()
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

    form.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-toggle-password');
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