</div>
    </main>

    <script>
        // Additional Admin Scripts
        
        // Confirm delete actions
        document.querySelectorAll('[data-confirm]').forEach(element => {
            element.addEventListener('click', function(e) {
                if (!confirm(this.dataset.confirm || 'Are you sure?')) {
                    e.preventDefault();
                }
            });
        });

        // Auto-close alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>
</body>
</html>