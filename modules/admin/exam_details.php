<?php
/**
 * Exam Details
 * 
 * This file displays detailed information about a specific exam
 */

$pageTitle = "Exam Details";
require_once '../../includes/header.php';
requireAuth('admin');

// Get exam ID from URL
$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$examId) {
    redirect('exams.php');
}

// Fetch exam details with related information
$exam = executeQuery("
    SELECT e.*, 
           s.subject_name, s.subject_code,
           c.class_name, c.grade_level, c.section,
           t.first_name as teacher_first_name, t.last_name as teacher_last_name
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    JOIN classes c ON e.class_id = c.id
    LEFT JOIN teacher_profiles t ON c.teacher_id = t.user_id
    WHERE e.id = ?
", [$examId]);

if (!$exam) {
    redirect('exams.php');
}

// Fetch enrolled students for this class
$students = executeQuery("
    SELECT s.*, 
           sp.first_name, sp.last_name, sp.roll_number,
           es.marks_obtained, es.remarks
    FROM students s
    JOIN student_profiles sp ON s.id = sp.user_id
    JOIN enrollments e ON s.id = e.student_id
    LEFT JOIN exam_submissions es ON s.id = es.student_id AND es.exam_id = ?
    WHERE e.class_id = ?
    ORDER BY sp.roll_number
", [$examId, $exam['class_id']]);

// Initialize $students as an empty array if the query returns false
if (!$students) {
    $students = [];
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Exam Details</h5>
                    <div>
                        <a href="exams.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Exams
                        </a>
                        <a href="edit_exam.php?id=<?php echo $examId; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Exam
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Basic Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th width="150">Exam Name:</th>
                                    <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Exam Type:</th>
                                    <td><?php echo ucfirst($exam['exam_type']); ?></td>
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
                            <h6 class="text-muted">Schedule & Marks</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th width="150">Date:</th>
                                    <td><?php echo date('M d, Y', strtotime($exam['date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Time:</th>
                                    <td>
                                        <?php echo date('h:i A', strtotime($exam['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($exam['end_time'])); ?>
                                    </td>
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

                    <?php if ($exam['description']): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-muted">Description</h6>
                                <p><?php echo nl2br(htmlspecialchars($exam['description'])); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="text-muted">Student Results</h6>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Roll Number</th>
                                            <th>Student Name</th>
                                            <th>Marks Obtained</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($student['marks_obtained'] !== null): ?>
                                                        <?php echo $student['marks_obtained']; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not submitted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($student['marks_obtained'] !== null): ?>
                                                        <?php if ($student['marks_obtained'] >= $exam['passing_marks']): ?>
                                                            <span class="badge bg-success">Pass</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Fail</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo $student['remarks'] ? htmlspecialchars($student['remarks']) : '-'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($student['marks_obtained'] !== null): ?>
                                                        <a href="edit_result.php?exam_id=<?php echo $examId; ?>&student_id=<?php echo $student['id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="add_result.php?exam_id=<?php echo $examId; ?>&student_id=<?php echo $student['id']; ?>" 
                                                           class="btn btn-sm btn-success">
                                                            <i class="fas fa-plus"></i>
                                                        </a>
                                                    <?php endif; ?>
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
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 