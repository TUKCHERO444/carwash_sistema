const formatTime = (seconds) => {
    const total = Math.max(0, Math.floor(seconds));
    const minutes = Math.floor(total / 60);
    const remainingSeconds = total % 60;

    return `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
};

const setDisabled = (button, disabled) => {
    if (!button) return;

    button.classList.toggle('pointer-events-none', disabled);
    button.classList.toggle('opacity-50', disabled);
    button.classList.toggle('cursor-not-allowed', disabled);
    button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
};

const startCountdown = (seconds, display, button) => {
    let remaining = Math.max(0, Math.ceil(seconds));

    const render = () => {
        display.textContent = formatTime(remaining);
        setDisabled(button, remaining > 0);

        return remaining > 0;
    };

    if (!render()) return;

    const interval = window.setInterval(() => {
        remaining -= 1;

        if (!render()) {
            window.clearInterval(interval);
        }
    }, 1000);
};

const init = () => {
    document.querySelectorAll('[data-retry-countdown]').forEach((display) => {
        const seconds = Number(display.dataset.retrySeconds ?? 60);

        startCountdown(seconds, display, document.querySelector('[data-retry-button]'));
    });

    document.querySelectorAll('[data-lockout-countdown]').forEach((display) => {
        const until = Date.parse(display.dataset.lockoutUntil ?? '');

        if (Number.isNaN(until)) return;

        const button = display.closest('[data-lockout-panel]')?.querySelector('[data-lockout-button]');

        startCountdown(Math.max(0, (until - Date.now()) / 1000), display, button);
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
