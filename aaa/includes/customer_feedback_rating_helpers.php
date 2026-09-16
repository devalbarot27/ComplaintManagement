<?php

function customer_feedback_rating_is_valid(string $value): bool
{
    $value = trim($value);
    if ($value === '' || !ctype_digit($value)) {
        return false;
    }

    $rating = (int) $value;

    return $rating >= 1 && $rating <= 10;
}

function customer_feedback_rating_validate(string $value, string $emptyMessage = 'Customer Feedback is required.'): ?string
{
    if (trim($value) === '') {
        return $emptyMessage;
    }

    if (!customer_feedback_rating_is_valid($value)) {
        return 'Please select a customer feedback rating between 1 and 10.';
    }

    return null;
}

function customer_feedback_rating_render_star_html(int $rating, bool $withLabel = true): string
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

function customer_feedback_rating_display(?string $feedback): string
{
    $feedback = trim((string) ($feedback ?? ''));
    if ($feedback === '') {
        return '-';
    }

    if (customer_feedback_rating_is_valid($feedback)) {
        return customer_feedback_rating_render_star_html((int) $feedback);
    }

    return htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8');
}

function service_log_render_customer_feedback_rating_field(string $prefix): void
{
    $wrapId = $prefix . 'CustomerFeedbackRating';
    $inputId = $prefix . 'CustomerFeedbackInput';
    $selectedId = $prefix . 'CustomerFeedbackSelected';
    ?>
    <label class="form-label" for="<?php echo htmlspecialchars($wrapId, ENT_QUOTES, 'UTF-8'); ?>">
        <i class="bi bi-star"></i>
        Customer Feedback <span class="text-danger">*</span>
    </label>
    <div class="complaint-closure-rating" id="<?php echo htmlspecialchars($wrapId, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="customer_feedback" id="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>" value="">
        <div class="closure-rating__items" role="group" aria-label="Customer feedback rating from 1 to 10">
            <?php for ($rating = 1; $rating <= 10; $rating++) { ?>
            <button type="button"
                class="closure-rating__item"
                data-rating="<?php echo $rating; ?>"
                aria-label="Rate <?php echo $rating; ?> out of 10">
                <i class="bi bi-star-fill"></i>
                <span><?php echo $rating; ?></span>
            </button>
            <?php } ?>
        </div>
        <div class="closure-rating__selected" id="<?php echo htmlspecialchars($selectedId, ENT_QUOTES, 'UTF-8'); ?>">No rating selected</div>
    </div>
    <div class="text-danger validation-msg" data-field="customer_feedback"></div>
    <?php
}