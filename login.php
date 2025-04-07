<?php
/**
 * Login Page
 */

// Start session
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

// Check for "Remember Me" token if user is not logged in
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $sql = "SELECT u.* FROM users u 
            JOIN user_tokens t ON u.id = t.user_id 
            WHERE t.token = ? AND t.expiry > NOW() AND u.status = 'active'";
    $user = executeSingleQuery($sql, [$token]);
    
    if ($user) {
        // Create user session
        createUserSession($user);
        
        // Regenerate token for security
        $newToken = generateToken();
        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
        
        // Update token in database
        $sql = "UPDATE user_tokens SET token = ?, expiry = ? WHERE token = ?";
        executeNonQuery($sql, [$newToken, date('Y-m-d H:i:s', $expiry), $token]);
        
        // Set new cookie
        setcookie('remember_token', $newToken, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
    }
}

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect to appropriate dashboard
    redirect(getRedirectUrl());
}

// Initialize variables
$username = '';
$error = '';
$success = '';
$role = 'admin'; // Default role

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $role = sanitize($_POST['role']); // Get the selected role
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Authenticate user with role
        $user = authenticateUserWithRole($username, $password, $role);
        
        if ($user) {
            // Create user session
            createUserSession($user);
            
            // Set remember me cookie if requested
            if ($remember) {
                $token = generateToken();
                $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Save token in database
                $sql = "INSERT INTO user_tokens (user_id, token, expiry) VALUES (?, ?, ?)";
                executeNonQuery($sql, [$user['id'], $token, date('Y-m-d H:i:s', $expiry)]);
                
                // Set cookie
                setcookie('remember_token', $token, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
            }
            
            // Redirect to appropriate dashboard
            redirect(getRedirectUrl());
        } else {
            $error = 'Invalid username, password, or role. Please try again.';
        }
    }
}

// Set page title
$pageTitle = 'Login';

// Additional inline styles to fix specific issues
$additionalStyles = "
<style>
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }
    .login-page {
        height: 100%;
        overflow: auto;
        display: flex;
        align-items: center;
    }
    .login-form {
        padding: 20px;
        margin: 0 auto;
    }
    .alert {
        margin-bottom: 1rem;
    }
</style>";
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
    
    <!-- Additional styles to fix scrolling -->
    <?php echo $additionalStyles; ?> 
</head>
<body>
    <div class="login-page">
        <div class="login-form">
            
            
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
            
            <div class="card shadow">
                <div class="card-header">
                    <h4 class="my-0 text-center">Sign In</h4>
                </div>
                <div class="card-body">
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Enter username or email" value="<?php echo htmlspecialchars($username); ?>" required>
                                <div class="invalid-feedback">
                                    Please enter your username or email.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                                <div class="invalid-feedback">
                                    Please enter your password.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Login As</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                <select class="form-select" id="role" name="role">
                                    <option value="admin" <?php echo ($role === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                    <option value="teacher" <?php echo ($role === 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                                    <option value="student" <?php echo ($role === 'student') ? 'selected' : ''; ?>>Student</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="mt-3 text-center">
                <a href="forgot-password.php" class="text-decoration-none">Forgot Password?</a>
            </div>
            
            <div class="mt-3 text-center">
                <p class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All rights reserved.</p>
                <p class="text-muted">Developed by Namoc Roberth</p>
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