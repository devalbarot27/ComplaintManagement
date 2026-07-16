/**
 * Reusable success modal (no external dependencies).
 *
 * Usage:
 *   SuccessModal.show({
 *     title: 'Order Created Successfully',
 *     message: 'Your order has been created successfully.',
 *     status: res.status,          // optional
 *     onClose: function () { ... } // optional
 *   });
 */
(function (window, document) {
  'use strict';

  var OVERLAY_ID = 'appSuccessModalOverlay';
  var isOpen = false;
  var onCloseCallback = null;
  var previouslyFocused = null;

  function ensureDom() {
    var existing = document.getElementById(OVERLAY_ID);
    if (existing) {
      return existing;
    }

    var overlay = document.createElement('div');
    overlay.id = OVERLAY_ID;
    overlay.className = 'success-modal-overlay';
    overlay.setAttribute('role', 'presentation');
    overlay.innerHTML =
      '<div class="success-modal" role="dialog" aria-modal="true" aria-labelledby="appSuccessModalTitle" aria-describedby="appSuccessModalMessage" tabindex="-1">' +
      '  <div class="success-modal__icon" aria-hidden="true"><i class="bi bi-check-circle-fill"></i></div>' +
      '  <h3 class="success-modal__title" id="appSuccessModalTitle"></h3>' +
      '  <p class="success-modal__message" id="appSuccessModalMessage"></p>' +
      '  <p class="success-modal__status" id="appSuccessModalStatus" hidden></p>' +
      '  <div class="success-modal__actions">' +
      '    <button type="button" class="success-modal__ok" id="appSuccessModalOk">OK</button>' +
      '  </div>' +
      '</div>';

    document.body.appendChild(overlay);

    // Click outside (on overlay backdrop) closes the modal
    overlay.addEventListener('click', function (event) {
      if (event.target === overlay) {
        close();
      }
    });

    // Prevent clicks inside the dialog from bubbling to the overlay
    var dialog = overlay.querySelector('.success-modal');
    if (dialog) {
      dialog.addEventListener('click', function (event) {
        event.stopPropagation();
      });
    }

    var okBtn = document.getElementById('appSuccessModalOk');
    if (okBtn) {
      okBtn.addEventListener('click', function () {
        close();
      });
    }

    return overlay;
  }

  function onKeyDown(event) {
    if (!isOpen) {
      return;
    }

    if (event.key === 'Escape' || event.key === 'Enter') {
      event.preventDefault();
      close();
    }
  }

  function show(options) {
    options = options || {};

    // Prevent stacking multiple success popups
    if (isOpen) {
      return;
    }

    var overlay = ensureDom();
    var titleEl = document.getElementById('appSuccessModalTitle');
    var messageEl = document.getElementById('appSuccessModalMessage');
    var statusEl = document.getElementById('appSuccessModalStatus');
    var okBtn = document.getElementById('appSuccessModalOk');
    var dialog = overlay.querySelector('.success-modal');

    titleEl.textContent = options.title || 'Success';
    messageEl.textContent = options.message || 'Your action completed successfully.';

    var statusValue = options.status;
    if (statusValue !== undefined && statusValue !== null && String(statusValue).trim() !== '') {
      statusEl.hidden = false;
      statusEl.textContent = 'Status: ' + String(statusValue);
    } else {
      statusEl.hidden = true;
      statusEl.textContent = '';
    }

    onCloseCallback = typeof options.onClose === 'function' ? options.onClose : null;
    previouslyFocused = document.activeElement;
    isOpen = true;

    overlay.classList.add('is-open');
    document.addEventListener('keydown', onKeyDown);

    // Focus OK for Enter accessibility
    window.setTimeout(function () {
      if (okBtn) {
        okBtn.focus();
      } else if (dialog) {
        dialog.focus();
      }
    }, 30);
  }

  function close() {
    if (!isOpen) {
      return;
    }

    var overlay = document.getElementById(OVERLAY_ID);
    if (overlay) {
      overlay.classList.remove('is-open');
    }

    document.removeEventListener('keydown', onKeyDown);
    isOpen = false;

    var callback = onCloseCallback;
    onCloseCallback = null;

    if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
      try {
        previouslyFocused.focus();
      } catch (e) {
        /* ignore focus restore errors */
      }
    }
    previouslyFocused = null;

    if (callback) {
      callback();
    }
  }

  window.SuccessModal = {
    show: show,
    close: close,
    isOpen: function () {
      return isOpen;
    }
  };
})(window, document);