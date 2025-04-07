<?php
/**
 * Admin Navigation Menu
 * 
 * This file contains the top navigation menu for the admin section
 */

// Get current page for highlighting
$currentFile = basename($_SERVER['PHP_SELF']);

/**
 * Check if a menu item is active
 * 
 * @param string $page Page filename to check
 * @return string 'active' if current page matches, empty string otherwise
 */
function isAdminPageActive($page) {
    global $currentFile;
    return ($currentFile == $page) ? 'btn-primary' : 'btn-outline-primary';
}
?>

<!-- Admin Navigation Menu -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-auto">
                <a href="<?php echo APP_URL; ?>/modules/admin/dashboard.php" class="btn <?php echo isAdminPageActive('dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
            <div class="col-auto">
                <a href="<?php echo APP_URL; ?>/modules/admin/students.php" class="btn <?php echo isAdminPageActive('students.php'); ?>">
                    <i class="fas fa-user-graduate"></i> Students
                </a>
            </div>
            <div class="col-auto">
                <a href="<?php echo APP_URL; ?>/modules/admin/teachers.php" class="btn <?php echo isAdminPageActive('teachers.php'); ?>">
                    <i class="fas fa-chalkboard-teacher"></i> Teachers
                </a>
            </div>
            <div class="col-auto">
                <a href="<?php echo APP_URL; ?>/modules/admin/classes.php" class="btn <?php echo isAdminPageActive('classes.php'); ?>">
                    <i class="fas fa-school"></i> Classes
                </a>
            </div>
            <div class="col-auto">
                <a href="<?php echo APP_URL; ?>/modules/admin/subjects.php" class="btn <?php echo isAdminPageActive('subjects.php'); ?>">
                    <i class="fas fa-book"></i> Subjects
                </a>
            </div>
            <div class="col-auto">
                <a href="<?php echo APP_URL; ?>/modules/admin/enrollment.php" class="btn <?php echo isAdminPageActive('enrollment.php'); ?>">
                    <i class="fas fa-user-plus"></i> Enrollment
                </a>
            </div>
            <div class="col-auto">
                <a href="<?php echo APP_URL; ?>/modules/admin/attendance.php" class="btn <?php echo isAdminPageActive('attendance.php'); ?>">
                    <i class="fas fa-calendar-check"></i> Attendance
                </a>
            </div>
            <div class="col-auto">
                <a href="<?php echo APP_URL; ?>/modules/admin/exams.php" class="btn <?php echo isAdminPageActive('exams.php'); ?>">
                    <i class="fas fa-clipboard-list"></i> Exams
                </a>
            </div>
            <div class="col-auto">
                <a href="<?php echo APP_URL; ?>/modules/admin/reports.php" class="btn <?php echo isAdminPageActive('reports.php'); ?>">
                    <i class="fas fa-file-alt"></i> Reports
                </a>
            </div>
            <div class="col-auto">
                <a href="<?php echo APP_URL; ?>/modules/admin/settings.php" class="btn <?php echo isAdminPageActive('settings.php'); ?>">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
        </div>
    </div>
</div> 