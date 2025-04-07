<?php
/**
 * Examination Management
 * 
 * This file handles the management of examinations
 */

$pageTitle = "Examination Management";
require_once '../../includes/header.php';
requireAuth('admin');

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_exam'])) {
        // Sanitize input
        $examName = sanitizeInput($_POST['exam_name']);
        $examType = sanitizeInput($_POST['exam_type']);
        $subjectId = (int)$_POST['subject_id'];
        $classId = (int)$_POST['class_id'];
        $date = sanitizeInput($_POST['date']);
        $startTime = sanitizeInput($_POST['start_time']);
        $endTime = sanitizeInput($_POST['end_time']);
        $maxMarks = (int)$_POST['max_marks'];
        $passingMarks = (int)$_POST['passing_marks'];
        $description = sanitizeInput($_POST['description']);
        
        // Validate input
        if (empty($examName) || empty($examType) || empty($subjectId) || empty($classId)) {
            $error = "Please fill in all required fields.";
        } else {
            try {
                // Begin transaction
                startTransaction();
                
                // Insert exam data
                $sql = "INSERT INTO exams (exam_name, exam_type, subject_id, class_id, date, 
                        start_time, end_time, max_marks, passing_marks, description) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [$examName, $examType, $subjectId, $classId, $date, 
                          $startTime, $endTime, $maxMarks, $passingMarks, $description];
                executeQuery($sql, $params);
                
                // Log activity
                logActivity($currentUser['id'], 'Added new exam: ' . $examName);
                
                // Commit transaction
                commitTransaction();
                
                $success = "Exam added successfully.";
            } catch (Exception $e) {
                // Rollback transaction on error
                rollbackTransaction();
                $error = "Error adding exam: " . $e->getMessage();
            }
        }
    }
}

// Fetch all exams with related information
$exams = executeQuery("
    SELECT e.*, 
           s.subject_name, s.subject_code,
           c.class_name, c.grade_level, c.section,
           t.first_name as teacher_first_name, t.last_name as teacher_last_name
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    LEFT JOIN teacher_profiles t ON c.teacher_id = t.user_id
    ORDER BY e.date DESC, e.start_time DESC
");

// Initialize $exams as an empty array if the query returns false
if (!$exams) {
    $exams = [];
}

// Fetch subjects for dropdown
$subjects = executeQuery("
    SELECT * FROM subjects 
    ORDER BY subject_name
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
            <h3 class="page-title mb-0">Examination Management</h3>
            <ul class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active">Exams</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-secondary me-2">
                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExamModal">
                <i class="fas fa-plus"></i> Add New Exam
            </button>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Examination Management</h5>
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
                                    <th>Exam Name</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Type</th>
                                    <th>Date & Time</th>
                                    <th>Marks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exams as $exam): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($exam['subject_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($exam['subject_code']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($exam['class_name']); ?>
                                            <br>
                                            <small class="text-muted">Grade <?php echo $exam['grade_level']; ?> - <?php echo htmlspecialchars($exam['section']); ?></small>
                                        </td>
                                        <td><?php echo ucfirst($exam['exam_type']); ?></td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($exam['date'])); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('h:i A', strtotime($exam['start_time'])); ?> - 
                                                <?php echo date('h:i A', strtotime($exam['end_time'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            Max: <?php echo $exam['max_marks']; ?>
                                            <br>
                                            <small class="text-muted">Pass: <?php echo $exam['passing_marks']; ?></small>
                                        </td>
                                        <td>
                                            <a href="exam_details.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $exam['id']; ?>)">
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

<!-- Add Exam Modal -->
<div class="modal fade" id="addExamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="exam_name" class="form-label">Exam Name</label>
                        <input type="text" class="form-control" id="exam_name" name="exam_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="exam_type" class="form-label">Exam Type</label>
                        <select class="form-select" id="exam_type" name="exam_type" required>
                            <option value="">Select Type</option>
                            <option value="quiz">Quiz</option>
                            <option value="midterm">Midterm</option>
                            <option value="final">Final</option>
                            <option value="assignment">Assignment</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="subject_id" class="form-label">Subject</label>
                        <select class="form-select" id="subject_id" name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['subject_code'] . ')'); ?>
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
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_time" class="form-label">Start Time</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_time" class="form-label">End Time</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_marks" class="form-label">Maximum Marks</label>
                                <input type="number" class="form-control" id="max_marks" name="max_marks" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="passing_marks" class="form-label">Passing Marks</label>
                                <input type="number" class="form-control" id="passing_marks" name="passing_marks" min="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_exam" class="btn btn-primary">Add Exam</button>
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
                Are you sure you want to delete this exam? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteButton" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(examId) {
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    const deleteButton = document.getElementById('deleteButton');
    deleteButton.href = `delete_exam.php?id=${examId}`;
    modal.show();
}
</script>

<?php require_once '../../includes/footer.php'; ?> 