            <!-- Global Bottom Bar / Footer -->
            <?php
            $footer_text = $GLOBAL_SETTINGS['footer_text'] ?? '© ' . date('Y') . ' Cyno Management System. All rights reserved.';
            $footer_links_json = $GLOBAL_SETTINGS['footer_links'] ?? '[]';
            $footer_links = json_decode($footer_links_json, true);
            if (!is_array($footer_links)) $footer_links = [];
            ?>
            <div class="global-bottom-bar" style="margin-top: 40px; padding: 20px; border-top: 1px solid var(--border-card); display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--text-muted); flex-wrap: wrap; gap: 15px;">
                <div class="footer-copyright">
                    <?= htmlspecialchars($footer_text) ?>
                </div>
                <?php if (!empty($footer_links)): ?>
                <div class="footer-links" style="display: flex; gap: 15px;">
                    <?php foreach($footer_links as $link): ?>
                        <a href="<?= htmlspecialchars($link['url']) ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 500;"><?= htmlspecialchars($link['name']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
        </div> <!-- end main-content -->
    </div> <!-- end app-container -->

    <div id="genericModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Modal Title</h2>
            <form id="modalForm" method="POST">
                <div id="modalFields">
                    <!-- Dynamic form fields -->
                </div>
                <div class="form-actions">
                    <button type="button" class="cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="submit">Save</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function closeModal() {
            document.getElementById('genericModal').style.display = 'none';
        }

        // Global UI/UX Helpers

        // 0. Override window.alert
        window.alert = function(msg) {
            Swal.fire({
                text: msg,
                icon: 'info',
                confirmButtonColor: '#6366f1'
            });
        };

        // 1. Toast Notifications
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        // 2. DataTables Auto-Initialization
        document.addEventListener('DOMContentLoaded', () => {
            const tables = document.querySelectorAll('.data-table table');
            tables.forEach(table => {
                if(!table.classList.contains('dataTable-table')) {
                    new simpleDatatables.DataTable(table, {
                        searchable: true,
                        fixedHeight: false,
                        perPage: 15,
                    });
                }
            });
        });

        // 3. SweetAlert2 intercept for inline onsubmit="return confirm('...')"
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
                const match = form.getAttribute('onsubmit').match(/confirm\(['"](.*?)['"]\)/);
                if(match) {
                    const msg = match[1];
                    form.removeAttribute('onsubmit'); // Strip native confirm
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Are you sure?',
                            text: msg,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#6366f1',
                            cancelButtonColor: '#ef4444',
                            confirmButtonText: 'Yes, proceed!'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                form.submit(); // Submit bypasses the event listener loop
                            }
                        });
                    });
                }
            });
        });
    </script>
</body>
</html>
