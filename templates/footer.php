</div>
    <!-- /Main Content -->

    <!-- Footer -->
    <footer class="footer mt-5 py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <span class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?></span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted"><a href="https://bigdrop.gr">Created by BigDrop.gr</a><br>For support contact at info@bigdrop.gr</span>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if (isset($last_sync) && $last_sync): ?>
                    <span class="text-muted">Last sync: <?php echo format_date($last_sync->sync_date); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/app.js"></script>
</body>
</html>