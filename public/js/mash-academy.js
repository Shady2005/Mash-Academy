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

    // Pause HTML5 preview videos & stop embedded iframes when closing modal
    document.querySelectorAll('.modal').forEach((modal) => {
        modal.addEventListener('hidden.bs.modal', () => {
            modal.querySelectorAll('video').forEach((v) => v.pause());
            modal.querySelectorAll('iframe').forEach((iframe) => {
                const currentSrc = iframe.getAttribute('src');
                if (currentSrc) {
                    iframe.setAttribute('src', currentSrc);
                }
            });
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
                const instructorSelect = promoForm.querySelector('#instructor_id');
                const payload = { code };
                if (instructorSelect && instructorSelect.value) {
                    payload.instructor_id = Number(instructorSelect.value);
                }

                const res = await fetch(checkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
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

    // 5F. Bunny.net Stream Player.js / postMessage Progress Bridge (Preserves 100% Drip-Lock)
    const bunnyIframe = document.querySelector('iframe[data-bunny-player]');
    if (bunnyIframe) {
        const progressUrl = bunnyIframe.dataset.progressUrl;
        const subscribeUrl = bunnyIframe.dataset.subscribeUrl;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        let duration = parseFloat(bunnyIframe.dataset.lessonDuration || '0') || 0;
        let currentTime = parseFloat(bunnyIframe.dataset.resumeSeconds || '0') || 0;
        let lastTime = currentTime;
        let accumulatedDelta = 0;
        let lastPingTs = Date.now();
        let isCompleted = bunnyIframe.dataset.isCompleted === '1';

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

        const sendBunnyHeartbeat = async (ended) => {
            if (!progressUrl) return;
            const deltaToSend = Math.max(0, Math.min(60, Math.round(accumulatedDelta)));
            const effectiveDuration = Math.max(1, duration || currentTime || 1);
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
                        current_time: ended ? effectiveDuration : currentTime,
                        duration: effectiveDuration,
                        delta_seconds: deltaToSend,
                        ended: Boolean(ended),
                    }),
                });

                if (res.status === 403) {
                    let redirect = subscribeUrl;
                    try {
                        const errData = await res.json();
                        if (errData.redirect) redirect = errData.redirect;
                    } catch {
                        // fallback
                    }
                    showAlert('Your enrollment has expired. Redirecting to Subscribe/Renew...', 'alert-danger');
                    if (redirect) {
                        setTimeout(() => { window.location.href = redirect; }, 1200);
                    }
                    return;
                }

                const data = await res.json();
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
                        setTimeout(() => { window.location.reload(); }, 1800);
                    }
                }
            } catch {
                // Resilient to network hiccups
            }
        };

        // Subscribe to Bunny.net Stream Player.js postMessage events
        const subscribeBunnyEvents = () => {
            try {
                const target = bunnyIframe.contentWindow;
                if (!target) return;
                ['timeupdate', 'pause', 'ended'].forEach((ev) => {
                    target.postMessage(JSON.stringify({ context: 'player.js', version: '0.0.11', method: 'addEventListener', value: ev }), '*');
                });
            } catch {
                // ignore cross-origin restrictions
            }
        };

        bunnyIframe.addEventListener('load', subscribeBunnyEvents);

        window.addEventListener('message', (event) => {
            if (!event.origin || !event.origin.includes('mediadelivery.net')) return;
            let payload = event.data;
            if (typeof payload === 'string') {
                try { payload = JSON.parse(payload); } catch { return; }
            }
            if (!payload || typeof payload !== 'object') return;

            if (payload.event === 'ready') {
                subscribeBunnyEvents();
                return;
            }

            if (payload.event === 'timeupdate' && payload.value) {
                const sec = Number(payload.value.seconds || 0);
                const dur = Number(payload.value.duration || duration || 0);
                if (dur > 0) duration = dur;
                const diff = sec - lastTime;
                if (diff > 0 && diff <= 3.5) {
                    accumulatedDelta += diff;
                }
                currentTime = sec;
                lastTime = sec;

                if (Date.now() - lastPingTs >= 5000 && accumulatedDelta >= 1) {
                    sendBunnyHeartbeat(false);
                }
            } else if (payload.event === 'pause') {
                if (accumulatedDelta >= 1) {
                    sendBunnyHeartbeat(false);
                }
            } else if (payload.event === 'ended') {
                if (!isCompleted) {
                    sendBunnyHeartbeat(true);
                }
            }
        });
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

    // 7. AI Provider Model Auto-Default (Admin Settings)
    const aiProviderSelect = document.getElementById('ai_provider');
    const aiModelInput = document.getElementById('ai_model');
    if (aiProviderSelect && aiModelInput) {
        aiProviderSelect.addEventListener('change', () => {
            const current = aiModelInput.value.trim();
            if (current === '' || current === 'gemini-2.5-flash' || current === 'gpt-4o-mini') {
                aiModelInput.value = aiProviderSelect.value === 'openai' ? 'gpt-4o-mini' : 'gemini-2.5-flash';
            }
        });
    }

    // 8. AI Bilingual Course Description Generator (Create / Edit Course)
    document.querySelectorAll('[data-ai-course-description-btn]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const titleInput = document.getElementById('title');
            const titleArInput = document.getElementById('title_ar');
            const feedback = document.getElementById('ai-course-desc-feedback');
            const url = btn.getAttribute('data-ai-url');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const title = titleInput?.value?.trim() || '';
            if (title.length < 3) {
                if (feedback) {
                    feedback.className = 'alert alert-warning small py-2 px-3 mb-0 w-100';
                    feedback.textContent = 'Please enter a Course Title (English) of at least 3 characters first.';
                }
                titleInput?.focus();
                return;
            }

            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
            feedback?.classList.add('d-none');

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        title,
                        title_ar: titleArInput?.value?.trim() || null,
                    }),
                });
                const payload = await res.json();

                if (!res.ok || !payload.ok || !payload.data) {
                    if (feedback) {
                        feedback.className = 'alert alert-danger small py-2 px-3 mb-0 w-100';
                        feedback.textContent = payload.message || 'Could not generate course descriptions. Please check AI settings.';
                    }
                    return;
                }

                const setVal = (id, val) => {
                    const el = document.getElementById(id);
                    if (el && typeof val === 'string' && val.trim() !== '') {
                        el.value = val;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                };
                setVal('short_description', payload.data.short_description);
                setVal('short_description_ar', payload.data.short_description_ar);
                setVal('description', payload.data.description);
                setVal('description_ar', payload.data.description_ar);

                if (feedback) {
                    feedback.className = 'alert alert-success small py-2 px-3 mb-0 w-100';
                    feedback.textContent = `Bilingual English & Arabic descriptions generated via ${payload.provider} (${payload.model}). Review and customize before saving.`;
                }
            } catch {
                if (feedback) {
                    feedback.className = 'alert alert-danger small py-2 px-3 mb-0 w-100';
                    feedback.textContent = 'Network error while generating course descriptions. Please try again.';
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        });
    });

    // 9. AI Quiz Questions Generator Modal (Admin Course Quizzes)
    document.querySelectorAll('[data-ai-quiz-modal]').forEach((modal) => {
        const generateBtn = modal.querySelector('[data-ai-quiz-generate-btn]');
        const topicInput = modal.querySelector('[data-ai-quiz-topic]');
        const countSelect = modal.querySelector('[data-ai-quiz-count]');
        const langSelect = modal.querySelector('[data-ai-quiz-lang]');
        const feedback = modal.querySelector('[data-ai-quiz-feedback]');
        const saveForm = modal.querySelector('[data-ai-quiz-save-form]');
        const previewList = modal.querySelector('[data-ai-quiz-preview-list]');
        const generateUrl = modal.getAttribute('data-generate-url');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const esc = (s) => String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        generateBtn?.addEventListener('click', async () => {
            const topic = topicInput?.value?.trim() || '';
            if (topic.length < 5) {
                if (feedback) {
                    feedback.className = 'alert alert-warning small py-2';
                    feedback.textContent = 'Please enter a topic or lesson notes (at least 5 characters).';
                }
                topicInput?.focus();
                return;
            }

            const origHtml = generateBtn.innerHTML;
            generateBtn.disabled = true;
            generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
            feedback?.classList.add('d-none');

            try {
                const res = await fetch(generateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        topic,
                        count: Number(countSelect?.value || 5),
                        language: langSelect?.value || 'en',
                    }),
                });
                const payload = await res.json();

                if (!res.ok || !payload.ok || !Array.isArray(payload.questions)) {
                    if (feedback) {
                        feedback.className = 'alert alert-danger small py-2';
                        feedback.textContent = payload.message || 'Could not generate quiz questions. Check your AI API key in Settings.';
                    }
                    return;
                }

                previewList.innerHTML = payload.questions.map((q, idx) => {
                    const optsHtml = (q.options || []).map((opt, optIdx) => `
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <div class="input-group-text">
                                    <input class="form-check-input mt-0" type="radio" name="questions[${idx}][correct_option]" value="${optIdx}" ${Number(q.correct_option) === optIdx ? 'checked' : ''}>
                                </div>
                                <input type="text" name="questions[${idx}][options][${optIdx}]" class="form-control" value="${esc(opt)}" required>
                            </div>
                        </div>
                    `).join('');

                    return `
                        <div class="border rounded-3 p-3" style="background-color: var(--ma-surface-subtle);">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <div class="form-check mb-0">
                                    <input type="hidden" name="questions[${idx}][selected]" value="0">
                                    <input class="form-check-input" type="checkbox" name="questions[${idx}][selected]" value="1" id="aiq_${modal.id}_${idx}" checked>
                                    <label class="form-check-label small fw-bold" for="aiq_${modal.id}_${idx}">Include Question #${idx + 1}</label>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <span class="small text-muted">Pts:</span>
                                    <input type="number" name="questions[${idx}][points]" min="1" max="100" value="${Number(q.points || 1)}" class="form-control form-control-sm" style="width: 68px;" required>
                                </div>
                            </div>
                            <input type="text" name="questions[${idx}][question_text]" class="form-control form-control-sm mb-2 fw-semibold" value="${esc(q.question_text)}" required>
                            <div class="row g-2">${optsHtml}</div>
                        </div>
                    `;
                }).join('');

                saveForm?.classList.remove('d-none');
                if (feedback) {
                    feedback.className = 'alert alert-success small py-2';
                    feedback.textContent = `Generated ${payload.questions.length} draft questions via ${payload.provider} (${payload.model}). Review and click Save below.`;
                }
            } catch {
                if (feedback) {
                    feedback.className = 'alert alert-danger small py-2';
                    feedback.textContent = 'Network error while generating quiz questions. Please try again.';
                }
            } finally {
                generateBtn.disabled = false;
                generateBtn.innerHTML = origHtml;
            }
        });
    });

    // 10. Student AI Course Tutor Drawer (Only active in DOM when enable_ai_student_tutor is ON)
    const studentTutorForm = document.getElementById('student-ai-tutor-form');
    if (studentTutorForm) {
        const input = document.getElementById('student-ai-tutor-input');
        const sendBtn = document.getElementById('student-ai-tutor-send');
        const msgBox = document.getElementById('student-ai-tutor-messages');
        const emptyEl = document.getElementById('student-ai-tutor-empty');
        const errBox = document.getElementById('student-ai-tutor-error');
        const quotaBadge = document.getElementById('student-ai-tutor-quota');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const history = [];

        const addBubble = (role, text) => {
            emptyEl?.classList.add('d-none');
            const wrap = document.createElement('div');
            wrap.className = role === 'user' ? 'd-flex justify-content-end' : 'd-flex justify-content-start';
            const bubble = document.createElement('div');
            bubble.className = role === 'user'
                ? 'p-2 px-3 rounded-3 small bg-navy-accent text-white'
                : 'p-2 px-3 rounded-3 small border bg-body-tertiary';
            bubble.style.maxWidth = '88%';
            bubble.style.whiteSpace = 'pre-wrap';
            bubble.textContent = text;
            wrap.appendChild(bubble);
            msgBox.appendChild(wrap);
            msgBox.scrollTop = msgBox.scrollHeight;
        };

        studentTutorForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const question = input.value.trim();
            if (!question) return;

            errBox?.classList.add('d-none');
            addBubble('user', question);
            input.value = '';
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const res = await fetch(studentTutorForm.dataset.askUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        lesson_id: studentTutorForm.dataset.lessonId ? Number(studentTutorForm.dataset.lessonId) : null,
                        question,
                        history,
                    }),
                });
                const payload = await res.json();

                if (quotaBadge && payload.remaining_today !== undefined && payload.daily_limit !== undefined) {
                    quotaBadge.textContent = `${payload.remaining_today} / ${payload.daily_limit} left today`;
                }

                if (!res.ok || !payload.ok) {
                    if (errBox) {
                        errBox.textContent = payload.message || 'AI Tutor could not answer right now.';
                        errBox.classList.remove('d-none');
                    }
                } else {
                    addBubble('assistant', payload.answer);
                    history.push({ role: 'user', content: question });
                    history.push({ role: 'assistant', content: payload.answer });
                }
            } catch {
                if (errBox) {
                    errBox.textContent = 'Network error contacting the AI Tutor. Please try again.';
                    errBox.classList.remove('d-none');
                }
            } finally {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="bi bi-send"></i>';
                input.focus();
            }
        });
    }

    // 11. Dismissible Global Announcement Top Bar
    const globalAnnBar = document.getElementById('ma-global-announcement-bar');
    if (globalAnnBar) {
        const annId = globalAnnBar.getAttribute('data-announcement-id');
        const dismissKey = `ma_dismissed_announcement_${annId}`;
        try {
            if (localStorage.getItem(dismissKey) === '1') {
                globalAnnBar.classList.add('d-none');
            }
        } catch {
            // ignore storage errors
        }

        const dismissBtn = globalAnnBar.querySelector('[data-dismiss-announcement]');
        dismissBtn?.addEventListener('click', () => {
            globalAnnBar.classList.add('d-none');
            try { localStorage.setItem(dismissKey, '1'); } catch { /* noop */ }
        });
    }

    // 12. Floating Live Customer Support Chat Widget (AJAX polling every 4.5s while open)
    const supportWidget = document.getElementById('ma-support-chat-widget');
    if (supportWidget) {
        const toggleBtn = document.getElementById('ma-support-chat-toggle');
        const closeBtn = document.getElementById('ma-support-chat-close');
        const panel = document.getElementById('ma-support-chat-panel');
        const unreadBadge = document.getElementById('ma-support-chat-unread');
        const guestForm = document.getElementById('ma-support-guest-form');
        const guestError = document.getElementById('ma-support-guest-error');
        const guestSubmit = document.getElementById('ma-support-guest-submit');
        const chatBody = document.getElementById('ma-support-chat-body');
        const msgList = document.getElementById('ma-support-chat-messages');
        const emptyEl = document.getElementById('ma-support-chat-empty');
        const chatError = document.getElementById('ma-support-chat-error');
        const msgForm = document.getElementById('ma-support-message-form');
        const msgInput = document.getElementById('ma-support-message-input');
        const msgSend = document.getElementById('ma-support-message-send');

        const stateUrl = supportWidget.dataset.stateUrl;
        const startUrl = supportWidget.dataset.startUrl;
        const sendUrl = supportWidget.dataset.sendUrl;
        const isAuth = supportWidget.dataset.isAuthenticated === '1';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        let isOpen = false;
        let hasConversation = false;
        let pollTimer = null;

        const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

        const renderSupportMessages = (messages) => {
            if (!msgList) return;
            if (!messages || messages.length === 0) {
                msgList.innerHTML = '';
                if (emptyEl) {
                    emptyEl.classList.remove('d-none');
                    msgList.appendChild(emptyEl);
                }
                return;
            }
            const nearBottom = (msgList.scrollHeight - msgList.scrollTop - msgList.clientHeight) < 80;
            msgList.innerHTML = messages.map((m) => `
                <div class="d-flex ${m.is_staff ? 'justify-content-start' : 'justify-content-end'}">
                    <div class="p-2 px-3 rounded-3 small ${m.is_staff ? 'border bg-body' : 'bg-navy-accent text-white'}" style="max-width: 85%;">
                        <div class="fw-semibold mb-1" style="font-size: 0.7rem; opacity: 0.85;">
                            ${esc(m.sender_name)} · ${esc(m.created_at)}
                        </div>
                        <div style="white-space: pre-wrap;">${esc(m.message)}</div>
                    </div>
                </div>
            `).join('');
            if (nearBottom) msgList.scrollTop = msgList.scrollHeight;
        };

        const syncViews = () => {
            if (hasConversation || isAuth) {
                guestForm?.classList.add('d-none');
                guestForm?.classList.remove('d-flex');
                chatBody?.classList.remove('d-none');
                chatBody?.classList.add('d-flex');
            } else {
                chatBody?.classList.add('d-none');
                chatBody?.classList.remove('d-flex');
                guestForm?.classList.remove('d-none');
                guestForm?.classList.add('d-flex');
            }
        };

        const fetchState = async (markRead = false) => {
            try {
                const url = markRead ? `${stateUrl}?mark_read=1` : stateUrl;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                if (!data.ok) return;

                hasConversation = Boolean(data.has_conversation);
                syncViews();
                if (hasConversation) {
                    renderSupportMessages(data.messages);
                }
                if (unreadBadge) {
                    const count = Number(data.unread_count || 0);
                    unreadBadge.textContent = count;
                    unreadBadge.classList.toggle('d-none', count <= 0 || isOpen);
                }
            } catch {
                // resilient
            }
        };

        const openChat = async () => {
            isOpen = true;
            panel?.classList.remove('d-none');
            panel?.classList.add('d-flex');
            toggleBtn?.setAttribute('aria-expanded', 'true');
            unreadBadge?.classList.add('d-none');
            await fetchState(true);
            if (!pollTimer) {
                pollTimer = setInterval(() => { if (isOpen) fetchState(true); }, 4500);
            }
        };

        const closeChat = () => {
            isOpen = false;
            panel?.classList.add('d-none');
            panel?.classList.remove('d-flex');
            toggleBtn?.setAttribute('aria-expanded', 'false');
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        };

        toggleBtn?.addEventListener('click', () => (isOpen ? closeChat() : openChat()));
        closeBtn?.addEventListener('click', closeChat);

        guestForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            guestError?.classList.add('d-none');
            guestSubmit.disabled = true;

            try {
                const res = await fetch(startUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        guest_name: document.getElementById('support_guest_name')?.value?.trim(),
                        guest_email: document.getElementById('support_guest_email')?.value?.trim(),
                        message: document.getElementById('support_guest_message')?.value?.trim(),
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    guestError.textContent = data.message || 'Please check your name, email, and message.';
                    guestError.classList.remove('d-none');
                } else {
                    hasConversation = true;
                    syncViews();
                    await fetchState(true);
                    msgList.scrollTop = msgList.scrollHeight;
                }
            } catch {
                guestError.textContent = 'Connection error. Please try again.';
                guestError.classList.remove('d-none');
            } finally {
                guestSubmit.disabled = false;
            }
        });

        msgForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const text = msgInput.value.trim();
            if (!text) return;

            chatError?.classList.add('d-none');
            msgSend.disabled = true;

            try {
                const targetUrl = hasConversation ? sendUrl : startUrl;
                const res = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ message: text }),
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    chatError.textContent = data.message || 'Could not send message.';
                    chatError.classList.remove('d-none');
                } else {
                    hasConversation = true;
                    msgInput.value = '';
                    await fetchState(true);
                    msgList.scrollTop = msgList.scrollHeight;
                }
            } catch {
                chatError.textContent = 'Connection error. Please try again.';
                chatError.classList.remove('d-none');
            } finally {
                msgSend.disabled = false;
                msgInput.focus();
            }
        });
    }

    // 13. Admin Quiz Timer Toggle (Create & Edit Modals)
    document.querySelectorAll('[data-quiz-timer-toggle]').forEach((checkbox) => {
        const targetSelector = checkbox.getAttribute('data-target');
        const target = targetSelector ? document.querySelector(targetSelector) : null;
        const durationInput = target?.querySelector('input[name="duration_minutes"]');

        const sync = () => {
            const on = checkbox.checked;
            target?.classList.toggle('d-none', !on);
            if (durationInput) {
                durationInput.required = on;
            }
        };

        checkbox.addEventListener('change', sync);
        sync();
    });

    // 14. Student Timed Quiz Sticky Countdown + Auto-Submit at 00:00:00
    const quizTimerBar = document.getElementById('ma-quiz-countdown-bar');
    const quizForm = document.getElementById('ma-student-quiz-form');
    if (quizTimerBar && quizForm) {
        const displayEl = document.getElementById('ma-quiz-timer-display');
        const iconEl = document.getElementById('ma-quiz-timer-icon');
        const autoFlagInput = document.getElementById('ma-quiz-auto-submitted');
        let remaining = Math.max(0, parseInt(quizTimerBar.dataset.remainingSeconds || '0', 10));
        let submitted = false;

        const formatHms = (sec) => {
            const s = Math.max(0, sec);
            const h = String(Math.floor(s / 3600)).padStart(2, '0');
            const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
            const r = String(s % 60).padStart(2, '0');
            return `${h}:${m}:${r}`;
        };

        const triggerAutoSubmit = () => {
            if (submitted) return;
            submitted = true;
            if (autoFlagInput) autoFlagInput.value = '1';
            quizForm.querySelectorAll('input[required]').forEach((el) => el.removeAttribute('required'));
            quizForm.submit();
        };

        const renderTick = () => {
            if (displayEl) {
                displayEl.textContent = formatHms(remaining);
            }
            if (remaining <= 120) {
                quizTimerBar.style.borderColor = '#dc2626';
                displayEl?.classList.remove('bg-navy-accent');
                displayEl?.classList.add('bg-danger');
                iconEl?.classList.remove('text-navy');
                iconEl?.classList.add('text-danger');
            }
            if (remaining <= 0) {
                triggerAutoSubmit();
            }
        };

        quizForm.addEventListener('submit', () => {
            submitted = true;
        });

        renderTick();
        if (remaining > 0) {
            const interval = setInterval(() => {
                remaining -= 1;
                renderTick();
                if (remaining <= 0 || submitted) {
                    clearInterval(interval);
                }
            }, 1000);
        }
    }
})();
