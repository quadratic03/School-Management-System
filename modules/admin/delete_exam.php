<?php
/**
 * Delete Exam
 * 
 * This file handles the deletion of exams
 */

require_once '../../includes/header.php';
requireAuth('admin');

// Get exam ID from URL
$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$examId) {
    redirect('exams.php');
}

try {
    // Begin transaction
    startTransaction();
    
    // Get exam details for logging
    $exam = executeQuery("SELECT exam_name FROM exams WHERE id = ?", [$examId]);
    
    if (!$exam) {
        throw new Exception("Exam not found.");
    }
    
    // Delete exam submissions first
    executeQuery("DELETE FROM exam_submissions WHERE exam_id = ?", [$examId]);
    
    // Delete the exam
    executeQuery("DELETE FROM exams WHERE id = ?", [$examId]);
    
    // Log activity
    logActivity($currentUser['id'], 'Deleted exam: ' . $exam['exam_name']);
    
    // Commit transaction
    commitTransaction();
    
    // Set success message
    $_SESSION['success'] = "Exam deleted successfully.";
} catch (Exception $e) {
    // Rollback transaction on error
    rollbackTransaction();
    
    // Set error message
    $_SESSION['error'] = "Error deleting exam: " . $e->getMessage();
}

// Redirect back to exams page
redirect('exams.php'); 