<?php
/**
 * Teacher Classes Management
 */

// Set page title
$pageTitle = 'My Classes';

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

// Get all classes assigned to this teacher
$classes = executeQuery("
    SELECT c.id, c.name, c.description, c.academic_year, s.id as subject_id, s.name as subject_name, 
           s.code as subject_code, 
           (SELECT COUNT(*) FROM student_profiles WHERE class_id = c.id) as student_count
    FROM classes c
    JOIN class_subjects cs ON c.id = cs.class_id
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.teacher_id = ?
    ORDER BY c.name, s.name
", [$teacherId]);

// Initialize the classes array if it's false or null
if (!$classes) {
    $classes = [];
}

// Group classes by class ID
$groupedClasses = [];
foreach ($classes as $class) {
    $classId = $class['id'];
    
    if (!isset($groupedClasses[$classId])) {
        $groupedClasses[$classId] = [
            'id' => $class['id'],
            'name' => $class['name'],
            'description' => $class['description'],
            'academic_year' => $class['academic_year'],
            'student_count' => $class['student_count'],
            'subjects' => []
        ];
    }
    
    $groupedClasses[$classId]['subjects'][] = [
        'id' => $class['subject_id'],
        'name' => $class['subject_name'],
        'code' => $class['subject_code']
    ];
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">My Classes</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Teacher</a></li>
                <li class="breadcrumb-item active">Classes</li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Class List</h5>
            </div>
            <div class="card-body">
                <?php if (count($groupedClasses) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover datatable">
                            <thead>
                                <tr>
                                    <th>Class</th>
                                    <th>Description</th>
                                    <th>Academic Year</th>
                                    <th>Subjects</th>
                                    <th>Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedClasses as $class): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class['name']); ?></td>
                                        <td><?php echo htmlspecialchars($class['description']); ?></td>
                                        <td><?php echo htmlspecialchars($class['academic_year']); ?></td>
                                        <td>
                                            <?php foreach ($class['subjects'] as $subject): ?>
                                                <span class="badge bg-info me-1 mb-1">
                                                    <?php echo htmlspecialchars($subject['name']); ?> (<?php echo htmlspecialchars($subject['code']); ?>)
                                                </span>
                                            <?php endforeach; ?>
                                        </td>
                                        <td><?php echo $class['student_count']; ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="students.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-users"></i> View Students
                                                </a>
                                                <a href="attendance.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-calendar-check"></i> Attendance
                                                </a>
                                                <a href="assignments.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-tasks"></i> Assignments
                                                </a>
                                                <a href="grades.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-chart-line"></i> Grades
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No classes found. You are not assigned to any classes.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Class Schedule -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">My Schedule</h5>
            </div>
            <div class="card-body">
                <?php if (is_array($classes) && count($classes) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="100">Time</th>
                                    <th>Monday</th>
                                    <th>Tuesday</th>
                                    <th>Wednesday</th>
                                    <th>Thursday</th>
                                    <th>Friday</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Example schedule rows -->
                                <tr>
                                    <td>8:00 - 9:00</td>
                                    <td class="bg-light-info">Math - Class 10A</td>
                                    <td></td>
                                    <td class="bg-light-info">Math - Class 10A</td>
                                    <td></td>
                                    <td class="bg-light-info">Math - Class 10A</td>
                                </tr>
                                <tr>
                                    <td>9:00 - 10:00</td>
                                    <td></td>
                                    <td class="bg-light-warning">Math - Class 11B</td>
                                    <td></td>
                                    <td class="bg-light-warning">Math - Class 11B</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>10:00 - 11:00</td>
                                    <td class="bg-light-success">Math - Class 9C</td>
                                    <td></td>
                                    <td class="bg-light-success">Math - Class 9C</td>
                                    <td></td>
                                    <td class="bg-light-success">Math - Class 9C</td>
                                </tr>
                                <tr>
                                    <td>11:00 - 12:00</td>
                                    <td></td>
                                    <td class="bg-light-danger">Math - Class 12D</td>
                                    <td></td>
                                    <td class="bg-light-danger">Math - Class 12D</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>12:00 - 1:00</td>
                                    <td colspan="5" class="text-center">Lunch Break</td>
                                </tr>
                                <tr>
                                    <td>1:00 - 2:00</td>
                                    <td class="bg-light-danger">Math - Class 12D</td>
                                    <td></td>
                                    <td class="bg-light-danger">Math - Class 12D</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>2:00 - 3:00</td>
                                    <td></td>
                                    <td class="bg-light-success">Math - Class 9C</td>
                                    <td></td>
                                    <td class="bg-light-success">Math - Class 9C</td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Note: This is a sample schedule. The actual schedule will be populated based on your class assignments.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No schedule found. You are not assigned to any classes.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize DataTable for classes list
    $(document).ready(function() {
        $('.datatable').DataTable({
            responsive: true,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            pageLength: 10,
        });
    });
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 