<?php
/**
 * Attendance Management
 * 
 * This file handles the student attendance tracking
 */

$pageTitle = "Attendance Management";
require_once '../../includes/header.php';
requireAuth('admin');

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = '';
$success = '';

// Get filter parameters
$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_attendance'])) {
        // Sanitize input
        $classId = (int)$_POST['class_id'];
        $date = sanitizeInput($_POST['date']);
        $attendanceData = $_POST['attendance'];
        
        try {
            // Begin transaction
            startTransaction();
            
            // Delete existing attendance records for the class and date
            $deleteSql = "DELETE FROM attendance 
                         WHERE class_id = ? AND date = ?";
            executeQuery($deleteSql, [$classId, $date]);
            
            // Insert new attendance records
            $insertSql = "INSERT INTO attendance (student_id, class_id, date, status, remarks) 
                         VALUES (?, ?, ?, ?, ?)";
            
            foreach ($attendanceData as $studentId => $data) {
                $params = [
                    $studentId,
                    $classId,
                    $date,
                    $data['status'],
                    sanitizeInput($data['remarks'])
                ];
                executeQuery($insertSql, $params);
            }
            
            // Log activity
            logActivity($currentUser['id'], 'Marked attendance for class ID: ' . $classId . ' on ' . $date);
            
            // Commit transaction
            commitTransaction();
            
            $success = "Attendance marked successfully.";
        } catch (Exception $e) {
            // Rollback transaction on error
            rollbackTransaction();
            $error = "Error marking attendance: " . $e->getMessage();
        }
    }
}

// Fetch all classes for filter
$classes = executeQuery("
    SELECT c.*, t.first_name, t.last_name 
    FROM classes c
    LEFT JOIN teacher_profiles t ON c.teacher_id = t.user_id
    ORDER BY c.grade_level, c.class_name
");

// Initialize $classes as an empty array if the query returns false
if (!$classes) {
    $classes = [];
}

// Fetch students and their attendance for selected class and date
$students = [];
$attendance = [];
if ($classId) {
    // Get students in the class
    $students = executeQuery("
        SELECT s.*, u.id as user_id
        FROM student_profiles s
        JOIN users u ON s.user_id = u.id
        JOIN student_classes sc ON u.id = sc.student_id
        WHERE sc.class_id = ? AND sc.status = 'active'
        ORDER BY s.last_name, s.first_name
    ", [$classId]);
    
    // Initialize $students as an empty array if the query returns false
    if (!$students) {
        $students = [];
    }
    
    // Get existing attendance records
    $attendanceRecords = executeQuery("
        SELECT * FROM attendance 
        WHERE class_id = ? AND date = ?
    ", [$classId, $date]);
    
    // Initialize $attendanceRecords as an empty array if the query returns false
    if (!$attendanceRecords) {
        $attendanceRecords = [];
    }
    
    // Convert attendance records to array for easy access
    foreach ($attendanceRecords as $record) {
        $attendance[$record['student_id']] = $record;
    }
}
?>

<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title mb-0">Attendance Management</h3>
            <ul class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active">Attendance</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Attendance Management</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <!-- Filter Form -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="class_id" class="form-label">Select Class</label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $classId == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name'] . ' - Grade ' . $class['grade_level'] . ' ' . $class['section']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" 
                                   value="<?php echo $date; ?>" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">View Attendance</button>
                        </div>
                    </form>
                    
                    <?php if ($classId && !empty($students)): ?>
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="class_id" value="<?php echo $classId; ?>">
                            <input type="hidden" name="date" value="<?php echo $date; ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($student['student_id']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <input type="radio" class="btn-check" 
                                                               name="attendance[<?php echo $student['user_id']; ?>][status]" 
                                                               id="present_<?php echo $student['user_id']; ?>" 
                                                               value="present"
                                                               <?php echo isset($attendance[$student['user_id']]) && $attendance[$student['user_id']]['status'] === 'present' ? 'checked' : ''; ?>>
                                                        <label class="btn btn-outline-success" for="present_<?php echo $student['user_id']; ?>">
                                                            Present
                                                        </label>
                                                        
                                                        <input type="radio" class="btn-check" 
                                                               name="attendance[<?php echo $student['user_id']; ?>][status]" 
                                                               id="absent_<?php echo $student['user_id']; ?>" 
                                                               value="absent"
                                                               <?php echo isset($attendance[$student['user_id']]) && $attendance[$student['user_id']]['status'] === 'absent' ? 'checked' : ''; ?>>
                                                        <label class="btn btn-outline-danger" for="absent_<?php echo $student['user_id']; ?>">
                                                            Absent
                                                        </label>
                                                        
                                                        <input type="radio" class="btn-check" 
                                                               name="attendance[<?php echo $student['user_id']; ?>][status]" 
                                                               id="late_<?php echo $student['user_id']; ?>" 
                                                               value="late"
                                                               <?php echo isset($attendance[$student['user_id']]) && $attendance[$student['user_id']]['status'] === 'late' ? 'checked' : ''; ?>>
                                                        <label class="btn btn-outline-warning" for="late_<?php echo $student['user_id']; ?>">
                                                            Late
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control form-control-sm" 
                                                           name="attendance[<?php echo $student['user_id']; ?>][remarks]" 
                                                           value="<?php echo isset($attendance[$student['user_id']]) ? htmlspecialchars($attendance[$student['user_id']]['remarks']) : ''; ?>"
                                                           placeholder="Optional remarks">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="text-end mt-3">
                                <button type="submit" name="mark_attendance" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Attendance
                                </button>
                            </div>
                        </form>
                    <?php elseif ($classId): ?>
                        <div class="alert alert-info">
                            No students found in this class.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Please select a class to view attendance.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 