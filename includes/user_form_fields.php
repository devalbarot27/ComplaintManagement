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
];

$isEditForm = !empty($formRecord['id']);
$selectedRole = (int) ($formRecord['role'] ?? 0);
$selectedSalesCoordinatorId = (int) ($formRecord['sales_coordinator_id'] ?? 0);
$showSalesCoordinatorField = user_role_requires_sales_coordinator($selectedRole);
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
        <label class="form-label">
            <i class="bi bi-person"></i> Username <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control" name="username" maxlength="100"
            placeholder="Unique login username" autocomplete="off"
            value="<?php echo htmlspecialchars((string) ($formRecord['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="text-danger validation-msg" data-field="username"></div>
    </div>
    <div class="col-md-6 form-group">
        <label class="form-label">
            <i class="bi bi-card-text"></i> Name <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control" name="name" maxlength="255"
            placeholder="Full name"
            value="<?php echo htmlspecialchars((string) ($formRecord['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="text-danger validation-msg" data-field="name"></div>
    </div>
    <div class="col-md-6 form-group">
        <label class="form-label">
            <i class="bi bi-envelope"></i> Email <span class="text-danger">*</span>
        </label>
        <input type="email" class="form-control" name="email" maxlength="255"
            placeholder="user@example.com" autocomplete="off"
            value="<?php echo htmlspecialchars((string) ($formRecord['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="text-danger validation-msg" data-field="email"></div>
    </div>
    <div class="col-md-6 form-group">
        <label class="form-label">
            <i class="bi bi-phone"></i> Mobile Number <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control" name="mobile_number" maxlength="10"
            placeholder="10-digit mobile number"
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
</div>