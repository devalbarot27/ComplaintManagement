(function (window) {
    'use strict';

    const RATING_INPUT_ID = 'closureCustomerFeedbackInput';
    const RATING_WRAP_ID = 'closureCustomerFeedbackRating';
    const RATING_SELECTED_ID = 'closureCustomerFeedbackSelected';

    function getRatingElements() {
        return {
            wrap: document.getElementById(RATING_WRAP_ID),
            input: document.getElementById(RATING_INPUT_ID),
            selected: document.getElementById(RATING_SELECTED_ID)
        };
    }

    function setHoverPreview(hoverRating) {
        const elements = getRatingElements();
        if (!elements.wrap) {
            return;
        }

        const value = parseInt(hoverRating, 10);
        const hasHover = Number.isInteger(value) && value >= 1 && value <= 10;

        elements.wrap.querySelectorAll('.closure-rating__item').forEach(function (button) {
            const itemRating = parseInt(button.getAttribute('data-rating'), 10);
            button.classList.toggle('is-hover', hasHover && itemRating <= value);
        });
    }

    function updateRatingDisplay(rating) {
        const elements = getRatingElements();
        if (!elements.wrap) {
            return;
        }

        const value = parseInt(rating, 10);
        const hasRating = Number.isInteger(value) && value >= 1 && value <= 10;

        elements.wrap.querySelectorAll('.closure-rating__item').forEach(function (button) {
            const itemRating = parseInt(button.getAttribute('data-rating'), 10);
            button.classList.toggle('is-selected', hasRating && itemRating === value);
            button.classList.toggle('is-active', hasRating && itemRating <= value);
            button.setAttribute('aria-pressed', hasRating && itemRating === value ? 'true' : 'false');
        });

        if (elements.input) {
            elements.input.value = hasRating ? String(value) : '';
        }

        if (elements.selected) {
            elements.selected.textContent = hasRating
                ? 'Selected rating: ' + value + '/10'
                : 'No rating selected';
        }

        if (hasRating) {
            elements.wrap.classList.remove('is-invalid');
        }
    }

    function resetClosureCustomerFeedbackRating() {
        updateRatingDisplay('');
    }

    function setClosureCustomerFeedbackRating(rating) {
        updateRatingDisplay(rating);
    }

    function initClosureCustomerFeedbackRating() {
        const elements = getRatingElements();
        if (!elements.wrap || elements.wrap.dataset.ratingInitialized === '1') {
            return;
        }

        elements.wrap.dataset.ratingInitialized = '1';

        const itemsWrap = elements.wrap.querySelector('.closure-rating__items');

        elements.wrap.querySelectorAll('.closure-rating__item').forEach(function (button) {
            button.addEventListener('mouseenter', function () {
                const rating = parseInt(button.getAttribute('data-rating'), 10);
                if (Number.isInteger(rating)) {
                    setHoverPreview(rating);
                }
            });

            button.addEventListener('click', function () {
                const rating = parseInt(button.getAttribute('data-rating'), 10);
                if (!Number.isInteger(rating)) {
                    return;
                }

                updateRatingDisplay(rating);

                const msg = document.querySelector('#closureForm .validation-msg[data-field="customer_feedback"]');
                if (msg) {
                    msg.textContent = '';
                }
            });
        });

        if (itemsWrap) {
            itemsWrap.addEventListener('mouseleave', function () {
                setHoverPreview(0);
            });
        }

        resetClosureCustomerFeedbackRating();
    }

    window.initClosureCustomerFeedbackRating = initClosureCustomerFeedbackRating;
    window.resetClosureCustomerFeedbackRating = resetClosureCustomerFeedbackRating;
    window.setClosureCustomerFeedbackRating = setClosureCustomerFeedbackRating;
    window.getClosureCustomerFeedbackRating = function () {
        const elements = getRatingElements();
        return elements.input ? elements.input.value.trim() : '';
    };
    window.setClosureCustomerFeedbackRatingError = function (message) {
        const elements = getRatingElements();
        if (elements.wrap) {
            elements.wrap.classList.add('is-invalid');
        }

        const msg = document.querySelector('#closureForm .validation-msg[data-field="customer_feedback"]');
        if (msg) {
            msg.textContent = message || '';
        }
    };
})(window);