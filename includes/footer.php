        </div><!-- end main-content -->
    </div><!-- end wrapper -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Custom JS -->
    <script src="<?= SITE_URL ?>/assets/js/main.js"></script>

    <?php if (isset($extraJs)): ?>
    <script><?= $extraJs ?></script>
    <?php endif; ?>

    <!-- Toast notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

</body>
</html>
