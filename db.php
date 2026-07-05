<?php
/**
 * Database Connection and Operations
 */

// Database Connection Settings
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'lab_survey';

// Create MySQLi Connection
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Check Connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set Character Set
$conn->set_charset("utf8mb4");

/**
 * Get all questions
 */
function get_all_questions() {
    global $conn;
    
    $sql = "SELECT id, title, type, allow_multiple, is_required, description, options, created_at FROM questions WHERE is_active = 1 ORDER BY id ASC";
    $result = $conn->query($sql);
    
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $row['options'] = json_decode($row['options'], true);
        $questions[] = $row;
    }
    
    return $questions;
}

/**
 * Get a single question by ID
 */
function get_question($id) {
    global $conn;
    
    $id = intval($id);
    $sql = "SELECT id, title, type, allow_multiple, is_required, description, options, created_at FROM questions WHERE id = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row) {
        $row['options'] = json_decode($row['options'], true);
        return $row;
    }
    
    return null;
}

/**
 * Add a new question
 */
function add_question($title, $type, $description, $options, $allow_multiple = 0, $is_required = 1) {
    global $conn;
    
    $title = trim($title);
    $type = trim($type);
    $description = trim($description);
    $options_json = json_encode($options, JSON_UNESCAPED_UNICODE);
    $allow_multiple = intval($allow_multiple);
    $is_required = intval($is_required);
    
    $sql = "INSERT INTO questions (title, type, allow_multiple, is_required, description, options) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssiiss", $title, $type, $allow_multiple, $is_required, $description, $options_json);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    } else {
        die("Insert failed: " . $conn->error);
    }
}

/**
 * Delete a question
 */
function delete_question($id) {
    global $conn;
    
    $id = intval($id);
    
    // Soft delete: Mark as inactive
    $sql = "UPDATE questions SET is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

/**
 * Update a question
 */
function update_question($id, $title, $type, $description, $options, $allow_multiple = 0, $is_required = 1) {
    global $conn;
    
    $id = intval($id);
    $title = trim($title);
    $type = trim($type);
    $description = trim($description);
    $options_json = json_encode($options, JSON_UNESCAPED_UNICODE);
    $allow_multiple = intval($allow_multiple);
    $is_required = intval($is_required);
    
    $sql = "UPDATE questions SET title = ?, type = ?, allow_multiple = ?, is_required = ?, description = ?, options = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssiissi", $title, $type, $allow_multiple, $is_required, $description, $options_json, $id);
    return $stmt->execute();
}

/**
 * Get responses for a question
 */
function get_question_responses($id) {
    global $conn;
    
    $id = intval($id);
    $sql = "SELECT id, question_id, answer, respondent, created_at FROM responses WHERE question_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $responses = [];
    while ($row = $result->fetch_assoc()) {
        $responses[] = $row;
    }
    
    return $responses;
}

/**
 * Add a new response with academic year tracking
 */
function add_response($question_id, $answer, $respondent = 'Anonymous', $academic_year = null) {
    global $conn;
    
    $question_id = intval($question_id);
    $answer = trim($answer);
    $respondent = trim($respondent);
    
    // Use current academic year if not provided
    if ($academic_year === null) {
        $academic_year = CURRENT_ACADEMIC_YEAR;
    }
    $academic_year = intval($academic_year);
    
    // Get user IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $sql = "INSERT INTO responses (question_id, academic_year, answer, respondent, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("iissss", $question_id, $academic_year, $answer, $respondent, $ip_address, $user_agent);
    return $stmt->execute();
}

/**
 * Get statistics overview
 */
function get_statistics() {
    global $conn;
    
    $stats = [];
    
    // Total questions
    $result = $conn->query("SELECT COUNT(*) as count FROM questions WHERE is_active = 1");
    $row = $result->fetch_assoc();
    $stats['total_questions'] = $row['count'];
    
    // Total responses
    $result = $conn->query("SELECT COUNT(*) as count FROM responses");
    $row = $result->fetch_assoc();
    $stats['total_responses'] = $row['count'];
    
    // Unique respondents
    $result = $conn->query("SELECT COUNT(DISTINCT respondent) as count FROM responses");
    $row = $result->fetch_assoc();
    $stats['total_respondents'] = $row['count'];
    
    return $stats;
}

/**
 * Get responses for a question filtered by academic year and semester
 */
function get_question_responses_by_year_semester($question_id, $academic_year = null, $semester = null) {
    global $conn;
    
    $question_id = intval($question_id);
    
    // Get current settings if not provided
    if ($academic_year === null || $semester === null) {
        $settings = get_current_academic_settings();
        $academic_year = $academic_year ?? $settings['academic_year'];
        $semester = $semester ?? $settings['semester'];
    }
    
    $academic_year = intval($academic_year);
    $semester = intval($semester);
    
    $sql = "SELECT id, question_id, answer, respondent, academic_year, semester, created_at FROM responses 
            WHERE question_id = ? AND academic_year = ? AND semester = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("iii", $question_id, $academic_year, $semester);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $responses = [];
    while ($row = $result->fetch_assoc()) {
        $responses[] = $row;
    }
    
    return $responses;
}

