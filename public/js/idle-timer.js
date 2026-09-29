/*
 * Inactivity logout for Narciana TRMS.
 * The server ends the session after SESSION_LIFETIME minutes without a request. This script:
 *  - shows a warning 1 minute before that, with a countdown and a "Stay logged in" button;
 *  - the button sends a small request to the server, which refreshes the session, and restarts the timers;
 *  - if nobody responds, it goes to the login page so no tenant data stays on an idle tablet.
 * Settings come from data-* attributes on the <script> tag in layouts/app.blade.php.
 */
(function () {
    var script = document.currentScript;
    var lifetimeMs = Number(script.dataset.lifetime) * 60 * 1000;
    var warnBeforeMs = 60 * 1000;
    var keepAliveUrl = script.dataset.keepAliveUrl;
    var loginUrl = script.dataset.loginUrl;
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    var box = document.getElementById('idle-warning');
    var secondsEl = document.getElementById('idle-seconds');
    var stayBtn = document.getElementById('idle-stay');

    var warnTimer, logoutTimer, countdown;

    function goToLogin() {
        window.location.href = loginUrl;
    }

    function showWarning() {
        var left = Math.round(warnBeforeMs / 1000);
        secondsEl.textContent = left;
        box.hidden = false;
        stayBtn.focus();
        countdown = setInterval(function () {
            left = Math.max(0, left - 1);
            secondsEl.textContent = left;
        }, 1000);
    }

    function start() {
        clearTimeout(warnTimer);
        clearTimeout(logoutTimer);
        clearInterval(countdown);
        box.hidden = true;
        warnTimer = setTimeout(showWarning, Math.max(0, lifetimeMs - warnBeforeMs));
        logoutTimer = setTimeout(goToLogin, lifetimeMs + 5000); // 5 s after the server has ended the session
    }

    stayBtn.addEventListener('click', function () {
        stayBtn.disabled = true;
        fetch(keepAliveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'manual',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        }).then(function (response) {
            if (response.status === 204) {
                start();
            } else {
                goToLogin(); // session already ended or account deactivated
            }
        }).catch(goToLogin).finally(function () {
            stayBtn.disabled = false;
        });
    });

    start();
})();
