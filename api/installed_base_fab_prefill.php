<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$fabNumber = trim((string) ($_GET['fab_number'] ?? ''));
$complaintId = (int) ($_GET['complaint_id'] ?? 0);

if ($fabNumber === '' && $complaintId <= 0) {
    api_json_echo(['found' => false]);
    exit;
}

require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';

$row = installed_base_fab_prefill_row(
    $obconn,
    $fabNumber,
    $complaintId > 0 ? $complaintId : null
);

$installedBaseRow = $fabNumber !== ''
    ? installed_base_latest_record_by_fab($obconn, $fabNumber)
    : null;
$hasInstalledBase = $installedBaseRow !== null;

if (!$row && !$hasInstalledBase) {
    api_json_echo(['found' => false]);
    exit;
}

$commissioningDate = '';
if ($hasInstalledBase && !empty($installedBaseRow['commissioning_date'])) {
    $commissioningDate = substr((string) $installedBaseRow['commissioning_date'], 0, 10);
}

// Machine Model comes only from Installed Base when FAB already exists.
$machineModelCode = '';
$machineModelDesc = '';
if ($hasInstalledBase) {
    $machineModelCode = (string) ($installedBaseRow['machine_model_code'] ?? '');
    $machineModelDesc = (string) ($installedBaseRow['machine_model'] ?? '');
}

$response = [
    'found' => true,
    'has_installed_base' => $hasInstalledBase,
    'customer_name' => (string) ($row['customer_name'] ?? ($installedBaseRow['customer_name'] ?? '')),
    'street_1' => (string) ($row['street_1'] ?? ($installedBaseRow['street_1'] ?? '')),
    'street_2' => (string) ($row['street_2'] ?? ($installedBaseRow['street_2'] ?? '')),
    'pincode' => (string) ($row['pincode'] ?? ($installedBaseRow['pincode'] ?? '')),
    'city' => (string) ($row['city'] ?? ($installedBaseRow['city'] ?? '')),
    'district' => (string) ($row['district'] ?? ($installedBaseRow['district'] ?? '')),
    'state' => (string) ($row['state'] ?? ($installedBaseRow['state'] ?? '')),
    'mobile' => (string) ($row['mobile'] ?? ($installedBaseRow['mobile'] ?? '')),
    'email' => (string) ($row['email'] ?? ($installedBaseRow['email'] ?? '')),
    'machine_model_code' => $machineModelCode,
    'machine_model' => $machineModelDesc,
    'commissioning_date' => $hasInstalledBase ? $commissioningDate : '',
    'running_hours' => $hasInstalledBase
        ? (string) ($installedBaseRow['running_hours'] ?? '')
        : '',
    'industry_segment' => $hasInstalledBase
        ? (string) ($installedBaseRow['industry_segment'] ?? '')
        : '',
    'remarks' => $hasInstalledBase
        ? (string) ($installedBaseRow['remarks'] ?? '')
        : '',
];

unset($row, $installedBaseRow);
api_json_echo($response);
