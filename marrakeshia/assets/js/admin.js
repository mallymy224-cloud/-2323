/* ============================================================
   مراكشية — سكربت لوحة التحكم
   ============================================================ */
(function () {
    'use strict';

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
       القائمة الجانبية على الشاشات الصغيرة
       -------------------------------------------------------- */
    function initSidebar() {
        const burger = qs('#adminBurger');
        const sidebar = qs('#adminSidebar');
        const overlay = qs('#adminOverlay');

        if (!burger || !sidebar) {
            return;
        }

        const setOpen = function (open) {
            sidebar.classList.toggle('is-open', open);
            burger.classList.toggle('is-open', open);
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            burger.setAttribute('aria-label', open ? 'إغلاق القائمة' : 'فتح القائمة');
            document.body.classList.toggle('admin-nav-open', open);

            if (overlay) {
                if (open) {
                    overlay.removeAttribute('hidden');
                } else {
                    overlay.setAttribute('hidden', '');
                }
            }
        };

        const isOpen = function () {
            return sidebar.classList.contains('is-open');
        };

        burger.addEventListener('click', function () {
            setOpen(!isOpen());
        });

        if (overlay) {
            overlay.addEventListener('click', function () {
                setOpen(false);
            });
        }

        qsa('a', sidebar).forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 1024) {
                    setOpen(false);
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isOpen()) {
                setOpen(false);
                burger.focus();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024 && isOpen()) {
                setOpen(false);
            }
        });
    }

    /* --------------------------------------------------------
       تأكيد قبل الحذف أو الإجراءات الحساسة
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
       معاينة صورة المنتج قبل الرفع
       -------------------------------------------------------- */
    function initImagePreview() {
        qsa('[data-image-input]').forEach(function (input) {
            const selector = input.getAttribute('data-preview-target') || '#imagePreview';
            const preview = qs(selector);
            if (!preview) {
                return;
            }

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file) {
                    return;
                }

                if (!/^image\//.test(file.type)) {
                    window.alert('يرجى اختيار ملف صورة صالح.');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    let img = preview.querySelector('img[data-preview-img]');
                    if (!img) {
                        const placeholder = preview.querySelector('[data-preview-img]');
                        if (placeholder && placeholder.parentNode) {
                            placeholder.parentNode.removeChild(placeholder);
                        }
                        img = document.createElement('img');
                        img.setAttribute('data-preview-img', '');
                        img.alt = 'معاينة الصورة';
                        preview.appendChild(img);
                    }
                    img.src = String(event.target.result);
                };
                reader.readAsDataURL(file);
            });
        });
    }

    /* --------------------------------------------------------
       عدد صور المعرض المختارة
       -------------------------------------------------------- */
    function initGalleryInput() {
        qsa('[data-gallery-input]').forEach(function (input) {
            const target = qs('[data-gallery-count]');
            input.addEventListener('change', function () {
                const count = input.files ? input.files.length : 0;
                if (target) {
                    target.textContent = count
                        ? 'تم اختيار ' + count + ' صورة'
                        : 'لم يتم اختيار صور';
                }
            });
        });
    }

    /* --------------------------------------------------------
       عدّاد الحروف
       -------------------------------------------------------- */
    function initCounters() {
        qsa('[data-counter]').forEach(function (field) {
            const target = document.getElementById(field.getAttribute('data-counter'));
            if (!target) {
                return;
            }
            const refresh = function () {
                target.textContent = field.value.length;
            };
            field.addEventListener('input', refresh);
            refresh();
        });
    }

    /* --------------------------------------------------------
       إغلاق تنبيهات اللوحة
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
       تشغيل
       -------------------------------------------------------- */
    onReady(function () {
        initSidebar();
        initConfirmForms();
        initImagePreview();
        initGalleryInput();
        initCounters();
        initAlerts();
    });
})();
