function getPermissionCheckboxes() {
    return Array.prototype.slice.call(document.querySelectorAll('.permission-checkbox'));
}

function updateMasterCheckboxState() {
    const master = document.getElementById('checkAllPermissions');
    if (!master) {
        return;
    }

    const boxes = getPermissionCheckboxes();
    if (boxes.length === 0) {
        master.checked = false;
        master.indeterminate = false;
        return;
    }

    const checkedCount = boxes.filter(function (box) {
        return box.checked;
    }).length;

    master.checked = checkedCount === boxes.length;
    master.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
}

function updateModuleCheckboxStates() {
    document.querySelectorAll('.rbac-module-block').forEach(function (block) {
        const moduleCheck = block.querySelector('.module-check-all');
        const boxes = Array.prototype.slice.call(block.querySelectorAll('.permission-checkbox'));
        if (!moduleCheck || boxes.length === 0) {
            return;
        }

        const checkedCount = boxes.filter(function (box) {
            return box.checked;
        }).length;

        moduleCheck.checked = checkedCount === boxes.length;
        moduleCheck.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    });
}

function setAllPermissionCheckboxes(checked) {
    getPermissionCheckboxes().forEach(function (box) {
        box.checked = checked;
    });
    updateMasterCheckboxState();
    updateModuleCheckboxStates();
}

function initAssignPermissionCheckAll() {
    const master = document.getElementById('checkAllPermissions');
    const clearBtn = document.getElementById('clearAllPermissions');

    if (master) {
        master.addEventListener('change', function () {
            setAllPermissionCheckboxes(master.checked);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            setAllPermissionCheckboxes(false);
        });
    }

    document.querySelectorAll('.module-check-all').forEach(function (moduleCheck) {
        moduleCheck.addEventListener('change', function () {
            const block = moduleCheck.closest('.rbac-module-block');
            if (!block) {
                return;
            }

            block.querySelectorAll('.permission-checkbox').forEach(function (box) {
                box.checked = moduleCheck.checked;
            });

            updateMasterCheckboxState();
            updateModuleCheckboxStates();
        });
    });

    getPermissionCheckboxes().forEach(function (box) {
        box.addEventListener('change', function () {
            updateMasterCheckboxState();
            updateModuleCheckboxStates();
        });
    });

    updateMasterCheckboxState();
    updateModuleCheckboxStates();
}

function bootAssignPermissionsPage() {
    const roleSelect = document.getElementById('roleSelect');
    if (roleSelect) {
        roleSelect.addEventListener('change', function () {
            if (roleSelect.value !== '') {
                document.getElementById('roleSelectForm').submit();
            }
        });
    }

    initAssignPermissionCheckAll();

    setTimeout(function () {
        $('.alert-success').fadeOut();
    }, 3000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAssignPermissionsPage);
} else {
    bootAssignPermissionsPage();
}
