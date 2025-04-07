<?php
/**
 * Student Management
 */

// Set page title
$pageTitle = 'Student Management';

// Include header
require_once '../../includes/header.php';

// Check if user is logged in and has admin role
requireAuth('admin');

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Load student data for edit or view
$currentStudent = null;
if (($action === 'edit' || $action === 'view') && $studentId > 0) {
    $currentStudent = executeQuery("
        SELECT sp.*, u.username, u.email, u.status, c.name as class_name
        FROM student_profiles sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN classes c ON sp.class_id = c.id
        WHERE sp.id = ?
    ", [$studentId]);
    
    if ($currentStudent) {
        $currentStudent = $currentStudent[0];
    } else {
        $error = 'Student not found.';
        $action = 'list';
    }
}

// Handle form submission for adding student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    // Get form data
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $classId = (int)$_POST['class_id']; // Convert to integer
    $admissionNumber = sanitize($_POST['admission_number']);
    $dateOfBirth = sanitize($_POST['date_of_birth']);
    $gender = sanitize($_POST['gender']);
    $parentName = sanitize($_POST['parent_name']);
    $parentPhone = sanitize($_POST['parent_phone']);
    $parentEmail = sanitize($_POST['parent_email']);
    
    // Debug values
    error_log("Adding student - Class ID: " . $classId);
    
    // Validate input
    if (empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password)) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            // Start transaction
            startTransaction();
            
            // Create user account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'student', 'active')";
            $userId = executeNonQuery($sql, [$username, $email, $hashedPassword]);
            
            error_log("Created user with ID: " . $userId);
            
            // Create student profile
            $sql = "INSERT INTO student_profiles (user_id, admission_number, first_name, last_name, 
                    date_of_birth, gender, parent_name, parent_phone, parent_email, class_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $studentId = executeNonQuery($sql, [$userId, $admissionNumber, $firstName, $lastName, 
                                 $dateOfBirth, $gender, $parentName, $parentPhone, 
                                 $parentEmail, $classId]);
            
            error_log("Created student profile with ID: " . $studentId);
            
            // Log activity
            logActivity($currentUser['id'], 'Added new student', "Added student: $firstName $lastName");
            
            // Commit transaction
            commitTransaction();
            
            $success = 'Student added successfully';
            $action = 'list'; // Switch to list view
        } catch (Exception $e) {
            // Rollback transaction on error
            rollbackTransaction();
            $error = 'Error adding student: ' . $e->getMessage();
            error_log("Error adding student: " . $e->getMessage());
        }
    }
}

// Handle form submission for editing student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    // Get form data
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $classId = (int)$_POST['class_id'];
    $admissionNumber = sanitize($_POST['admission_number']);
    $dateOfBirth = sanitize($_POST['date_of_birth']);
    $gender = sanitize($_POST['gender']);
    $parentName = sanitize($_POST['parent_name']);
    $parentPhone = sanitize($_POST['parent_phone']);
    $parentEmail = sanitize($_POST['parent_email']);
    $status = sanitize($_POST['status']);
    
    // Validate input
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            // Start transaction
            startTransaction();
            
            // Update user data
            $sql = "UPDATE users SET email = ?, status = ? WHERE id = ?";
            executeNonQuery($sql, [$email, $status, $currentStudent['user_id']]);
            
            // Update student profile
            $sql = "UPDATE student_profiles SET
                    first_name = ?, last_name = ?, admission_number = ?,
                    date_of_birth = ?, gender = ?, parent_name = ?,
                    parent_phone = ?, parent_email = ?, class_id = ?
                    WHERE id = ?";
            executeNonQuery($sql, [
                $firstName, $lastName, $admissionNumber,
                $dateOfBirth, $gender, $parentName,
                $parentPhone, $parentEmail, $classId,
                $studentId
            ]);
            
            // Log activity
            logActivity($currentUser['id'], 'Updated student', "Updated student: $firstName $lastName");
            
            // Commit transaction
            commitTransaction();
            
            $success = 'Student updated successfully';
            
            // Reload the student data
            $currentStudent = executeQuery("
                SELECT sp.*, u.username, u.email, u.status, c.name as class_name
                FROM student_profiles sp
                JOIN users u ON sp.user_id = u.id
                LEFT JOIN classes c ON sp.class_id = c.id
                WHERE sp.id = ?
            ", [$studentId]);
            
            if ($currentStudent) {
                $currentStudent = $currentStudent[0];
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            rollbackTransaction();
            $error = 'Error updating student: ' . $e->getMessage();
        }
    }
}

