function initVerifyOtpFormValidation() {
    const form = document.getElementById('otpForm');
    const inputs = document.querySelectorAll('.otp-inputs input');

    if (!form || !inputs.length) {
        return;
    }

    inputs.forEach(function (input, index) {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 1);
            if (this.value.length === 1 && inputs[index + 1]) {
                inputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !this.value && inputs[index - 1]) {
                inputs[index - 1].focus();
            }
        });
    });

    const otpMessage = document.getElementById('otpValidationMsg');
    const otpHiddenInput = document.getElementById('otp');

    form.addEventListener('submit', function (e) {
        let otp = '';
        inputs.forEach(function (input) {
            otp += input.value;
        });

        if (otpHiddenInput) {
            otpHiddenInput.value = otp;
        }

        if (otp.length !== 6) {
            e.preventDefault();
            if (otpMessage) {
                otpMessage.textContent = 'Please enter the complete 6-digit OTP.';
            }
            return;
        }

        if (otpMessage) {
            otpMessage.textContent = '';
        }
    });
}

function initResendOtpCountdown() {
    const resendBtn = document.getElementById('resendOtpBtn');
    const resendCounter = document.getElementById('resendOtpCounter');
    const resendLabel = document.getElementById('resendOtpLabel');
    const resendReady = document.getElementById('resendOtpReady');
    const resendForm = document.getElementById('resendOtpForm');

    if (!resendBtn) {
        return;
    }

    let secondsRemaining = parseInt(window.otpResendSecondsRemaining, 10);
    if (Number.isNaN(secondsRemaining) || secondsRemaining < 0) {
        secondsRemaining = 0;
    }

    function showCountdownState() {
        resendBtn.disabled = true;
        if (resendLabel) {
            resendLabel.style.display = '';
        }
        if (resendReady) {
            resendReady.style.display = 'none';
        }
        if (resendCounter) {
            resendCounter.textContent = String(secondsRemaining);
        }
    }

    function showReadyState() {
        resendBtn.disabled = false;
        if (resendLabel) {
            resendLabel.style.display = 'none';
        }
        if (resendReady) {
            resendReady.style.display = '';
        }
    }

    if (secondsRemaining > 0) {
        showCountdownState();
    } else {
        showReadyState();
    }

    if (secondsRemaining <= 0) {
        return;
    }

    const timer = window.setInterval(function () {
        secondsRemaining -= 1;

        if (secondsRemaining <= 0) {
            window.clearInterval(timer);
            showReadyState();
            return;
        }

        if (resendCounter) {
            resendCounter.textContent = String(secondsRemaining);
        }
    }, 1000);

    if (resendForm) {
        resendForm.addEventListener('submit', function (e) {
            if (resendBtn.disabled) {
                e.preventDefault();
            }
        });
    }
}

function bootVerifyOtpPage() {
    initVerifyOtpFormValidation();
    initResendOtpCountdown();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootVerifyOtpPage);
} else {
    bootVerifyOtpPage();
}
