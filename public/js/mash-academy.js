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

    // 3. Registration Policy Checkbox UX Feedback
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

    // 4. Flexible Assignment Form (File / MCQ / Both) Dynamic Field Toggle
    document.querySelectorAll('[data-assignment-type-form]').forEach((form) => {
        const select = form.querySelector('[data-submission-type-select]');
        const mcqFields = form.querySelector('[data-mcq-fields]');
        const instructionsInput = form.querySelector('#assignment_instructions');
        const instructionsRequired = form.querySelector('[data-instructions-required]');

        const sync = () => {
            if (!select) return;
            const val = select.value;
            const needsMcq = val === 'mcq' || val === 'both';
            const needsFile = val === 'file' || val === 'both';

            mcqFields?.classList.toggle('d-none', !needsMcq);
            if (instructionsInput) {
                instructionsInput.required = needsFile;
            }
            if (instructionsRequired) {
                instructionsRequired.classList.toggle('d-none', !needsFile);
            }
        };

        select?.addEventListener('change', sync);
        sync();
    });

    // 5. Real-time Lesson Video Upload handler lives in resources/views/admin/courses/lessons/index.blade.php

    // Pause HTML5 preview video when closing modal
    document.querySelectorAll('.modal').forEach((modal) => {
        modal.addEventListener('hidden.bs.modal', () => {
            modal.querySelectorAll('video').forEach((v) => v.pause());
        });
    });

    // 5A. HTML5 Video Track / Codec Decode Detector (catches HEVC/H.265 audio-only & decode errors)
    document.querySelectorAll('video[data-codec-check]').forEach((video) => {
        const selector = video.getAttribute('data-codec-warning-target');
        const warningEl = selector ? document.querySelector(selector) : null;
        if (!warningEl) return;

        const showWarning = () => {
            warningEl.classList.remove('d-none');
        };

        const hideWarning = () => {
            warningEl.classList.add('d-none');
        };

        const checkVideoTrack = () => {
            if (video.videoWidth > 0 && video.videoHeight > 0) {
                hideWarning();
                return;
            }
            // Metadata/data loaded (e.g., audio track decoded), but no decodable video frames (common with HEVC/H.265)
            if (video.readyState >= 1 && (video.videoWidth === 0 || video.videoHeight === 0)) {
                showWarning();
            }
        };

        video.addEventListener('loadedmetadata', checkVideoTrack);
        video.addEventListener('loadeddata', checkVideoTrack);
        video.addEventListener('canplay', checkVideoTrack);
        video.addEventListener('playing', checkVideoTrack);
        video.addEventListener('error', () => {
            const code = video.error?.code;
            // MEDIA_ERR_DECODE (3) or MEDIA_ERR_SRC_NOT_SUPPORTED (4) or generic playback error
            if (!code || code === 3 || code === 4) {
                showWarning();
            }
        });

        video.querySelectorAll('source').forEach((source) => {
            source.addEventListener('error', showWarning);
        });

        const parentModal = video.closest('.modal');
        if (parentModal) {
            parentModal.addEventListener('shown.bs.modal', checkVideoTrack);
        }
    });

    // 5B. Enrollment Quick Duration Preset Buttons (Admin Enrollments)
    document.querySelectorAll('[data-duration-preset]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const valInput = document.getElementById('duration_value');
            const unitSelect = document.getElementById('duration_unit');
            if (valInput) valInput.value = btn.getAttribute('data-duration-preset');
            if (unitSelect) unitSelect.value = btn.getAttribute('data-duration-unit');
        });
    });

    // 5C. Student Checkout Promo Code Live Validator
    const applyPromoBtn = document.getElementById('apply-promo-btn');
    const promoInput = document.getElementById('promo_code');
    const promoForm = promoInput?.closest('form[data-promo-check-url]');
    const promoFeedback = document.getElementById('promo-feedback');
    const discountRow = document.getElementById('summary-discount-row');
    const discountBadge = document.getElementById('summary-promo-badge');
    const discountAmountEl = document.getElementById('summary-discount-amount');
    const finalPriceEl = document.getElementById('summary-final-price');
    const originalPriceEl = document.getElementById('summary-original-price');

    if (applyPromoBtn && promoInput && promoForm) {
        applyPromoBtn.addEventListener('click', async () => {
            const code = promoInput.value.trim().toUpperCase();
            promoInput.value = code;
            const checkUrl = promoForm.getAttribute('data-promo-check-url');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            applyPromoBtn.disabled = true;
            try {
                const res = await fetch(checkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ code }),
                });

                const data = await res.json();
                const currency = data.currency || 'EGP';
                const formatMoney = (n) => Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                if (promoFeedback) {
                    promoFeedback.classList.remove('d-none', 'text-success', 'text-danger');
                    promoFeedback.classList.add(data.valid ? 'text-success' : 'text-danger', 'fw-semibold');
                    promoFeedback.textContent = data.message || (data.valid ? 'Promo code applied.' : 'Invalid promo code.');
                }

                if (data.valid) {
                    discountRow?.classList.remove('d-none');
                    if (discountBadge) discountBadge.textContent = `${data.code} (${data.discount_label})`;
                    if (discountAmountEl) discountAmountEl.textContent = `-${formatMoney(data.discount_amount)} ${currency}`;
                    if (finalPriceEl) finalPriceEl.textContent = `${formatMoney(data.final_price)} ${currency}`;
                } else {
                    discountRow?.classList.add('d-none');
                    if (originalPriceEl && finalPriceEl) {
                        finalPriceEl.textContent = originalPriceEl.textContent;
                    }
                }
            } catch {
                if (promoFeedback) {
                    promoFeedback.classList.remove('d-none', 'text-success');
                    promoFeedback.classList.add('text-danger', 'fw-semibold');
                    promoFeedback.textContent = 'Could not verify promo code right now. Please try again.';
                }
            } finally {
                applyPromoBtn.disabled = false;
            }
        });
    }

    // 5D. Locked Lesson Modal Content Populator
    const lockedModal = document.getElementById('lockedLessonModal');
    if (lockedModal) {
        lockedModal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;
            const titleEl = document.getElementById('locked-modal-title');
            const reasonEl = document.getElementById('locked-modal-reason');
            if (titleEl) titleEl.textContent = trigger.getAttribute('data-lock-title') || '';
            if (reasonEl) reasonEl.textContent = trigger.getAttribute('data-lock-reason') || 'Complete the previous lesson first.';
        });
    }

    // 5E. Phase 4 HTML5 Video Player: Strict 100% Full-Watch, Anti-Skip, Resume & Mid-Session Expiry Handler
    const lessonVideo = document.getElementById('ma-lesson-video');
    if (lessonVideo) {
        const progressUrl = lessonVideo.dataset.progressUrl;
        const subscribeUrl = lessonVideo.dataset.subscribeUrl;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const resumeSeconds = parseFloat(lessonVideo.dataset.resumeSeconds || '0');
        let maxReached = parseFloat(lessonVideo.dataset.maxReached || '0');
        let isCompleted = lessonVideo.dataset.isCompleted === '1';
        let lastTime = resumeSeconds;
        let accumulatedDelta = 0;
        let lastPingTs = Date.now();

        const alertEl = document.getElementById('player-alert');
        const watchPctEl = document.getElementById('lesson-watch-pct');
        const statusBadgeEl = document.getElementById('lesson-status-badge');
        const coursePctEl = document.getElementById('course-progress-pct');
        const courseBarEl = document.getElementById('course-progress-bar');
        const nextCtaEl = document.getElementById('next-lesson-cta');

        const showAlert = (text, variant = 'alert-secondary') => {
            if (!alertEl || !text) return;
            alertEl.className = `alert small py-2 mb-3 ${variant}`;
            alertEl.textContent = text;
        };

        // Resume from last saved position once metadata loads
        lessonVideo.addEventListener('loadedmetadata', () => {
            const dur = lessonVideo.duration || 0;
            if (resumeSeconds > 0 && dur > 0 && resumeSeconds < dur - 2) {
                lessonVideo.currentTime = resumeSeconds;
                lastTime = resumeSeconds;
            }
        });

        // Client-side anti-skip guard (also strictly enforced on server)
        lessonVideo.addEventListener('seeking', () => {
            if (!isCompleted && lessonVideo.currentTime > maxReached + 2.5) {
                lessonVideo.currentTime = maxReached;
                showAlert('Fast-forwarding ahead is disabled. Watch 100% of this video from start to finish to unlock the next lesson.', 'alert-warning');
            }
        });

        lessonVideo.addEventListener('timeupdate', () => {
            const nowTime = lessonVideo.currentTime;
            const diff = nowTime - lastTime;

            if (!isCompleted && diff > 3.5) {
                lessonVideo.currentTime = maxReached;
                return;
            }

            if (diff > 0 && diff <= 3.5) {
                accumulatedDelta += diff;
                if (nowTime > maxReached) {
                    maxReached = nowTime;
                }
            }
            lastTime = nowTime;

            if (Date.now() - lastPingTs >= 5000 && accumulatedDelta >= 1) {
                sendHeartbeat(false);
            }
        });

        lessonVideo.addEventListener('pause', () => {
            if (accumulatedDelta >= 1 || lessonVideo.currentTime > 0) {
                sendHeartbeat(false);
            }
        });

        lessonVideo.addEventListener('ended', () => {
            sendHeartbeat(true);
        });

        // Handle stream error (e.g., mid-session expiry returning 403 on Range fetch)
        lessonVideo.addEventListener('error', () => {
            sendHeartbeat(false);
        });

        async function sendHeartbeat(ended) {
            if (!progressUrl) return;
            const deltaToSend = Math.max(0, Math.round(accumulatedDelta));
            const duration = lessonVideo.duration || parseFloat(lessonVideo.dataset.lessonDuration || '1') || 1;
            accumulatedDelta = 0;
            lastPingTs = Date.now();

            try {
                const res = await fetch(progressUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        current_time: lessonVideo.currentTime || 0,
                        duration,
                        delta_seconds: deltaToSend,
                        ended: Boolean(ended),
                    }),
                });

                if (res.status === 403) {
                    lessonVideo.pause();
                    let redirect = subscribeUrl;
                    try {
                        const errData = await res.json();
                        if (errData.redirect) redirect = errData.redirect;
                    } catch {
                        // fallback to subscribeUrl
                    }
                    showAlert('Your enrollment has expired. Redirecting to the Subscribe/Renew page...', 'alert-danger');
                    if (redirect) {
                        setTimeout(() => { window.location.href = redirect; }, 1200);
                    }
                    return;
                }

                const data = await res.json();

                if (data.skipped_blocked) {
                    maxReached = Number(data.allowed_position || 0);
                    if (!isCompleted && lessonVideo.currentTime > maxReached + 2) {
                        lessonVideo.currentTime = maxReached;
                    }
                    showAlert(data.message || 'Please watch the full video without skipping.', 'alert-warning');
                }

                if (watchPctEl && data.watched_percentage !== undefined) {
                    watchPctEl.textContent = `${data.watched_percentage}%`;
                }
                if (coursePctEl && data.course_completion_percentage !== undefined) {
                    coursePctEl.textContent = `${data.course_completion_percentage}%`;
                }
                if (courseBarEl && data.course_completion_percentage !== undefined) {
                    courseBarEl.style.width = `${Math.min(100, data.course_completion_percentage)}%`;
                }

                if (data.completed) {
                    isCompleted = true;
                    if (statusBadgeEl) {
                        statusBadgeEl.className = 'badge bg-success';
                        statusBadgeEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Completed (100%)';
                    }
                    if (data.just_completed) {
                        showAlert(data.message || 'Lesson completed (100%)! Next lesson is now unlocked.', 'alert-success');
                        if (nextCtaEl && data.next_lesson_url) {
                            nextCtaEl.innerHTML = `<a href="${data.next_lesson_url}" class="btn btn-ma-primary btn-sm">Next Lesson <i class="bi bi-arrow-right ms-1"></i></a>`;
                        }
                        // Refresh sidebar lock icons smoothly after a brief beat if next lesson unlocked
                        setTimeout(() => { window.location.reload(); }, 1800);
                    }
                }
            } catch {
                // Keep playback resilient on transient network hiccups
            }
        }
    }

    // 6. Accessible :user-invalid ARIA Sync + Polite Live Region Announcer
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
