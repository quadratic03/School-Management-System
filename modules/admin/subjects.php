<?php
/**
 * Subject Management
 * 
 * This file handles the management of school subjects
 */

$pageTitle = "Subject Management";
require_once '../../includes/header.php';
requireAuth('admin');

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_subject'])) {
        // Sanitize input
        $subjectName = sanitizeInput($_POST['subject_name']);
        $subjectCode = sanitizeInput($_POST['subject_code']);
        $description = sanitizeInput($_POST['description']);
        $gradeLevel = sanitizeInput($_POST['grade_level']);
        $credits = (int)$_POST['credits'];
        
        // Validate input
        if (empty($subjectName) || empty($subjectCode)) {
            $error = "Please fill in all required fields.";
        } else {
            try {
                // Begin transaction
                startTransaction();
                
                // Insert subject data
                $sql = "INSERT INTO subjects (subject_name, subject_code, description, grade_level, credits) 
                        VALUES (?, ?, ?, ?, ?)";
                $params = [$subjectName, $subjectCode, $description, $gradeLevel, $credits];
                executeQuery($sql, $params);
                
                // Log activity
                logActivity($currentUser['id'], 'Added new subject: ' . $subjectName);
                
                // Commit transaction
                commitTransaction();
                
                $success = "Subject added successfully.";
            } catch (Exception $e) {
                // Rollback transaction on error
                rollbackTransaction();
                $error = "Error adding subject: " . $e->getMessage();
            }
        }
    }
}

// Fetch all subjects
$subjects = executeQuery("
    SELECT s.*, 
           (SELECT COUNT(*) FROM class_subjects WHERE subject_id = s.id) as class_count,
           (SELECT COUNT(*) FROM teacher_subjects WHERE subject_id = s.id) as teacher_count
    FROM subjects s
    ORDER BY s.grade_level, s.subject_name
");

// Initialize $subjects as an empty array if the query returns false
if (!$subjects) {
    $subjects = [];
}
?>

<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title mb-0">Subject Management</h3>
            <ul class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active">Subjects</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-secondary me-2">
                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i class="fas fa-plus"></i> Add New Subject
            </button>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Subject Management</h5>
                    
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
                                    <th>Subject Name</th>
                                    <th>Subject Code</th>
                                    <th>Grade Level</th>
                                    <th>Credits</th>
                                    <th>Classes</th>
                                    <th>Teachers</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                        <td><?php echo htmlspecialchars($subject['grade_level']); ?></td>
                                        <td><?php echo $subject['credits']; ?></td>
                                        <td><?php echo $subject['class_count']; ?></td>
                                        <td><?php echo $subject['teacher_count']; ?></td>
                                        <td>
                                            <a href="subject_details.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_subject.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $subject['id']; ?>)">
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

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="subject_name" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="subject_code" class="form-label">Subject Code</label>
                        <input type="text" class="form-control" id="subject_code" name="subject_code" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="grade_level" class="form-label">Grade Level</label>
                        <select class="form-select" id="grade_level" name="grade_level" required>
                            <option value="">Select Grade Level</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>">Grade <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="credits" class="form-label">Credits</label>
                        <input type="number" class="form-control" id="credits" name="credits" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_subject" class="btn btn-primary">Add Subject</button>
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
                Are you sure you want to delete this subject? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteButton" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(subjectId) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    const deleteButton = document.getElementById('deleteButton');
    deleteButton.href = `delete_subject.php?id=${subjectId}`;
    modal.show();
}
</script>

<?php require_once '../../includes/footer.php'; ?> 