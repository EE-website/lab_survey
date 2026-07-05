<?php
/**
 * Survey Submission Page - Allows Users to Fill Out Survey
 */
$page_title = '問卷填寫';
include 'db.php';
include 'header.php';

$message = '';
$message_type = '';
$selected_course = null;

// Get current academic settings
$settings = get_current_academic_settings();
$academic_year = $settings['academic_year'];
$semester = $settings['semester'];

// Get available courses for this semester
$available_courses = get_courses_by_year_semester($academic_year, $semester);

// Check if survey is open
$survey_is_open = ($settings['status'] === 'open');

// Check if a course is selected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_course'])) {
    $course_id = intval($_POST['course_id'] ?? 0);
    if ($course_id > 0) {
        $selected_course = get_course($course_id);
        if (!$selected_course) {
            $message = '所選課程不存在';
            $message_type = 'danger';
        }
    } else {
        $message = '請選擇一門課程';
        $message_type = 'warning';
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    // Check if survey is still open
    if (!$survey_is_open) {
        $message = '問卷已關閉，無法提交回應';
        $message_type = 'danger';
    } else {
        $respondent = trim($_POST['respondent_name'] ?? 'Anonymous');
        $student_id = trim($_POST['student_id'] ?? '');
        $course_id = intval($_POST['course_id_hidden'] ?? 0);
        $responses = $_POST['responses'] ?? [];
        
        if ($course_id <= 0) {
            $message = '請先選擇課程';
            $message_type = 'danger';
        } else {
            $saved_count = 0;
            foreach ($responses as $question_id => $answer) {
                // Convert to comma-separated string
                if (is_array($answer)) {
                    if (empty($answer)) {
                        continue;
                    }
                    $answer = implode(', ', array_map('trim', $answer));
                } else {
                    $answer = trim($answer);
                    if (empty($answer)) {
                        continue;
                    }
                }
                
                // Insert response
                $question_id = intval($question_id);
                $respondent_name = trim($respondent);
                $student_id_clean = trim($student_id);
                
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                $sql = "INSERT INTO responses (question_id, course_id, academic_year, semester, answer, respondent, student_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                if ($stmt) {
                    $stmt->bind_param("iiiiissss", $question_id, $course_id, $academic_year, $semester, $answer, $respondent_name, $student_id_clean, $ip_address, $user_agent);
                    if ($stmt->execute()) {
                        $saved_count++;
                    }
                }
            }
            
            if ($saved_count > 0) {
                $message = "感謝您的填寫！已收到 $saved_count 份回應。";
                $message_type = 'success';
                $selected_course = null;
            } else {
                $message = '請至少回答一個問題！';
                $message_type = 'warning';
            }
        }
    }
}

// Get questions for selected course, or all questions if no course selected
$questions = ($selected_course) ? get_questions_by_course($selected_course['id']) : [];
?>

