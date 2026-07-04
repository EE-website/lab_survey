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
    
    $sql = "SELECT academic_year, semester FROM system_settings WHERE status = 1 LIMIT 1";
    $result = $conn->query($sql);
    
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    
    $row = $result->fetch_assoc();
    
    // If no settings found, return defaults
    if (!$row) {
        return ['academic_year' => 114, 'semester' => 1];
    }
    
    return $row;
}

/**
 * Update academic year and semester settings
 */
function set_academic_settings($academic_year, $semester) {
    global $conn;
    
    $academic_year = intval($academic_year);
    $semester = intval($semester);
    
    if ($semester < 1 || $semester > 2) {
        return false;
    }
    
    $sql = "UPDATE system_settings SET academic_year = ?, semester = ? WHERE status = 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Statement preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $academic_year, $semester);
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

?>