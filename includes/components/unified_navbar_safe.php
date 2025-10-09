<nav class="bg-slate-800 border-b border-slate-700 px-6 py-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <h1 class="text-xl font-semibold text-white">ELMS</h1>
        </div>
        
        <div class="flex items-center space-x-4">
            <!-- Notifications Bell -->
            <div class="relative">
                <button onclick="toggleNotificationDropdown()" class="relative p-2 text-slate-300 hover:text-white hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-bell text-xl"></i>
                    <?php if (isset($unread_alert_count) && $unread_alert_count > 0): ?>
                    <span class="notification-badge absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-semibold">
                        <?php echo $unread_alert_count > 99 ? '99+' : $unread_alert_count; ?>
                    </span>
                    <?php endif; ?>
                </button>
                
                <!-- Notification Dropdown -->
                <div id="notificationDropdown" class="absolute right-0 mt-2 w-80 md:w-96 bg-slate-800 border border-slate-700 rounded-lg shadow-xl hidden">
                    <div class="p-4 border-b border-slate-700">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <i class="fas fa-bell mr-2"></i>
                            Notifications
                            <?php if (isset($unread_alert_count) && $unread_alert_count > 0): ?>
                            <span class="ml-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-semibold">
                                <?php echo $unread_alert_count > 99 ? '99+' : $unread_alert_count; ?>
                            </span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="max-h-64 overflow-y-auto">
                        <?php if (isset($leave_alerts) && !empty($leave_alerts)): ?>
                            <?php foreach ($leave_alerts as $alert): ?>
                            <div class="notification-item p-3 border-b border-slate-700/50 hover:bg-slate-700/30 transition-all duration-200 cursor-pointer" 
                                 onclick="openNotificationModal(<?php echo $alert['id']; ?>, '<?php echo htmlspecialchars($alert['alert_type'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($alert['message'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($alert['created_at'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($alert['sent_by_name'] ?? 'System', ENT_QUOTES); ?>')">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-blue-500/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-bell text-xs text-blue-400"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-semibold text-white"><?php echo htmlspecialchars($alert['alert_type']); ?></h4>
                                        <p class="text-xs text-slate-400 truncate"><?php echo htmlspecialchars(substr($alert['message'], 0, 60)); ?>...</p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center">
                                <i class="fas fa-bell-slash text-4xl text-slate-500 mb-4"></i>
                                <p class="text-slate-400">No notifications</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- User Dropdown -->
            <div class="relative">
                <button onclick="toggleUserDropdown()" class="flex items-center space-x-2 text-slate-300 hover:text-white">
                    <i class="fas fa-user-circle text-xl"></i>
                    <span><?php echo htmlspecialchars($employee['name']); ?></span>
                    <i class="fas fa-chevron-down text-sm"></i>
                </button>
                
                <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-slate-700 border border-slate-600 rounded-lg shadow-lg hidden">
                    <div class="py-2">
                        <a href="profile.php" class="block px-4 py-2 text-slate-300 hover:bg-slate-600 hover:text-white">Profile</a>
                        <a href="../logout.php" class="block px-4 py-2 text-slate-300 hover:bg-slate-600 hover:text-white">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
        
        // Ensure dropdown is properly positioned and isolated
        if (!dropdown.classList.contains('hidden')) {
            dropdown.style.position = 'absolute';
            dropdown.style.zIndex = '1000';
            dropdown.style.isolation = 'isolate';
            
            // Remove any misplaced elements that might have appeared
            const misplacedInputs = dropdown.querySelectorAll('input');
            misplacedInputs.forEach(input => {
                input.remove();
            });
        }
    }
    if (notificationDropdown) {
        notificationDropdown.classList.add('hidden');
    }
}

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    const userDropdown = document.getElementById('userDropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
    if (userDropdown) {
        userDropdown.classList.add('hidden');
    }
}

