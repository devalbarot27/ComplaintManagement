function initComplaintCategoryFormValidation() {
    const form = document.getElementById('complaintCategoryForm');
    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        name: {
            presence: { allowEmpty: false, message: '^Name is required' },
            length: { maximum: 100, message: '^Name cannot exceed 100 characters' }
        },
        status: {
            presence: { allowEmpty: false, message: '^Status is required' }
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

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const errors = validate(form, constraints);
        showErrors(errors);
        if (!errors) {
            form.submit();
        }
    });
}

function initComplaintCategoryDatatable() {
    const $table = $('#complaintCategoriesTable');
    if (!$table.length) {
        return null;
    }

    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/complaint_categories_datatable.php',
            type: 'POST',
            data: function (payload) {
                payload.status_filter = document.getElementById('complaintCategoryStatusFilter').value;
            }
        },
        order: [[0, 'desc']],
        pageLength: 10,
        columns: [
            { data: 'id' },
            { data: 'name' },
            { data: 'status', orderable: false },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });
}

function fillComplaintCategoryForm(record) {
    const form = document.getElementById('complaintCategoryForm');
    if (!form || !record) {
        return;
    }

    document.getElementById('complaintCategoryRecordId').value = record.id || '';
    document.getElementById('complaintCategoryFormModeLabel').textContent = record.id
        ? 'Edit Complaint Category'
        : 'Add Complaint Category';
    document.getElementById('submitComplaintCategoryBtn').innerHTML = record.id
        ? '<i class="bi bi-check-lg"></i> Update Complaint Category'
        : '<i class="bi bi-check-lg"></i> Save Complaint Category';

    form.querySelector('[name="name"]').value = record.name || '';
    form.querySelector('[name="status"]').value = record.status || 'active';
}

function resetComplaintCategoryForm() {
    const form = document.getElementById('complaintCategoryForm');
    if (!form) {
        return;
    }
    form.reset();
    document.getElementById('complaintCategoryRecordId').value = '';
    document.getElementById('complaintCategoryFormModeLabel').textContent = 'Add Complaint Category';
    document.getElementById('submitComplaintCategoryBtn').innerHTML = '<i class="bi bi-check-lg"></i> Save Complaint Category';
    form.querySelector('[name="status"]').value = 'active';
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}

function openComplaintCategoryFormPanel() {
    document.getElementById('complaintCategoryFormCard').classList.add('show');
    document.getElementById('openComplaintCategoryForm').style.display = 'none';
    document.getElementById('closeComplaintCategoryForm').classList.add('show');
}

function closeComplaintCategoryFormPanel() {
    document.getElementById('complaintCategoryFormCard').classList.remove('show');
    document.getElementById('openComplaintCategoryForm').style.display = 'flex';
    document.getElementById('closeComplaintCategoryForm').classList.remove('show');
    resetComplaintCategoryForm();
}

function bootComplaintCategoriesPage() {
    initComplaintCategoryFormValidation();
    const table = initComplaintCategoryDatatable();

    document.getElementById('cancelComplaintCategoryForm').addEventListener('click', closeComplaintCategoryFormPanel);
    document.getElementById('closeComplaintCategoryForm').addEventListener('click', closeComplaintCategoryFormPanel);
    document.getElementById('openComplaintCategoryForm').addEventListener('click', function () {
        resetComplaintCategoryForm();
        openComplaintCategoryFormPanel();
    });

    document.getElementById('complaintCategoryStatusFilter').addEventListener('change', function () {
        if (table) {
            table.ajax.reload();
        }
    });

    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-complaint-category-btn');
        if (!editBtn) {
            return;
        }
        const id = editBtn.getAttribute('data-id');
        $.getJSON('api/complaint_categories_get.php', { id: id })
            .done(function (record) {
                resetComplaintCategoryForm();
                fillComplaintCategoryForm(record);
                openComplaintCategoryFormPanel();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .fail(function () {
                alert('Failed to load complaint category details.');
            });
    });

    setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootComplaintCategoriesPage);
} else {
    bootComplaintCategoriesPage();
}