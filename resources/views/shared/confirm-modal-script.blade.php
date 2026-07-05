<script>
    (() => {
        const modal = document.querySelector('[data-confirm-modal]');

        if (! modal) {
            return;
        }

        const dialogNode = modal.querySelector('.confirm-modal-dialog');
        const titleNode = modal.querySelector('[data-confirm-title]');
        const messageNode = modal.querySelector('[data-confirm-message]');
        const acceptNode = modal.querySelector('[data-confirm-accept]');
        const cancelNode = modal.querySelectorAll('[data-confirm-cancel]');
        const iconNode = modal.querySelector('[data-confirm-icon]');
        const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
        let pendingAction = null;
        let lastFocused = null;

        const getFocusableNodes = () => Array.from(modal.querySelectorAll(focusableSelector))
            .filter((node) => ! node.hasAttribute('disabled') && ! node.getAttribute('aria-hidden'));

        const closeModal = () => {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            pendingAction = null;

            if (lastFocused instanceof HTMLElement) {
                lastFocused.focus();
            }
        };

        const openModal = (trigger) => {
            lastFocused = trigger;

            const title = trigger.getAttribute('data-confirm-title') || 'Konfirmasi Aksi';
            const message = trigger.getAttribute('data-confirm-message') || 'Tindakan ini akan dijalankan setelah kamu konfirmasi.';
            const confirmLabel = trigger.getAttribute('data-confirm-confirm-label') || 'Lanjutkan';
            const variant = trigger.getAttribute('data-confirm-variant') || 'default';
            const targetSelector = trigger.getAttribute('data-confirm-target');
            const targetForm = targetSelector ? document.querySelector(targetSelector) : trigger.closest('form');
            const href = trigger.getAttribute('href');

            titleNode.textContent = title;
            messageNode.textContent = message;
            acceptNode.textContent = confirmLabel;
            acceptNode.dataset.variant = variant;
            iconNode.dataset.variant = variant;

            if (targetForm instanceof HTMLFormElement) {
                pendingAction = () => {
                    if (typeof targetForm.requestSubmit === 'function') {
                        if (trigger instanceof HTMLButtonElement || trigger instanceof HTMLInputElement) {
                            targetForm.requestSubmit(trigger);
                            return;
                        }

                        targetForm.requestSubmit();
                        return;
                    }

                    targetForm.submit();
                };
            } else if (href && href !== '#') {
                pendingAction = () => {
                    window.location.assign(href);
                };
            } else {
                pendingAction = null;
            }

            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            window.setTimeout(() => acceptNode.focus(), 0);
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-confirm]');

            if (trigger) {
                event.preventDefault();
                openModal(trigger);
                return;
            }

            if (event.target.closest('[data-confirm-cancel]')) {
                event.preventDefault();
                closeModal();
                return;
            }

            if (event.target.closest('[data-confirm-accept]')) {
                event.preventDefault();
                const action = pendingAction;
                closeModal();

                if (typeof action === 'function') {
                    action();
                }
            }
        });

        document.addEventListener('keydown', (event) => {
            if (modal.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal();
                return;
            }

            if (event.key === 'Tab') {
                const focusableNodes = getFocusableNodes();

                if (! focusableNodes.length) {
                    event.preventDefault();
                    dialogNode.focus();
                    return;
                }

                const firstNode = focusableNodes[0];
                const lastNode = focusableNodes[focusableNodes.length - 1];

                if (event.shiftKey && document.activeElement === firstNode) {
                    event.preventDefault();
                    lastNode.focus();
                    return;
                }

                if (! event.shiftKey && document.activeElement === lastNode) {
                    event.preventDefault();
                    firstNode.focus();
                }
            }
        });

        cancelNode.forEach((node) => {
            node.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    closeModal();
                }
            });
        });
    })();
</script>
