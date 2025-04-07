<?php
/**
 * Teacher Management
 */

// Set page title
$pageTitle = 'Teacher Management';

// Include header
require_once '../../includes/header.php';

// Check if user is logged in and has admin role
requireAuth('admin');

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$teacherId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Load teacher data for edit or view
$currentTeacher = null;
if (($action === 'edit' || $action === 'view') && $teacherId > 0) {
    $currentTeacher = executeQuery("
        SELECT tp.*, u.username, u.email, u.status
        FROM teacher_profiles tp
        JOIN users u ON tp.user_id = u.id
        WHERE tp.id = ?
    ", [$teacherId]);
    
    if ($currentTeacher) {
        $currentTeacher = $currentTeacher[0];
    } else {
        $error = 'Teacher not found.';
        $action = 'list';
    }
}

// Handle form submission for adding teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    // Get form data
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $employeeId = sanitize($_POST['employee_id']);
    $department = sanitize($_POST['department']);
    $qualification = sanitize($_POST['qualification']);
    $experience = sanitize($_POST['experience']);
    $joiningDate = sanitize($_POST['joining_date']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    
    // Validate input
    if (empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password)) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            // Start transaction
            startTransaction();
            
            // Create user account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'teacher')";
            $userId = executeNonQuery($sql, [$username, $email, $hashedPassword]);
            
            // Create teacher profile
            $sql = "INSERT INTO teacher_profiles (user_id, employee_id, first_name, last_name, 
                    department, qualification, experience, joining_date, phone, address) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            executeNonQuery($sql, [$userId, $employeeId, $firstName, $lastName, 
                                 $department, $qualification, $experience, $joiningDate, 
                                 $phone, $address]);
            
            // Log activity
            logActivity($userId, 'Added new teacher', "Added teacher: $firstName $lastName");
            
            // Commit transaction
            commitTransaction();
            
            $success = 'Teacher added successfully';
            $action = 'list'; // Switch to list view
        } catch (Exception $e) {
            // Rollback transaction on error
            rollbackTransaction();
            $error = 'Error adding teacher: ' . $e->getMessage();
        }
    }
}

// Handle form submission for editing teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    // Get form data
    $firstName = sanitize($_POST['first_name']);
    $lastName = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $employeeId = sanitize($_POST['employee_id']);
    $department = sanitize($_POST['department']);
    $qualification = sanitize($_POST['qualification']);
    $experience = sanitize($_POST['experience']);
    $joiningDate = sanitize($_POST['joining_date']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
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
            executeNonQuery($sql, [$email, $status, $currentTeacher['user_id']]);
            
            // Update teacher profile
            $sql = "UPDATE teacher_profiles SET
                    first_name = ?, last_name = ?, employee_id = ?,
                    department = ?, qualification = ?, experience = ?,
                    joining_date = ?, phone = ?, address = ?
                    WHERE id = ?";
            executeNonQuery($sql, [
                $firstName, $lastName, $employeeId,
                $department, $qualification, $experience,
                $joiningDate, $phone, $address,
                $teacherId
            ]);
            
            // Log activity
            logActivity($currentUser['id'], 'Updated teacher', "Updated teacher: $firstName $lastName");
            
            // Commit transaction
            commitTransaction();
            
            $success = 'Teacher updated successfully';
            
            // Reload the teacher data
            $currentTeacher = executeQuery("
                SELECT tp.*, u.username, u.email, u.status
                FROM teacher_profiles tp
                JOIN users u ON tp.user_id = u.id
                WHERE tp.id = ?
            ", [$teacherId]);
            
            if ($currentTeacher) {
                $currentTeacher = $currentTeacher[0];
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            rollbackTransaction();
            $error = 'Error updating teacher: ' . $e->getMessage();
        }
    }
}

