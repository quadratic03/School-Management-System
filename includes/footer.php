                </main>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="footer mt-auto py-3 bg-light">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> <?php echo isset($settings['school_name']) ? htmlspecialchars($settings['school_name']) : htmlspecialchars(APP_NAME); ?>. All rights reserved.</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-0 text-muted">Version 1.0.0</p>
                    </div>
                </div>
            </div>
        </footer>
        
        <!-- Bootstrap JS with Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        
        <!-- Custom JavaScript -->
        <script src="<?php echo APP_URL; ?>/assets/js/script.js"></script>
        
        <!-- Initialize Bootstrap components -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize all dropdowns
                var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
                dropdownElementList.map(function (dropdownToggleEl) {
                    return new bootstrap.Dropdown(dropdownToggleEl);
                });
            });
        </script>
        
        <?php if (isset($extraScripts)): ?>
            <?php echo $extraScripts; ?>
        <?php endif; ?>
    </body>
</html> 