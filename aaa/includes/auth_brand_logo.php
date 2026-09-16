<?php
$brandLogoClass = $brandLogoClass ?? 'brand-logo';
$brandImageClass = $brandImageClass ?? 'brand-logo__image';
$brandLogoAlt = $brandLogoAlt ?? 'Dealer Portal';
$brandLogoSrc = $brandLogoSrc ?? 'uploads/vayu.png';
?>
<div class="<?php echo htmlspecialchars($brandLogoClass, ENT_QUOTES, 'UTF-8'); ?>">
    <img
        src="<?php echo htmlspecialchars($brandLogoSrc, ENT_QUOTES, 'UTF-8'); ?>"
        alt="<?php echo htmlspecialchars($brandLogoAlt, ENT_QUOTES, 'UTF-8'); ?>"
        class="<?php echo htmlspecialchars($brandImageClass, ENT_QUOTES, 'UTF-8'); ?>"
    >
</div>