// Handle student deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $deleteStudentId = (int)$_POST['student_id'];
    
    if ($deleteStudentId > 0) {
        try {
            // Start transaction
            startTransaction();
            
            // Get student info for logging
            $student = executeSingleQuery(
                "SELECT sp.first_name, sp.last_name, sp.user_id FROM student_profiles sp WHERE id = ?", 
                [$deleteStudentId]
            );
            
            if ($student) {
                // Delete the user (will cascade to student_profile)
                executeNonQuery("DELETE FROM users WHERE id = ?", [$student['user_id']]);
                
                // Log activity
                logActivity(
                    $currentUser['id'], 
                    'Deleted student', 
                    "Deleted student: {$student['first_name']} {$student['last_name']}"
                );
                
                $success = 'Student deleted successfully';
            } else {
                throw new Exception('Student not found');
            }
            
            // Commit transaction
            commitTransaction();
        } catch (Exception $e) {
            // Rollback transaction on error
            rollbackTransaction();
            $error = 'Error deleting student: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid student ID';
    }
    
    $action = 'list';
}

// Get all classes for dropdown
$classes = executeQuery("SELECT id, name, description FROM classes ORDER BY name");

// Initialize classes as empty array if query returns false
if (!$classes) {
    $classes = [];
}

// Process classes to extract grade and section from description
foreach ($classes as &$class) {
    $description = $class['description'] ?? '';
    $gradeLevel = '';
    $section = '';
    
    // Extract grade level and section from description if possible
    if (preg_match('/Grade\s+(\d+),\s+Section\s+([^,]+)/i', $description, $matches)) {
        $gradeLevel = $matches[1];
        $section = $matches[2];
    }
    
    $class['grade_level'] = $gradeLevel;
    $class['section'] = $section;
    $class['class_name'] = $class['name']; // For backward compatibility
}
unset($class); // Break the reference

// Get all students for listing
$sql = "
    SELECT sp.*, c.name as class_name, u.username, u.email, u.status
    FROM student_profiles sp
    JOIN users u ON sp.user_id = u.id
    LEFT JOIN classes c ON sp.class_id = c.id
    ORDER BY sp.first_name, sp.last_name
";
error_log("Students query: " . $sql);
$students = executeQuery($sql);

// For debugging - log last query error
if (!$students) {
    error_log("Error fetching students: " . getLastQueryError());
} else {
    error_log("Found " . count($students) . " students");
}

// Initialize students as empty array if query returns false
if (!$students) {
    $students = [];
}
?>

<!-- Page Header -->
<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title mb-0">Student Management</h3>
            <ul class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active">Students</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-secondary me-2">
                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
            </a>
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Student
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

