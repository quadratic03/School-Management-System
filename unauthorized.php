<?php
/**
 * Unauthorized Access Page
 */

// Include required files
require_once 'includes/config.php';

// Set page title
$pageTitle = 'Unauthorized Access';

// Custom styles
$extraStyles = '<style>
    body {
        background-color: #f5f5f5;
    }
    .unauthorized-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    .unauthorized-content {
        max-width: 600px;
        padding: 2rem;
    }
    .error-code {
        font-size: 6rem;
        font-weight: bold;
        color: #dc3545;
        margin-bottom: 1rem;
    }
    .error-message {
        font-size: 1.5rem;
        margin-bottom: 2rem;
    }
    .error-description {
        color: #6c757d;
        margin-bottom: 2rem;
    }
</style>';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars(APP_NAME); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    
    <?php echo $extraStyles; ?>
</head>
<body>
    <div class="unauthorized-container">
        <div class="unauthorized-content">
            <div class="error-code">403</div>
            <div class="error-message">Access Denied</div>
            <div class="error-description">
                <p>You do not have permission to access this page.</p>
                <p>If you believe this is an error, please contact your system administrator.</p>
            </div>
            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                <a href="<?php echo APP_URL; ?>" class="btn btn-primary me-md-2">
                    <i class="fas fa-home me-2"></i>Go to Home
                </a>
                <a href="<?php echo APP_URL; ?>/login.php" class="btn btn-outline-secondary">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 