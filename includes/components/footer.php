            </div>
        </main>
    </div>

    <!-- Common JavaScript Functions -->
    <script>
        // Notification toggle function
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('[id$="Modal"]');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            });
        });

        // Show notification function
        function showNotification(message, type = 'info') {
            const bgColor = type === 'success' ? 'bg-green-500/20 border-green-500/30 text-green-400' : 
                           type === 'error' ? 'bg-red-500/20 border-red-500/30 text-red-400' : 
                           type === 'warning' ? 'bg-yellow-500/20 border-yellow-500/30 text-yellow-400' : 
                           'bg-blue-500/20 border-blue-500/30 text-blue-400';
            
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'error' ? 'fa-exclamation-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 
                        'fa-info-circle';
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 ${bgColor} border rounded-xl p-4 max-w-sm shadow-xl`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${icon} mr-3"></i>
                    <div class="flex-1">${message}</div>
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="ml-3 text-current hover:opacity-75">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Fetch pending leave count for admin pages
        function fetchPendingLeaveCount() {
            fetch('api/get_pending_leave_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('pendingLeaveBadge');
                        if (badge && data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'inline-block';
                        } else if (badge) {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error fetching pending leave count:', error));
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch pending leave count if on admin pages
            if (document.getElementById('pendingLeaveBadge')) {
                fetchPendingLeaveCount();
                setInterval(fetchPendingLeaveCount, 30000);
            }
        });
    </script>
</body>
</html>
