<?php
/**
 * Forgot Password Page
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
$email = '';
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = sanitize($_POST['email']);
    
    // Validate input
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if email exists
        $sql = "SELECT * FROM users WHERE email = ? AND status = 'active'";
        $user = executeSingleQuery($sql, [$email]);
        
        if ($user) {
            // Generate reset token
            $token = generateToken();
            $expiry = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);
            
            // Save token in database
            $sql = "INSERT INTO password_resets (user_id, token, expiry) VALUES (?, ?, ?)";
            $result = executeNonQuery($sql, [$user['id'], $token, $expiry]);
            
            if ($result) {
                // Send password reset email (this should be implemented later)
                // For now, just show success message with the reset link
                $resetLink = APP_URL . '/reset-password.php?token=' . $token;
                
                $success = 'A password reset link has been sent to your email address. Please check your inbox.';
                
                // NOTE: In a real application, you would send an email with the reset link
                // For development/testing, we'll just display the link
                $success .= ' <br><small>(For development: <a href="' . $resetLink . '">' . $resetLink . '</a>)</small>';
                
                // Clear email field after successful submission
                $email = '';
            } else {
                $error = 'An error occurred. Please try again later.';
            }
        } else {
            // Don't reveal that the email doesn't exist for security reasons
            $success = 'If your email address exists in our database, you will receive a password reset link shortly.';
            $email = '';
        }
    }
}

// Set page title
$pageTitle = 'Forgot Password';

// Custom styles for login page
$extraStyles = '<style>
    body {
        background-color: #f5f5f5;
    }
    .forgot-password-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .forgot-password-form {
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
    <div class="forgot-password-page">
        <div class="forgot-password-form">
           
            
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
            
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <p class="mb-3">Enter your email address below and we'll send you a link to reset your password.</p>
                    
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
                                <div class="invalid-feedback">
                                    Please enter a valid email address.
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
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
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html> 