/**
 * Get all available academic year and semester combinations
 */
function get_all_academic_year_semester_combinations() {
    global $conn;
    
    $sql = "SELECT DISTINCT academic_year, semester FROM responses ORDER BY academic_year DESC, semester DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    
    $combinations = [];
    while ($row = $result->fetch_assoc()) {
        $combinations[] = [
            'academic_year' => $row['academic_year'],
            'semester' => $row['semester']
        ];
    }
    
    return $combinations;
}

/**
 * Get all available academic years from responses
 */
function get_all_academic_years() {
    global $conn;
    
    $sql = "SELECT DISTINCT academic_year FROM responses ORDER BY academic_year DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    
    $years = [];
    while ($row = $result->fetch_assoc()) {
        $years[] = $row['academic_year'];
    }
    
    return $years;
}

/**
 * Convert ROC year to Western year
 */
function roc_to_western($roc_year) {
    return $roc_year + 1911;
}

/**
 * Convert Western year to ROC year
 */
function western_to_roc($western_year) {
    return $western_year - 1911;
}

/**
 * Get current academic year and semester settings
 */
function get_current_academic_settings() {
    global $conn;
    
    $sql = "SELECT academic_year, semester, status FROM system_settings LIMIT 1";
    $result = $conn->query($sql);
    
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    
    $row = $result->fetch_assoc();
    
    // If no settings found, return defaults
    if (!$row) {
        return ['academic_year' => 114, 'semester' => 1, 'status' => 'open'];
    }
    
    return $row;
}

/**
 * Update academic year and semester settings
 */
function set_academic_settings($academic_year, $semester, $status = 'open') {
    global $conn;
    
    $academic_year = intval($academic_year);
    $semester = intval($semester);
    $status = trim($status);
    
    if ($semester < 1 || $semester > 2) {
        return false;
    }
    
    // Validate status
    if (!in_array($status, ['open', 'closed'])) {
        $status = 'open';
    }
    
    // Check if record exists
    $check_sql = "SELECT COUNT(*) as cnt FROM system_settings";
    $result = $conn->query($check_sql);
    $row = $result->fetch_assoc();
    
    if ($row['cnt'] > 0) {
        // Update existing record
        $sql = "UPDATE system_settings SET academic_year = ?, semester = ?, status = ? LIMIT 1";
    } else {
        // Insert new record
        $sql = "INSERT INTO system_settings (academic_year, semester, status) VALUES (?, ?, ?)";
    }
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("iis", $academic_year, $semester, $status);
    return $stmt->execute();
}

/**
 * Get semester name in Chinese
 */
function get_semester_name($semester) {
    $semester = intval($semester);
    return ($semester === 1) ? '上學期' : '下學期';
}

/**
 * Calculate statistics for a question with proper handling of multiple choice answers
 * For allow_multiple questions, answers are stored as comma-separated: "option1, option2, option3"
 * We split them and count each option separately
 */
function calculate_question_statistics($question_id, $responses, $question_type = 'rating', $allow_multiple = 0) {
    $stats = [];
    
    foreach ($responses as $response) {
        $answer = trim($response['answer']);
        
        // For multiple choice with allow_multiple enabled, split and count each option
        if ($question_type === 'multiple_choice' && $allow_multiple) {
            // Split by comma
            $answers = array_map('trim', explode(',', $answer));
            foreach ($answers as $single_answer) {
                if (!empty($single_answer)) {
                    if (!isset($stats[$single_answer])) {
                        $stats[$single_answer] = 0;
                    }
                    $stats[$single_answer]++;
                }
            }
        } else {
            // For single choice or rating, count as is
            if (!isset($stats[$answer])) {
                $stats[$answer] = 0;
            }
            $stats[$answer]++;
        }
    }
    
    return $stats;
}

/**
 * Get all courses
 */
function get_all_courses() {
    global $conn;
    
    $sql = "SELECT id, course_code, course_name, instructor_name, description, semester, academic_year, is_active, created_at FROM courses WHERE is_active = 1 ORDER BY academic_year DESC, semester DESC, course_code ASC";
    $result = $conn->query($sql);
    
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    
    return $courses;
}

/**
 * Get a single course by ID
 */
function get_course($id) {
    global $conn;
    
    $id = intval($id);
    $sql = "SELECT id, course_code, course_name, instructor_name, description, semester, academic_year, is_active, created_at FROM courses WHERE id = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row ? $row : null;
}

/**
 * Add a new course
 */
