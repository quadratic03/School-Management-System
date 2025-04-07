<?php
/**
 * Teacher Reports
 */

// Set page title
$pageTitle = 'Reports';

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

// Initialize variables
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$reportType = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : '';
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-t'); // Last day of current month

// Report data
$reportData = [];
$reportTitle = '';

// Generate report based on selected type
if ($reportType && $selectedClass > 0) {
    switch ($reportType) {
        case 'attendance':
            $reportTitle = 'Attendance Report';
            $reportData = generateAttendanceReport($selectedClass, $startDate, $endDate);
            break;
            
        case 'performance':
            $reportTitle = 'Academic Performance Report';
            $reportData = generatePerformanceReport($selectedClass);
            break;
            
        case 'submission':
            $reportTitle = 'Assignment Submission Report';
            $reportData = generateSubmissionReport($selectedClass, $startDate, $endDate);
            break;
    }
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Reports</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Teacher</a></li>
                <li class="breadcrumb-item active">Reports</li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Report Filter -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="class_id" class="form-label">Class</label>
                        <select name="class_id" id="class_id" class="form-select" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $selectedClass == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select name="report_type" id="report_type" class="form-select" required>
                            <option value="">Select Report Type</option>
                            <option value="attendance" <?php echo $reportType == 'attendance' ? 'selected' : ''; ?>>Attendance Report</option>
                            <option value="performance" <?php echo $reportType == 'performance' ? 'selected' : ''; ?>>Academic Performance Report</option>
                            <option value="submission" <?php echo $reportType == 'submission' ? 'selected' : ''; ?>>Assignment Submission Report</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Report Display -->
<?php if ($reportType && $selectedClass > 0 && !empty($reportData)): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><?php echo $reportTitle; ?></h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-success" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn btn-sm btn-info" onclick="exportToCSV()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                <div class="card-subtitle mt-2">
                    Class: <?php echo htmlspecialchars(getClassNameById($selectedClass)); ?> | 
                    <?php if ($reportType !== 'performance'): ?>
                        Period: <?php echo date('d M Y', strtotime($startDate)); ?> - <?php echo date('d M Y', strtotime($endDate)); ?>
                    <?php else: ?>
                        Current Academic Term
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php if ($reportType === 'attendance'): ?>
                        <!-- Attendance Report Table -->
                        <table class="table table-bordered table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Total Days</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Late</th>
                                    <th>Attendance %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $record): ?>
                                    <?php
                                    $attendancePercentage = $record['total_days'] > 0 ? 
                                        round(($record['present'] / $record['total_days']) * 100, 1) : 0;
                                    $colorClass = getAttendanceColorClass($attendancePercentage);
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                        <td><?php echo $record['total_days']; ?></td>
                                        <td><?php echo $record['present']; ?></td>
                                        <td><?php echo $record['absent']; ?></td>
                                        <td><?php echo $record['late']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo $colorClass; ?>" 
                                                     role="progressbar" style="width: <?php echo $attendancePercentage; ?>%;" 
                                                     aria-valuenow="<?php echo $attendancePercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $attendancePercentage; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php elseif ($reportType === 'performance'): ?>
                        <!-- Performance Report Table -->
                        <table class="table table-bordered table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Assignments Completed</th>
                                    <th>Average Score</th>
                                    <th>Grade</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $record): ?>
                                    <?php
                                    $gradeColor = getGradeColor($record['average_percentage']);
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                        <td><?php echo $record['assignments_completed']; ?> / <?php echo $record['total_assignments']; ?></td>
                                        <td><?php echo $record['average_score']; ?> (<?php echo $record['average_percentage']; ?>%)</td>
                                        <td>
                                            <span class="badge bg-<?php echo $gradeColor; ?>">
                                                <?php echo getGradeLetter($record['average_percentage']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo $gradeColor; ?>" 
                                                     role="progressbar" style="width: <?php echo $record['average_percentage']; ?>%;" 
                                                     aria-valuenow="<?php echo $record['average_percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $record['average_percentage']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php elseif ($reportType === 'submission'): ?>
                        <!-- Submission Report Table -->
                        <table class="table table-bordered table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Due Date</th>
                                    <th>Subject</th>
                                    <th>Submissions</th>
                                    <th>On Time</th>
                                    <th>Late</th>
                                    <th>Missing</th>
                                    <th>Average Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($record['due_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['subject_name']); ?></td>
                                        <td><?php echo $record['total_submissions']; ?> / <?php echo $record['total_students']; ?></td>
                                        <td><?php echo $record['on_time']; ?></td>
                                        <td><?php echo $record['late']; ?></td>
                                        <td><?php echo $record['missing']; ?></td>
                                        <td><?php echo $record['average_score']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php elseif ($reportType && $selectedClass > 0 && empty($reportData)): ?>
<div class="alert alert-info mt-4">
    <i class="fas fa-info-circle"></i> No data available for the selected report criteria.
</div>
<?php endif; ?>

<script>
    // Show/hide date inputs based on report type
    document.getElementById('report_type').addEventListener('change', function() {
        const dateInputs = document.querySelectorAll('#start_date, #end_date');
        const dateLabels = document.querySelectorAll('label[for="start_date"], label[for="end_date"]');
        
        if (this.value === 'performance') {
            dateInputs.forEach(input => {
                input.disabled = true;
                input.parentElement.style.opacity = 0.5;
            });
            dateLabels.forEach(label => {
                label.style.opacity = 0.5;
            });
        } else {
            dateInputs.forEach(input => {
                input.disabled = false;
                input.parentElement.style.opacity = 1;
            });
            dateLabels.forEach(label => {
                label.style.opacity = 1;
            });
        }
    });
    
    // Trigger the change event to set initial state
    document.addEventListener('DOMContentLoaded', function() {
        const reportType = document.getElementById('report_type');
        if (reportType.value === 'performance') {
            const dateInputs = document.querySelectorAll('#start_date, #end_date');
            const dateLabels = document.querySelectorAll('label[for="start_date"], label[for="end_date"]');
            
            dateInputs.forEach(input => {
                input.disabled = true;
                input.parentElement.style.opacity = 0.5;
            });
            dateLabels.forEach(label => {
                label.style.opacity = 0.5;
            });
        }
    });
    
    // Export table to CSV
    function exportToCSV() {
        const table = document.getElementById('reportTable');
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                // Get text content, replacing any commas with spaces to avoid CSV issues
                let data = cols[j].textContent.replace(/,/g, ' ').trim();
                // Wrap in quotes to handle any other special characters
                row.push('"' + data + '"');
            }
            
            csv.push(row.join(','));
        }
        
        // Download CSV file
        const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
        const downloadLink = document.createElement('a');
        
        // Create a link to the file
        downloadLink.href = URL.createObjectURL(csvFile);
        downloadLink.download = 'report.csv';
        
        // Hide download link
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        
        // Click download link
        downloadLink.click();
    }
</script>

<?php
// Include footer
require_once '../../includes/footer.php';

/**
 * Generate attendance report for a class within date range
 * 
 * @param int $classId Class ID
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @return array Report data
 */
function generateAttendanceReport($classId, $startDate, $endDate) {
    // Get students in the class
    $students = executeQuery("
        SELECT id, first_name, last_name
        FROM student_profiles
        WHERE class_id = ?
        ORDER BY last_name, first_name
    ", [$classId]);
    
    $reportData = [];
    
    foreach ($students as $student) {
        // Get attendance stats for this student in the date range
        $stats = executeSingleQuery("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
            FROM attendance
            WHERE student_id = ? AND class_id = ? AND date BETWEEN ? AND ?
        ", [$student['id'], $classId, $startDate, $endDate]);
        
        if ($stats) {
            $reportData[] = [
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'total_days' => $stats['total_days'] ?? 0,
                'present' => $stats['present'] ?? 0,
                'absent' => $stats['absent'] ?? 0,
                'late' => $stats['late'] ?? 0
            ];
        } else {
            $reportData[] = [
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'total_days' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0
            ];
        }
    }
    
    return $reportData;
}

/**
 * Generate academic performance report for a class
 * 
 * @param int $classId Class ID
 * @return array Report data
 */
function generatePerformanceReport($classId) {
    global $currentUser;
    
    // Get students in the class
    $students = executeQuery("
        SELECT id, first_name, last_name
        FROM student_profiles
        WHERE class_id = ?
        ORDER BY last_name, first_name
    ", [$classId]);
    
    // Get total assignments count for this class created by the current teacher
    $totalAssignments = executeSingleQuery("
        SELECT COUNT(*) as count
        FROM assignments
        WHERE class_id = ? AND created_by = ?
    ", [$classId, $currentUser['id']]);
    
    $totalAssignments = $totalAssignments ? $totalAssignments['count'] : 0;
    
    $reportData = [];
    
    foreach ($students as $student) {
        // Get assignment statistics for this student
        $stats = executeSingleQuery("
            SELECT 
                COUNT(*) as completed,
                AVG(s.score) as avg_score,
                AVG(s.score / a.max_score * 100) as avg_percentage
            FROM assignment_submissions s
            JOIN assignments a ON s.assignment_id = a.id
            WHERE s.student_id = ? AND a.class_id = ? AND a.created_by = ? AND s.score IS NOT NULL
        ", [$student['id'], $classId, $currentUser['id']]);
        
        $completedAssignments = $stats ? $stats['completed'] : 0;
        $averageScore = $stats && $stats['avg_score'] !== null ? round($stats['avg_score'], 1) : 0;
        $averagePercentage = $stats && $stats['avg_percentage'] !== null ? round($stats['avg_percentage'], 1) : 0;
        
        $reportData[] = [
            'student_name' => $student['first_name'] . ' ' . $student['last_name'],
            'assignments_completed' => $completedAssignments,
            'total_assignments' => $totalAssignments,
            'average_score' => $averageScore,
            'average_percentage' => $averagePercentage
        ];
    }
    
    return $reportData;
}

/**
 * Generate assignment submission report for a class within date range
 * 
 * @param int $classId Class ID
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @return array Report data
 */
function generateSubmissionReport($classId, $startDate, $endDate) {
    global $currentUser;
    
    // Get assignments for this class in the date range
    $assignments = executeQuery("
        SELECT a.id, a.title, a.due_date, a.max_score, s.name as subject_name
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        WHERE a.class_id = ? AND a.created_by = ? AND a.due_date BETWEEN ? AND ?
        ORDER BY a.due_date DESC
    ", [$classId, $currentUser['id'], $startDate, $endDate]);
    
    // Get total students in the class
    $totalStudents = executeSingleQuery("
        SELECT COUNT(*) as count
        FROM student_profiles
        WHERE class_id = ?
    ", [$classId]);
    
    $totalStudents = $totalStudents ? $totalStudents['count'] : 0;
    
    $reportData = [];
    
    foreach ($assignments as $assignment) {
        // Get submission statistics for this assignment
        $stats = executeSingleQuery("
            SELECT 
                COUNT(*) as total_submissions,
                SUM(CASE WHEN submission_date <= ? THEN 1 ELSE 0 END) as on_time,
                SUM(CASE WHEN submission_date > ? THEN 1 ELSE 0 END) as late,
                AVG(score) as avg_score
            FROM assignment_submissions
            WHERE assignment_id = ?
        ", [$assignment['due_date'], $assignment['due_date'], $assignment['id']]);
        
        $totalSubmissions = $stats ? $stats['total_submissions'] : 0;
        $onTime = $stats ? $stats['on_time'] : 0;
        $late = $stats ? $stats['late'] : 0;
        $missing = $totalStudents - $totalSubmissions;
        $avgScore = $stats && $stats['avg_score'] !== null ? round($stats['avg_score'], 1) : 0;
        
        $reportData[] = [
            'title' => $assignment['title'],
            'due_date' => $assignment['due_date'],
            'subject_name' => $assignment['subject_name'],
            'total_submissions' => $totalSubmissions,
            'total_students' => $totalStudents,
            'on_time' => $onTime,
            'late' => $late,
            'missing' => $missing,
            'average_score' => $avgScore
        ];
    }
    
    return $reportData;
}

/**
 * Get class name by ID
 * 
 * @param int $classId Class ID
 * @return string Class name or empty string if not found
 */
function getClassNameById($classId) {
    $class = executeSingleQuery("SELECT name FROM classes WHERE id = ?", [$classId]);
    return $class ? $class['name'] : '';
}

/**
 * Get attendance color class based on percentage
 * 
 * @param int $percentage Attendance percentage
 * @return string Bootstrap color class name
 */
function getAttendanceColorClass($percentage) {
    if ($percentage >= 90) return 'success';
    if ($percentage >= 75) return 'info';
    if ($percentage >= 60) return 'warning';
    return 'danger';
}

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