<div class="container container-main">
    <h1 class="page-title">
        <i class="fas fa-poll"></i> 填寫問卷
    </h1>

    <?php if (!$survey_is_open): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-lock"></i> <strong>問卷已關閉</strong>
        <br>
        <small>民國 <?php echo $academic_year; ?> <?php echo get_semester_name($semester); ?> 的問卷填寫已結束，感謝您的參與！</small>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Course Selection Step -->
    <?php if ($survey_is_open && (!$selected_course || count($available_courses) === 0)): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-graduation-cap"></i> 第一步：選擇課程
        </div>
        <div class="card-body">
            <?php if (count($available_courses) === 0): ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle"></i> 民國 <?php echo $academic_year; ?>
                <?php echo get_semester_name($semester); ?> 目前沒有可評量的課程
            </div>
            <?php else: ?>
            <p class="text-muted mb-3">民國 <?php echo $academic_year; ?>
                <?php echo get_semester_name($semester); ?> - 請選擇一門課程進行評量</p>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="course_id" class="form-label">
                        <strong>選擇課程</strong> <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="course_id" name="course_id" required>
                        <option value="">-- 請選擇課程 --</option>
                        <?php foreach ($available_courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>">
                            [<?php echo htmlspecialchars($course['course_code']); ?>]
                            <?php echo htmlspecialchars($course['course_name']); ?> -
                            <?php echo htmlspecialchars($course['instructor_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="select_course" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> 開始評量
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Survey Questions Form -->
    <?php if ($selected_course): ?>
    <?php if (count($questions) === 0): ?>
    <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle"></i> 此課程目前沒有評量問題
        <form method="POST" action="" style="display: inline;">
            <button type="submit" class="btn btn-sm btn-link">返回課程選擇</button>
        </form>
    </div>
    <?php else: ?>
    <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle"></i>
        <strong>課程：</strong> [<?php echo htmlspecialchars($selected_course['course_code']); ?>]
        <?php echo htmlspecialchars($selected_course['course_name']); ?> -
        <?php echo htmlspecialchars($selected_course['instructor_name']); ?>
        <form method="POST" action="" style="display: inline; float: right;">
            <button type="submit" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> 返回課程選擇
            </button>
        </form>
    </div>

    <form method="POST" action="" class="needs-validation" novalidate>
        <input type="hidden" name="course_id_hidden" value="<?php echo $selected_course['id']; ?>">

        <!-- Respondent Information -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <i class="fas fa-user"></i> 個人資訊
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="student_id" class="form-label">
                        <strong>學號</strong>
                    </label>
                    <input type="text" class="form-control" id="student_id" name="student_id" placeholder="請輸入你的學號"
                        required>
                </div>
                <div class="mb-3">
                    <label for="respondent_name" class="form-label">
                        <strong>您的姓名</strong>
                    </label>
                    <input type="text" class="form-control" id="respondent_name" name="respondent_name" required>
                </div>
            </div>
        </div>

        <!-- Survey Questions -->
        <?php foreach ($questions as $index => $question): ?>
        <div class="card mb-3">
            <div class="card-header">
                <strong>問題 <?php echo $index + 1; ?></strong> -
                <?php echo htmlspecialchars($question['title']); ?>
            </div>
            <div class="card-body">
                <?php if (!empty($question['description'])): ?>
                <p class="text-muted mb-3">
                    <small><?php echo htmlspecialchars($question['description']); ?></small>
                </p>
                <?php endif; ?>

                <?php if ($question['type'] === 'rating'): ?>
                <!-- Rating Question -->
                <div class="rating-group" data-question-id="<?php echo $question['id']; ?>"
                    data-required="<?php echo $question['is_required'] ? '1' : '0'; ?>">
                    <div class="btn-group w-100" role="group">
                        <?php foreach ($question['options'] as $option_idx => $option): ?>
                        <input type="radio" class="btn-check" name="responses[<?php echo $question['id']; ?>]"
                            id="q<?php echo $question['id']; ?>_opt<?php echo $option_idx; ?>"
                            value="<?php echo htmlspecialchars($option); ?>"
                            <?php echo ($question['is_required']) ? 'required' : ''; ?>>
                        <label class="btn btn-outline-primary"
                            for="q<?php echo $question['id']; ?>_opt<?php echo $option_idx; ?>">
                            <?php echo htmlspecialchars($option); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($question['is_required']): ?>
                    <div class="invalid-feedback" style="display: none;">
                        <small class="text-danger">請選擇一個選項</small>
                    </div>
                    <?php endif; ?>
                </div>

                <?php elseif ($question['type'] === 'multiple_choice'): ?>
                <!-- Multiple Choice Question -->
                <div class="choice-group" data-question-id="<?php echo $question['id']; ?>"
                    data-required="<?php echo $question['is_required'] ? '1' : '0'; ?>">
                    <?php foreach ($question['options'] as $option_idx => $option): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input checkbox-group"
                            type="<?php echo ($question['allow_multiple']) ? 'checkbox' : 'radio'; ?>"
                            name="responses[<?php echo $question['id']; ?>]<?php echo ($question['allow_multiple']) ? '[]' : ''; ?>"
                            id="q<?php echo $question['id']; ?>_opt<?php echo $option_idx; ?>"
                            value="<?php echo htmlspecialchars($option); ?>">
                        <label class="form-check-label"
                            for="q<?php echo $question['id']; ?>_opt<?php echo $option_idx; ?>">
                            <?php echo htmlspecialchars($option); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    <div class="invalid-feedback" style="display: none;">
                        <small class="text-danger">請至少選擇一個選項</small>
                    </div>
                </div>

                <?php elseif ($question['type'] === 'text'): ?>
                <!-- Text Question -->
                <textarea class="form-control" name="responses[<?php echo $question['id']; ?>]" rows="4"
                    placeholder="請輸入您的回答..." <?php echo ($question['is_required']) ? 'required' : ''; ?>></textarea>

                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Submit Buttons -->
        <div class="d-grid gap-2 mb-3">
            <?php if ($survey_is_open): ?>
            <button type="submit" name="submit_survey" class="btn btn-primary btn-lg">
                <i class="fas fa-paper-plane"></i> 提交問卷
            </button>
            <?php else: ?>
            <button type="submit" name="submit_survey" class="btn btn-primary btn-lg" disabled>
                <i class="fas fa-lock"></i> 問卷已關閉，無法提交
            </button>
            <small class="text-muted text-center">此學期問卷填寫已結束</small>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                var isValid = form.checkValidity();

                // Check required radio/checkbox groups (both rating and choice)
                var requiredGroups = form.querySelectorAll(
                    '.rating-group[data-required="1"], .choice-group[data-required="1"]'
                );
                requiredGroups.forEach(function(group) {
                    var questionId = group.getAttribute('data-question-id');
                    var inputs = group.querySelectorAll(
                        'input[type="checkbox"], input[type="radio"]');
                    var isChecked = false;

                    inputs.forEach(function(input) {
                        if (input.checked) {
                            isChecked = true;
                        }
                    });

                    if (!isChecked) {
                        isValid = false;
                        var feedback = group.querySelector('.invalid-feedback');
                        if (feedback) {
                            feedback.style.display = 'block';
                        }
                    } else {
                        var feedback = group.querySelector('.invalid-feedback');
                        if (feedback) {
                            feedback.style.display = 'none';
                        }
                    }
                });

                if (!isValid) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

</body>

</html>