function add_course($course_code, $course_name, $instructor_name, $description = '', $semester = null, $academic_year = null) {
    global $conn;
    
    $course_code = trim($course_code);
    $course_name = trim($course_name);
    $instructor_name = trim($instructor_name);
    $description = trim($description);
    
    // Use current semester and academic year if not provided
    if ($semester === null || $academic_year === null) {
        $settings = get_current_academic_settings();
        $semester = $semester ?? $settings['semester'];
        $academic_year = $academic_year ?? $settings['academic_year'];
    }
    
    $semester = intval($semester);
    $academic_year = intval($academic_year);
    
    $sql = "INSERT INTO courses (course_code, course_name, instructor_name, description, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssssii", $course_code, $course_name, $instructor_name, $description, $semester, $academic_year);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    } else {
        die("Insert failed: " . $conn->error);
    }
}

/**
 * Update a course
 */
function update_course($id, $course_code, $course_name, $instructor_name, $description = '', $semester = null, $academic_year = null) {
    global $conn;
    
    $id = intval($id);
    $course_code = trim($course_code);
    $course_name = trim($course_name);
    $instructor_name = trim($instructor_name);
    $description = trim($description);
    
    // Use current values if not provided
    if ($semester === null || $academic_year === null) {
        $course = get_course($id);
        $semester = $semester ?? $course['semester'];
        $academic_year = $academic_year ?? $course['academic_year'];
    }
    
    $semester = intval($semester);
    $academic_year = intval($academic_year);
    
    $sql = "UPDATE courses SET course_code = ?, course_name = ?, instructor_name = ?, description = ?, semester = ?, academic_year = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssssiii", $course_code, $course_name, $instructor_name, $description, $semester, $academic_year, $id);
    return $stmt->execute();
}

/**
 * Delete a course (soft delete)
 */
function delete_course($id) {
    global $conn;
    
    $id = intval($id);
    
    // Soft delete: Mark as inactive
    $sql = "UPDATE courses SET is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

/**
 * Get questions for a specific course (via course_questions junction table)
 */
function get_questions_by_course($course_id) {
    global $conn;
    
    $course_id = intval($course_id);
    $sql = "SELECT q.id, q.title, q.type, q.allow_multiple, q.is_required, q.description, q.options, q.created_at 
            FROM questions q
            INNER JOIN course_questions cq ON q.id = cq.question_id
            WHERE cq.course_id = ? AND q.is_active = 1 
            ORDER BY q.id ASC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $row['options'] = json_decode($row['options'], true);
        $questions[] = $row;
    }
    
    return $questions;
}

/**
 * Get courses by academic year and semester
 */
function get_courses_by_year_semester($academic_year = null, $semester = null) {
    global $conn;
    
    // Get current settings if not provided
    if ($academic_year === null || $semester === null) {
        $settings = get_current_academic_settings();
        $academic_year = $academic_year ?? $settings['academic_year'];
        $semester = $semester ?? $settings['semester'];
    }
    
    $academic_year = intval($academic_year);
    $semester = intval($semester);
    
    $sql = "SELECT id, course_code, course_name, instructor_name, description, semester, academic_year, is_active, created_at FROM courses WHERE academic_year = ? AND semester = ? AND is_active = 1 ORDER BY course_code ASC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $academic_year, $semester);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    
    return $courses;
}

/**
 * Add a question to a course (create association in course_questions)
 */
function add_course_question($course_id, $question_id) {
    global $conn;
    
    $course_id = intval($course_id);
    $question_id = intval($question_id);
    
    $sql = "INSERT INTO course_questions (course_id, question_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $course_id, $question_id);
    return $stmt->execute();
}

/**
 * Add multiple questions to a course
 */
function add_course_questions($course_id, $question_ids = []) {
    global $conn;
    
    if (empty($question_ids)) {
        return true; // No questions to add
    }
    
    $course_id = intval($course_id);
    $success_count = 0;
    
    foreach ($question_ids as $question_id) {
        $question_id = intval($question_id);
        $sql = "INSERT INTO course_questions (course_id, question_id) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE course_id = course_id";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ii", $course_id, $question_id);
            if ($stmt->execute()) {
                $success_count++;
            }
        }
    }
    
    return $success_count > 0;
}

/**
 * Remove a question from a course
 */
function remove_course_question($course_id, $question_id) {
    global $conn;
    
    $course_id = intval($course_id);
    $question_id = intval($question_id);
    
    $sql = "DELETE FROM course_questions WHERE course_id = ? AND question_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $course_id, $question_id);
    return $stmt->execute();
}

/**
 * Remove all questions from a course
 */
function remove_all_course_questions($course_id) {
    global $conn;
    
    $course_id = intval($course_id);
    
    $sql = "DELETE FROM course_questions WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $course_id);
    return $stmt->execute();
}

/**
 * Get all question IDs for a course
 */
function get_course_question_ids($course_id) {
    global $conn;
    
    $course_id = intval($course_id);
    $sql = "SELECT question_id FROM course_questions WHERE course_id = ? ORDER BY question_id ASC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $question_ids = [];
    while ($row = $result->fetch_assoc()) {
        $question_ids[] = $row['question_id'];
    }
    
    return $question_ids;
}

?>