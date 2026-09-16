function checkPasswordHistory(payload) {
    return fetch('api/password_history_check.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: new URLSearchParams(payload)
    }).then(function (response) {
        return response.json();
    });
}

function passwordHistoryFieldError(response) {
    if (response && response.error) {
        return response.error;
    }
    return 'Unable to verify password history. Please try again.';
}