<!-- Add Student Form -->
<?php if ($action === 'add'): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Add New Student</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="admission_number" class="form-label">Admission Number *</label>
                            <input type="text" class="form-control" id="admission_number" name="admission_number" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="class_id" class="form-label">Class *</label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php if(is_array($classes) && count($classes) > 0): ?>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['class_name'] . ' - Grade ' . $class['grade_level'] . ' ' . $class['section']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No classes available</option>
                                <?php endif; ?>
                            </select>
                            <?php if(!is_array($classes) || count($classes) == 0): ?>
                                <div class="mt-2 text-danger">
                                    <small>* Please create classes first in the Classes section before adding students.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="parent_name" class="form-label">Parent/Guardian Name</label>
                            <input type="text" class="form-control" id="parent_name" name="parent_name">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="parent_phone" class="form-label">Parent/Guardian Phone</label>
                            <input type="tel" class="form-control" id="parent_phone" name="parent_phone">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="parent_email" class="form-label">Parent/Guardian Email</label>
                            <input type="email" class="form-control" id="parent_email" name="parent_email">
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="?action=list" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Student</button>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($action === 'edit'): ?>
    <!-- Edit Student Form -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Edit Student</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($currentStudent['first_name']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($currentStudent['last_name']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($currentStudent['email']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo $currentStudent['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $currentStudent['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentStudent['username']); ?>" readonly>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="admission_number" class="form-label">Admission Number *</label>
                            <input type="text" class="form-control" id="admission_number" name="admission_number" value="<?php echo htmlspecialchars($currentStudent['admission_number']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="class_id" class="form-label">Class *</label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php if(is_array($classes) && count($classes) > 0): ?>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php echo $currentStudent['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No classes available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo $currentStudent['date_of_birth']; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $currentStudent['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $currentStudent['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $currentStudent['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="parent_name" class="form-label">Parent/Guardian Name</label>
                            <input type="text" class="form-control" id="parent_name" name="parent_name" value="<?php echo htmlspecialchars($currentStudent['parent_name'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="parent_phone" class="form-label">Parent/Guardian Phone</label>
                            <input type="tel" class="form-control" id="parent_phone" name="parent_phone" value="<?php echo htmlspecialchars($currentStudent['parent_phone'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="parent_email" class="form-label">Parent/Guardian Email</label>
                            <input type="email" class="form-control" id="parent_email" name="parent_email" value="<?php echo htmlspecialchars($currentStudent['parent_email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="?action=list" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Student</button>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($action === 'view'): ?>
    <!-- View Student Details -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title">Student Details</h5>
            <div>
                <a href="?action=edit&id=<?php echo $currentStudent['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="?action=list" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <!-- Student basic info card -->
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <?php if (!empty($currentStudent['profile_image'])): ?>
                                    <img src="<?php echo APP_URL . '/uploads/profile_images/' . $currentStudent['profile_image']; ?>" alt="Profile Image" class="rounded-circle img-fluid" style="width: 150px; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;">
                                        <i class="fas fa-user fa-4x text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h4><?php echo htmlspecialchars($currentStudent['first_name'] . ' ' . $currentStudent['last_name']); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($currentStudent['admission_number']); ?></p>
                            <span class="badge bg-<?php echo $currentStudent['status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($currentStudent['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <!-- Student details tabs -->
                    <ul class="nav nav-tabs" id="studentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">Personal Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button" role="tab">Academic Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="parents-tab" data-bs-toggle="tab" data-bs-target="#parents" type="button" role="tab">Parent/Guardian Details</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3 border border-top-0 rounded-bottom" id="studentTabsContent">
                        <!-- Personal Details Tab -->
                        <div class="tab-pane fade show active" id="details" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Username:</strong></p>
                                    <p><?php echo htmlspecialchars($currentStudent['username']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Email:</strong></p>
                                    <p><?php echo htmlspecialchars($currentStudent['email']); ?></p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Date of Birth:</strong></p>
                                    <p><?php echo !empty($currentStudent['date_of_birth']) ? date('M d, Y', strtotime($currentStudent['date_of_birth'])) : 'Not set'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Gender:</strong></p>
                                    <p><?php echo !empty($currentStudent['gender']) ? ucfirst($currentStudent['gender']) : 'Not set'; ?></p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Phone:</strong></p>
                                    <p><?php echo !empty($currentStudent['phone']) ? htmlspecialchars($currentStudent['phone']) : 'Not set'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Address:</strong></p>
                                    <p><?php echo !empty($currentStudent['current_address']) ? htmlspecialchars($currentStudent['current_address']) : 'Not set'; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Academic Details Tab -->
                        <div class="tab-pane fade" id="academic" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Current Class:</strong></p>
                                    <p><?php echo htmlspecialchars($currentStudent['class_name'] ?? 'Not Assigned'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Roll Number:</strong></p>
                                    <p><?php echo !empty($currentStudent['roll_number']) ? htmlspecialchars($currentStudent['roll_number']) : 'Not set'; ?></p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Admission Date:</strong></p>
                                    <p><?php echo !empty($currentStudent['admission_date']) ? date('M d, Y', strtotime($currentStudent['admission_date'])) : 'Not set'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Section:</strong></p>
                                    <p><?php echo !empty($currentStudent['section']) ? htmlspecialchars($currentStudent['section']) : 'Not set'; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Parent/Guardian Details Tab -->
                        <div class="tab-pane fade" id="parents" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Parent/Guardian Name:</strong></p>
                                    <p><?php echo !empty($currentStudent['parent_name']) ? htmlspecialchars($currentStudent['parent_name']) : 'Not set'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Parent/Guardian Phone:</strong></p>
                                    <p><?php echo !empty($currentStudent['parent_phone']) ? htmlspecialchars($currentStudent['parent_phone']) : 'Not set'; ?></p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Parent/Guardian Email:</strong></p>
                                    <p><?php echo !empty($currentStudent['parent_email']) ? htmlspecialchars($currentStudent['parent_email']) : 'Not set'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Students List -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Students List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Admission No.</th>
                            <th>Class</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(is_array($students) && count($students) > 0): ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($student['email']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['parent_phone']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?action=edit&id=<?php echo $student['id']; ?>" 
                                               class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=view&id=<?php echo $student['id']; ?>" 
                                               class="btn btn-outline-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteStudent(<?php echo $student['id']; ?>)" 
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No students found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Delete Student Modal -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this student? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="?action=delete" style="display: inline;">
                    <input type="hidden" name="student_id" id="deleteStudentId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteStudent(studentId) {
    document.getElementById('deleteStudentId').value = studentId;
    new bootstrap.Modal(document.getElementById('deleteStudentModal')).show();
}
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 