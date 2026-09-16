<?php
/**
 * Read-only Warranty / AMC fields for Service Log add/edit forms.
 * Filled from Installed Base commissioning date and AMC coverage for the FAB.
 */
?>
<div class="col-md-4 form-group">
    <label class="form-label"><i class="bi bi-shield-check"></i> Warranty</label>
    <input type="text" class="form-control address-auto-field" name="machine_warranty" readonly
        placeholder="Auto-filled from Fab Number" style="background-color:#f8f9fa;">
</div>
<div class="col-md-4 form-group service-log-warranty-end-wrap d-none">
    <label class="form-label service-log-warranty-end-label"><i class="bi bi-calendar-x"></i> Warranty Ended On</label>
    <input type="text" class="form-control address-auto-field" name="warranty_ended_on" readonly
        placeholder="Auto-filled from Fab Number" style="background-color:#f8f9fa;">
</div>
<div class="col-md-4 form-group">
    <label class="form-label"><i class="bi bi-clipboard-check"></i> Under AMC</label>
    <input type="text" class="form-control address-auto-field" name="under_amc" readonly
        placeholder="Auto-filled from Fab Number" style="background-color:#f8f9fa;">
</div>
<div class="col-md-4 form-group service-log-amc-end-wrap d-none">
    <label class="form-label"><i class="bi bi-calendar-event"></i> AMC End Date</label>
    <input type="text" class="form-control address-auto-field" name="amc_end_date" readonly
        placeholder="Auto-filled from Fab Number" style="background-color:#f8f9fa;">
</div>
