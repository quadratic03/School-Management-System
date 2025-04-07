<?php
/**
 * Reset Password Page
 */

// Start session
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect to appropriate dashboard
    redirect(getRedirectUrl());
}

// Initialize variables
$token = '';
$error = '';
$success = '';
$validToken = false;
$userId = null;

// Get token from URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize($_GET['token']);
    
    // Verify token
    $sql = "SELECT * FROM password_resets WHERE token = ? AND expiry > NOW()";
    $reset = executeSingleQuery($sql, [$token]);
    
    if ($reset) {
        $validToken = true;
        $userId = $reset['user_id'];
    } else {
        $error = 'Invalid or expired reset token. Please request a new password reset link.';
    }
} else {
    $error = 'No reset token provided. Please request a password reset link.';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    // Get form data
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate input
    if (empty($password) || empty($confirmPassword)) {
        $error = 'Please enter both password fields';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        // Hash password
        $hashedPassword = hashPassword($password);
        
        // Update user password
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $result = executeNonQuery($sql, [$hashedPassword, $userId]);
        
        if ($result) {
            // Delete all password reset tokens for this user
            $sql = "DELETE FROM password_resets WHERE user_id = ?";
            executeNonQuery($sql, [$userId]);
            
            $success = 'Your password has been reset successfully. You can now login with your new password.';
            $validToken = false; // Prevent form from being displayed
        } else {
            $error = 'An error occurred. Please try again later.';
        }
    }
}

// Set page title
$pageTitle = 'Reset Password';

// Custom styles for login page
$extraStyles = '<style>
    body {
        background-color: #f5f5f5;
    }
    .reset-password-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .reset-password-form {
        width: 100%;
        max-width: 400px;
        padding: 15px;
        margin: auto;
    }
    .school-logo {
        width: 80px;
        height: 80px;
        margin-bottom: 20px;
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
    <div class="reset-password-page">
        <div class="reset-password-form">
            <div class="text-center mb-4">
                <img src="<?php echo APP_URL; ?>/assets/images/logo.png" alt="School Logo" class="school-logo" onerror="this.src='<?php echo APP_URL; ?>/assets/images/default-logo.png'">
                <h1 class="h3 mb-3 font-weight-normal"><?php echo htmlspecialchars(APP_NAME); ?></h1>
                <p class="text-muted">Create a new password</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($validToken): ?>
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <p class="mb-3">Please enter your new password below.</p>
                        
                        <form method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?token=' . htmlspecialchars($token); ?>" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                    <div class="invalid-feedback">
                                        Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.
                                    </div>
                                </div>
                                <div class="form-text">
                                    Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                    <div class="invalid-feedback">
                                        Please confirm your password.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Reset Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-3 text-center">
                <a href="login.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                </a>
            </div>
            
            <div class="mt-5 text-center">
                <p class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All rights reserved.</p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/script.js"></script>
    
    <script>
        // Form validation
        (function() {
            'use strict';
            
            // Fetch all forms we want to apply validation to
            var forms = document.querySelectorAll('.needs-validation');
            
            // Loop over them and prevent submission
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    // Check if passwords match
                    var password = document.getElementById('password');
                    var confirmPassword = document.getElementById('confirm_password');
                    
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                        event.preventDefault();
                        event.stopPropagation();
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Add realtime password match validation
            document.getElementById('confirm_password').addEventListener('input', function() {
                var password = document.getElementById('password').value;
                var confirmPassword = this.value;
                
                if (password !== confirmPassword) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        })();
    </script>
</body>
</html> 