<?php
/**
 * Teacher Communication
 */

// Set page title
$pageTitle = 'Communication';

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

// Initialize classes if it's false or null
if (!$classes) {
    $classes = [];
}

// Initialize variables
$success = '';
$error = '';

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipientType = sanitize($_POST['recipient_type']);
    $recipients = isset($_POST['recipients']) ? $_POST['recipients'] : [];
    $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    if (empty($subject) || empty($message)) {
        $error = 'Subject and message are required.';
    } elseif (empty($recipients) && $recipientType !== 'all_class') {
        $error = 'Please select at least one recipient.';
    } else {
        try {
            // Begin transaction
            startTransaction();
            
            // Get actual recipient IDs based on selection
            $recipientIds = [];
            
            if ($recipientType === 'students') {
                // Individual students
                $recipientIds = $recipients;
            } elseif ($recipientType === 'parents') {
                // Individual parents
                $recipientIds = $recipients;
            } elseif ($recipientType === 'all_class') {
                // All students in a class
                $students = executeQuery("
                    SELECT id FROM student_profiles WHERE class_id = ?
                ", [$classId]);
                
                $recipientIds = array_column($students, 'id');
            }
            
            if (empty($recipientIds)) {
                throw new Exception('No recipients found.');
            }
            
            // Insert message
            $sql = "INSERT INTO messages (sender_id, sender_type, subject, content, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            executeNonQuery($sql, [$currentUser['id'], 'teacher', $subject, $message]);
            
            $messageId = lastInsertId();
            
            // Insert message recipients
            $insertRecipientSql = "INSERT INTO message_recipients (message_id, recipient_id, recipient_type, status, read_at) 
                                 VALUES (?, ?, ?, 'unread', NULL)";
            
            foreach ($recipientIds as $recipientId) {
                $recipientType = ($recipientType === 'parents') ? 'parent' : 'student';
                executeNonQuery($insertRecipientSql, [$messageId, $recipientId, $recipientType]);
            }
            
            // Handle file attachment if provided
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../../uploads/message_attachments/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = $messageId . '_' . time() . '_' . basename($_FILES['attachment']['name']);
                $uploadFile = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadFile)) {
                    $sql = "UPDATE messages SET attachment = ? WHERE id = ?";
                    executeNonQuery($sql, [$fileName, $messageId]);
                } else {
                    throw new Exception('Failed to upload attachment.');
                }
            }
            
            // Log activity
            logActivity($currentUser['id'], 'Sent message', 'Subject: ' . $subject);
            
            // Commit transaction
            commitTransaction();
            
            $success = 'Message sent successfully.';
        } catch (Exception $e) {
            // Rollback transaction
            rollbackTransaction();
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get messages sent by the teacher
$sentMessages = executeQuery("
    SELECT m.*, 
           (SELECT COUNT(*) FROM message_recipients WHERE message_id = m.id) as recipient_count,
           (SELECT COUNT(*) FROM message_recipients WHERE message_id = m.id AND status = 'read') as read_count
    FROM messages m
    WHERE m.sender_id = ? AND m.sender_type = 'teacher'
    ORDER BY m.created_at DESC
    LIMIT 50
", [$currentUser['id']]);

// Initialize sentMessages if it's false or null
if (!$sentMessages) {
    $sentMessages = [];
}

// Get messages received by the teacher
$receivedMessages = executeQuery("
    SELECT m.*, u.username as sender_username, 
           CASE 
                WHEN m.sender_type = 'student' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM student_profiles WHERE user_id = m.sender_id)
                WHEN m.sender_type = 'admin' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM admin_profiles WHERE user_id = m.sender_id)
                WHEN m.sender_type = 'parent' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM parent_profiles WHERE user_id = m.sender_id)
                ELSE u.username
           END as sender_name,
           mr.status, mr.read_at
    FROM messages m
    JOIN message_recipients mr ON m.id = mr.message_id
    JOIN users u ON m.sender_id = u.id
    WHERE mr.recipient_id = ? AND mr.recipient_type = 'teacher'
    ORDER BY m.created_at DESC
    LIMIT 50
", [$currentUser['id']]);

// Initialize receivedMessages if it's false or null
if (!$receivedMessages) {
    $receivedMessages = [];
}

