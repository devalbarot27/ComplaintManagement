<?php

if (!isset($roleOptions) || !is_array($roleOptions)) {
    $roleOptions = [];
}

if (!isset($salesCoordinatorOptions) || !is_array($salesCoordinatorOptions)) {
    $salesCoordinatorOptions = [];
}

$formRecord = $formRecord ?? [
    'id' => 0,
    'role' => 0,
    'username' => '',
    'name' => '',
    'email' => '',
    'mobile_number' => '',
    'sales_coordinator_id' => 0,
    'customer_code' => '',
    'level_1_approval' => false,
    'level_2_approval' => false,
];

$isEditForm = !empty($formRecord['id']);
$selectedRole = (int) ($formRecord['role'] ?? 0);
$selectedSalesCoordinatorId = (int) ($formRecord['sales_coordinator_id'] ?? 0);
$selectedCustomerCode = trim((string) ($formRecord['customer_code'] ?? ''));
$selectedCustomerLabel = '';
if ($selectedCustomerCode !== '' && isset($obconn) && $obconn instanceof PDO) {
    $selectedCustomerLabel = user_customer_code_label($obconn, $selectedCustomerCode);
}
$showSalesCoordinatorField = user_role_requires_sales_coordinator($selectedRole);
$showApprovalFields = user_role_has_approval_options($selectedRole);
$level1Checked = !empty($formRecord['level_1_approval']);
$level2Checked = !empty($formRecord['level_2_approval']);
?>
<div class="row g-3">
    <div class="col-md-6 form-group">
        <label class="form-label" for="userRoleSelect">
            <i class="bi bi-person-badge"></i> Role <span class="text-danger">*</span>
        </label>
        <select class="form-control" name="role" id="userRoleSelect">
            <option value="">Select role</option>
            <?php foreach ($roleOptions as $roleId => $roleLabel) { ?>
            <option value="<?php echo (int) $roleId; ?>"<?php echo $selectedRole === (int) $roleId ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($roleLabel); ?>
            </option>
            <?php } ?>
        </select>
        <div class="text-danger validation-msg" data-field="role"></div>
    </div>
    <div class="col-md-6 form-group" id="salesCoordinatorFieldWrap"<?php echo $showSalesCoordinatorField ? '' : ' style="display: none;"'; ?>>
        <label class="form-label" for="salesCoordinatorSelect">
            <i class="bi bi-person-check"></i> Sales Coordinator <span class="text-danger">*</span>
        </label>
        <select class="form-control" name="sales_coordinator_id" id="salesCoordinatorSelect">
            <option value="">Select Sales Coordinator</option>
            <?php foreach ($salesCoordinatorOptions as $salesCoordinator) { ?>
            <?php $optionId = (int) ($salesCoordinator['id'] ?? 0); ?>
            <option value="<?php echo $optionId; ?>"<?php echo $selectedSalesCoordinatorId === $optionId ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars(user_sales_coordinator_option_label($salesCoordinator)); ?>
            </option>
            <?php } ?>
        </select>
        <div class="text-danger validation-msg" data-field="sales_coordinator_id"></div>
    </div>
    <div class="col-md-6 form-group">
        <label class="form-label" for="userCustomerCodeSelect">
            <i class="bi bi-building"></i> Customer Code <span class="text-danger">*</span>
        </label>
        <select class="form-control" name="customer_code" id="userCustomerCodeSelect" style="width:100%;">
            <option value=""></option>
            <?php if ($selectedCustomerCode !== '') { ?>
            <option value="<?php echo htmlspecialchars($selectedCustomerCode, ENT_QUOTES, 'UTF-8'); ?>" selected>
                <?php echo htmlspecialchars($selectedCustomerLabel !== '' ? $selectedCustomerLabel : $selectedCustomerCode, ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php } ?>
        </select>
        <div class="text-danger validation-msg" data-field="customer_code"></div>
    </div>
    <div class="col-md-6 form-group">
        <label class="form-label">
            <i class="bi bi-person"></i> Username <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control" name="username" maxlength="100"
            placeholder="Unique login username" autocomplete="off"
            pattern="[A-Za-z0-9_]+"
            title="Letters, numbers, and underscore only. Special characters are not allowed."
            value="<?php echo htmlspecialchars((string) ($formRecord['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="text-danger validation-msg" data-field="username"></div>
    </div>
    <div class="col-md-6 form-group">
        <label class="form-label">
            <i class="bi bi-card-text"></i> Name <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control" name="name" maxlength="255"
            placeholder="Full name"
            pattern="[A-Za-z]+([ .'\-][A-Za-z]+)*"
            title="Letters, spaces, dots, hyphens, and apostrophes only. Special characters are not allowed."
            value="<?php echo htmlspecialchars((string) ($formRecord['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="text-danger validation-msg" data-field="name"></div>
    </div>
    <div class="col-md-6 form-group">
        <label class="form-label">
            <i class="bi bi-envelope"></i> Email <span class="text-danger">*</span>
        </label>
        <input type="email" class="form-control" name="email" maxlength="255"
            placeholder="user@example.com" autocomplete="off"
            pattern="[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}"
            title="Enter a valid email address without special characters."
            value="<?php echo htmlspecialchars((string) ($formRecord['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="text-danger validation-msg" data-field="email"></div>
    </div>
    <div class="col-md-6 form-group">
        <label class="form-label">
            <i class="bi bi-phone"></i> Mobile Number <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control" name="mobile_number" maxlength="10"
            placeholder="10-digit mobile number"
            pattern="[1-9][0-9]{9}"
            inputmode="numeric"
            title="Enter a valid 10-digit mobile number."
            value="<?php echo htmlspecialchars((string) ($formRecord['mobile_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="text-danger validation-msg" data-field="mobile_number"></div>
    </div>
    <div class="col-md-6 form-group">
        <label class="form-label">
            <i class="bi bi-key"></i> Password <span class="text-danger" id="userPasswordRequired"<?php echo $isEditForm ? ' style="display: none;"' : ''; ?>>*</span>
        </label>
        <div class="input-group">
            <input type="password" class="form-control" name="password" id="userPasswordInput"
                placeholder="Enter password" autocomplete="new-password">
            <button class="btn btn-outline-secondary" type="button"
                data-toggle-field="userPasswordInput" tabindex="-1">
                <i class="bi bi-eye-slash"></i>
            </button>
        </div>
        <small class="text-muted d-block mt-1" id="userPasswordHint">
            <?php echo $isEditForm
                ? 'Leave blank to keep the current password.'
                : 'Minimum 8 characters with digit, uppercase, lowercase, and special character.'; ?>
        </small>
        <div class="text-danger validation-msg" data-field="password"></div>
    </div>
    <div class="col-12 form-group" id="userApprovalFieldsWrap"<?php echo $showApprovalFields ? '' : ' style="display: none;"'; ?>>
        <label class="form-label d-block">
            <i class="bi bi-check2-square"></i> Approval
        </label>
        <div class="d-flex flex-wrap gap-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="level_1_approval" value="1"
                    id="userLevel1Approval"<?php echo $level1Checked ? ' checked' : ''; ?>>
                <label class="form-check-label" for="userLevel1Approval">Level 1 Approval</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="level_2_approval" value="1"
                    id="userLevel2Approval"<?php echo $level2Checked ? ' checked' : ''; ?>>
                <label class="form-check-label" for="userLevel2Approval">Level 2 Approval</label>
            </div>
        </div>
    </div>
</div>