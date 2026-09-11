<?php

require_once __DIR__ . '/warranty_claims_helpers.php';
require_once __DIR__ . '/distance_wise_price_helpers.php';

function complaint_closure_table_has_column(PDO $conn, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'complaint_closures'
          AND column_name = :column_name
        LIMIT 1
    ");
    $stmt->bindValue(':column_name', $column);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function complaint_closure_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    if (!complaint_closure_table_has_column($conn, 'km_travelled')) {
        $conn->exec('ALTER TABLE complaint_closures ADD COLUMN km_travelled NUMERIC(8,2) NULL');
    }
    if (!complaint_closure_table_has_column($conn, 'visit_charge_price')) {
        $conn->exec('ALTER TABLE complaint_closures ADD COLUMN visit_charge_price NUMERIC(12,2) NULL');
    }
    if (!complaint_closure_table_has_column($conn, 'service_date')) {
        $conn->exec('ALTER TABLE complaint_closures ADD COLUMN service_date DATE NULL');
    }
    if (!complaint_closure_table_has_column($conn, 'service_claim_id')) {
        $conn->exec('ALTER TABLE complaint_closures ADD COLUMN service_claim_id INTEGER NULL');
    }

    $ensured = true;
}

/**
 * Latest Warranty Service Claim distance details for a call ticket.
 *
 * @return array{
 *     found: bool,
 *     service_claim_id: int,
 *     km_travelled: string,
 *     km_travelled_label: string,
 *     visit_charge_price: string,
 *     visit_charge_label: string,
 *     service_date: string,
 *     service_date_label: string
 * }
 */
function complaint_closure_distance_from_service_claim(PDO $conn, int $complaintId): array
{
    $empty = [
        'found' => false,
        'service_claim_id' => 0,
        'km_travelled' => '',
        'km_travelled_label' => '',
        'visit_charge_price' => '',
        'visit_charge_label' => '',
        'service_date' => '',
        'service_date_label' => '',
    ];

    $claim = service_claim_latest_for_complaint($conn, $complaintId);
    if (!$claim) {
        return $empty;
    }

    $km = trim((string) ($claim['km_travelled'] ?? ''));
    $price = trim((string) ($claim['visit_charge_price'] ?? ''));
    $serviceDate = trim((string) ($claim['service_date'] ?? ''));
    $serviceDateLabel = '';
    if ($serviceDate !== '') {
        $timestamp = strtotime($serviceDate);
        $serviceDateLabel = $timestamp ? date('d M Y', $timestamp) : $serviceDate;
    }

    return [
        'found' => true,
        'service_claim_id' => (int) ($claim['id'] ?? 0),
        'km_travelled' => $km,
        'km_travelled_label' => $km !== '' ? distance_wise_price_format_number($km) : '',
        'visit_charge_price' => $price,
        'visit_charge_label' => $price !== '' ? distance_wise_price_format_rupees($price) : '',
        'service_date' => $serviceDate,
        'service_date_label' => $serviceDateLabel,
    ];
}

function complaint_closure_is_valid_customer_feedback(string $value): bool
{
    $value = trim($value);
    if ($value === '' || !ctype_digit($value)) {
        return false;
    }

    $rating = (int) $value;

    return $rating >= 1 && $rating <= 10;
}

function complaint_closure_validate_customer_feedback(string $value): ?string
{
    if (trim($value) === '') {
        return 'Customer feedback is required when call closure is Yes.';
    }

    if (!complaint_closure_is_valid_customer_feedback($value)) {
        return 'Please select a customer feedback rating between 1 and 10.';
    }

    return null;
}

function complaint_closure_customer_feedback_is_rating(string $value): bool
{
    return complaint_closure_is_valid_customer_feedback($value);
}

function complaint_closure_render_star_rating_html(int $rating, bool $withLabel = true): string
{
    $rating = max(1, min(10, $rating));
    $html = '<span class="complaint-feedback-rating" title="' . $rating . ' out of 10">';

    for ($i = 1; $i <= 10; $i++) {
        $activeClass = $i <= $rating ? ' is-active' : '';
        $html .= '<i class="bi bi-star-fill complaint-feedback-rating__star' . $activeClass . '"></i>';
    }

    if ($withLabel) {
        $html .= '<span class="complaint-feedback-rating__value">' . $rating . '/10</span>';
    }

    $html .= '</span>';

    return $html;
}

function complaint_closure_display_customer_feedback(?string $feedback): string
{
    $feedback = trim((string) ($feedback ?? ''));
    if ($feedback === '') {
        return '-';
    }

    if (complaint_closure_customer_feedback_is_rating($feedback)) {
        return complaint_closure_render_star_rating_html((int) $feedback);
    }

    return htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8');
}

function complaint_closure_customer_feedback_activity_label(?string $feedback): string
{
    $feedback = trim((string) ($feedback ?? ''));
    if ($feedback === '') {
        return '';
    }

    if (complaint_closure_customer_feedback_is_rating($feedback)) {
        return ((int) $feedback) . '/10';
    }

    return $feedback;
}