// Handle teacher deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $deleteTeacherId = (int)$_POST['teacher_id'];
    
    if ($deleteTeacherId > 0) {
        try {
            // Start transaction
            startTransaction();
            
            // Get teacher info for logging
            $teacher = executeSingleQuery(
                "SELECT tp.first_name, tp.last_name, tp.user_id FROM teacher_profiles tp WHERE id = ?", 
                [$deleteTeacherId]
            );
            
            if ($teacher) {
                // Check if teacher has associated classes
                $classCount = executeSingleQuery(
                    "SELECT COUNT(*) as count FROM class_subjects WHERE teacher_id = ?", 
                    [$deleteTeacherId]
                );
                
                if ($classCount && $classCount['count'] > 0) {
                    throw new Exception('Cannot delete teacher with assigned classes. Please reassign classes first.');
                }
                
                // Delete the user (will cascade to teacher_profile)
                executeNonQuery("DELETE FROM users WHERE id = ?", [$teacher['user_id']]);
                
                // Log activity
                logActivity(
                    $currentUser['id'], 
                    'Deleted teacher', 
                    "Deleted teacher: {$teacher['first_name']} {$teacher['last_name']}"
                );
                
                $success = 'Teacher deleted successfully';
            } else {
                throw new Exception('Teacher not found');
            }
            
            // Commit transaction
            commitTransaction();
        } catch (Exception $e) {
            // Rollback transaction on error
            rollbackTransaction();
            $error = 'Error deleting teacher: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid teacher ID';
    }
    
    $action = 'list';
}

