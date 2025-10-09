<?php
/**
 * System-wide CSS includes for ELMS
 * Include this file in all pages that need Tailwind CSS
 */

// Determine asset path based on current directory
$current_dir = dirname($_SERVER['PHP_SELF']);
$asset_path = '';

if (strpos($current_dir, '/admin') !== false || 
    strpos($current_dir, '/user') !== false || 
    strpos($current_dir, '/department') !== false || 
    strpos($current_dir, '/director') !== false || 
    strpos($current_dir, '/auth') !== false) {
    $asset_path = '../../assets/css/';
} else {
    $asset_path = 'assets/css/';
}
?>

<!-- ELMS Tailwind CSS System -->
<link rel="stylesheet" href="<?php echo $asset_path; ?>tailwind.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>font-awesome-local.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>style.css">
<link rel="stylesheet" href="<?php echo $asset_path; ?>dark-theme.css">

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
<link rel="stylesheet" href="<?php echo $asset_path; ?>admin_style.css">
<?php endif; ?>

<script src="../../assets/libs/chartjs/chart.umd.min.js"></script>

<script>
// ELMS Global Functions
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('-translate-x-full');
}

// Removed conflicting dropdown functions - using unified navbar instead

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });
});
</script>