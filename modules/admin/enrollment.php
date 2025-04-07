<?php
/**
 * Student Enrollment
 * 
 * This file handles the student enrollment process
 */

$pageTitle = "Student Enrollment";
require_once '../../includes/header.php';
requireAuth('admin');

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enroll_student'])) {
        // Sanitize input
        $studentId = (int)$_POST['student_id'];
        $classId = (int)$_POST['class_id'];
        $academicYear = sanitizeInput($_POST['academic_year']);
        $enrollmentDate = sanitizeInput($_POST['enrollment_date']);
        $status = sanitizeInput($_POST['status']);
        
        // Validate input
        if (empty($studentId) || empty($classId)) {
            $error = "Please fill in all required fields.";
        } else {
            try {
                // Begin transaction
                startTransaction();
                
                // Check if student is already enrolled in this class
                $checkSql = "SELECT id FROM enrollments 
                            WHERE student_id = ? AND class_id = ? AND academic_year = ?";
                $checkResult = executeQuery($checkSql, [$studentId, $classId, $academicYear]);
                
                if ($checkResult) {
                    throw new Exception("Student is already enrolled in this class for the selected academic year.");
                }
                
                // Insert enrollment data
                $sql = "INSERT INTO enrollments (student_id, class_id, academic_year, enrollment_date, status) 
                        VALUES (?, ?, ?, ?, ?)";
                $params = [$studentId, $classId, $academicYear, $enrollmentDate, $status];
                executeQuery($sql, $params);
                
                // Log activity
                logActivity($currentUser['id'], 'Enrolled student ID: ' . $studentId . ' in class ID: ' . $classId);
                
                // Commit transaction
                commitTransaction();
                
                $success = "Student enrolled successfully.";
            } catch (Exception $e) {
                // Rollback transaction on error
                rollbackTransaction();
                $error = "Error enrolling student: " . $e->getMessage();
            }
        }
    }
}

// Fetch all enrollments with student and class details
$enrollments = executeQuery("
    SELECT e.*, 
           s.first_name, s.last_name, s.student_id as student_number,
           c.class_name, c.grade_level, c.section,
           t.first_name as teacher_first_name, t.last_name as teacher_last_name
    FROM enrollments e
    JOIN student_profiles s ON e.student_id = s.user_id
    JOIN classes c ON e.class_id = c.id
    LEFT JOIN teacher_profiles t ON c.teacher_id = t.user_id
    ORDER BY e.academic_year DESC, c.grade_level, c.class_name, s.last_name, s.first_name
");

// Initialize $enrollments as an empty array if the query returns false
if (!$enrollments) {
    $enrollments = [];
}

// Fetch students for dropdown
$students = executeQuery("
    SELECT u.id, s.first_name, s.last_name, s.student_id 
    FROM users u 
    JOIN student_profiles s ON u.id = s.user_id 
    WHERE u.role = 'student'
    ORDER BY s.last_name, s.first_name
");

// Fetch classes for dropdown
$classes = executeQuery("
    SELECT c.*, t.first_name, t.last_name 
    FROM classes c
    LEFT JOIN teacher_profiles t ON c.teacher_id = t.user_id
    ORDER BY c.grade_level, c.class_name
");
?>

<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title mb-0">Student Enrollment</h3>
            <ul class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active">Enrollment</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-secondary me-2">
                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEnrollmentModal">
                <i class="fas fa-plus"></i> New Enrollment
            </button>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Student Enrollment</h5>
                    
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Teacher</th>
                                    <th>Academic Year</th>
                                    <th>Enrollment Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrollments as $enrollment): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($enrollment['last_name'] . ', ' . $enrollment['first_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($enrollment['student_number']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($enrollment['class_name']); ?>
                                            <br>
                                            <small class="text-muted">Grade <?php echo $enrollment['grade_level']; ?> - <?php echo htmlspecialchars($enrollment['section']); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($enrollment['teacher_first_name'] && $enrollment['teacher_last_name']) {
                                                echo htmlspecialchars($enrollment['teacher_first_name'] . ' ' . $enrollment['teacher_last_name']);
                                            } else {
                                                echo 'Not Assigned';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($enrollment['academic_year']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($enrollment['enrollment_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $enrollment['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($enrollment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="enrollment_details.php?id=<?php echo $enrollment['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_enrollment.php?id=<?php echo $enrollment['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $enrollment['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enrollment Modal -->
<div class="modal fade" id="enrollModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Student Enrollment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="student_id" class="form-label">Student</label>
                        <select class="form-select" id="student_id" name="student_id" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' (' . $student['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="class_id" class="form-label">Class</label>
                        <select class="form-select" id="class_id" name="class_id" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['class_name'] . ' - Grade ' . $class['grade_level'] . ' ' . $class['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="academic_year" class="form-label">Academic Year</label>
                        <input type="text" class="form-control" id="academic_year" name="academic_year" 
                               value="<?php echo date('Y'); ?>-<?php echo date('Y') + 1; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="enrollment_date" class="form-label">Enrollment Date</label>
                        <input type="date" class="form-control" id="enrollment_date" name="enrollment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="enroll_student" class="btn btn-primary">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this enrollment? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteButton" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(enrollmentId) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    const deleteButton = document.getElementById('deleteButton');
    deleteButton.href = `delete_enrollment.php?id=${enrollmentId}`;
    modal.show();
}
</script>

<?php require_once '../../includes/footer.php'; ?> 