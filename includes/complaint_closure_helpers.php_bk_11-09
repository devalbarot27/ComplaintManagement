<?php

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