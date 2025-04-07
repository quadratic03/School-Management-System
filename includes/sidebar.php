<?php
/**
 * Sidebar Template
 * 
 * This file contains sidebar navigation based on user role
 */

// Get current user role
$userRole = isset($currentUser['role']) ? $currentUser['role'] : '';

// Get active page for menu highlighting
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

/**
 * Check if a menu item is active
 * 
 * @param string $page Page filename to check
 * @return string 'active' if current page matches, empty string otherwise
 */
function isActive($page) {
    global $currentFile;
    return ($currentFile == $page) ? 'active' : '';
}

/**
 * Check if a directory is active
 * 
 * @param string $dir Directory name to check
 * @return string 'active' if current directory matches, empty string otherwise
 */
function isDirActive($dir) {
    global $currentDir;
    return ($currentDir == $dir) ? 'active' : '';
}
?>

<div class="sidebar-sticky">
    <!-- User profile mini section -->
    <div class="d-flex align-items-center pb-3 mb-3 border-bottom">
        <i class="fas fa-user-circle text-primary me-2" style="font-size: 2rem;"></i>
        <div>
            <div class="fw-bold"><?php echo htmlspecialchars($currentUser['username']); ?></div>
            <small class="text-muted"><?php echo ucfirst(htmlspecialchars($currentUser['role'])); ?></small>
        </div>
    </div>

    <ul class="nav flex-column">
        <?php if ($userRole === 'teacher'): ?>
            <!-- Teacher Menu -->
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('dashboard.php'); ?>" href="<?php echo APP_URL; ?>/modules/teacher/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('student'); ?>" href="<?php echo APP_URL; ?>/modules/teacher/students.php">
                    <i class="fas fa-user-graduate"></i>
                    My Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('class'); ?>" href="<?php echo APP_URL; ?>/modules/teacher/classes.php">
                    <i class="fas fa-school"></i>
                    My Classes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('attendance'); ?>" href="<?php echo APP_URL; ?>/modules/teacher/attendance.php">
                    <i class="fas fa-calendar-check"></i>
                    Attendance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('assignment'); ?>" href="<?php echo APP_URL; ?>/modules/teacher/assignments.php">
                    <i class="fas fa-tasks"></i>
                    Assignments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('grades'); ?>" href="<?php echo APP_URL; ?>/modules/teacher/grades.php">
                    <i class="fas fa-chart-line"></i>
                    Grade Book
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('reports'); ?>" href="<?php echo APP_URL; ?>/modules/teacher/reports.php">
                    <i class="fas fa-file-alt"></i>
                    Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('communication'); ?>" href="<?php echo APP_URL; ?>/modules/teacher/communication.php">
                    <i class="fas fa-comments"></i>
                    Communication
                </a>
            </li>
        <?php elseif ($userRole === 'student'): ?>
            <!-- Student Menu -->
            <li class="nav-item">
                <a class="nav-link <?php echo isActive('dashboard.php'); ?>" href="<?php echo APP_URL; ?>/modules/student/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('class'); ?>" href="<?php echo APP_URL; ?>/modules/student/classes.php">
                    <i class="fas fa-school"></i>
                    My Classes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('attendance'); ?>" href="<?php echo APP_URL; ?>/modules/student/attendance.php">
                    <i class="fas fa-calendar-check"></i>
                    My Attendance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('assignment'); ?>" href="<?php echo APP_URL; ?>/modules/student/assignments.php">
                    <i class="fas fa-tasks"></i>
                    Assignments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('exam'); ?>" href="<?php echo APP_URL; ?>/modules/student/exams.php">
                    <i class="fas fa-clipboard-list"></i>
                    Exams
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('grades'); ?>" href="<?php echo APP_URL; ?>/modules/student/grades.php">
                    <i class="fas fa-chart-line"></i>
                    My Grades
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo isDirActive('communication'); ?>" href="<?php echo APP_URL; ?>/modules/student/communication.php">
                    <i class="fas fa-comments"></i>
                    Communication
                </a>
            </li>
        <?php endif; ?>
    </ul>
    
    <hr>
    
    <!-- User Logout Button -->
    <div class="px-3 mb-3">
        <a href="<?php echo APP_URL; ?>/logout.php" class="btn btn-danger w-100">
            <i class="fas fa-sign-out-alt me-2"></i>
            Logout
        </a>
    </div>
</div> 