// Get class-student mapping
$classStudents = [];
if (is_array($classes)) {
    foreach ($classes as $class) {
        $students = executeQuery("
            SELECT sp.id, sp.first_name, sp.last_name, sp.admission_number
            FROM student_profiles sp
            WHERE sp.class_id = ?
            ORDER BY sp.last_name, sp.first_name
        ", [$class['id']]);
        
        if (!$students) {
            $students = [];
        }
        
        $classStudents[$class['id']] = $students;
    }
}

// Mark message as read if viewing a specific message
if (isset($_GET['read']) && isset($_GET['id'])) {
    $messageId = (int)$_GET['id'];
    
    executeNonQuery("
        UPDATE message_recipients 
        SET status = 'read', read_at = NOW() 
        WHERE message_id = ? AND recipient_id = ? AND recipient_type = 'teacher'
    ", [$messageId, $currentUser['id']]);
    
    // Refresh received messages
    $receivedMessages = executeQuery("
        SELECT m.*, u.username as sender_username, 
               CASE 
                    WHEN m.sender_type = 'student' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM student_profiles WHERE user_id = m.sender_id)
                    WHEN m.sender_type = 'admin' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM admin_profiles WHERE user_id = m.sender_id)
                    WHEN m.sender_type = 'parent' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM parent_profiles WHERE user_id = m.sender_id)
                    ELSE u.username
               END as sender_name,
               mr.status, mr.read_at
        FROM messages m
        JOIN message_recipients mr ON m.id = mr.message_id
        JOIN users u ON m.sender_id = u.id
        WHERE mr.recipient_id = ? AND mr.recipient_type = 'teacher'
        ORDER BY m.created_at DESC
        LIMIT 50
    ", [$currentUser['id']]);
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Communication</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Teacher</a></li>
                <li class="breadcrumb-item active">Communication</li>
            </ul>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeMessageModal">
                <i class="fas fa-envelope"></i> Compose New Message
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

<!-- Message Tabs -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs nav-tabs-solid nav-justified">
                    <li class="nav-item">
                        <a class="nav-link active" href="#inbox" data-bs-toggle="tab">
                            <i class="fas fa-inbox"></i> Inbox
                            <?php
                            $unreadCount = 0;
                            if (is_array($receivedMessages)) {
                                foreach ($receivedMessages as $message) {
                                    if ($message['status'] === 'unread') {
                                        $unreadCount++;
                                    }
                                }
                            }
                            if ($unreadCount > 0):
                            ?>
                            <span class="badge rounded-pill bg-danger ms-1"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#sent" data-bs-toggle="tab">
                            <i class="fas fa-paper-plane"></i> Sent Messages
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content mt-3">
                    <!-- Inbox Tab -->
                    <div class="tab-pane show active" id="inbox">
                        <?php if (is_array($receivedMessages) && count($receivedMessages) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>From</th>
                                            <th>Subject</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($receivedMessages as $message): ?>
                                            <tr class="<?php echo $message['status'] === 'unread' ? 'table-active' : ''; ?>">
                                                <td>
                                                    <?php if ($message['status'] === 'unread'): ?>
                                                        <span class="badge bg-info">New</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Read</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($message['sender_name']); ?></strong>
                                                    <div class="small text-muted"><?php echo ucfirst($message['sender_type']); ?></div>
                                                </td>
                                                <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></td>
                                                <td>
                                                    <a href="#" class="btn btn-sm btn-info view-message" data-bs-toggle="modal" data-bs-target="#viewMessageModal" 
                                                       data-id="<?php echo $message['id']; ?>"
                                                       data-sender="<?php echo htmlspecialchars($message['sender_name']); ?>"
                                                       data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                       data-content="<?php echo htmlspecialchars($message['content']); ?>"
                                                       data-date="<?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?>"
                                                       data-attachment="<?php echo $message['attachment']; ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <a href="#" class="btn btn-sm btn-primary reply-message" data-bs-toggle="modal" data-bs-target="#composeMessageModal"
                                                       data-recipient-id="<?php echo $message['sender_id']; ?>"
                                                       data-recipient-type="<?php echo $message['sender_type']; ?>"
                                                       data-subject="Re: <?php echo htmlspecialchars($message['subject']); ?>">
                                                        <i class="fas fa-reply"></i> Reply
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Your inbox is empty.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sent Messages Tab -->
                    <div class="tab-pane" id="sent">
                        <?php if (is_array($sentMessages) && count($sentMessages) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Recipients</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sentMessages as $message): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                                <td><?php echo $message['recipient_count']; ?> recipients</td>
                                                <td><?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <?php 
                                                        $readPercentage = $message['recipient_count'] > 0 ? 
                                                            round(($message['read_count'] / $message['recipient_count']) * 100) : 0;
                                                        ?>
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?php echo $readPercentage; ?>%;" 
                                                             aria-valuenow="<?php echo $readPercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo $readPercentage; ?>% Read
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="#" class="btn btn-sm btn-info view-sent-message" data-bs-toggle="modal" data-bs-target="#viewSentMessageModal" 
                                                       data-id="<?php echo $message['id']; ?>"
                                                       data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                       data-content="<?php echo htmlspecialchars($message['content']); ?>"
                                                       data-date="<?php echo date('M d, Y H:i', strtotime($message['created_at'])); ?>"
                                                       data-attachment="<?php echo $message['attachment']; ?>"
                                                       data-recipients="<?php echo $message['recipient_count']; ?>"
                                                       data-read="<?php echo $message['read_count']; ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> You haven't sent any messages yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Message Modal -->
<div class="modal fade" id="composeMessageModal" tabindex="-1" aria-labelledby="composeMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="composeMessageModalLabel">Compose New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="post" enctype="multipart/form-data" id="composeMessageForm">
                    <div class="mb-3">
                        <label for="recipient_type" class="form-label">Recipient Type</label>
                        <select class="form-select" id="recipient_type" name="recipient_type" required>
                            <option value="">Select Recipient Type</option>
                            <option value="students">Individual Students</option>
                            <option value="parents">Individual Parents</option>
                            <option value="all_class">Entire Class</option>
                        </select>
                    </div>
                    
                    <div id="class_select_container" class="mb-3" style="display: none;">
                        <label for="class_id" class="form-label">Select Class</label>
                        <select class="form-select" id="class_id" name="class_id">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>">
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="recipient_select_container" class="mb-3" style="display: none;">
                        <label for="recipients" class="form-label">Select Recipients</label>
                        <select class="form-select" id="recipients" name="recipients[]" multiple style="height: 150px;">
                            <!-- Will be populated via JavaScript -->
                        </select>
                        <small class="form-text text-muted">Hold Ctrl (Windows) or Command (Mac) to select multiple recipients.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attachment" class="form-label">Attachment (Optional)</label>
                        <input type="file" class="form-control" id="attachment" name="attachment">
                        <small class="form-text text-muted">Max file size: 5MB</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="composeMessageForm" name="send_message" class="btn btn-primary">Send Message</button>
            </div>
        </div>
    </div>
</div>

<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewMessageModalLabel">View Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>From:</strong> <span id="view_sender"></span>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Date:</strong> <span id="view_date"></span>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Subject:</strong> <span id="view_subject"></span>
                </div>
                <div class="mb-3">
                    <div class="card">
                        <div class="card-body" id="view_content"></div>
                    </div>
                </div>
                <div class="mb-3" id="view_attachment_container" style="display: none;">
                    <strong>Attachment:</strong> <a href="#" id="view_attachment" target="_blank"></a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="reply_button">Reply</button>
            </div>
        </div>
    </div>
</div>

<!-- View Sent Message Modal -->
<div class="modal fade" id="viewSentMessageModal" tabindex="-1" aria-labelledby="viewSentMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewSentMessageModalLabel">View Sent Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>To:</strong> <span id="view_sent_recipients"></span> recipients
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Date:</strong> <span id="view_sent_date"></span>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Subject:</strong> <span id="view_sent_subject"></span>
                </div>
                <div class="mb-3">
                    <div class="card">
                        <div class="card-body" id="view_sent_content"></div>
                    </div>
                </div>
                <div class="mb-3" id="view_sent_attachment_container" style="display: none;">
                    <strong>Attachment:</strong> <a href="#" id="view_sent_attachment" target="_blank"></a>
                </div>
                <div class="mb-3">
                    <strong>Read Status:</strong> <span id="view_sent_read_status"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle recipient type selection
    document.getElementById('recipient_type').addEventListener('change', function() {
        const recipientType = this.value;
        const classSelectContainer = document.getElementById('class_select_container');
        const recipientSelectContainer = document.getElementById('recipient_select_container');
        
        if (recipientType === 'all_class') {
            classSelectContainer.style.display = 'block';
            recipientSelectContainer.style.display = 'none';
        } else if (recipientType === 'students' || recipientType === 'parents') {
            classSelectContainer.style.display = 'block';
            recipientSelectContainer.style.display = 'block';
        } else {
            classSelectContainer.style.display = 'none';
            recipientSelectContainer.style.display = 'none';
        }
    });
    
    // Handle class selection
    document.getElementById('class_id').addEventListener('change', function() {
        const classId = this.value;
        const recipientType = document.getElementById('recipient_type').value;
        const recipientSelect = document.getElementById('recipients');
        
        // Clear existing options
        recipientSelect.innerHTML = '';
        
        if (classId && (recipientType === 'students' || recipientType === 'parents')) {
            // Get students for this class
            const classStudents = <?php echo json_encode($classStudents); ?>;
            
            if (classStudents[classId]) {
                classStudents[classId].forEach(function(student) {
                    const option = document.createElement('option');
                    option.value = student.id;
                    option.textContent = student.first_name + ' ' + student.last_name + ' (' + student.admission_number + ')';
                    recipientSelect.appendChild(option);
                });
            }
        }
    });
    
    // Handle view message
    document.querySelectorAll('.view-message').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const sender = this.getAttribute('data-sender');
            const subject = this.getAttribute('data-subject');
            const content = this.getAttribute('data-content');
            const date = this.getAttribute('data-date');
            const attachment = this.getAttribute('data-attachment');
            
            document.getElementById('view_sender').textContent = sender;
            document.getElementById('view_subject').textContent = subject;
            document.getElementById('view_content').textContent = content;
            document.getElementById('view_date').textContent = date;
            
            const attachmentContainer = document.getElementById('view_attachment_container');
            const attachmentLink = document.getElementById('view_attachment');
            
            if (attachment) {
                attachmentContainer.style.display = 'block';
                attachmentLink.textContent = attachment.split('_').pop(); // Show just the filename
                attachmentLink.href = '<?php echo APP_URL; ?>/uploads/message_attachments/' + attachment;
            } else {
                attachmentContainer.style.display = 'none';
            }
            
            // Set up reply button
            document.getElementById('reply_button').onclick = function() {
                // Close view modal
                $('#viewMessageModal').modal('hide');
                
                // Open compose modal with reply info
                setTimeout(function() {
                    $('#composeMessageModal').modal('show');
                    document.getElementById('subject').value = 'Re: ' + subject;
                }, 500);
            };
            
            // Mark as read
            window.location.href = 'communication.php?read=1&id=' + id;
        });
    });
    
    // Handle view sent message
    document.querySelectorAll('.view-sent-message').forEach(function(button) {
        button.addEventListener('click', function() {
            const subject = this.getAttribute('data-subject');
            const content = this.getAttribute('data-content');
            const date = this.getAttribute('data-date');
            const attachment = this.getAttribute('data-attachment');
            const recipients = this.getAttribute('data-recipients');
            const read = this.getAttribute('data-read');
            
            document.getElementById('view_sent_subject').textContent = subject;
            document.getElementById('view_sent_content').textContent = content;
            document.getElementById('view_sent_date').textContent = date;
            document.getElementById('view_sent_recipients').textContent = recipients;
            
            const readPercentage = recipients > 0 ? Math.round((read / recipients) * 100) : 0;
            document.getElementById('view_sent_read_status').textContent = read + ' out of ' + recipients + ' (' + readPercentage + '%) recipients have read this message';
            
            const attachmentContainer = document.getElementById('view_sent_attachment_container');
            const attachmentLink = document.getElementById('view_sent_attachment');
            
            if (attachment) {
                attachmentContainer.style.display = 'block';
                attachmentLink.textContent = attachment.split('_').pop(); // Show just the filename
                attachmentLink.href = '<?php echo APP_URL; ?>/uploads/message_attachments/' + attachment;
            } else {
                attachmentContainer.style.display = 'none';
            }
        });
    });
    
    // Handle reply to message
    document.querySelectorAll('.reply-message').forEach(function(button) {
        button.addEventListener('click', function() {
            const subject = this.getAttribute('data-subject');
            document.getElementById('subject').value = subject;
        });
    });
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?> 