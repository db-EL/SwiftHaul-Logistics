// ============================================================
// SwiftHaul Toast Notifications
// Styled in-app notifications instead of native browser alert()/
// notification popups. Usage:
//   showToast('Payment confirmed!', 'success');
//   showToast('Something went wrong', 'error');
//   showToast('New order available nearby', 'info');
// ============================================================

(function () {
    function ensureContainer() {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        return container;
    }

    const ICONS = {
        success: 'fa-circle-check',
        error: 'fa-triangle-exclamation',
        info: 'fa-circle-info',
        order: 'fa-box',
    };

    window.showToast = function (message, type = 'info', options = {}) {
        const container = ensureContainer();
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;

        const icon = ICONS[type] || ICONS.info;
        const actionHtml = options.actionLabel
            ? `<button class="toast-action btn btn-primary btn-sm">${options.actionLabel}</button>`
            : '';

        toast.innerHTML = `
            <i class="fa-solid ${icon} toast-icon"></i>
            <div class="toast-body">
                <div>${message}</div>
                ${actionHtml}
            </div>
            <button class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
        `;

        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('toast-show'));

        const dismiss = () => {
            toast.classList.remove('toast-show');
            toast.classList.add('toast-hide');
            setTimeout(() => toast.remove(), 350);
        };

        toast.querySelector('.toast-close').addEventListener('click', dismiss);

        if (options.actionLabel && options.onAction) {
            toast.querySelector('.toast-action').addEventListener('click', () => {
                options.onAction();
                dismiss();
            });
        }

        // Toasts with an action wait for the person to respond instead of
        // auto-dismissing, unless a duration was explicitly given.
        const duration = options.duration ?? (options.actionLabel ? 0 : (type === 'error' ? 7000 : 5000));
        if (duration > 0) setTimeout(dismiss, duration);

        return toast;
    };

    // Convenience wrapper matching the shape of most of our AJAX responses: { success, message }
    window.showToastFromResult = function (result, successType = 'success') {
        if (!result) return;
        showToast(result.message || (result.success ? 'Done.' : 'Something went wrong.'), result.success ? successType : 'error');
    };
})();
