<?php
/**
 * AJAX Endpoint: Get questions for a course
 * Returns JSON with question IDs for the specified course
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';

$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

if ($course_id <= 0) {
    echo json_encode(['question_ids' => []]);
    exit;
}

$question_ids = get_course_question_ids($course_id);

echo json_encode(['question_ids' => $question_ids]);
?>