// Get all teachers for listing
$teachers = executeQuery("
    SELECT tp.*, u.username, u.email, u.status,
           (SELECT COUNT(*) FROM class_subjects WHERE teacher_id = tp.id) as total_classes
    FROM teacher_profiles tp
    JOIN users u ON tp.user_id = u.id
    ORDER BY tp.first_name, tp.last_name
");
?>

<!-- Page Header -->
<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title mb-0">Teacher Management</h3>
            <ul class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active">Teachers</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-secondary me-2">
                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
            </a>
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Teacher
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

<!-- Add Teacher Form -->
<?php if ($action === 'add'): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Add New Teacher</h5>
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
                            <label for="employee_id" class="form-label">Employee ID *</label>
                            <input type="text" class="form-control" id="employee_id" name="employee_id" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="department" class="form-label">Department *</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="Science">Science</option>
                                <option value="English">English</option>
                                <option value="History">History</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Physical Education">Physical Education</option>
                                <option value="Arts">Arts</option>
                                <option value="Music">Music</option>
                                <option value="Languages">Languages</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="qualification" class="form-label">Qualification *</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="experience" class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" id="experience" name="experience" min="0">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="joining_date" class="form-label">Joining Date</label>
                            <input type="date" class="form-control" id="joining_date" name="joining_date">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="1"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="?action=list" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Teacher</button>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($action === 'edit'): ?>
    <!-- Edit Teacher Form -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Edit Teacher</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($currentTeacher['first_name']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($currentTeacher['last_name']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($currentTeacher['email']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo $currentTeacher['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $currentTeacher['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentTeacher['username']); ?>" readonly>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Employee ID *</label>
                            <input type="text" class="form-control" id="employee_id" name="employee_id" value="<?php echo htmlspecialchars($currentTeacher['employee_id']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="department" class="form-label">Department *</label>
                            <select class="form-select" id="department" name="department" required>
                                <option value="">Select Department</option>
                                <option value="Mathematics" <?php echo $currentTeacher['department'] === 'Mathematics' ? 'selected' : ''; ?>>Mathematics</option>
                                <option value="Science" <?php echo $currentTeacher['department'] === 'Science' ? 'selected' : ''; ?>>Science</option>
                                <option value="English" <?php echo $currentTeacher['department'] === 'English' ? 'selected' : ''; ?>>English</option>
                                <option value="History" <?php echo $currentTeacher['department'] === 'History' ? 'selected' : ''; ?>>History</option>
                                <option value="Computer Science" <?php echo $currentTeacher['department'] === 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Physical Education" <?php echo $currentTeacher['department'] === 'Physical Education' ? 'selected' : ''; ?>>Physical Education</option>
                                <option value="Arts" <?php echo $currentTeacher['department'] === 'Arts' ? 'selected' : ''; ?>>Arts</option>
                                <option value="Music" <?php echo $currentTeacher['department'] === 'Music' ? 'selected' : ''; ?>>Music</option>
                                <option value="Languages" <?php echo $currentTeacher['department'] === 'Languages' ? 'selected' : ''; ?>>Languages</option>
                                <option value="Other" <?php echo $currentTeacher['department'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="qualification" class="form-label">Qualification *</label>
                            <input type="text" class="form-control" id="qualification" name="qualification" value="<?php echo htmlspecialchars($currentTeacher['qualification']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="experience" class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" id="experience" name="experience" min="0" value="<?php echo htmlspecialchars($currentTeacher['experience']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="joining_date" class="form-label">Joining Date</label>
                            <input type="date" class="form-control" id="joining_date" name="joining_date" value="<?php echo $currentTeacher['joining_date']; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($currentTeacher['phone']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($currentTeacher['address']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="text-end">
                    <a href="?action=list" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Teacher</button>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($action === 'view'): ?>
    <!-- View Teacher Details -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title">Teacher Details</h5>
            <div>
                <a href="?action=edit&id=<?php echo $currentTeacher['id']; ?>" class="btn btn-primary">
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
                    <!-- Teacher basic info card -->
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <?php if (!empty($currentTeacher['profile_image'])): ?>
                                    <img src="<?php echo APP_URL . '/uploads/profile_images/' . $currentTeacher['profile_image']; ?>" alt="Profile Image" class="rounded-circle img-fluid" style="width: 150px; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;">
                                        <i class="fas fa-user-tie fa-4x text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h4><?php echo htmlspecialchars($currentTeacher['first_name'] . ' ' . $currentTeacher['last_name']); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($currentTeacher['department']); ?></p>
                            <p><span class="badge bg-primary"><?php echo htmlspecialchars($currentTeacher['employee_id']); ?></span></p>
                            <span class="badge bg-<?php echo $currentTeacher['status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($currentTeacher['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <!-- Teacher details tabs -->
                    <ul class="nav nav-tabs" id="teacherTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">Personal Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button" role="tab">Professional Details</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="classes-tab" data-bs-toggle="tab" data-bs-target="#classes" type="button" role="tab">Classes</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3 border border-top-0 rounded-bottom" id="teacherTabsContent">
                        <!-- Personal Details Tab -->
                        <div class="tab-pane fade show active" id="details" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Username:</strong></p>
                                    <p><?php echo htmlspecialchars($currentTeacher['username']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Email:</strong></p>
                                    <p><?php echo htmlspecialchars($currentTeacher['email']); ?></p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Phone:</strong></p>
                                    <p><?php echo !empty($currentTeacher['phone']) ? htmlspecialchars($currentTeacher['phone']) : 'Not set'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Address:</strong></p>
                                    <p><?php echo !empty($currentTeacher['address']) ? htmlspecialchars($currentTeacher['address']) : 'Not set'; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Professional Details Tab -->
                        <div class="tab-pane fade" id="academic" role="tabpanel">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Department:</strong></p>
                                    <p><?php echo htmlspecialchars($currentTeacher['department']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Qualification:</strong></p>
                                    <p><?php echo htmlspecialchars($currentTeacher['qualification']); ?></p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Years of Experience:</strong></p>
                                    <p><?php echo !empty($currentTeacher['experience']) ? htmlspecialchars($currentTeacher['experience']) : 'Not set'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Joining Date:</strong></p>
                                    <p><?php echo !empty($currentTeacher['joining_date']) ? date('M d, Y', strtotime($currentTeacher['joining_date'])) : 'Not set'; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Classes Tab -->
                        <div class="tab-pane fade" id="classes" role="tabpanel">
                            <?php
                            // Get assigned classes
                            $assignedClasses = executeQuery("
                                SELECT c.id, c.name, c.description, s.name as subject_name, s.code as subject_code
                                FROM class_subjects cs
                                JOIN classes c ON cs.class_id = c.id
                                JOIN subjects s ON cs.subject_id = s.id
                                WHERE cs.teacher_id = ?
                                ORDER BY c.name, s.name
                            ", [$teacherId]);
                            ?>
                            
                            <?php if ($assignedClasses && count($assignedClasses) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>Class</th>
                                                <th>Subject</th>
                                                <th>Code</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assignedClasses as $class): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($class['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($class['subject_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($class['subject_code']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No classes assigned to this teacher.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Teachers List -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Teachers List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Employee ID</th>
                            <th>Department</th>
                            <th>Classes</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($teacher['employee_id']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['department']); ?></td>
                                <td><?php echo number_format($teacher['total_classes']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $teacher['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($teacher['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=edit&id=<?php echo $teacher['id']; ?>" 
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?action=view&id=<?php echo $teacher['id']; ?>" 
                                           class="btn btn-outline-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteTeacher(<?php echo $teacher['id']; ?>)" 
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Delete Teacher Modal -->
<div class="modal fade" id="deleteTeacherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this teacher? This action cannot be undone.</p>
                <p class="text-warning"><strong>Note:</strong> Teachers with assigned classes cannot be deleted. Please reassign classes first.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="?action=delete" style="display: inline;">
                    <input type="hidden" name="teacher_id" id="deleteTeacherId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteTeacher(teacherId) {
    document.getElementById('deleteTeacherId').value = teacherId;
    new bootstrap.Modal(document.getElementById('deleteTeacherModal')).show();
}
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 