function openNotificationModal(alertId, alertType, message, createdAt, sentBy) {
    console.log('Opening notification modal:', { alertId, alertType, message, createdAt, sentBy });
    
    // Create modal overlay
    const modalOverlay = document.createElement('div');
    modalOverlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    
    // Create modal content
    const modalContent = document.createElement('div');
    modalContent.className = 'bg-slate-800 border border-slate-700 rounded-lg w-full max-w-lg max-h-80vh overflow-y-auto';
    
    modalContent.innerHTML = `
        <div class="p-6 border-b border-slate-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-bell text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-white">${alertType}</h3>
                        <p class="text-sm text-slate-400">Alert ID: ${alertId}</p>
                    </div>
                </div>
                <button onclick="closeNotificationModal()" class="text-slate-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-slate-300 mb-2">Message</h4>
                <div class="bg-slate-700 rounded-lg p-4 max-h-32 overflow-y-auto">
                    <p class="text-slate-200 text-sm">${message}</p>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <h4 class="font-semibold text-slate-300 mb-1">Sent By</h4>
                    <p class="text-slate-400">${sentBy}</p>
                </div>
                <div>
                    <h4 class="font-semibold text-slate-300 mb-1">Date & Time</h4>
                    <p class="text-slate-400">${new Date(createdAt).toLocaleString()}</p>
                </div>
            </div>
        </div>
        
        <div class="p-6 border-t border-slate-700 flex justify-end">
            <button onclick="closeNotificationModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                Close
            </button>
        </div>
    `;
    
    modalOverlay.appendChild(modalContent);
    document.body.appendChild(modalOverlay);
    
    // Close modal when clicking outside
    modalOverlay.onclick = function(e) {
        if (e.target === modalOverlay) {
            closeNotificationModal();
        }
    };
    
    // Store modal reference for closing
    window.currentNotificationModal = modalOverlay;
}

function closeNotificationModal() {
    if (window.currentNotificationModal) {
        document.body.removeChild(window.currentNotificationModal);
        window.currentNotificationModal = null;
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const userDropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (userDropdown && !event.target.closest('.relative')) {
        userDropdown.classList.add('hidden');
    }
    if (notificationDropdown && !event.target.closest('.relative')) {
        notificationDropdown.classList.add('hidden');
    }
});

console.log('Safe navbar loaded successfully');

// Clean up any misplaced elements in dropdowns
function cleanupDropdowns() {
    const userDropdown = document.getElementById('userDropdown');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    [userDropdown, notificationDropdown].forEach(dropdown => {
        if (dropdown) {
            // Remove any input elements that shouldn't be in the dropdown
            const misplacedInputs = dropdown.querySelectorAll('input');
            misplacedInputs.forEach(input => {
                input.remove();
            });
            
            // Ensure proper styling
            dropdown.style.position = 'absolute';
            dropdown.style.zIndex = '1000';
            dropdown.style.isolation = 'isolate';
        }
    });
}

// Initialize cleanup when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    cleanupDropdowns();
});
</script>

<style>
    /* Clean dropdown styles */
    #userDropdown, #notificationDropdown {
        z-index: 1000;
        box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.3);
        position: absolute !important;
        isolation: isolate;
    }
    
    /* Ensure proper stacking */
    nav {
        z-index: 50;
        position: relative;
    }
    
    .relative {
        z-index: 100;
        position: relative;
    }
    
    /* Prevent search input from appearing in dropdown */
    #searchInput {
        position: relative;
        z-index: 1;
    }
    
    /* Ensure dropdown content is properly contained */
    #userDropdown .py-2 {
        position: relative;
        z-index: 1001;
        background: inherit;
    }
    
    /* Additional dropdown isolation */
    #userDropdown {
        overflow: hidden;
        contain: layout style paint;
    }
    
    /* Ensure no elements bleed into dropdown */
    #userDropdown * {
        position: relative;
    }
    
    /* Prevent any input elements from appearing in dropdown */
    #userDropdown input {
        display: none !important;
    }
</style>
