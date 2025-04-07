<?php
/**
 * Reports Generation
 */

// Set page title
$pageTitle = 'Reports';

// Include header
require_once '../../includes/header.php';

// Check if user is logged in and has admin role
requireAuth('admin');

// Initialize variables
$error = '';
$success = '';
$reportType = isset($_GET['type']) ? $_GET['type'] : '';
$academicYear = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';

// Get academic years for dropdown
$academicYears = executeQuery("
    SELECT DISTINCT academic_year 
    FROM settings 
    WHERE setting_key = 'academic_year' 
    ORDER BY academic_year DESC
");

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $reportType = sanitize($_POST['report_type']);
    $academicYear = sanitize($_POST['academic_year']);
    
    try {
        switch ($reportType) {
            case 'attendance':
                // Generate attendance report
                $reportData = executeQuery("
                    SELECT 
                        sp.first_name, sp.last_name, sp.admission_number,
                        c.name as class_name,
                        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days
                    FROM student_profiles sp
                    JOIN classes c ON sp.class_id = c.id
                    LEFT JOIN attendance a ON sp.id = a.student_id
                    WHERE a.academic_year = ?
                    GROUP BY sp.id
                    ORDER BY c.name, sp.first_name
                ", [$academicYear]);
                
                // Generate CSV
                $filename = "attendance_report_{$academicYear}.csv";
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Name', 'Admission No.', 'Class', 'Present Days', 'Absent Days', 'Late Days']);
                
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['first_name'] . ' ' . $row['last_name'],
                        $row['admission_number'],
                        $row['class_name'],
                        $row['present_days'],
                        $row['absent_days'],
                        $row['late_days']
                    ]);
                }
                
                fclose($output);
                exit;
                
            case 'grades':
                // Generate grades report
                $reportData = executeQuery("
                    SELECT 
                        sp.first_name, sp.last_name, sp.admission_number,
                        c.name as class_name,
                        s.name as subject_name,
                        g.score, g.grade, g.remarks
                    FROM student_profiles sp
                    JOIN classes c ON sp.class_id = c.id
                    JOIN grades g ON sp.id = g.student_id
                    JOIN subjects s ON g.subject_id = s.id
                    WHERE g.academic_year = ?
                    ORDER BY c.name, sp.first_name, s.name
                ", [$academicYear]);
                
                // Generate CSV
                $filename = "grades_report_{$academicYear}.csv";
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Name', 'Admission No.', 'Class', 'Subject', 'Score', 'Grade', 'Remarks']);
                
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['first_name'] . ' ' . $row['last_name'],
                        $row['admission_number'],
                        $row['class_name'],
                        $row['subject_name'],
                        $row['score'],
                        $row['grade'],
                        $row['remarks']
                    ]);
                }
                
                fclose($output);
                exit;
                
            case 'enrollment':
                // Generate enrollment report
                $reportData = executeQuery("
                    SELECT 
                        c.name as class_name,
                        COUNT(sp.id) as total_students,
                        COUNT(CASE WHEN sp.gender = 'male' THEN 1 END) as male_students,
                        COUNT(CASE WHEN sp.gender = 'female' THEN 1 END) as female_students
                    FROM classes c
                    LEFT JOIN student_profiles sp ON c.id = sp.class_id
                    WHERE sp.academic_year = ?
                    GROUP BY c.id
                    ORDER BY c.name
                ", [$academicYear]);
                
                // Generate CSV
                $filename = "enrollment_report_{$academicYear}.csv";
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Class', 'Total Students', 'Male Students', 'Female Students']);
                
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['class_name'],
                        $row['total_students'],
                        $row['male_students'],
                        $row['female_students']
                    ]);
                }
                
                fclose($output);
                exit;
                
            case 'performance':
                // Generate performance report
                $reportData = executeQuery("
                    SELECT 
                        c.name as class_name,
                        s.name as subject_name,
                        COUNT(g.id) as total_students,
                        AVG(g.score) as average_score,
                        COUNT(CASE WHEN g.grade = 'A' THEN 1 END) as grade_a,
                        COUNT(CASE WHEN g.grade = 'B' THEN 1 END) as grade_b,
                        COUNT(CASE WHEN g.grade = 'C' THEN 1 END) as grade_c,
                        COUNT(CASE WHEN g.grade = 'D' THEN 1 END) as grade_d,
                        COUNT(CASE WHEN g.grade = 'F' THEN 1 END) as grade_f
                    FROM classes c
                    JOIN grades g ON c.id = g.class_id
                    JOIN subjects s ON g.subject_id = s.id
                    WHERE g.academic_year = ?
                    GROUP BY c.id, s.id
                    ORDER BY c.name, s.name
                ", [$academicYear]);
                
                // Generate CSV
                $filename = "performance_report_{$academicYear}.csv";
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Class', 'Subject', 'Total Students', 'Average Score', 
                                 'Grade A', 'Grade B', 'Grade C', 'Grade D', 'Grade F']);
                
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['class_name'],
                        $row['subject_name'],
                        $row['total_students'],
                        number_format($row['average_score'], 2),
                        $row['grade_a'],
                        $row['grade_b'],
                        $row['grade_c'],
                        $row['grade_d'],
                        $row['grade_f']
                    ]);
                }
                
                fclose($output);
                exit;
        }
    } catch (Exception $e) {
        $error = 'Error generating report: ' . $e->getMessage();
    }
}
?>

<!-- Page Header -->
<div class="page-header mb-3">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title mb-0">Reports</h3>
            <ul class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin</a></li>
                <li class="breadcrumb-item active">Reports</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Report Generation Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Generate Report</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="report_type" class="form-label">Report Type *</label>
                        <select class="form-select" id="report_type" name="report_type" required>
                            <option value="">Select Report Type</option>
                            <option value="attendance" <?php echo $reportType === 'attendance' ? 'selected' : ''; ?>>
                                Attendance Report
                            </option>
                            <option value="grades" <?php echo $reportType === 'grades' ? 'selected' : ''; ?>>
                                Grades Report
                            </option>
                            <option value="enrollment" <?php echo $reportType === 'enrollment' ? 'selected' : ''; ?>>
                                Enrollment Report
                            </option>
                            <option value="performance" <?php echo $reportType === 'performance' ? 'selected' : ''; ?>>
                                Performance Report
                            </option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="academic_year" class="form-label">Academic Year *</label>
                        <select class="form-select" id="academic_year" name="academic_year" required>
                            <option value="">Select Academic Year</option>
                            <?php foreach ($academicYears as $year): ?>
                                <option value="<?php echo $year['academic_year']; ?>" 
                                        <?php echo $academicYear === $year['academic_year'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year['academic_year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" name="generate_report" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Descriptions -->
<div class="row">
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Attendance Report</h5>
                <p class="card-text">Generate detailed attendance records for all students, including present, absent, and late days.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Grades Report</h5>
                <p class="card-text">Export student grades across all subjects, including scores, grades, and remarks.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Enrollment Report</h5>
                <p class="card-text">View class-wise enrollment statistics with gender distribution.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Performance Report</h5>
                <p class="card-text">Analyze class and subject-wise performance metrics with grade distribution.</p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 