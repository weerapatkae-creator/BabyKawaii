        </div><!-- end main-content -->
    </div><!-- end wrapper -->

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

    <!-- Toast notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

</body>
</html>
