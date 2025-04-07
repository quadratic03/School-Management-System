<?php
/**
 * Teacher Assignments Management
 */

// Set page title
$pageTitle = 'Assignments';

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
$subjects = executeQuery("
    SELECT s.id, s.name, c.id as class_id, c.name as class_name
    FROM subjects s
    JOIN class_subjects cs ON s.id = cs.subject_id
    JOIN classes c ON cs.class_id = c.id
    WHERE cs.teacher_id = ?
    ORDER BY c.name, s.name
", [$teacherId]);

// Prepare class-subject mapping
$classSubjects = [];
foreach ($subjects as $subject) {
    if (!isset($classSubjects[$subject['class_id']])) {
        $classSubjects[$subject['class_id']] = [];
    }
    $classSubjects[$subject['class_id']][] = $subject;
}

// Initialize variables
$success = '';
$error = '';

// Handle assignment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $classId = (int)$_POST['class_id'];
    $subjectId = (int)$_POST['subject_id'];
    $dueDate = sanitize($_POST['due_date']);
    $maxScore = (int)$_POST['max_score'];
    
    // Validate input
    if (empty($title) || empty($description) || !$classId || !$subjectId || empty($dueDate) || $maxScore <= 0) {
        $error = 'Please fill all required fields.';
    } else {
        try {
            // Begin transaction
            startTransaction();
            
            // Insert assignment record
            $sql = "INSERT INTO assignments (title, description, class_id, subject_id, due_date, max_score, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            executeNonQuery($sql, [$title, $description, $classId, $subjectId, $dueDate, $maxScore, $currentUser['id']]);
            
            $assignmentId = lastInsertId();
            
            // Handle file upload
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../uploads/assignments/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = $assignmentId . '_' . time() . '_' . basename($_FILES['attachment']['name']);
                $uploadFile = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadFile)) {
                    $sql = "UPDATE assignments SET attachment = ? WHERE id = ?";
                    executeNonQuery($sql, [$fileName, $assignmentId]);
                } else {
                    throw new Exception('Failed to upload file.');
                }
            }
            
            // Log activity
            logActivity($currentUser['id'], 'Created new assignment', 'Assignment: ' . $title);
            
            // Commit transaction
            commitTransaction();
            
            $success = 'Assignment created successfully.';
        } catch (Exception $e) {
            // Rollback transaction
            rollbackTransaction();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get all assignments for this teacher with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$filterStatus = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';

// Build query based on filters
$whereConditions = ["a.created_by = ?"];
$params = [$currentUser['id']];

if ($selectedClassId > 0) {
    $whereConditions[] = "a.class_id = ?";
    $params[] = $selectedClassId;
}

if ($filterStatus === 'active') {
    $whereConditions[] = "a.due_date >= CURDATE()";
} elseif ($filterStatus === 'past') {
    $whereConditions[] = "a.due_date < CURDATE()";
}

$whereClause = implode(" AND ", $whereConditions);

// Count total assignments for pagination
$totalAssignments = executeSingleQuery("
    SELECT COUNT(*) as count
    FROM assignments a
    WHERE $whereClause
", $params);
$totalAssignments = $totalAssignments ? $totalAssignments['count'] : 0;

$totalPages = ceil($totalAssignments / $limit);

// Get assignments with details
$assignments = executeQuery("
    SELECT a.*, c.name as class_name, s.name as subject_name,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN subjects s ON a.subject_id = s.id
    WHERE $whereClause
    ORDER BY a.due_date DESC
    LIMIT $limit OFFSET $offset
", $params);
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Assignments</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Teacher</a></li>
                <li class="breadcrumb-item active">Assignments</li>
            </ul>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                <i class="fas fa-plus"></i> Create Assignment
            </button>
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

<!-- Assignments Filter -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="class_id" class="form-label">Filter by Class</label>
                        <select name="class_id" id="class_id" class="form-select">
                            <option value="0">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo $selectedClassId == $class['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Assignment Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="past" <?php echo $filterStatus === 'past' ? 'selected' : ''; ?>>Past</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="assignments.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Assignments List -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Assignments</h5>
            </div>
            <div class="card-body">
                <?php if (count($assignments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Due Date</th>
                                    <th>Submissions</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                    <?php 
                                    $dueDate = strtotime($assignment['due_date']);
                                    $isOverdue = $dueDate < time();
                                    $statusClass = $isOverdue ? 'danger' : 'success';
                                    $statusText = $isOverdue ? 'Past Due' : 'Active';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['class_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['subject_name']); ?></td>
                                        <td><?php echo date('M d, Y', $dueDate); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $assignment['submission_count']; ?> submissions</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="assignment_details.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="assignment_submissions.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-clipboard-check"></i> Submissions
                                                </a>
                                                <a href="assignment_edit.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Assignments pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&class_id=<?php echo $selectedClassId; ?>&status=<?php echo $filterStatus; ?>">
                                        Previous
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&class_id=<?php echo $selectedClassId; ?>&status=<?php echo $filterStatus; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&class_id=<?php echo $selectedClassId; ?>&status=<?php echo $filterStatus; ?>">
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No assignments found. Create a new assignment to get started.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Assignment Modal -->
<div class="modal fade" id="createAssignmentModal" tabindex="-1" aria-labelledby="createAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createAssignmentModalLabel">Create New Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="post" enctype="multipart/form-data" id="createAssignmentForm">
                    <div class="mb-3">
                        <label for="title" class="form-label">Assignment Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="modal_class_id" class="form-label">Class</label>
                            <select class="form-select" id="modal_class_id" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="subject_id" class="form-label">Subject</label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <!-- Will be populated via JavaScript -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="due_date" class="form-label">Due Date</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="max_score" class="form-label">Maximum Score</label>
                            <input type="number" class="form-control" id="max_score" name="max_score" min="1" value="100" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attachment" class="form-label">Attachment (Optional)</label>
                        <input type="file" class="form-control" id="attachment" name="attachment">
                        <small class="form-text text-muted">Allowed file types: PDF, DOC, DOCX, PPT, PPTX, ZIP (Max: 10MB)</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="createAssignmentForm" name="create_assignment" class="btn btn-primary">Create Assignment</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Populate subjects based on selected class
    const classSubjects = <?php echo json_encode($classSubjects); ?>;
    
    document.getElementById('modal_class_id').addEventListener('change', function() {
        const classId = this.value;
        const subjectSelect = document.getElementById('subject_id');
        
        // Clear existing options
        subjectSelect.innerHTML = '<option value="">Select Subject</option>';
        
        if (classId && classSubjects[classId]) {
            classSubjects[classId].forEach(function(subject) {
                const option = document.createElement('option');
                option.value = subject.id;
                option.textContent = subject.name;
                subjectSelect.appendChild(option);
            });
        }
    });
    
    // Set default due date to tomorrow
    document.addEventListener('DOMContentLoaded', function() {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        const dateInput = document.getElementById('due_date');
        dateInput.min = tomorrow.toISOString().split('T')[0];
        dateInput.value = tomorrow.toISOString().split('T')[0];
    });
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 