(function (window) {
    'use strict';

    function createCustomerFeedbackRating(config) {
        config = config || {};

        function getElements() {
            return {
                wrap: document.getElementById(config.wrapId || ''),
                input: document.getElementById(config.inputId || ''),
                selected: document.getElementById(config.selectedId || '')
            };
        }

        function getValidationMessageEl() {
            const form = config.formSelector ? document.querySelector(config.formSelector) : null;
            if (form) {
                return form.querySelector('.validation-msg[data-field="customer_feedback"]');
            }

            return document.querySelector('.validation-msg[data-field="customer_feedback"]');
        }

        function setHoverPreview(hoverRating) {
            const elements = getElements();
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
            const elements = getElements();
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

        function init() {
            const elements = getElements();
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

                    const msg = getValidationMessageEl();
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

            updateRatingDisplay('');
        }

        return {
            init: init,
            reset: function () {
                updateRatingDisplay('');
            },
            set: function (rating) {
                updateRatingDisplay(rating);
            },
            get: function () {
                const elements = getElements();
                return elements.input ? elements.input.value.trim() : '';
            },
            setError: function (message) {
                const elements = getElements();
                if (elements.wrap) {
                    elements.wrap.classList.toggle('is-invalid', !!message);
                }

                const msg = getValidationMessageEl();
                if (msg) {
                    msg.textContent = message || '';
                }
            }
        };
    }

    window.createCustomerFeedbackRating = createCustomerFeedbackRating;
})(window);