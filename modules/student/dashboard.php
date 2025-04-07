<?php
/**
 * Student Dashboard
 */

// Set page title
$pageTitle = 'Student Dashboard';

// Include header
require_once '../../includes/header.php';

// Check if user is logged in and has student role
requireAuth('student');

// Get student ID
$studentId = 0;
$studentProfile = getUserProfile($currentUser['id'], 'student');
if ($studentProfile) {
    $studentId = $studentProfile['id'];
}

// Get student's class information
$classInfo = null;
if ($studentProfile && $studentProfile['class_id']) {
    $classInfo = executeSingleQuery("SELECT c.*, 
                                    (SELECT COUNT(*) FROM student_profiles WHERE class_id = c.id) as student_count 
                                FROM classes c
                                WHERE c.id = ?", 
                               [$studentProfile['class_id']]);
}

// Get student's subjects and teachers
$subjects = [];
if ($studentProfile && $studentProfile['class_id']) {
    $subjects = executeQuery("SELECT s.*, cs.teacher_id, 
                                   CONCAT(tp.first_name, ' ', tp.last_name) as teacher_name
                            FROM subjects s
                            JOIN class_subjects cs ON s.id = cs.subject_id
                            LEFT JOIN teacher_profiles tp ON cs.teacher_id = tp.id
                            WHERE cs.class_id = ?
                            ORDER BY s.name",
                           [$studentProfile['class_id']]);
}

// Get upcoming assignments
$upcomingAssignments = executeQuery("SELECT a.*, s.name as subject_name, 
                                       CONCAT(tp.first_name, ' ', tp.last_name) as teacher_name,
                                       (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submitted
                                FROM assignments a
                                JOIN subjects s ON a.subject_id = s.id
                                JOIN class_subjects cs ON a.subject_id = cs.subject_id AND a.class_id = cs.class_id
                                JOIN teacher_profiles tp ON cs.teacher_id = tp.id
                                WHERE a.class_id = ? AND a.due_date >= CURDATE()
                                ORDER BY a.due_date ASC
                                LIMIT 5", 
                               [$studentId, $studentProfile['class_id']]);

// Get recent grades
$recentGrades = executeQuery("SELECT g.*, s.name as subject_name, e.name as exam_name
                            FROM grades g
                            JOIN subjects s ON g.subject_id = s.id
                            JOIN exams e ON g.exam_id = e.id
                            WHERE g.student_id = ?
                            ORDER BY g.created_at DESC
                            LIMIT 5", 
                           [$studentId]);

// Get attendance statistics
$attendanceStats = executeSingleQuery("SELECT 
                                      SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                                      SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                                      SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
                                      SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count,
                                      COUNT(*) as total_count
                                  FROM attendance
                                  WHERE student_id = ?", 
                                 [$studentId]);

// Calculate attendance percentages for chart
$presentPercentage = 0;
$absentPercentage = 0;
$latePercentage = 0;
$excusedPercentage = 0;

if ($attendanceStats && $attendanceStats['total_count'] > 0) {
    $presentPercentage = round(($attendanceStats['present_count'] / $attendanceStats['total_count']) * 100);
    $absentPercentage = round(($attendanceStats['absent_count'] / $attendanceStats['total_count']) * 100);
    $latePercentage = round(($attendanceStats['late_count'] / $attendanceStats['total_count']) * 100);
    $excusedPercentage = round(($attendanceStats['excused_count'] / $attendanceStats['total_count']) * 100);
}

// Get grade statistics
$gradeStats = executeQuery("SELECT s.name as subject_name, AVG(g.marks_obtained / g.max_marks * 100) as average_percentage
                          FROM grades g
                          JOIN subjects s ON g.subject_id = s.id
                          WHERE g.student_id = ?
                          GROUP BY g.subject_id
                          ORDER BY average_percentage DESC", 
                         [$studentId]);

// Format grade statistics for chart
$subjectLabels = [];
$subjectGrades = [];

if ($gradeStats) {
    foreach ($gradeStats as $grade) {
        $subjectLabels[] = $grade['subject_name'];
        $subjectGrades[] = round($grade['average_percentage'], 1);
    }
}

// Custom scripts for dashboard
$extraScripts = '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Attendance Chart
        const attendanceCtx = document.getElementById("attendanceChart").getContext("2d");
        const attendanceChart = new Chart(attendanceCtx, {
            type: "pie",
            data: {
                labels: ["Present", "Absent", "Late", "Excused"],
                datasets: [{
                    data: [' . $presentPercentage . ', ' . $absentPercentage . ', ' . $latePercentage . ', ' . $excusedPercentage . '],
                    backgroundColor: ["#28a745", "#dc3545", "#ffc107", "#6c757d"]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: "bottom"
                    },
                    title: {
                        display: true,
                        text: "Attendance Overview"
                    }
                }
            }
        });
        
        // Performance Chart
        const performanceCtx = document.getElementById("performanceChart").getContext("2d");
        const performanceChart = new Chart(performanceCtx, {
            type: "bar",
            data: {
                labels: ' . json_encode($subjectLabels) . ',
                datasets: [{
                    label: "Grade Percentage",
                    data: ' . json_encode($subjectGrades) . ',
                    backgroundColor: "#007bff"
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: "Academic Performance"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: "Percentage (%)"
                        }
                    }
                }
            }
        });
    });
</script>';

// Get current academic year
$academicYear = executeSingleQuery("SELECT setting_value FROM settings WHERE setting_key = 'academic_year'");
$academicYear = $academicYear ? $academicYear['setting_value'] : 'Unknown';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Dashboard</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Student</a></li>
                <li class="breadcrumb-item active">Dashboard</li>
            </ul>
        </div>
        <div class="col-auto">
            <span class="badge bg-info p-2">
                Academic Year: <?php echo htmlspecialchars($academicYear); ?>
            </span>
        </div>
    </div>
