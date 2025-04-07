<?php
/**
 * Teacher Attendance Management
 */

// Set page title
$pageTitle = 'Attendance Management';

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
    SELECT DISTINCT c.id, c.name
    FROM classes c
    JOIN class_subjects cs ON c.id = cs.class_id
    WHERE cs.teacher_id = ?
    ORDER BY c.name
", [$teacherId]);

// Handle attendance form submission
$success = '';
$error = '';
$students = [];
$selectedClass = 0;
$selectedDate = date('Y-m-d');

if (isset($_GET['class_id'])) {
    $selectedClass = (int)$_GET['class_id'];
}

if (isset($_GET['date'])) {
    $selectedDate = sanitize($_GET['date']);
}

// Get students for selected class
if ($selectedClass > 0) {
    $students = executeQuery("
        SELECT sp.id, sp.first_name, sp.last_name, sp.admission_number, sp.profile_image
        FROM student_profiles sp
        WHERE sp.class_id = ?
        ORDER BY sp.last_name, sp.first_name
    ", [$selectedClass]);

    // Check if attendance already exists for this class and date
    $existingAttendance = [];
    $attendanceRecords = executeQuery("
        SELECT student_id, status
        FROM attendance
        WHERE class_id = ? AND date = ?
    ", [$selectedClass, $selectedDate]);

    foreach ($attendanceRecords as $record) {
        $existingAttendance[$record['student_id']] = $record['status'];
    }
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $classId = (int)$_POST['class_id'];
    $date = sanitize($_POST['date']);
    $studentIds = $_POST['student_id'];
    $statuses = $_POST['status'];
    
    if (!$classId || !$date || !$studentIds || !$statuses) {
        $error = 'Invalid attendance data.';
    } else {
        try {
            // Begin transaction
            startTransaction();
            
            // Delete existing attendance for this class and date
            executeNonQuery("DELETE FROM attendance WHERE class_id = ? AND date = ?", [$classId, $date]);
            
            // Insert new attendance records
            $insertSql = "INSERT INTO attendance (class_id, student_id, date, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            
            foreach ($studentIds as $key => $studentId) {
                $status = $statuses[$key];
                executeNonQuery($insertSql, [$classId, $studentId, $date, $status, $currentUser['id']]);
            }
            
            // Commit transaction
            commitTransaction();
            
            // Log activity
            logActivity($currentUser['id'], 'Recorded attendance', 'Class: ' . $classId . ', Date: ' . $date);
            
            $success = 'Attendance has been recorded successfully.';
            
            // Reload attendance records
            $existingAttendance = [];
            $attendanceRecords = executeQuery("
                SELECT student_id, status
                FROM attendance
                WHERE class_id = ? AND date = ?
            ", [$classId, $date]);
            
            foreach ($attendanceRecords as $record) {
                $existingAttendance[$record['student_id']] = $record['status'];
            }
        } catch (Exception $e) {
            // Rollback transaction
            rollbackTransaction();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get attendance summary for this month
$currentMonth = date('m');
$currentYear = date('Y');
$attendanceSummary = [];

if ($selectedClass > 0) {
    $summary = executeQuery("
        SELECT 
            a.student_id,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
            COUNT(*) as total_days
        FROM attendance a
        WHERE a.class_id = ? AND MONTH(a.date) = ? AND YEAR(a.date) = ?
        GROUP BY a.student_id
    ", [$selectedClass, $currentMonth, $currentYear]);
    
    foreach ($summary as $record) {
        $attendanceSummary[$record['student_id']] = $record;
    }
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Attendance Management</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Teacher</a></li>
                <li class="breadcrumb-item active">Attendance</li>
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

<!-- Attendance Filter -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Select Class and Date</h5>
            </div>
            <div class="card-body">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-5">
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
                    <div class="col-md-5">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date" value="<?php echo $selectedDate; ?>" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Load Students
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Form -->
<?php if ($selectedClass > 0 && count($students) > 0): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Record Attendance</h5>
                <h6 class="card-subtitle mb-2 text-muted">
                    Class: <?php echo htmlspecialchars(getClassNameById($selectedClass)); ?> | 
                    Date: <?php echo date('d M Y', strtotime($selectedDate)); ?>
                </h6>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <input type="hidden" name="class_id" value="<?php echo $selectedClass; ?>">
                    <input type="hidden" name="date" value="<?php echo $selectedDate; ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Admission #</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <?php 
                                    $currentStatus = isset($existingAttendance[$student['id']]) ? $existingAttendance[$student['id']] : 'present';
                                    ?>
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
                                                    <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <input type="radio" class="btn-check" name="status[<?php echo $student['id']; ?>]" id="present_<?php echo $student['id']; ?>" value="present" <?php echo $currentStatus == 'present' ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-success" for="present_<?php echo $student['id']; ?>">Present</label>
                                                
                                                <input type="radio" class="btn-check" name="status[<?php echo $student['id']; ?>]" id="absent_<?php echo $student['id']; ?>" value="absent" <?php echo $currentStatus == 'absent' ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-danger" for="absent_<?php echo $student['id']; ?>">Absent</label>
                                                
                                                <input type="radio" class="btn-check" name="status[<?php echo $student['id']; ?>]" id="late_<?php echo $student['id']; ?>" value="late" <?php echo $currentStatus == 'late' ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-warning" for="late_<?php echo $student['id']; ?>">Late</label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm" name="notes[<?php echo $student['id']; ?>]" placeholder="Optional notes">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="text-end mt-3">
                        <button type="submit" name="submit_attendance" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Summary for Current Month -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Attendance Summary for <?php echo date('F Y'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered datatable">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Present Days</th>
                                <th>Absent Days</th>
                                <th>Late Days</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $summary = isset($attendanceSummary[$student['id']]) ? $attendanceSummary[$student['id']] : null;
                                $presentCount = $summary ? $summary['present_count'] : 0;
                                $absentCount = $summary ? $summary['absent_count'] : 0;
                                $lateCount = $summary ? $summary['late_count'] : 0;
                                $totalDays = $summary ? $summary['total_days'] : 0;
                                
                                $attendancePercentage = $totalDays > 0 ? round(($presentCount / $totalDays) * 100) : 0;
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td><?php echo $presentCount; ?></td>
                                    <td><?php echo $absentCount; ?></td>
                                    <td><?php echo $lateCount; ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo getAttendanceColorClass($attendancePercentage); ?>" 
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
                </div>
            </div>
        </div>
    </div>
</div>
<?php elseif ($selectedClass > 0 && count($students) == 0): ?>
<div class="alert alert-info mt-4">
    <i class="fas fa-info-circle"></i> No students found in this class.
</div>
<?php endif; ?>

<script>
    // Initialize DataTable
    $(document).ready(function() {
        $('.datatable').DataTable({
            responsive: true,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            pageLength: 25
        });
    });
    
    // Function to get attendance color class based on percentage
    function getAttendanceColorClass(percentage) {
        if (percentage >= 90) return 'success';
        if (percentage >= 75) return 'info';
        if (percentage >= 50) return 'warning';
        return 'danger';
    }
</script>

<?php
// Include footer
require_once '../../includes/footer.php';

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
    if ($percentage >= 50) return 'warning';
    return 'danger';
}
?> 