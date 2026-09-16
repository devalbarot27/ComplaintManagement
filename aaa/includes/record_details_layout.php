<?php

function record_details_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function record_details_display_value(?string $value): string
{
    $trimmed = trim((string) $value);

    return $trimmed !== '' ? $trimmed : '-';
}

/**
 * @param array<int, string> $metaHtml
 */
function record_details_page_header(
    string $eyebrow,
    string $title,
    string $backUrl,
    string $backLabel,
    string $icon = 'bi-file-text',
    array $metaHtml = []
): void {
    ?>
    <div class="record-details-header mb-4">
        <div class="record-details-header__main">
            <div class="record-details-header__icon" aria-hidden="true">
                <i class="bi <?php echo record_details_escape($icon); ?>"></i>
            </div>
            <div>
                <div class="record-details-header__eyebrow"><?php echo record_details_escape($eyebrow); ?></div>
                <h1 class="record-details-header__title"><?php echo record_details_escape($title); ?></h1>
                <?php if ($metaHtml !== []) { ?>
                <div class="record-details-header__meta">
                    <?php foreach ($metaHtml as $metaItem) { ?>
                        <?php echo $metaItem; ?>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
        <a href="<?php echo record_details_escape($backUrl); ?>" class="btn btn-light border record-details-back-btn">
            <i class="bi bi-arrow-left"></i>
            <?php echo record_details_escape($backLabel); ?>
        </a>
    </div>
    <?php
}

function record_details_card_start(): void
{
    echo '<div class="record-details-card mb-3"><div class="record-details-card__body">';
}

function record_details_card_end(): void
{
    echo '</div></div>';
}

function record_details_section_start(int $number, string $title, string $hint, bool $isLast = false): void
{
    $sectionClass = 'complaint-form-section' . ($isLast ? ' mb-0' : '');
    ?>
    <section class="<?php echo $sectionClass; ?>">
        <div class="complaint-form-section__head">
            <span class="complaint-form-section__badge"><?php echo (int) $number; ?></span>
            <div>
                <h2 class="complaint-form-section__title"><?php echo record_details_escape($title); ?></h2>
                <p class="complaint-form-section__hint"><?php echo record_details_escape($hint); ?></p>
            </div>
        </div>
        <div class="row g-3">
    <?php
}

function record_details_section_end(): void
{
    echo '</div></section>';
}

function record_details_field(
    string $label,
    string $value,
    string $colClass = 'col-md-4',
    bool $multiline = false,
    bool $allowHtml = false
): void {
    $display = record_details_display_value($value);
    ?>
    <div class="<?php echo record_details_escape($colClass); ?> complaint-detail-field">
        <div class="complaint-detail-field__label"><?php echo record_details_escape($label); ?></div>
        <div class="complaint-detail-field__value<?php echo $multiline ? ' complaint-detail-field__value--multiline' : ''; ?>">
            <?php
            if ($allowHtml) {
                echo $value;
            } elseif ($multiline && $display !== '-') {
                echo nl2br(record_details_escape($display));
            } else {
                echo record_details_escape($display);
            }
            ?>
        </div>
    </div>
    <?php
}

function record_details_id_chip(int $id): string
{
    return '<span class="record-details-chip"><i class="bi bi-hash"></i> ' . (int) $id . '</span>';
}
