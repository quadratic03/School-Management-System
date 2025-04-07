<?php
/**
 * Teacher Students Management
 */

// Set page title
$pageTitle = 'My Students';

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

// Get all students assigned to this teacher's classes
$students = executeQuery("
    SELECT DISTINCT sp.id, sp.user_id, sp.first_name, sp.last_name, sp.admission_number, 
           sp.gender, sp.profile_image, c.name as class_name, c.id as class_id
    FROM student_profiles sp
    JOIN classes c ON sp.class_id = c.id
    JOIN class_subjects cs ON c.id = cs.class_id
    WHERE cs.teacher_id = ?
    ORDER BY c.name, sp.last_name, sp.first_name
", [$teacherId]);

// Get subjects taught by this teacher
$subjects = executeQuery("
    SELECT s.id, s.name, s.code, c.id as class_id, c.name as class_name
    FROM subjects s
    JOIN class_subjects cs ON s.id = cs.subject_id
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
    ORDER BY c.name, s.name
", [$teacherId]);

// Handle student filter by class
$filteredStudents = $students;
$selectedClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

if ($selectedClass > 0) {
    $filteredStudents = [];
    foreach ($students as $student) {
        if ($student['class_id'] == $selectedClass) {
            $filteredStudents[] = $student;
        }
    }
}

// Group classes for the filter dropdown
$classes = [];
foreach ($subjects as $subject) {
    $classId = $subject['class_id'];
    $className = $subject['class_name'];
    
    if (!isset($classes[$classId])) {
        $classes[$classId] = $className;
    }
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">My Students</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Teacher</a></li>
                <li class="breadcrumb-item active">Students</li>
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
                <div class="row align-items-center">
                    <div class="col">
                        <h5 class="card-title">Student List</h5>
                    </div>
                    <div class="col-auto">
                        <!-- Class Filter -->
                        <form method="get" class="d-flex align-items-center">
                            <label for="class_filter" class="me-2">Filter by Class:</label>
                            <select name="class_id" id="class_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="0">All Classes</option>
                                <?php foreach ($classes as $classId => $className): ?>
                                    <option value="<?php echo $classId; ?>" <?php echo $selectedClass == $classId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($className); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($filteredStudents) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover datatable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Admission #</th>
                                    <th>Class</th>
                                    <th>Gender</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredStudents as $student): ?>
                                    <tr>
                                        <td><?php echo $student['id']; ?></td>
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
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="student_details.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="grades.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-chart-line"></i> Grades
                                                </a>
                                                <a href="attendance.php?student_id=<?php echo $student['id']; ?>" class="btn btn-sm btn-secondary">
                                                    <i class="fas fa-calendar-check"></i> Attendance
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No students found. You are not assigned to any classes with students.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize DataTable for student list
    $(document).ready(function() {
        $('.datatable').DataTable({
            responsive: true,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            pageLength: 25,
        });
    });
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 