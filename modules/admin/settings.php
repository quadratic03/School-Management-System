<?php
/**
 * System Settings
 */

// Set page title
$pageTitle = 'Settings';

// Include header
require_once '../../includes/header.php';

// Check if user is logged in and has admin role
requireAuth('admin');

// Initialize variables
$error = '';
$success = '';

// Get current settings
$settings = executeQuery("SELECT * FROM settings ORDER BY setting_key");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        startTransaction();
        
        // Update each setting
        foreach ($_POST['settings'] as $key => $value) {
            $sql = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
            executeNonQuery($sql, [$value, $key]);
        }
        
        // Log activity
        logActivity($currentUser['id'], 'Updated system settings', 'Modified system configuration');
        
        // Commit transaction
        commitTransaction();
        
        $success = 'Settings updated successfully';
        
        // Refresh settings
        $settings = executeQuery("SELECT * FROM settings ORDER BY setting_key");
    } catch (Exception $e) {
        // Rollback transaction on error
        rollbackTransaction();
        $error = 'Error updating settings: ' . $e->getMessage();
    }
}
?>

<!-- Page Header -->
<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title mb-0">System Settings</h3>
            <ul class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Settings Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Configure System Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <!-- School Information -->
            <div class="mb-4">
                <h5 class="border-bottom pb-2">School Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="school_name" class="form-label">School Name</label>
                            <input type="text" class="form-control" id="school_name" 
                                   name="settings[school_name]" 
                                   value="<?php echo htmlspecialchars(getSettingValue($settings, 'school_name')); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <input type="text" class="form-control" id="academic_year" 
                                   name="settings[academic_year]" 
                                   value="<?php echo htmlspecialchars(getSettingValue($settings, 'academic_year')); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="school_address" class="form-label">School Address</label>
                            <textarea class="form-control" id="school_address" 
                                      name="settings[school_address]" rows="2"><?php 
                                echo htmlspecialchars(getSettingValue($settings, 'school_address')); 
                            ?></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="school_phone" class="form-label">School Phone</label>
                            <input type="tel" class="form-control" id="school_phone" 
                                   name="settings[school_phone]" 
                                   value="<?php echo htmlspecialchars(getSettingValue($settings, 'school_phone')); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="school_email" class="form-label">School Email</label>
                            <input type="email" class="form-control" id="school_email" 
                                   name="settings[school_email]" 
                                   value="<?php echo htmlspecialchars(getSettingValue($settings, 'school_email')); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Configuration -->
            <div class="mb-4">
                <h5 class="border-bottom pb-2">System Configuration</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="system_timezone" class="form-label">System Timezone</label>
                            <select class="form-select" id="system_timezone" 
                                    name="settings[system_timezone]">
                                <?php
                                $timezones = DateTimeZone::listIdentifiers();
                                $currentTimezone = getSettingValue($settings, 'system_timezone');
                                foreach ($timezones as $timezone) {
                                    $selected = $timezone === $currentTimezone ? 'selected' : '';
                                    echo "<option value=\"$timezone\" $selected>$timezone</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="grading_system" class="form-label">Grading System</label>
                            <select class="form-select" id="grading_system" 
                                    name="settings[grading_system]">
                                <option value="letter" <?php echo getSettingValue($settings, 'grading_system') === 'letter' ? 'selected' : ''; ?>>
                                    Letter Grades (A, B, C, D, F)
                                </option>
                                <option value="percentage" <?php echo getSettingValue($settings, 'grading_system') === 'percentage' ? 'selected' : ''; ?>>
                                    Percentage (0-100)
                                </option>
                                <option value="gpa" <?php echo getSettingValue($settings, 'grading_system') === 'gpa' ? 'selected' : ''; ?>>
                                    GPA (0.0-4.0)
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="attendance_type" class="form-label">Attendance Tracking</label>
                            <select class="form-select" id="attendance_type" 
                                    name="settings[attendance_type]">
                                <option value="daily" <?php echo getSettingValue($settings, 'attendance_type') === 'daily' ? 'selected' : ''; ?>>
                                    Daily Attendance
                                </option>
                                <option value="subject" <?php echo getSettingValue($settings, 'attendance_type') === 'subject' ? 'selected' : ''; ?>>
                                    Subject-wise Attendance
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Security Settings -->
            <div class="mb-4">
                <h5 class="border-bottom pb-2">Security Settings</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password_min_length" class="form-label">Minimum Password Length</label>
                            <input type="number" class="form-control" id="password_min_length" 
                                   name="settings[password_min_length]" min="6" max="20" 
                                   value="<?php echo htmlspecialchars(getSettingValue($settings, 'password_min_length')); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password_reset_expiry" class="form-label">Password Reset Link Expiry (hours)</label>
                            <input type="number" class="form-control" id="password_reset_expiry" 
                                   name="settings[password_reset_expiry]" min="1" max="72" 
                                   value="<?php echo htmlspecialchars(getSettingValue($settings, 'password_reset_expiry')); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Helper function to get setting value
function getSettingValue($settings, $key) {
    foreach ($settings as $setting) {
        if ($setting['setting_key'] === $key) {
            return $setting['setting_value'];
        }
    }
    return '';
}

// Include footer
require_once '../../includes/footer.php';
?> 