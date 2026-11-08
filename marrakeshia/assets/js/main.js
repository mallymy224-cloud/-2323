/* ============================================================
   مراكشية — سكربت واجهة المتجر
   ============================================================ */
(function () {
    'use strict';

    /* --------------------------------------------------------
       أدوات عامة
       -------------------------------------------------------- */
    const qs = (selector, scope) => (scope || document).querySelector(selector);
    const qsa = (selector, scope) => Array.prototype.slice.call((scope || document).querySelectorAll(selector));

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    /* --------------------------------------------------------
       التنبيهات المنبثقة (Toast)
       -------------------------------------------------------- */
    const TOAST_ICONS = {
        success: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M20 7 9 18l-5-5"/></svg>',
        error: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 7v6M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>',
        info: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 11v6M12 7h.01"/><circle cx="12" cy="12" r="9"/></svg>'
    };

    function showToast(message, type, duration) {
        const wrap = qs('#toastWrap');
        if (!wrap || !message) {
            return;
        }

        type = type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success');

        const toast = document.createElement('div');
        toast.className = 'toast' + (type === 'error' ? ' is-error' : '');
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.innerHTML = (TOAST_ICONS[type] || '') + '<span></span>';
        toast.lastChild.textContent = message;
        wrap.appendChild(toast);

        const remove = () => {
            toast.classList.add('is-out');
            window.setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 320);
        };

        window.setTimeout(remove, typeof duration === 'number' ? duration : 3600);
        toast.addEventListener('click', remove);
    }

    // متاح للأكواد الإضافية في الصفحات
    window.showToast = showToast;

    /* --------------------------------------------------------
       تحديث شارة السلة
       -------------------------------------------------------- */
    function updateCartBadge(count) {
        const badge = qs('#cartCount');
        if (!badge || count === null || typeof count === 'undefined') {
            return;
        }
        const value = Math.max(0, parseInt(count, 10) || 0);
        badge.textContent = value;
        badge.setAttribute('data-count', value);

        badge.classList.remove('is-bump');
        void badge.offsetWidth;
        badge.classList.add('is-bump');
        window.setTimeout(() => badge.classList.remove('is-bump'), 500);
    }

    /* --------------------------------------------------------
       إضافة إلى السلة عبر AJAX
       -------------------------------------------------------- */
    function initAddToCart() {
        qsa('form.js-add-to-cart').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.fetch || !window.FormData) {
                    return; // المتصفح قديم — يُرسل النموذج بالطريقة التقليدية
                }

                event.preventDefault();

                const button = form.querySelector('button[type="submit"]');
                if (button && (button.disabled || button.classList.contains('is-loading'))) {
                    return;
                }

                const originalHtml = button ? button.innerHTML : '';
                if (button) {
                    button.classList.add('is-loading');
                    button.disabled = true;
                }

                const restore = function () {
                    if (button) {
                        button.classList.remove('is-loading');
                        button.disabled = false;
                        button.innerHTML = originalHtml;
                    }
                };

                // ملاحظة: form.action قد تُحجب بواسطة حقل name="action"، لذا نقرأ الخاصية من العنصر مباشرة.
                const actionUrl = form.getAttribute('action') || window.location.href;

                fetch(actionUrl, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                    .then(function (response) {
                        return response.json().catch(function () {
                            throw new Error('تعذر قراءة استجابة المتجر، حاولي مرة أخرى.');
                        });
                    })
                    .then(function (data) {
                        if (data && data.ok) {
                            updateCartBadge(data.count);
                            showToast(data.message || 'تمت الإضافة إلى السلة', 'success');
                            const qty = form.querySelector('input[name="quantity"]');
                            if (qty) {
                                qty.value = 1;
                            }
                        } else {
                            showToast((data && data.message) || 'تعذر إتمام الإضافة، حاولي مرة أخرى.', 'error');
                        }
                    })
                    .catch(function (error) {
                        showToast(error && error.message ? error.message : 'حدث خطأ في الاتصال، حاولي مرة أخرى.', 'error');
                    })
                    .then(restore, restore);
            });
        });
    }

    /* --------------------------------------------------------
       أزرار زيادة/إنقاص الكمية
       -------------------------------------------------------- */
    function initQuantityControls() {
        qsa('[data-qty]').forEach(function (control) {
            const input = control.querySelector('input[type="number"]');
            if (!input) {
                return;
            }

            const step = function (direction) {
                const min = input.min !== '' ? parseInt(input.min, 10) : 1;
                const max = input.max !== '' ? parseInt(input.max, 10) : 9999;
                const current = parseInt(input.value, 10) || min;
                let next = current + direction;

                if (!isNaN(min)) {
                    next = Math.max(min, next);
                }
                if (!isNaN(max)) {
                    next = Math.min(max, next);
                }

                if (next !== current) {
                    input.value = next;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            };

            const minus = control.querySelector('[data-qty-minus]');
            const plus = control.querySelector('[data-qty-plus]');

            if (minus) {
                minus.addEventListener('click', function () { step(-1); });
            }
            if (plus) {
                plus.addEventListener('click', function () { step(1); });
            }

            input.addEventListener('blur', function () {
                const min = input.min !== '' ? parseInt(input.min, 10) : 1;
                const max = input.max !== '' ? parseInt(input.max, 10) : 9999;
                let value = parseInt(input.value, 10);
                if (isNaN(value)) {
                    value = min;
                }
                value = Math.max(min, Math.min(max, value));
                input.value = value;
            });
        });
    }

    /* --------------------------------------------------------
       قائمة الجوال
       -------------------------------------------------------- */
    function initMobileNav() {
        const toggle = qs('#navToggle');
        const nav = qs('#mobileNav');
        if (!toggle || !nav) {
            return;
        }

        const setOpen = function (open) {
            if (open) {
                nav.removeAttribute('hidden');
            } else {
                nav.setAttribute('hidden', '');
            }
            toggle.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'إغلاق القائمة' : 'فتح القائمة');
        };

        const isOpen = function () {
            return !nav.hasAttribute('hidden');
        };

        toggle.addEventListener('click', function () {
            setOpen(!isOpen());
        });

        qsa('a', nav).forEach(function (link) {
            link.addEventListener('click', function () { setOpen(false); });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isOpen()) {
                setOpen(false);
                toggle.focus();
            }
        });

        let lastWidth = window.innerWidth;
        window.addEventListener('resize', function () {
            // إغلاق القائمة عند الانتقال إلى شاشة كبيرة
            if (window.innerWidth !== lastWidth && window.innerWidth > 960 && isOpen()) {
                setOpen(false);
            }
            lastWidth = window.innerWidth;
        });
    }

    /* --------------------------------------------------------
       رأس الصفحة عند التمرير + زر العودة للأعلى
       -------------------------------------------------------- */
    function initScrollUi() {
        const header = qs('#siteHeader');
        const backToTop = qs('#backToTop');
        let ticking = false;

        const update = function () {
            const scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;

            if (header) {
                header.classList.toggle('is-scrolled', scrollY > 12);
            }
            if (backToTop) {
                backToTop.classList.toggle('is-visible', scrollY > 420);
            }
            ticking = false;
        };

        window.addEventListener('scroll', function () {
            if (!ticking) {
                window.requestAnimationFrame(update);
                ticking = true;
            }
        }, { passive: true });

        if (backToTop) {
            backToTop.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        update();
    }

    /* --------------------------------------------------------
       إغلاق التنبيهات
       -------------------------------------------------------- */
    function initAlerts() {
        document.addEventListener('click', function (event) {
            const closer = event.target.closest('[data-dismiss], .alert-close');
            if (!closer) {
                return;
            }
            const alert = closer.closest('.alert');
            if (!alert) {
                return;
            }
            event.preventDefault();
            alert.style.transition = 'opacity .2s ease, transform .2s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-6px)';
            window.setTimeout(function () {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 210);
        });
    }

    /* --------------------------------------------------------
       تأكيد قبل تنفيذ إجراءات الحذف
       -------------------------------------------------------- */
    function initConfirmForms() {
        qsa('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const message = form.getAttribute('data-confirm') || 'هل أنت متأكدة؟';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }

    /* --------------------------------------------------------
       إرسال تلقائي للقوائم المنسدلة (الترتيب مثلاً)
       -------------------------------------------------------- */
    function initAutoSubmit() {
        qsa('[data-autosubmit]').forEach(function (field) {
            field.addEventListener('change', function () {
                const form = field.form;
                if (form) {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            });
        });
    }

    /* --------------------------------------------------------
       عدّاد الحروف (يُستخدم في لوحة التحكم أيضاً)
       -------------------------------------------------------- */
    function initCounters() {
        qsa('[data-counter]').forEach(function (field) {
            const target = document.getElementById(field.getAttribute('data-counter'));
            if (!target) {
                return;
            }
            const max = parseInt(field.getAttribute('maxlength'), 10) || 0;
            const refresh = function () {
                target.textContent = field.value.length;
                if (max > 0) {
                    target.textContent += '';
                }
            };
            field.addEventListener('input', refresh);
            refresh();
        });
    }

    /* --------------------------------------------------------
       اختيار المقاس في صفحة المنتج
       -------------------------------------------------------- */
    function initSizePicker() {
        qsa('[data-size-picker]').forEach(function (picker) {
            const chips = qsa('.size-chip', picker);
            const hidden = qs('[data-size-input]');
            if (!hidden || !chips.length) {
                return;
            }

            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    chips.forEach(function (other) {
                        other.classList.remove('is-active');
                        other.setAttribute('aria-pressed', 'false');
                    });
                    chip.classList.add('is-active');
                    chip.setAttribute('aria-pressed', 'true');
                    hidden.value = chip.getAttribute('data-size-id') || '0';
                });
            });
        });
    }

    /* --------------------------------------------------------
       معرض صور المنتج
       -------------------------------------------------------- */
    function initProductGallery() {
        const main = qs('#mainProductImage');
        const thumbs = qsa('[data-gallery-src]');
        if (!main || !thumbs.length) {
            return;
        }

        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                thumbs.forEach(function (other) {
                    other.classList.remove('is-active');
                    other.setAttribute('aria-pressed', 'false');
                });
                thumb.classList.add('is-active');
                thumb.setAttribute('aria-pressed', 'true');
                main.src = thumb.getAttribute('data-gallery-src') || main.src;
            });
        });
    }

    /* --------------------------------------------------------
       تبديل طريقة دخول العميل (بريد / هاتف)
       -------------------------------------------------------- */
    function initLoginMode() {
        const tabs = qsa('[data-auth-tabs] .auth-tab input[name="login_mode"]');
        const input = qs('[data-identifier-input]');
        if (!tabs.length || !input) {
            return;
        }

        const label = qs('[data-identifier-label]');
        const hint = qs('[data-identifier-hint]');

        const apply = function (mode) {
            qsa('[data-auth-tabs] .auth-tab').forEach(function (tab) {
                const radio = tab.querySelector('input');
                tab.classList.toggle('is-active', !!radio && radio.checked);
            });

            if (mode === 'phone') {
                if (label) { label.textContent = 'رقم الهاتف'; }
                if (hint) { hint.textContent = 'استخدمي رقم الهاتف الذي سجّلتِ به حسابك.'; }
                input.placeholder = '0612345678';
                input.setAttribute('autocomplete', 'tel');
                input.setAttribute('inputmode', 'tel');
            } else {
                if (label) { label.textContent = 'البريد الإلكتروني'; }
                if (hint) { hint.textContent = 'أدخلي بريدك الإلكتروني المسجّل.'; }
                input.placeholder = 'name@example.com';
                input.setAttribute('autocomplete', 'email');
                input.setAttribute('inputmode', 'email');
            }
        };

        tabs.forEach(function (radio) {
            radio.addEventListener('change', function () { apply(radio.value); });
        });

        const checked = tabs.find(function (radio) { return radio.checked; });
        apply(checked ? checked.value : 'email');
    }

    /* --------------------------------------------------------
       تمييز طريقة الدفع المختارة
       -------------------------------------------------------- */
    function initPaymentOptions() {
        const radios = qsa('input[name="payment_method"]');
        if (!radios.length) {
            return;
        }

        const cardDetails = qs('#cardDetails');
        const cardInputs = cardDetails ? qsa('input', cardDetails) : [];
        const cardRadios = qsa('input[name="payment_method"][data-requires-card]');

        const refresh = function () {
            radios.forEach(function (radio) {
                const option = radio.closest('[data-payment-option]');
                if (option) {
                    option.classList.toggle('is-selected', radio.checked);
                }
            });

            if (cardDetails) {
                const active = cardRadios.some(function (radio) { return radio.checked; });
                cardDetails.hidden = !active;
                cardInputs.forEach(function (input) {
                    input.disabled = !active;
                    input.required = active;
                    if (!active) {
                        input.value = ''; // عدم إبقاء بيانات البطاقة في الصفحة عند الدفع نقداً
                    }
                });
            }
        };

        radios.forEach(function (radio) {
            radio.addEventListener('change', refresh);
        });
        refresh();
    }

    /* --------------------------------------------------------
       تنسيق حقول البطاقة (تجريبي)
       -------------------------------------------------------- */
    function initCardFields() {
        const number = qs('[data-card-number]');
        if (number) {
            number.addEventListener('input', function () {
                const digits = number.value.replace(/\D/g, '').slice(0, 19);
                number.value = digits.replace(/(.{4})/g, '$1 ').trim();
            });
        }

        const expiry = qs('[data-card-expiry]');
        if (expiry) {
            expiry.addEventListener('input', function () {
                const digits = expiry.value.replace(/\D/g, '').slice(0, 4);
                expiry.value = digits.length > 2
                    ? digits.slice(0, 2) + '/' + digits.slice(2)
                    : digits;
            });
        }

        const cvv = qs('[data-card-cvv]');
        if (cvv) {
            cvv.addEventListener('input', function () {
                cvv.value = cvv.value.replace(/\D/g, '').slice(0, 4);
            });
        }
    }

    /* --------------------------------------------------------
       تشغيل
       -------------------------------------------------------- */
    onReady(function () {
        initAddToCart();
        initQuantityControls();
        initSizePicker();
        initProductGallery();
        initLoginMode();
        initPaymentOptions();
        initCardFields();
        initMobileNav();
        initScrollUi();
        initAlerts();
        initConfirmForms();
        initAutoSubmit();
        initCounters();
    });
})();