</div>

<!-- Class Information -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <?php if ($classInfo): ?>
                            <h4 class="card-title">
                                Class: <?php echo htmlspecialchars($classInfo['name']); ?>
                            </h4>
                            <p class="text-muted">
                                <i class="fas fa-users me-2"></i> 
                                <?php echo number_format($classInfo['student_count']); ?> Students
                            </p>
                            <?php if (!empty($classInfo['description'])): ?>
                                <p><?php echo htmlspecialchars($classInfo['description']); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                You are not assigned to any class. Please contact your administrator.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="dropdown">
                            <button class="btn btn-primary dropdown-toggle" type="button" id="quickActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bolt me-2"></i> Quick Actions
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="quickActionsDropdown">
                                <li><a class="dropdown-item" href="attendance.php"><i class="fas fa-calendar-check me-2"></i> View Attendance</a></li>
                                <li><a class="dropdown-item" href="grades.php"><i class="fas fa-chart-line me-2"></i> View Grades</a></li>
                                <li><a class="dropdown-item" href="assignments.php"><i class="fas fa-tasks me-2"></i> View Assignments</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subject Teachers -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">My Subjects & Teachers</h5>
            </div>
            <div class="card-body">
                <?php if ($subjects && count($subjects) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Code</th>
                                    <th>Teacher</th>
                                    <th>Credit Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($subject['code']); ?></span></td>
                                        <td>
                                            <?php if ($subject['teacher_id']): ?>
                                                <?php echo htmlspecialchars($subject['teacher_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $subject['credit_hours']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No subjects assigned to your class yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Performance Charts -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Attendance Overview</h5>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart"></canvas>
            </div>
            <div class="card-footer text-center">
                <div class="row">
                    <div class="col">
                        <span class="d-block fw-bold text-success"><?php echo $presentPercentage; ?>%</span>
                        <small>Present</small>
                    </div>
                    <div class="col">
                        <span class="d-block fw-bold text-danger"><?php echo $absentPercentage; ?>%</span>
                        <small>Absent</small>
                    </div>
                    <div class="col">
                        <span class="d-block fw-bold text-warning"><?php echo $latePercentage; ?>%</span>
                        <small>Late</small>
                    </div>
                    <div class="col">
                        <span class="d-block fw-bold text-secondary"><?php echo $excusedPercentage; ?>%</span>
                        <small>Excused</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Academic Performance</h5>
            </div>
            <div class="card-body">
                <?php if ($gradeStats && count($gradeStats) > 0): ?>
                    <canvas id="performanceChart"></canvas>
                <?php else: ?>
                    <div class="alert alert-info">No grade data available yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Assignments and Recent Grades -->
<div class="row">
    <!-- Upcoming Assignments -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Upcoming Assignments</h5>
            </div>
            <div class="card-body">
                <?php if ($upcomingAssignments && count($upcomingAssignments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingAssignments as $assignment): ?>
                                    <tr>
                                        <td>
                                            <a href="assignments.php?action=view&id=<?php echo $assignment['id']; ?>">
                                                <?php echo htmlspecialchars($assignment['title']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                            <div class="small text-muted">By: <?php echo htmlspecialchars($assignment['teacher_name']); ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                                $dueDate = new DateTime($assignment['due_date']);
                                                $today = new DateTime('today');
                                                $interval = $today->diff($dueDate);
                                                $daysLeft = $interval->days;
                                                $daysLeftClass = 'text-success';
                                                
                                                if ($daysLeft < 3) {
                                                    $daysLeftClass = 'text-danger';
                                                } elseif ($daysLeft < 7) {
                                                    $daysLeftClass = 'text-warning';
                                                }
                                                
                                                echo date('M d, Y', strtotime($assignment['due_date']));
                                                echo ' <span class="' . $daysLeftClass . '">(' . $daysLeft . ' days left)</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($assignment['submitted'] > 0): ?>
                                                <span class="badge bg-success">Submitted</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No upcoming assignments.</div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="assignments.php" class="btn btn-sm btn-outline-primary">View All Assignments</a>
            </div>
        </div>
    </div>
    
    <!-- Recent Grades -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Recent Grades</h5>
            </div>
            <div class="card-body">
                <?php if ($recentGrades && count($recentGrades) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Exam</th>
                                    <th>Marks</th>
                                    <th>Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentGrades as $grade): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($grade['exam_name']); ?></td>
                                        <td>
                                            <?php 
                                                echo $grade['marks_obtained'] . ' / ' . $grade['max_marks'];
                                                $percentage = ($grade['marks_obtained'] / $grade['max_marks']) * 100;
                                                echo ' <span class="text-muted">(' . round($percentage, 1) . '%)</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $gradeClass = 'bg-success';
                                                
                                                if ($grade['grade'] === 'F') {
                                                    $gradeClass = 'bg-danger';
                                                } elseif ($grade['grade'] === 'D') {
                                                    $gradeClass = 'bg-warning';
                                                } elseif ($grade['grade'] === 'C') {
                                                    $gradeClass = 'bg-info';
                                                } elseif ($grade['grade'] === 'B') {
                                                    $gradeClass = 'bg-primary';
                                                }
                                                
                                                echo '<span class="badge ' . $gradeClass . '">' . $grade['grade'] . '</span>';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No grade records found.</div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="grades.php" class="btn btn-sm btn-outline-primary">View All Grades</a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 