        </div><!-- end main-content -->
    </div><!-- end wrapper -->

    <!-- CSRF auto-protection: ฝัง token ลงทุกฟอร์ม POST + แนบ header ให้ fetch same-origin -->
    <script>
    (function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (!meta) return;
        var TOKEN = meta.getAttribute('content');

        // 1) ฝัง hidden input ลงทุกฟอร์มที่เป็น method POST
        function injectForms(root) {
            (root || document).querySelectorAll('form').forEach(function (f) {
                if ((f.method || '').toUpperCase() !== 'POST') return;
                if (f.querySelector('input[name="csrf_token"]')) return;
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = 'csrf_token'; i.value = TOKEN;
                f.appendChild(i);
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { injectForms(); });
        } else { injectForms(); }
        // ครอบคลุมฟอร์มที่ถูกสร้างภายหลัง (เช่น modal) ก่อน submit
        document.addEventListener('submit', function (e) {
            if (e.target && e.target.tagName === 'FORM') injectForms(e.target.parentNode || document);
        }, true);

        // 2) แนบ X-CSRF-Token ให้ fetch ที่เป็น same-origin และไม่ใช่ GET/HEAD
        var _fetch = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            var url    = (typeof input === 'string') ? input : (input && input.url) || '';
            var method = (init.method || (typeof input === 'object' && input && input.method) || 'GET').toUpperCase();
            var sameOrigin = url === '' || url.charAt(0) === '/' || url.indexOf(window.location.origin) === 0 || !/^https?:\/\//i.test(url);
            if (method !== 'GET' && method !== 'HEAD' && sameOrigin) {
                var h = new Headers(init.headers || (typeof input === 'object' && input && input.headers) || {});
                if (!h.has('X-CSRF-Token')) h.set('X-CSRF-Token', TOKEN);
                init.headers = h;
            }
            return _fetch.call(this, input, init);
        };
    })();
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Custom JS -->
    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>
    <!-- Global modal backdrop fix (Bootstrap 5.3 cleanup guard) -->
    <script>
    (function () {
        function forceModalCleanup() {
            // Only clean up if no modal is still visible
            if (document.querySelector('.modal.show')) return;
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.modal-backdrop').forEach(function (b) { b.remove(); });
        }
        // Fire on every modal hide — catches data-bs-dismiss, ESC key, and programmatic .hide()
        document.addEventListener('hidden.bs.modal', function () {
            setTimeout(forceModalCleanup, 50);
        });
        // Fallback: also catch clicks on data-bs-dismiss in case event doesn't fire
        document.addEventListener('click', function (e) {
            var t = e.target.closest('[data-bs-dismiss="modal"]');
            if (t) setTimeout(forceModalCleanup, 380);
        });
    })();
    </script>

    <?php if (isset($extraJs)): ?>
    <script><?= $extraJs ?></script>
    <?php endif; ?>

    <!-- Global inbox badge poller (ทุกหน้า) -->
    <?php if (isset($_SESSION['admin_id'])): ?>
    <script>
    (function () {
        const POLL_MS = 3000;
        const API     = '<?= SITE_URL ?>/api/inbox-notify.php?last_id=0';

        function updateInboxBadge(n) {
            // Sidebar badge
            const sb = document.getElementById('sidebarInboxBadge');
            if (sb) {
                if (n > 0) { sb.textContent = n > 99 ? '99+' : n; sb.style.display = ''; }
                else        { sb.style.display = 'none'; }
            }
            // Header button + badge
            const btn   = document.getElementById('headerInboxBtn');
            const badge = document.getElementById('headerInboxBadge');
            const label = document.getElementById('headerInboxLabel');
            if (btn) {
                if (n > 0) {
                    btn.classList.replace('btn-outline-secondary', 'btn-danger');
                    if (badge) { badge.textContent = n > 99 ? '99+' : n; badge.style.display = ''; }
                    if (label) label.style.display = '';
                } else {
                    btn.classList.replace('btn-danger', 'btn-outline-secondary');
                    if (badge) badge.style.display = 'none';
                    if (label) label.style.display = 'none';
                }
            }
        }

        function poll() {
            fetch(API, { cache: 'no-store' })
                .then(r => r.json())
                .then(d => updateInboxBadge(parseInt(d.stats?.total_unread) || 0))
                .catch(() => {})
                .finally(() => setTimeout(poll, POLL_MS));
        }

        // ถ้าอยู่หน้า inbox อยู่แล้ว ไม่ต้องเปิด poller ซ้ำ (inbox.php มีของตัวเอง)
        if (!document.getElementById('inboxApp')) poll();
    })();
    </script>
    <?php endif; ?>

    <!-- Toast notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

</body>
</html>
