<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

rbac_require_api_access($obconn);
installed_base_ensure_schema($obconn);

header('Content-Type: application/json; charset=utf-8');

$fabNumber = trim((string) ($_GET['fab_number'] ?? ''));
$complaintId = (int) ($_GET['complaint_id'] ?? 0);
$editingRecordId = (int) ($_GET['record_id'] ?? 0);

if ($fabNumber === '' && $complaintId <= 0) {
    api_json_echo(['found' => false, 'blocked' => false]);
    exit;
}

if ($fabNumber !== '') {
    $ownershipError = installed_base_validate_fab_for_current_user(
        $obconn,
        $fabNumber,
        null,
        $editingRecordId
    );

    if ($ownershipError !== null) {
        api_json_echo([
            'found' => false,
            'blocked' => true,
            'available' => false,
            'has_installed_base' => false,
            'message' => $ownershipError,
        ]);
        exit;
    }
}

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
    api_json_echo([
        'found' => false,
        'blocked' => false,
        'available' => true,
    ]);
    exit;
}

$commissioningDate = '';
if ($hasInstalledBase && !empty($installedBaseRow['commissioning_date'])) {
    $commissioningDate = installed_base_format_date_for_input(
        (string) $installedBaseRow['commissioning_date']
    );
}

$machineModelCode = '';
$machineModelDesc = '';
if ($hasInstalledBase) {
    $machineModelCode = (string) ($installedBaseRow['machine_model_code'] ?? '');
    $machineModelDesc = (string) ($installedBaseRow['machine_model'] ?? '');
}

$customerId = (int) ($row['customer_id'] ?? ($installedBaseRow['customer_id'] ?? 0));
$customerLabel = (string) ($row['customer_label'] ?? ($installedBaseRow['customer_label'] ?? ''));

$response = [
    'found' => true,
    'blocked' => false,
    'available' => true,
    'has_installed_base' => $hasInstalledBase,
    'customer_id' => $customerId > 0 ? $customerId : '',
    'customer_label' => $customerLabel,
    'machine_model_code' => $machineModelCode,
    'machine_model' => $machineModelDesc,
    'commissioning_date' => $hasInstalledBase ? $commissioningDate : '',
    'running_hours' => $hasInstalledBase
        ? (string) ($installedBaseRow['running_hours'] ?? '')
        : '',
    'downstream' => $hasInstalledBase
        ? (string) ($installedBaseRow['downstream'] ?? '')
        : '',
    'industry_segment' => $hasInstalledBase
        ? (string) ($installedBaseRow['industry_segment'] ?? '')
        : '',
    'remarks' => $hasInstalledBase
        ? (string) ($installedBaseRow['remarks'] ?? '')
        : '',
    'message' => '',
];

unset($row, $installedBaseRow);
api_json_echo($response);
