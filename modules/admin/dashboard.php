<?php
/**
 * Admin Dashboard
 */

// Set page title
$pageTitle = 'Admin Dashboard';

// Include header
require_once '../../includes/header.php';

// Check if user is logged in and has admin role
requireAuth('admin');

// Get statistics for dashboard
$totalStudents = executeSingleQuery("SELECT COUNT(*) as count FROM student_profiles");
$totalStudents = $totalStudents ? $totalStudents['count'] : 0;

$totalTeachers = executeSingleQuery("SELECT COUNT(*) as count FROM teacher_profiles");
$totalTeachers = $totalTeachers ? $totalTeachers['count'] : 0;

$totalClasses = executeSingleQuery("SELECT COUNT(*) as count FROM classes");
$totalClasses = $totalClasses ? $totalClasses['count'] : 0;

$totalSubjects = executeSingleQuery("SELECT COUNT(*) as count FROM subjects");
$totalSubjects = $totalSubjects ? $totalSubjects['count'] : 0;

// Get recent activities
$recentActivities = executeQuery("SELECT a.*, u.username, u.role 
                                FROM activity_logs a 
                                JOIN users u ON a.user_id = u.id 
                                ORDER BY a.created_at DESC 
                                LIMIT 10");

// Custom scripts for dashboard
$extraScripts = '<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Charts initialization
        const attendanceCtx = document.getElementById("attendanceChart").getContext("2d");
        const performanceCtx = document.getElementById("performanceChart").getContext("2d");
        const enrollmentCtx = document.getElementById("enrollmentChart").getContext("2d");
        
        // Attendance Chart
        const attendanceChart = new Chart(attendanceCtx, {
            type: "pie",
            data: {
                labels: ["Present", "Absent", "Late", "Excused"],
                datasets: [{
                    data: [85, 5, 8, 2],
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
                        text: "Overall Attendance Statistics"
                    }
                }
            }
        });
        
        // Performance Chart
        const performanceChart = new Chart(performanceCtx, {
            type: "bar",
            data: {
                labels: ["A", "B", "C", "D", "F"],
                datasets: [{
                    label: "Students",
                    data: [125, 190, 140, 85, 36],
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
                        text: "Grade Distribution"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: "Number of Students"
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: "Grades"
                        }
                    }
                }
            }
        });
        
        // Enrollment Chart
        const enrollmentChart = new Chart(enrollmentCtx, {
            type: "line",
            data: {
                labels: ["2018", "2019", "2020", "2021", "2022", "2023"],
                datasets: [{
                    label: "Total Students",
                    data: [750, 820, 900, 850, 950, ' . $totalStudents . '],
                    borderColor: "#007bff",
                    backgroundColor: "rgba(0, 123, 255, 0.1)",
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: "Enrollment Trends"
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: "Number of Students"
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: "Year"
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

<?php include_once '../../includes/admin_nav.php'; ?>

<!-- Page Header -->
<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title mb-0">Dashboard</h3>
            <ul class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active">Dashboard</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center">
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
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="value"><?php echo number_format($totalStudents); ?></div>
                    <div class="label">Students</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-light">
            <div class="card-body">
                <div class="card-stats">
                    <div class="icon text-info">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="value"><?php echo number_format($totalTeachers); ?></div>
                    <div class="label">Teachers</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-light">
            <div class="card-body">
                <div class="card-stats">
                    <div class="icon text-success">
                        <i class="fas fa-school"></i>
                    </div>
                    <div class="value"><?php echo number_format($totalClasses); ?></div>
                    <div class="label">Classes</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-light">
            <div class="card-body">
                <div class="card-stats">
                    <div class="icon text-warning">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="value"><?php echo number_format($totalSubjects); ?></div>
                    <div class="label">Subjects</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Recent Activities -->
<div class="row">
    <!-- Charts Row -->
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Attendance Overview</h5>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Grade Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Enrollment Trends</h5>
            </div>
            <div class="card-body">
                <canvas id="enrollmentChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities -->
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Recent Activities</h5>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <?php if ($recentActivities && count($recentActivities) > 0): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <li class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <span class="badge bg-<?php 
                                            $role = $activity['role'];
                                            echo $role === 'admin' ? 'danger' : ($role === 'teacher' ? 'info' : 'success');
                                        ?>">
                                            <?php echo ucfirst(htmlspecialchars($activity['role'])); ?>
                                        </span>
                                        <?php echo htmlspecialchars($activity['username']); ?>
                                    </h6>
                                    <small><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($activity['action']); ?></p>
                                <?php if (!empty($activity['details'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($activity['details']); ?></small>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item">No recent activities found.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="card-footer">
                <a href="activity-logs.php" class="btn btn-sm btn-outline-primary">View All Activities</a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 