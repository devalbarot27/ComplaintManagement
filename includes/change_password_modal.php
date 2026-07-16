<?php
$changePasswordRedirect = htmlspecialchars(
    $_SERVER['REQUEST_URI'] ?? 'index.php',
    ENT_QUOTES,
    'UTF-8'
);
$openChangePasswordModal = !empty($_SESSION['open_change_password_modal']);
$changePasswordError = '';
if ($openChangePasswordModal) {
    $changePasswordError = trim((string) ($_SESSION['error_message'] ?? ''));
    unset($_SESSION['open_change_password_modal'], $_SESSION['error_message']);
}
?>
<link href="css/complaint_form.css" rel="stylesheet" />
<link href="css/complaint_buttons.css" rel="stylesheet" />
<style>
.change-password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 18px;
    cursor: pointer;
    padding: 0;
}
.change-password-toggle:hover { color: #1565d8; }
.change-password-field { position: relative; }
.change-password-field .form-control { padding-right: 44px; }
</style>

<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content complaint-form-modal">
            <form method="post" action="change_password.php" id="changePasswordForm" novalidate>
                <input type="hidden" name="redirect_to" value="<?php echo $changePasswordRedirect; ?>">

                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <div>
                            <h2 class="complaint-form-header__title">Change Password</h2>
                            <p class="complaint-form-header__subtitle">Update your account password.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="complaint-form-body">
                    <?php if ($changePasswordError !== '') { ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($changePasswordError); ?>
                    </div>
                    <?php } ?>

                    <section class="complaint-form-section">
                        <div class="row g-3">
                            <div class="col-12 form-group">
                                <label class="form-label" for="current_password">
                                    <i class="bi bi-key"></i>
                                    Current Password <span class="text-danger">*</span>
                                </label>
                                <div class="change-password-field">
                                    <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password">
                                    <button type="button" class="change-password-toggle" data-toggle-password="current_password" aria-label="Show password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="text-danger validation-msg" data-field="current_password"></div>
                            </div>

                            <div class="col-12 form-group">
                                <label class="form-label" for="change_new_password">
                                    <i class="bi bi-lock"></i>
                                    New Password <span class="text-danger">*</span>
                                </label>
                                <div class="change-password-field">
                                    <input type="password" class="form-control" id="change_new_password" name="new_password" autocomplete="new-password">
                                    <button type="button" class="change-password-toggle" data-toggle-password="change_new_password" aria-label="Show password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Minimum 8 characters with at least one digit, uppercase letter, lowercase letter, and special character.
                                </small>
                                <div class="text-danger validation-msg" data-field="new_password"></div>
                            </div>

                            <div class="col-12 form-group">
                                <label class="form-label" for="change_confirm_password">
                                    <i class="bi bi-lock-fill"></i>
                                    Confirm Password <span class="text-danger">*</span>
                                </label>
                                <div class="change-password-field">
                                    <input type="password" class="form-control" id="change_confirm_password" name="confirm_password" autocomplete="new-password">
                                    <button type="button" class="change-password-toggle" data-toggle-password="change_confirm_password" aria-label="Show password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="text-danger validation-msg" data-field="confirm_password"></div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="complaint-form-actions">
                    <button type="button" class="btn btn-light btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="submit-btn btn-complaint-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/validate.js/0.13.1/validate.min.js"></script>
<script>window.openChangePasswordModal = <?php echo $openChangePasswordModal ? 'true' : 'false'; ?>;</script>
<script src="js/change_password_validation.js"></script>
