(() => {
    'use strict';

    // 1. Show/Hide Password Toggles
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-toggle-password');
            const input = document.getElementById(targetId);
            if (!input) return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');

            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
        });
    });

    // 2. Live Logo Preview on Academy Settings page
    const logoInput = document.getElementById('logo');
    const logoPreview = document.getElementById('logo-preview-img');
    const logoPreviewPlaceholder = document.getElementById('logo-preview-placeholder');
    if (logoInput && logoPreview) {
        logoInput.addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                logoPreview.src = e.target.result;
                logoPreview.classList.remove('d-none');
                if (logoPreviewPlaceholder) {
                    logoPreviewPlaceholder.classList.add('d-none');
                }
            };
            reader.readAsDataURL(file);
        });
    }

    // 3. Registration Policy Checkbox UX Feedback (server-side validation always remains authoritative)
    const registerForm = document.getElementById('student-register-form');
    const policyCheckbox = document.getElementById('agree_policy');
    const policyConsentBox = document.getElementById('policy-consent-box');
    const policyClientError = document.getElementById('policy-client-error');

    if (registerForm && policyCheckbox) {
        policyCheckbox.addEventListener('change', () => {
            if (policyCheckbox.checked) {
                policyConsentBox?.classList.remove('has-error');
                policyClientError?.classList.add('d-none');
            }
        });

        registerForm.addEventListener('submit', (event) => {
            if (!policyCheckbox.checked) {
                event.preventDefault();
                policyConsentBox?.classList.add('has-error');
                policyClientError?.classList.remove('d-none');
                policyCheckbox.focus();
            }
        });
    }

    // 4. Accessible :user-invalid ARIA Sync + Polite Live Region Announcer
    const forms = document.querySelectorAll('.ma-form');
    if (forms.length > 0) {
        const announcer = document.createElement('div');
        announcer.setAttribute('aria-live', 'polite');
        announcer.classList.add('visually-hidden');
        document.body.append(announcer);

        const supportsUserInvalid = CSS.supports('selector(:user-invalid)');
        const dirtyState = new WeakMap();
        const flagged = new WeakSet();
        const announced = new WeakSet();
        let announceTimer;

        const linkError = (el, shown) => {
            const errorId = el.dataset.error;
            if (!errorId) return;
            const ids = (el.getAttribute('aria-describedby') || '')
                .split(/\s+/)
                .filter((id) => id && id !== errorId);
            if (shown) ids.push(errorId);
            if (ids.length) el.setAttribute('aria-describedby', ids.join(' '));
            else el.removeAttribute('aria-describedby');
        };

        const announceError = (el) => {
            const errorEl = el.dataset.error ? document.getElementById(el.dataset.error) : null;
            if (!errorEl) return;
            const label = el.labels?.[0]?.textContent?.trim() || '';
            clearTimeout(announceTimer);
            announcer.textContent = '';
            announceTimer = setTimeout(() => {
                announcer.textContent = (label ? `${label}: ` : '') + errorEl.textContent.trim();
            }, 400);
        };

        const syncField = (el, shouldAnnounce = false) => {
            if (!el.matches?.('input, select, textarea') || el.type === 'hidden') return;

            const isInvalid = supportsUserInvalid
                ? el.matches(':user-invalid')
                : el.classList.contains('user-invalid-fallback');

            if (isInvalid) {
                if (shouldAnnounce && !announced.has(el)) {
                    announceError(el);
                    announced.add(el);
                }
                el.setAttribute('aria-invalid', 'true');
                linkError(el, true);
                flagged.add(el);
            } else if (flagged.has(el) || el.getAttribute('aria-invalid') !== 'true') {
                el.setAttribute('aria-invalid', 'false');
                linkError(el, false);
                flagged.delete(el);
                announced.delete(el);
            }
        };

        forms.forEach((form) => {
            form.querySelectorAll('input, select, textarea').forEach((el) => {
                if (!el.hasAttribute('aria-invalid')) {
                    el.setAttribute('aria-invalid', 'false');
                }
            });

            form.addEventListener('blur', (e) => {
                const target = e.target;
                if (!supportsUserInvalid && target.checkValidity) {
                    const state = dirtyState.get(target) || { interacted: false, blurred: false };
                    state.blurred = true;
                    dirtyState.set(target, state);
                    if (state.interacted) {
                        target.classList.toggle('user-invalid-fallback', !target.validity.valid);
                        target.classList.toggle('user-valid-fallback', target.validity.valid);
                    }
                }
                syncField(target, true);
            }, true);

            form.addEventListener('input', (e) => {
                const target = e.target;
                if (!supportsUserInvalid && target.checkValidity) {
                    const state = dirtyState.get(target) || { interacted: false, blurred: false };
                    state.interacted = true;
                    dirtyState.set(target, state);
                    if (state.blurred) {
                        target.classList.toggle('user-invalid-fallback', !target.validity.valid);
                        target.classList.toggle('user-valid-fallback', target.validity.valid);
                    }
                }
                syncField(target, false);
            });

            form.addEventListener('invalid', (e) => {
                const target = e.target;
                if (!supportsUserInvalid) {
                    dirtyState.set(target, { interacted: true, blurred: true });
                    target.classList.add('user-invalid-fallback');
                }
                announced.add(target);
                syncField(target, false);
            }, true);
        });
    }
})();
