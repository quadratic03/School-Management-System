<?php
/**
 * Header Template
 */

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/database.php';

// Get current user if logged in
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$userProfile = null;

if ($currentUser) {
    $userProfile = getUserProfile($currentUser['id'], $currentUser['role']);
}

// Get settings
$settings = [];
$settingsData = executeQuery("SELECT setting_key, setting_value FROM settings");

if ($settingsData) {
    foreach ($settingsData as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
}

// Set page title
$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . (isset($settings['school_name']) ? $settings['school_name'] : APP_NAME) : (isset($settings['school_name']) ? $settings['school_name'] : APP_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    
    <?php if (isset($extraStyles)): ?>
        <?php echo $extraStyles; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <?php if (isLoggedIn() && $currentUser['role'] !== 'admin'): ?>
            <!-- Sidebar Toggle Button -->
            <button id="sidebarToggleBtn" class="btn btn-primary btn-sm me-2">
                <i class="fas fa-bars"></i>
            </button>
            <?php endif; ?>
            
            <a class="navbar-brand" href="<?php echo APP_URL; ?>">
                <?php echo isset($settings['school_name']) ? htmlspecialchars($settings['school_name']) : htmlspecialchars(APP_NAME); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isLoggedIn()): ?>
                        <!-- Notifications Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <span class="badge bg-danger rounded-pill">0</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
                                <li><a class="dropdown-item" href="#">No new notifications</a></li>
                            </ul>
                        </li>
                        
                        <!-- User Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($currentUser['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><h6 class="dropdown-header">Role: <?php echo ucfirst(htmlspecialchars($currentUser['role'])); ?></h6></li>
                                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/<?php echo $currentUser['role']; ?>/profile.php"><i class="fas fa-id-card me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/<?php echo $currentUser['role']; ?>/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo APP_URL; ?>/login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Container -->
    <div class="container-fluid mt-5 pt-3">
        <div class="row">
            <?php if (isLoggedIn()): ?>
                <?php if ($currentUser['role'] !== 'admin'): ?>
                <!-- Sidebar - Collapsible drawer -->
                <div class="sidebar" id="sidebar">
                    <?php include_once __DIR__ . '/sidebar.php'; ?>
                </div>
                
                <!-- Overlay for closing sidebar when clicked outside -->
                <div class="sidebar-overlay" id="sidebarOverlay"></div>
                
                <!-- Main Content -->
                <main class="main-content">
                <?php else: ?>
                <!-- Admin uses full width with no sidebar -->
                <main class="col-12 px-md-4 py-4">
                <?php endif; ?>
                    <?php
                    // Display flash messages if any
                    $flashMessage = getFlashMessage();
                    if ($flashMessage): 
                    ?>
                    <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo $flashMessage['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
            <?php else: ?>
                <!-- Main Content (Full Width) -->
                <main class="col-12 px-md-4 py-4">
                    <?php
                    // Display flash messages if any
                    $flashMessage = getFlashMessage();
                    if ($flashMessage): 
                    ?>
                    <div class="alert alert-<?php echo $flashMessage['type']; ?> alert-dismissible fade show" role="alert">
                        <?php echo $flashMessage['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 