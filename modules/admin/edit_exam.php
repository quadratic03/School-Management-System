<?php
/**
 * Edit Exam
 * 
 * This file handles editing exam details
 */

$pageTitle = "Edit Exam";
require_once '../../includes/header.php';
requireAuth('admin');

// Get exam ID from URL
$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$examId) {
    redirect('exams.php');
}

// Initialize variables
$error = '';
$success = '';

// Fetch exam details
$exam = executeQuery("
    SELECT e.*, 
           s.subject_name, s.subject_code,
           c.class_name, c.grade_level, c.section
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    WHERE e.id = ?
", [$examId]);

if (!$exam) {
    redirect('exams.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            
            // Update exam data
            $sql = "UPDATE exams SET 
                    exam_name = ?, exam_type = ?, subject_id = ?, class_id = ?,
                    date = ?, start_time = ?, end_time = ?, max_marks = ?,
                    passing_marks = ?, description = ?
                    WHERE id = ?";
            $params = [$examName, $examType, $subjectId, $classId, $date,
                      $startTime, $endTime, $maxMarks, $passingMarks,
                      $description, $examId];
            executeQuery($sql, $params);
            
            // Log activity
            logActivity($currentUser['id'], 'Updated exam: ' . $examName);
            
            // Commit transaction
            commitTransaction();
            
            $success = "Exam updated successfully.";
            
            // Refresh exam data
            $exam = executeQuery("
                SELECT e.*, 
                       s.subject_name, s.subject_code,
                       c.class_name, c.grade_level, c.section
                FROM exams e
                JOIN subjects s ON e.subject_id = s.id
                JOIN classes c ON e.class_id = c.id
                WHERE e.id = ?
            ", [$examId]);
        } catch (Exception $e) {
            // Rollback transaction on error
            rollbackTransaction();
            $error = "Error updating exam: " . $e->getMessage();
        }
    }
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

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Edit Exam</h5>
                    <div>
                        <a href="exams.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Exams
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="exam_name" class="form-label">Exam Name</label>
                                    <input type="text" class="form-control" id="exam_name" name="exam_name" 
                                           value="<?php echo htmlspecialchars($exam['exam_name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="exam_type" class="form-label">Exam Type</label>
                                    <select class="form-select" id="exam_type" name="exam_type" required>
                                        <option value="">Select Type</option>
                                        <option value="quiz" <?php echo $exam['exam_type'] === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                                        <option value="midterm" <?php echo $exam['exam_type'] === 'midterm' ? 'selected' : ''; ?>>Midterm</option>
                                        <option value="final" <?php echo $exam['exam_type'] === 'final' ? 'selected' : ''; ?>>Final</option>
                                        <option value="assignment" <?php echo $exam['exam_type'] === 'assignment' ? 'selected' : ''; ?>>Assignment</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="subject_id" class="form-label">Subject</label>
                                    <select class="form-select" id="subject_id" name="subject_id" required>
                                        <option value="">Select Subject</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>" 
                                                    <?php echo $subject['id'] === $exam['subject_id'] ? 'selected' : ''; ?>>
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
                                            <option value="<?php echo $class['id']; ?>"
                                                    <?php echo $class['id'] === $exam['class_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['class_name'] . ' - Grade ' . $class['grade_level'] . ' ' . $class['section']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="date" name="date" 
                                           value="<?php echo $exam['date']; ?>" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="start_time" class="form-label">Start Time</label>
                                            <input type="time" class="form-control" id="start_time" name="start_time" 
                                                   value="<?php echo $exam['start_time']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="end_time" class="form-label">End Time</label>
                                            <input type="time" class="form-control" id="end_time" name="end_time" 
                                                   value="<?php echo $exam['end_time']; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_marks" class="form-label">Maximum Marks</label>
                                            <input type="number" class="form-control" id="max_marks" name="max_marks" 
                                                   value="<?php echo $exam['max_marks']; ?>" min="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="passing_marks" class="form-label">Passing Marks</label>
                                            <input type="number" class="form-control" id="passing_marks" name="passing_marks" 
                                                   value="<?php echo $exam['passing_marks']; ?>" min="1" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" 
                                              rows="3"><?php echo htmlspecialchars($exam['description']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()
</script>

<?php require_once '../../includes/footer.php'; ?> 