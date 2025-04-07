<?php
/**
 * Teacher Profile
 * 
 * This file manages the teacher profile view and edit functionality
 */

// Set page title
$pageTitle = 'My Profile';

// Include header
require_once '../../includes/header.php';

// Check if user is logged in and has teacher role
requireAuth('teacher');

// Initialize variables
$error = '';
$success = '';

// Get the current user
$currentUser = getCurrentUser();
$userProfile = getUserProfile($currentUser['id'], 'teacher');

// If profile doesn't exist, create a basic one
if (!$userProfile) {
    $sql = "INSERT INTO teacher_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)";
    executeNonQuery($sql, [$currentUser['id'], '', '']);
    $userProfile = getUserProfile($currentUser['id'], 'teacher');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Sanitize input
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $bio = sanitize($_POST['bio']);
    $qualification = sanitize($_POST['qualification']);
    
    // Validate input
    if (empty($firstName) || empty($lastName)) {
        $error = 'First name and last name are required.';
    } else {
        // Begin transaction
        startTransaction();
        
        try {
            // Update teacher profile
            $sql = "UPDATE teacher_profiles SET first_name = ?, last_name = ?, phone = ?, address = ?, bio = ?, qualification = ? WHERE user_id = ?";
            executeNonQuery($sql, [$firstName, $lastName, $phone, $address, $bio, $qualification, $currentUser['id']]);
            
            // Handle profile image upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../uploads/profile_images/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = $currentUser['id'] . '_' . time() . '_' . basename($_FILES['profile_image']['name']);
                $uploadFile = $uploadDir . $fileName;
                
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                
                if (in_array($_FILES['profile_image']['type'], $allowedTypes)) {
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadFile)) {
                        // Update profile image in database
                        $sql = "UPDATE teacher_profiles SET profile_image = ? WHERE user_id = ?";
                        executeNonQuery($sql, [$fileName, $currentUser['id']]);
                    } else {
                        throw new Exception('Failed to upload profile image.');
                    }
                } else {
                    throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
                }
            }
            
            // Update password if provided
            if (!empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
                if ($_POST['new_password'] !== $_POST['confirm_password']) {
                    throw new Exception('Passwords do not match.');
                }
                
                if (strlen($_POST['new_password']) < 6) {
                    throw new Exception('Password must be at least 6 characters long.');
                }
                
                $hashedPassword = hashPassword($_POST['new_password']);
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                executeNonQuery($sql, [$hashedPassword, $currentUser['id']]);
            }
            
            // Log activity
            logActivity($currentUser['id'], 'Updated profile information', 'Teacher profile update');
            
            // Commit transaction
            commitTransaction();
            
            // Set success message
            $success = 'Profile updated successfully.';
            
            // Reload profile data
            $userProfile = getUserProfile($currentUser['id'], 'teacher');
        } catch (Exception $e) {
            // Rollback transaction
            rollbackTransaction();
            $error = $e->getMessage();
        }
    }
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">My Profile</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Teacher</a></li>
                <li class="breadcrumb-item active">Profile</li>
            </ul>
        </div>
        <div class="col-auto">
            
        </div>
    </div>
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

<div class="row">
    <!-- Profile Information -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-4">
                    <?php if (!empty($userProfile['profile_image'])): ?>
                        <img src="<?php echo APP_URL; ?>/uploads/profile_images/<?php echo htmlspecialchars($userProfile['profile_image']); ?>" alt="Profile Image" class="img-fluid rounded-circle profile-image" style="width: 150px; height: 150px; object-fit: cover;">
                    <?php else: ?>
                        <img src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/icons/person-circle.svg" alt="Default Profile" class="img-fluid rounded-circle profile-image" style="width: 150px; height: 150px; object-fit: cover; background-color: #f8f9fa; padding: 10px;">
                    <?php endif; ?>
                </div>
                
                <h4 class="text-center"><?php echo htmlspecialchars($userProfile['first_name'] . ' ' . $userProfile['last_name']); ?></h4>
                <p class="text-center text-muted mb-0">Teacher</p>
                
                <hr>
                
                <div class="profile-details">
                    <div class="detail-item">
                        <div class="detail-label">Username</div>
                        <div class="detail-value"><?php echo htmlspecialchars($currentUser['username']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Phone</div>
                        <div class="detail-value"><?php echo !empty($userProfile['phone']) ? htmlspecialchars($userProfile['phone']) : 'Not provided'; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Qualification</div>
                        <div class="detail-value"><?php echo !empty($userProfile['qualification']) ? htmlspecialchars($userProfile['qualification']) : 'Not provided'; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Last Login</div>
                        <div class="detail-value"><?php echo $currentUser['last_login'] ? date('M d, Y h:i A', strtotime($currentUser['last_login'])) : 'Never'; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <span class="badge bg-success"><?php echo ucfirst(htmlspecialchars($currentUser['status'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Form -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Edit Profile</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userProfile['first_name']); ?>" required>
                            <div class="invalid-feedback">First name is required.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userProfile['last_name']); ?>" required>
                            <div class="invalid-feedback">Last name is required.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($userProfile['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="qualification" class="form-label">Qualification</label>
                        <input type="text" class="form-control" id="qualification" name="qualification" value="<?php echo htmlspecialchars($userProfile['qualification'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($userProfile['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">About Me / Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($userProfile['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="profile_image" class="form-label">Profile Image</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image">
                        <small class="form-text text-muted">Allowed formats: JPG, PNG, GIF. Max size: 2MB.</small>
                    </div>
                    
                    <hr>
                    
                    <h5 class="mb-3">Change Password</h5>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                        <small class="form-text text-muted">Leave blank to keep current password.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 