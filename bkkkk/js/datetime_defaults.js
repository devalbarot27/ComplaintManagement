function getCurrentDateLocal() {
    const now = new Date();
    const pad = function (n) {
        return String(n).padStart(2, '0');
    };

    return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
}

function getCurrentDateTimeLocal() {
    const now = new Date();
    const pad = function (n) {
        return String(n).padStart(2, '0');
    };

    return getCurrentDateLocal() + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
}

function setCurrentDateInput(input) {
    if (input) {
        input.value = getCurrentDateLocal();
    }
}

function setCurrentDateTimeInput(input) {
    if (input) {
        input.value = getCurrentDateTimeLocal();
    }
}
