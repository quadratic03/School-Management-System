<?php
/**
 * Class Management
 * 
 * This file handles the management of school classes
 */

$pageTitle = "Class Management";
require_once '../../includes/header.php';
requireAuth('admin');

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_class'])) {
        // Sanitize input
        $className = sanitize($_POST['class_name']);
        $gradeLevel = sanitize($_POST['grade_level']);
        $section = sanitize($_POST['section']);
        $capacity = (int)$_POST['capacity'];
        $teacherId = (int)$_POST['teacher_id'];
        $academicYear = sanitize($_POST['academic_year']);
        
        // Validate input
        if (empty($className) || empty($gradeLevel) || empty($section)) {
            $error = "Please fill in all required fields.";
        } else {
            try {
                // Begin transaction
                startTransaction();
                
                // Build description
                $description = "Grade " . $gradeLevel . ", Section " . $section . ", Academic Year: " . $academicYear;
                
                // Insert class data
                $sql = "INSERT INTO classes (name, description) VALUES (?, ?)";
                $params = [$className, $description];
                $classId = executeNonQuery($sql, $params);
                
                // If teacher is assigned, create class-subject relationship
                if ($teacherId) {
                    // Create a default "All Subjects" entry in the subjects table if not exists
                    $defaultSubject = executeSingleQuery("SELECT id FROM subjects WHERE name = 'All Subjects' LIMIT 1");
                    if (!$defaultSubject) {
                        $sql = "INSERT INTO subjects (name, code, description) VALUES (?, ?, ?)";
                        $subjectId = executeNonQuery($sql, ['All Subjects', 'ALL', 'Default subject for all class activities']);
                    } else {
                        $subjectId = $defaultSubject['id'];
                    }
                    
                    // Create class-subject relationship
                    $sql = "INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (?, ?, ?)";
                    executeNonQuery($sql, [$classId, $subjectId, $teacherId]);
                }
                
                // Log activity
                logActivity($currentUser['id'], 'Added new class: ' . $className);
                
                // Commit transaction
                commitTransaction();
                
                $success = "Class added successfully.";
            } catch (Exception $e) {
                // Rollback transaction on error
                rollbackTransaction();
                $error = "Error adding class: " . $e->getMessage();
            }
        }
    }
}

// Fetch all classes with teacher names
$classes = executeQuery("
    SELECT c.*, 
           (SELECT GROUP_CONCAT(CONCAT(tp.first_name, ' ', tp.last_name) SEPARATOR ', ') 
            FROM class_subjects cs 
            JOIN teacher_profiles tp ON cs.teacher_id = tp.id 
            WHERE cs.class_id = c.id) as teacher_names,
           (SELECT COUNT(*) FROM student_profiles WHERE class_id = c.id) as student_count
    FROM classes c
    ORDER BY c.name
");

// Initialize $classes as an empty array if the query returns false
if (!$classes) {
    $classes = [];
}

// Fetch teachers for dropdown
$teachers = executeQuery("
    SELECT tp.id, tp.first_name, tp.last_name 
    FROM users u 
    JOIN teacher_profiles tp ON u.id = tp.user_id 
    WHERE u.role = 'teacher' AND u.status = 'active'
    ORDER BY tp.first_name, tp.last_name
");

// Initialize $teachers as an empty array if the query returns false
if (!$teachers) {
    $teachers = [];
}
?>

<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title mb-0">Class Management</h3>
            <ul class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active">Classes</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-secondary me-2">
                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClass">
                <i class="fas fa-plus"></i> Add New Class
            </button>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Class Management</h5>
                    
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
                                    <th>Class Name</th>
                                    <th>Description</th> 
                                    <th>Teachers</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (is_array($classes) && count($classes) > 0): ?>
                                    <?php foreach ($classes as $class): ?>
                                        <?php 
                                        // Extract grade, section from description if possible
                                        $description = $class['description'];
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($class['name']); ?></td>
                                            <td><?php echo htmlspecialchars($description); ?></td>
                                            <td>
                                                <?php 
                                                if ($class['teacher_names']) {
                                                    echo htmlspecialchars($class['teacher_names']);
                                                } else {
                                                    echo 'Not Assigned';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo $class['student_count']; ?></td>
                                            <td>
                                                <a href="class_details.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_class.php?id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $class['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No classes found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="class_name" class="form-label">Class Name</label>
                        <input type="text" class="form-control" id="class_name" name="class_name" required>
                        <small class="form-text text-muted">Example: "Class 10A", "Grade 5B"</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="grade_level" class="form-label">Grade Level</label>
                                <select class="form-select" id="grade_level" name="grade_level" required>
                                    <option value="">Select Grade Level</option>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="section" class="form-label">Section</label>
                                <input type="text" class="form-control" id="section" name="section" required>
                                <small class="form-text text-muted">Example: "A", "B", "Alpha"</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="capacity" class="form-label">Capacity</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" min="1" value="30" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="academic_year" class="form-label">Academic Year</label>
                                <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                       value="<?php echo date('Y'); ?>-<?php echo date('Y') + 1; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="teacher_id" class="form-label">Class Teacher</label>
                        <select class="form-select" id="teacher_id" name="teacher_id">
                            <option value="">Select Teacher</option>
                            <?php if(is_array($teachers) && count($teachers) > 0): ?>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No teachers available</option>
                            <?php endif; ?>
                        </select>
                        <?php if(!is_array($teachers) || count($teachers) == 0): ?>
                            <div class="mt-2 text-danger">
                                <small>* Please add teachers first in the Teachers section before assigning to classes.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_class" class="btn btn-primary">Add Class</button>
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
                Are you sure you want to delete this class? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteButton" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(classId) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    const deleteButton = document.getElementById('deleteButton');
    deleteButton.href = `delete_class.php?id=${classId}`;
    modal.show();
}
</script>

<?php require_once '../../includes/footer.php'; ?> 