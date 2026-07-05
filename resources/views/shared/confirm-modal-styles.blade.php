<style>
.confirm-modal-shell[hidden] {
    display: none;
}

.confirm-modal-shell {
    position: fixed;
    inset: 0;
    z-index: 120;
}

.confirm-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
}

.confirm-modal-dialog {
    position: relative;
    z-index: 1;
    width: min(100% - 32px, 440px);
    margin: min(12vh, 96px) auto 0;
    padding: 24px;
    border: 1px solid var(--border);
    border-radius: 20px;
    background: #ffffff;
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.18);
}

.confirm-modal-icon {
    width: 52px;
    height: 52px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    background: color-mix(in srgb, var(--primary) 12%, #ffffff);
    color: var(--primary);
    font-size: 1.125rem;
}

.confirm-modal-icon[data-variant="danger"] {
    background: #fee2e2;
    color: #dc2626;
}

.confirm-modal-copy {
    margin-top: 18px;
}

.confirm-modal-title {
    margin: 0;
    font-size: 1.125rem;
    line-height: 1.35;
    color: var(--text);
}

.confirm-modal-message {
    margin: 10px 0 0;
    color: var(--muted);
    font-size: 0.9375rem;
    line-height: 1.7;
}

.confirm-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 22px;
    flex-wrap: wrap;
}

.confirm-modal-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    padding: 0 16px;
    border-radius: 12px;
    border: 1px solid transparent;
    background: #ffffff;
    cursor: pointer;
    font-weight: 700;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, filter 0.18s ease;
}

.confirm-modal-btn:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.confirm-modal-btn-primary {
    background: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
}

.confirm-modal-btn-primary[data-variant="danger"] {
    background: #dc2626;
    border-color: #dc2626;
}

.confirm-modal-btn-secondary {
    border-color: var(--border);
    color: var(--text);
}

@media (max-width: 767px) {
    .confirm-modal-dialog {
        width: min(100% - 24px, 440px);
        margin-top: 72px;
        padding: 20px;
        border-radius: 18px;
    }

    .confirm-modal-actions {
        flex-direction: column-reverse;
    }

    .confirm-modal-btn {
        width: 100%;
    }
}
</style>
