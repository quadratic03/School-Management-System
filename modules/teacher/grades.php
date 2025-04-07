<?php
/**
 * Teacher Grade Management
 */

// Set page title
$pageTitle = 'Grade Book';

// Include header
require_once '../../includes/header.php';

// Check if user is logged in and has teacher role
requireAuth('teacher');

// Get teacher ID
$teacherId = 0;
$teacherProfile = getUserProfile($currentUser['id'], 'teacher');
if ($teacherProfile) {
    $teacherId = $teacherProfile['id'];
}

// Get classes taught by this teacher
$classes = executeQuery("
    SELECT DISTINCT c.id, c.name
    FROM classes c
    JOIN class_subjects cs ON c.id = cs.class_id
    WHERE cs.teacher_id = ?
    ORDER BY c.name
", [$teacherId]);

// Get subjects taught by this teacher
$subjects = [];
if (isset($_GET['class_id']) && $_GET['class_id'] > 0) {
    $classId = (int)$_GET['class_id'];
    $subjects = executeQuery("
        SELECT s.id, s.name, s.code
        FROM subjects s
        JOIN class_subjects cs ON s.id = cs.subject_id
        WHERE cs.class_id = ? AND cs.teacher_id = ?
        ORDER BY s.name
    ", [$classId, $teacherId]);
}

// Initialize variables
$success = '';
$error = '';
$students = [];
$selectedClass = 0;
$selectedSubject = 0;
$assignments = [];

// Handle filter selection
if (isset($_GET['class_id'])) {
    $selectedClass = (int)$_GET['class_id'];
}

if (isset($_GET['subject_id'])) {
    $selectedSubject = (int)$_GET['subject_id'];
}

// Get students and grades if class and subject are selected
if ($selectedClass > 0 && $selectedSubject > 0) {
    // Get students in the selected class
    $students = executeQuery("
        SELECT s.id, s.first_name, s.last_name, s.admission_number, s.profile_image
        FROM student_profiles s
        WHERE s.class_id = ?
        ORDER BY s.last_name, s.first_name
    ", [$selectedClass]);
    
    // Get assignments for the selected class and subject
    $assignments = executeQuery("
        SELECT a.id, a.title, a.due_date, a.max_score
        FROM assignments a
        WHERE a.class_id = ? AND a.subject_id = ? AND a.created_by = ?
        ORDER BY a.due_date DESC
    ", [$selectedClass, $selectedSubject, $currentUser['id']]);
    
    // Get grades for each assignment and student
    $grades = [];
    if (count($assignments) > 0) {
        $assignmentIds = array_column($assignments, 'id');
        $placeholders = implode(',', array_fill(0, count($assignmentIds), '?'));
        
        $gradesQuery = "
            SELECT s.id, s.assignment_id, s.student_id, s.score, s.feedback
            FROM assignment_submissions s
            WHERE s.assignment_id IN ($placeholders)
        ";
        
        $gradesResult = executeQuery($gradesQuery, $assignmentIds);
        
        foreach ($gradesResult as $grade) {
            $grades[$grade['student_id']][$grade['assignment_id']] = $grade;
        }
    }
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $classId = (int)$_POST['class_id'];
    $subjectId = (int)$_POST['subject_id'];
    $studentIds = $_POST['student_id'] ?? [];
    $assignmentIds = $_POST['assignment_id'] ?? [];
    $scores = $_POST['score'] ?? [];
    $feedback = $_POST['feedback'] ?? [];
    
    try {
        // Begin transaction
        startTransaction();
        
        // Update grades
        foreach ($studentIds as $index => $studentId) {
            $assignmentId = $assignmentIds[$index];
            $score = !empty($scores[$index]) ? (float)$scores[$index] : null;
            $feedbackText = $feedback[$index] ?? '';
            
            // Check if submission exists
            $submission = executeSingleQuery("
                SELECT id FROM assignment_submissions 
                WHERE student_id = ? AND assignment_id = ?
            ", [$studentId, $assignmentId]);
            
            if ($submission) {
                // Update existing submission
                executeNonQuery("
                    UPDATE assignment_submissions 
                    SET score = ?, feedback = ?, graded_by = ?, graded_at = NOW()
                    WHERE student_id = ? AND assignment_id = ?
                ", [$score, $feedbackText, $currentUser['id'], $studentId, $assignmentId]);
            } else {
                // Create new submission record (with null file as it's teacher created)
                executeNonQuery("
                    INSERT INTO assignment_submissions 
                    (assignment_id, student_id, submission_file, submission_date, score, feedback, graded_by, graded_at)
                    VALUES (?, ?, NULL, NOW(), ?, ?, ?, NOW())
                ", [$assignmentId, $studentId, $score, $feedbackText, $currentUser['id']]);
            }
        }
        
        // Commit transaction
        commitTransaction();
        
        // Log activity
        logActivity($currentUser['id'], 'Updated student grades', 'Class: ' . $classId . ', Subject: ' . $subjectId);
        
        $success = 'Grades have been saved successfully.';
        
        // Redirect to avoid form resubmission
        header("Location: grades.php?class_id=$classId&subject_id=$subjectId&success=1");
        exit;
    } catch (Exception $e) {
        // Rollback transaction
        rollbackTransaction();
        $error = 'Error: ' . $e->getMessage();
    }
}

// Display success message after redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = 'Grades have been saved successfully.';
}

// Calculate statistics
$classAverage = [];
$assignmentStats = [];

if ($selectedClass > 0 && $selectedSubject > 0 && count($assignments) > 0) {
    // Calculate class average for each assignment
    foreach ($assignments as $assignment) {
        $assignmentId = $assignment['id'];
        $maxScore = $assignment['max_score'];
        
        $stats = executeSingleQuery("
            SELECT 
                COUNT(*) as total_submissions,
                AVG(score) as average_score,
                MIN(score) as min_score,
                MAX(score) as max_score
            FROM assignment_submissions
            WHERE assignment_id = ? AND score IS NOT NULL
        ", [$assignmentId]);
        
        if ($stats) {
            $assignmentStats[$assignmentId] = [
                'total' => $stats['total_submissions'],
                'average' => $stats['average_score'] ? round($stats['average_score'], 1) : 0,
                'min' => $stats['min_score'] ? $stats['min_score'] : 0,
                'max' => $stats['max_score'] ? $stats['max_score'] : 0,
                'percentage' => $maxScore > 0 ? round(($stats['average_score'] / $maxScore) * 100, 1) : 0
            ];
        }
    }
    
    // Calculate overall class average
    $overallAvg = executeSingleQuery("
        SELECT 
            AVG(s.score / a.max_score * 100) as percentage
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.id
        WHERE a.class_id = ? AND a.subject_id = ? AND s.score IS NOT NULL
    ", [$selectedClass, $selectedSubject]);
    
    $classAverage = $overallAvg ? round($overallAvg['percentage'], 1) : 0;
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Grade Book</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Teacher</a></li>
                <li class="breadcrumb-item active">Grades</li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $error; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $success; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Grades Filter -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-5">
                        <label for="class_id" class="form-label">Class</label>
                        <select name="class_id" id="class_id" class="form-select" required onchange="this.form.submit()">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $selectedClass == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="subject_id" class="form-label">Subject</label>
                        <select name="subject_id" id="subject_id" class="form-select" required <?php echo count($subjects) > 0 ? '' : 'disabled'; ?> onchange="this.form.submit()">
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $selectedSubject == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['name']); ?> (<?php echo htmlspecialchars($subject['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" <?php echo $selectedClass == 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-filter"></i> View Grades
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($selectedClass > 0 && $selectedSubject > 0): ?>

<!-- Class Stats -->
<?php if (count($assignments) > 0): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Class Performance</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <h4>Class Average</h4>
                            <div class="display-4 text-<?php echo getGradeColor($classAverage); ?>">
                                <?php echo $classAverage; ?>%
                            </div>
                            <div class="grade-letter text-<?php echo getGradeColor($classAverage); ?>">
                                <?php echo getGradeLetter($classAverage); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <h5>Assignment Stats</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Assignment</th>
                                        <th>Average</th>
                                        <th>Min</th>
                                        <th>Max</th>
                                        <th>Submissions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <?php 
                                        $stats = isset($assignmentStats[$assignment['id']]) ? $assignmentStats[$assignment['id']] : null;
                                        if (!$stats) continue;
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                            <td class="text-<?php echo getGradeColor($stats['percentage']); ?>">
                                                <?php echo $stats['average']; ?> (<?php echo $stats['percentage']; ?>%)
                                            </td>
                                            <td><?php echo $stats['min']; ?></td>
                                            <td><?php echo $stats['max']; ?></td>
                                            <td><?php echo $stats['total']; ?> / <?php echo count($students); ?></td>
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
<?php endif; ?>

<!-- Grade Book -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Grade Book</h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-success" onclick="printGradeBook()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="exportGradeBook()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($students) > 0 && count($assignments) > 0): ?>
                    <form method="post" action="" id="gradeForm">
                        <input type="hidden" name="class_id" value="<?php echo $selectedClass; ?>">
                        <input type="hidden" name="subject_id" value="<?php echo $selectedSubject; ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered" id="gradeBookTable">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="align-middle">Student</th>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <th colspan="2" class="text-center">
                                                <?php echo htmlspecialchars($assignment['title']); ?>
                                                <div class="small text-muted">
                                                    Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    Max: <?php echo $assignment['max_score']; ?> pts
                                                </div>
                                            </th>
                                        <?php endforeach; ?>
                                        <th rowspan="2" class="align-middle text-center">Average</th>
                                        <th rowspan="2" class="align-middle text-center">Grade</th>
                                    </tr>
                                    <tr>
                                        <?php foreach ($assignments as $assignment): ?>
                                            <th class="text-center">Score</th>
                                            <th class="text-center">Feedback</th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm me-2">
                                                        <?php if (!empty($student['profile_image'])): ?>
                                                            <img src="<?php echo APP_URL; ?>/uploads/profile_images/<?php echo htmlspecialchars($student['profile_image']); ?>" 
                                                                 alt="Profile" class="avatar-img rounded-circle">
                                                        <?php else: ?>
                                                            <div class="avatar-text rounded-circle bg-primary">
                                                                <span><?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($student['admission_number']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <?php 
                                            $totalScore = 0;
                                            $totalMax = 0;
                                            $gradedAssignments = 0;
                                            
                                            foreach ($assignments as $assignment): 
                                                $grade = isset($grades[$student['id']][$assignment['id']]) ? $grades[$student['id']][$assignment['id']] : null;
                                                $score = $grade ? $grade['score'] : '';
                                                $feedbackText = $grade ? $grade['feedback'] : '';
                                                
                                                // Add to running total if score exists
                                                if ($score !== '' && $score !== null) {
                                                    $totalScore += $score;
                                                    $totalMax += $assignment['max_score'];
                                                    $gradedAssignments++;
                                                }
                                            ?>
                                                <td>
                                                    <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                                    <input type="hidden" name="assignment_id[]" value="<?php echo $assignment['id']; ?>">
                                                    <input type="number" class="form-control form-control-sm" name="score[]" min="0" max="<?php echo $assignment['max_score']; ?>" value="<?php echo $score; ?>" placeholder="Score">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" name="feedback[]" value="<?php echo htmlspecialchars($feedbackText); ?>" placeholder="Feedback">
                                                </td>
                                            <?php endforeach; ?>
                                            
                                            <?php
                                            // Calculate average
                                            $average = $gradedAssignments > 0 ? ($totalScore / $totalMax) * 100 : 0;
                                            $gradeLetter = getGradeLetter($average);
                                            $gradeColor = getGradeColor($average);
                                            ?>
                                            
                                            <td class="text-center">
                                                <span class="text-<?php echo $gradeColor; ?>">
                                                    <?php echo number_format($average, 1); ?>%
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $gradeColor; ?>"><?php echo $gradeLetter; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-end mt-3">
                            <button type="submit" name="save_grades" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Grades
                            </button>
                        </div>
                    </form>
                <?php elseif (count($students) == 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No students found in this class.
                    </div>
                <?php elseif (count($assignments) == 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No assignments found for this class and subject. 
                        <a href="assignments.php" class="alert-link">Create assignments</a> to start grading.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
    function printGradeBook() {
        window.print();
    }
    
    function exportGradeBook() {
        // Convert table to CSV
        const table = document.getElementById('gradeBookTable');
        let csv = [];
        
        // Get headers
        const headers = [];
        const headerRowsCount = table.tHead.rows.length;
        const headerCells = table.tHead.rows[headerRowsCount - 1].cells;
        
        headers.push('Student');
        for (let i = 1; i < headerCells.length - 2; i += 2) {
            headers.push(table.tHead.rows[0].cells[(i-1)/2 + 1].textContent.trim() + ' - Score');
            headers.push(table.tHead.rows[0].cells[(i-1)/2 + 1].textContent.trim() + ' - Feedback');
        }
        headers.push('Average');
        headers.push('Grade');
        
        csv.push(headers.join(','));
        
        // Get data
        for (let i = 0; i < table.tBodies[0].rows.length; i++) {
            const row = table.tBodies[0].rows[i];
            const rowData = [];
            
            // Student name
            const studentName = row.cells[0].textContent.trim().split('\n')[0];
            rowData.push('"' + studentName + '"');
            
            // Scores and feedback
            for (let j = 1; j < row.cells.length; j++) {
                const cellValue = row.cells[j].textContent.trim();
                rowData.push('"' + cellValue + '"');
            }
            
            csv.push(rowData.join(','));
        }
        
        // Download CSV file
        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'gradebook.csv');
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<?php
// Include footer
require_once '../../includes/footer.php';

/**
 * Get grade letter based on percentage
 * 
 * @param float $percentage Grade percentage
 * @return string Grade letter
 */
function getGradeLetter($percentage) {
    if ($percentage >= 90) return 'A';
    if ($percentage >= 80) return 'B';
    if ($percentage >= 70) return 'C';
    if ($percentage >= 60) return 'D';
    return 'F';
}

/**
 * Get color class based on grade percentage
 * 
 * @param float $percentage Grade percentage
 * @return string Bootstrap color class
 */
function getGradeColor($percentage) {
    if ($percentage >= 90) return 'success';
    if ($percentage >= 80) return 'info';
    if ($percentage >= 70) return 'primary';
    if ($percentage >= 60) return 'warning';
    return 'danger';
}
?> 