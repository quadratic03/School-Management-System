<?php
/**
 * Teacher Dashboard
 */

// Set page title
$pageTitle = 'Teacher Dashboard';

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

// Get statistics for dashboard
$totalClasses = executeSingleQuery("SELECT COUNT(*) as count FROM class_subjects WHERE teacher_id = ?", [$teacherId]);
$totalClasses = $totalClasses ? $totalClasses['count'] : 0;

$totalStudents = executeSingleQuery("
    SELECT COUNT(DISTINCT sp.id) as count 
    FROM student_profiles sp
    JOIN classes c ON sp.class_id = c.id
    JOIN class_subjects cs ON c.id = cs.class_id
    WHERE cs.teacher_id = ?
", [$teacherId]);
$totalStudents = $totalStudents ? $totalStudents['count'] : 0;

$totalAssignments = executeSingleQuery("
    SELECT COUNT(*) as count 
    FROM assignments 
    WHERE created_by = ?
", [$currentUser['id']]);
$totalAssignments = $totalAssignments ? $totalAssignments['count'] : 0;

$pendingSubmissions = executeSingleQuery("
    SELECT COUNT(*) as count 
    FROM assignment_submissions as s
    JOIN assignments as a ON s.assignment_id = a.id
    WHERE a.created_by = ? AND s.score IS NULL
", [$currentUser['id']]);
$pendingSubmissions = $pendingSubmissions ? $pendingSubmissions['count'] : 0;

// Get teacher's classes
$classes = executeQuery("
    SELECT c.id, c.name, s.name as subject_name, s.code as subject_code,
           (SELECT COUNT(*) FROM student_profiles WHERE class_id = c.id) as student_count
    FROM classes c
    JOIN class_subjects cs ON c.id = cs.class_id
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ?
    ORDER BY c.name
", [$teacherId]);

// Get upcoming assignments
$upcomingAssignments = executeQuery("
    SELECT a.*, c.name as class_name, s.name as subject_name,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN subjects s ON a.subject_id = s.id
    WHERE a.created_by = ? AND a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
", [$currentUser['id']]);

// Get recent submissions
$recentSubmissions = executeQuery("
    SELECT s.*, a.title as assignment_title, a.due_date, 
           sp.first_name, sp.last_name, c.name as class_name
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN student_profiles sp ON s.student_id = sp.id
    JOIN classes c ON a.class_id = c.id
    WHERE a.created_by = ?
    ORDER BY s.submission_date DESC
    LIMIT 5
", [$currentUser['id']]);

// Custom scripts for dashboard
$extraScripts = '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Submissions Chart
        const submissionsCtx = document.getElementById("submissionsChart").getContext("2d");
        const submissionsChart = new Chart(submissionsCtx, {
            type: "bar",
            data: {
                labels: ["On Time", "Late", "Missing"],
                datasets: [{
                    label: "Submissions",
                    data: [65, 15, 20],
                    backgroundColor: ["#28a745", "#ffc107", "#dc3545"]
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
                        text: "Assignment Submissions Overview"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: "Percentage"
                        }
                    }
                }
            }
        });
        
        // Grade Distribution Chart
        const gradesCtx = document.getElementById("gradesChart").getContext("2d");
        const gradesChart = new Chart(gradesCtx, {
            type: "pie",
            data: {
                labels: ["A", "B", "C", "D", "F"],
                datasets: [{
                    data: [25, 30, 25, 15, 5],
                    backgroundColor: ["#28a745", "#20c997", "#17a2b8", "#fd7e14", "#dc3545"]
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
                        text: "Grade Distribution"
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
                <li class="breadcrumb-item"><a href="dashboard.php">Teacher</a></li>
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

<!-- Stats Cards -->
<div class="row">
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-light">
            <div class="card-body">
                <div class="card-stats">
                    <div class="icon text-primary">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="value"><?php echo number_format($totalClasses); ?></div>
                    <div class="label">My Classes</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-light">
            <div class="card-body">
                <div class="card-stats">
                    <div class="icon text-info">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="value"><?php echo number_format($totalStudents); ?></div>
                    <div class="label">My Students</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-light">
            <div class="card-body">
                <div class="card-stats">
                    <div class="icon text-warning">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="value"><?php echo number_format($totalAssignments); ?></div>
                    <div class="label">Assignments</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-light">
            <div class="card-body">
                <div class="card-stats">
                    <div class="icon text-danger">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="value"><?php echo number_format($pendingSubmissions); ?></div>
                    <div class="label">Pending Reviews</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- My Classes and Charts -->
<div class="row">
    <!-- My Classes -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">My Classes</h5>
            </div>
            <div class="card-body">
                <?php if ($classes && count($classes) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Students</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class['name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($class['subject_name']); ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($class['subject_code']); ?></span>
                                        </td>
                                        <td><?php echo number_format($class['student_count']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="students.php?class_id=<?php echo $class['id']; ?>" class="btn btn-outline-info" title="View Students">
                                                    <i class="fas fa-users"></i>
                                                </a>
                                                <a href="attendance.php?class_id=<?php echo $class['id']; ?>" class="btn btn-outline-primary" title="Take Attendance">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </a>
                                                <a href="assignments.php?class_id=<?php echo $class['id']; ?>" class="btn btn-outline-warning" title="Assignments">
                                                    <i class="fas fa-tasks"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        You don't have any assigned classes yet. Please contact the administrator.
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="classes.php" class="btn btn-sm btn-outline-primary">View All Classes</a>
            </div>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="col-md-6">
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Submissions Status</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="submissionsChart" height="170"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Grade Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="gradesChart" height="170"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upcoming Assignments and Recent Submissions -->
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
                                    <th>Class & Subject</th>
                                    <th>Due Date</th>
                                    <th>Submissions</th>
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
                                        <td><?php echo htmlspecialchars($assignment['class_name'] . ' - ' . $assignment['subject_name']); ?></td>
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
                                        <td><?php echo number_format($assignment['submission_count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No upcoming assignments found. <a href="assignments.php?action=add" class="alert-link">Create one now</a>.
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="assignments.php" class="btn btn-sm btn-outline-primary">View All Assignments</a>
                <a href="assignments.php?action=add" class="btn btn-sm btn-primary float-end">
                    <i class="fas fa-plus"></i> Create Assignment
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recent Submissions -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Recent Submissions</h5>
            </div>
            <div class="card-body">
                <?php if ($recentSubmissions && count($recentSubmissions) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Assignment</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSubmissions as $submission): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></td>
                                        <td>
                                            <a href="assignments.php?action=view&id=<?php echo $submission['assignment_id']; ?>">
                                                <?php echo htmlspecialchars($submission['assignment_title']); ?>
                                            </a>
                                            <div class="small text-muted"><?php echo htmlspecialchars($submission['class_name']); ?></div>
                                        </td>
                                        <td>
                                            <?php
                                                $status = 'Pending';
                                                $statusClass = 'bg-warning';
                                                
                                                if ($submission['score'] !== null) {
                                                    $status = 'Graded';
                                                    $statusClass = 'bg-success';
                                                } elseif (strtotime($submission['submission_date']) > strtotime($submission['due_date'])) {
                                                    $status = 'Late';
                                                    $statusClass = 'bg-danger';
                                                }
                                                
                                                echo '<span class="badge ' . $statusClass . '">' . $status . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <a href="assignments.php?action=grade&id=<?php echo $submission['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-check-circle"></i> Grade
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No recent submissions found.
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="assignments.php?view=submissions" class="btn btn-sm btn-outline-primary">View All Submissions</a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 