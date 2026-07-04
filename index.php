<?php
/**
 * Survey Submission Page - Allows Users to Fill Out Survey
 */
$page_title = '問卷填寫';
include 'db.php';
include 'header.php';

$message = '';
$message_type = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    $respondent = trim($_POST['respondent_name'] ?? 'Anonymous');
    $student_id = trim($_POST['student_id'] ?? '');
    $responses = $_POST['responses'] ?? [];
    
    // Get current academic year and semester
    $settings = get_current_academic_settings();
    $academic_year = $settings['academic_year'];
    $semester = $settings['semester'];
    
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
        
        $sql = "INSERT INTO responses (question_id, academic_year, semester, answer, respondent, student_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("iiisssss", $question_id, $academic_year, $semester, $answer, $respondent_name, $student_id_clean, $ip_address, $user_agent);
                if ($stmt->execute()) {
                    $saved_count++;
                }
            }
        }
    
    if ($saved_count > 0) {
        $message = "感謝您的填寫！已收到 $saved_count 份回應。";
        $message_type = 'success';
    } else {
        $message = '請至少回答一個問題！';
        $message_type = 'warning';
    }
}

$questions = get_all_questions();
?>

<div class="container container-main">
    <h1 class="page-title">
        <i class="fas fa-poll"></i> 填寫問卷
    </h1>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'info-circle'; ?>"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (count($questions) === 0): ?>
    <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle"></i> 目前沒有可填寫的問卷
    </div>
    <?php else: ?>
    <form method="POST" action="" class="needs-validation" novalidate>
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
            <button type="submit" name="submit_survey" class="btn btn-primary btn-lg">
                <i class="fas fa-paper-plane"></i> 提交問卷
            </button>
        </div>
    </form>
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