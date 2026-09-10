(function () {
    var IDLE_MS = 20 * 60 * 1000;
    var TOUCH_THROTTLE_MS = 60 * 1000;
    var idleTimer = null;
    var lastTouchAt = 0;
    var activityEvents = [
        'mousedown',
        'mousemove',
        'keydown',
        'scroll',
        'touchstart',
        'click',
        'wheel',
        'pointerdown',
        'input',
    ];

    function redirectToLogout() {
        window.location.href = 'logout.php';
    }

    function touchServerActivity() {
        var now = Date.now();
        if (now - lastTouchAt < TOUCH_THROTTLE_MS) {
            return;
        }
        lastTouchAt = now;

        fetch('api/session_idle_touch.php', {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Accept': 'application/json' },
        }).then(function (res) {
            if (res.status === 401) {
                redirectToLogout();
            }
        }).catch(function () {
            // Ignore network errors; client idle timer still applies.
        });
    }

    function resetIdleTimer() {
        if (idleTimer !== null) {
            window.clearTimeout(idleTimer);
        }
        idleTimer = window.setTimeout(redirectToLogout, IDLE_MS);
        touchServerActivity();
    }

    activityEvents.forEach(function (eventName) {
        document.addEventListener(eventName, resetIdleTimer, {
            capture: true,
            passive: true,
        });
    });

    window.addEventListener('focus', resetIdleTimer);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            resetIdleTimer();
        }
    });

    resetIdleTimer();
})();
