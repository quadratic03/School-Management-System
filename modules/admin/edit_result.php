<?php
/**
 * Edit Exam Result
 * 
 * This file handles editing exam results for students
 */

$pageTitle = "Edit Exam Result";
require_once '../../includes/header.php';
requireAuth('admin');

// Get exam and student IDs from URL
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$examId || !$studentId) {
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

// Fetch student details
$student = executeQuery("
    SELECT s.*, sp.first_name, sp.last_name, sp.roll_number
    FROM students s
    JOIN student_profiles sp ON s.id = sp.user_id
    WHERE s.id = ?
", [$studentId]);

if (!$student) {
    redirect('exams.php');
}

// Fetch existing result
$result = executeQuery("
    SELECT * FROM exam_submissions 
    WHERE exam_id = ? AND student_id = ?
", [$examId, $studentId]);

if (!$result) {
    redirect('add_result.php?exam_id=' . $examId . '&student_id=' . $studentId);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $marksObtained = (int)$_POST['marks_obtained'];
    $remarks = sanitizeInput($_POST['remarks']);
    
    // Validate input
    if ($marksObtained < 0 || $marksObtained > $exam['max_marks']) {
        $error = "Marks obtained must be between 0 and " . $exam['max_marks'];
    } else {
        try {
            // Begin transaction
            startTransaction();
            
            // Update exam submission
            $sql = "UPDATE exam_submissions 
                    SET marks_obtained = ?, remarks = ? 
                    WHERE exam_id = ? AND student_id = ?";
            $params = [$marksObtained, $remarks, $examId, $studentId];
            executeQuery($sql, $params);
            
            // Log activity
            logActivity($currentUser['id'], "Updated exam result for student {$student['first_name']} {$student['last_name']}");
            
            // Commit transaction
            commitTransaction();
            
            $success = "Exam result updated successfully.";
            
            // Redirect to exam details page after a short delay
            header("refresh:2;url=exam_details.php?id=" . $examId);
        } catch (Exception $e) {
            // Rollback transaction on error
            rollbackTransaction();
            $error = "Error updating exam result: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Edit Exam Result</h5>
                    <div>
                        <a href="exam_details.php?id=<?php echo $examId; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Exam Details
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
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Exam Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th width="150">Exam Name:</th>
                                    <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Subject:</th>
                                    <td>
                                        <?php echo htmlspecialchars($exam['subject_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($exam['subject_code']); ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Class:</th>
                                    <td>
                                        <?php echo htmlspecialchars($exam['class_name']); ?>
                                        <br>
                                        <small class="text-muted">Grade <?php echo $exam['grade_level']; ?> - <?php echo htmlspecialchars($exam['section']); ?></small>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Student Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th width="150">Student Name:</th>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Roll Number:</th>
                                    <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                </tr>
                                <tr>
                                    <th>Maximum Marks:</th>
                                    <td><?php echo $exam['max_marks']; ?></td>
                                </tr>
                                <tr>
                                    <th>Passing Marks:</th>
                                    <td><?php echo $exam['passing_marks']; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="marks_obtained" class="form-label">Marks Obtained</label>
                                    <input type="number" class="form-control" id="marks_obtained" name="marks_obtained" 
                                           value="<?php echo $result['marks_obtained']; ?>"
                                           min="0" max="<?php echo $exam['max_marks']; ?>" required>
                                    <div class="invalid-feedback">
                                        Please enter marks between 0 and <?php echo $exam['max_marks']; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" 
                                              rows="3"><?php echo htmlspecialchars($result['remarks']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Result
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