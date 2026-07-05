<?php
/**
 * Results Page - Display Statistics and Charts
 */
$page_title = '結果統計';
include 'db.php';
include 'header.php';

// Get current academic settings
$current_settings = get_current_academic_settings();

// Get selected academic year and semester from GET parameters or use current
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $current_settings['academic_year'];
$selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : $current_settings['semester'];
$selected_course = isset($_GET['course']) ? intval($_GET['course']) : 0;

$available_combinations = get_all_academic_year_semester_combinations();

// If no data available, add current settings as default
if (empty($available_combinations)) {
    $available_combinations = [['academic_year' => $current_settings['academic_year'], 'semester' => $current_settings['semester']]];
}

// Get available courses for the selected semester
$available_courses = get_courses_by_year_semester($selected_year, $selected_semester);

// Get questions based on selected course
if ($selected_course > 0) {
    // Get questions for specific course
    $questions = get_questions_by_course($selected_course);
} else {
    // Get all questions (for backward compatibility and overview)
    $questions = get_all_questions();
}
?>

<div class="container container-main">
    <h1 class="page-title">
        <i class="fas fa-chart-bar"></i> 結果統計
    </h1>

    <!-- Survey Status Alert -->
    <div class="alert alert-<?php echo ($current_settings['status'] === 'open') ? 'success' : 'warning'; ?> mb-3">
        <i class="fas fa-<?php echo ($current_settings['status'] === 'open') ? 'unlock' : 'lock'; ?>"></i>
        <strong>問卷狀態：</strong>
        <?php echo ($current_settings['status'] === 'open') ? '開放' : '已關閉'; ?>
        (民國 <?php echo $current_settings['academic_year']; ?>
        <?php echo get_semester_name($current_settings['semester']); ?>)
    </div>

    <!-- Academic Year, Semester and Course Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <label for="academic_year_filter" class="form-label">
                        <strong><i class="fas fa-filter"></i> 選擇學年度：</strong>
                    </label>
                </div>
                <div class="col-md-9">
                    <form method="GET" class="d-flex gap-2">
                        <select class="form-select" id="academic_year_filter" name="year" onchange="this.form.submit()"
                            style="flex: 1;">
                            <?php foreach ($available_combinations as $combo): ?>
                            <option value="<?php echo $combo['academic_year']; ?>"
                                data-semester="<?php echo $combo['semester']; ?>"
                                <?php echo ($selected_year === intval($combo['academic_year']) && $selected_semester === intval($combo['semester'])) ? 'selected' : ''; ?>>
                                民國 <?php echo $combo['academic_year']; ?>
                                <?php echo get_semester_name($combo['semester']); ?> (西元
                                <?php echo roc_to_western($combo['academic_year']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-select" id="course_filter" name="course" onchange="this.form.submit()"
                            style="flex: 1;">
                            <?php foreach ($available_courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"
                                <?php echo ($selected_course === intval($course['id'])) ? 'selected' : ''; ?>>
                                [<?php echo htmlspecialchars($course['course_code']); ?>]
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="semester" id="semester_input"
                            value="<?php echo $selected_semester; ?>">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle"></i>
        <strong>注意：</strong> 以下是民國 <?php echo $selected_year; ?> <?php echo get_semester_name($selected_semester); ?>
        <?php if ($selected_course > 0): ?>
        <!-- 課程：[<?php echo htmlspecialchars($available_courses[array_search($selected_course, array_column($available_courses, 'id'))]['course_code'] ?? ''); ?>] 
        <?php echo htmlspecialchars($available_courses[array_search($selected_course, array_column($available_courses, 'id'))]['course_name'] ?? ''); ?> -->
        <?php endif; ?>
        的問卷回應統計結果。
    </div>

    <?php if (count($questions) === 0): ?>
    <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle"></i> 目前沒有可用的問卷問題
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($questions as $question): ?>
        <?php 
                $responses = get_question_responses_by_year_semester($question['id'], $selected_year, $selected_semester);
                
                // Filter responses by selected course if specified
                if ($selected_course > 0) {
                    $responses = array_filter($responses, function($response) use ($selected_course) {
                        // Check if response has course_id match
                        // Since we need to query the database for course_id, we'll do it in the response
                        return true; // Placeholder - filtering should be done at database level
                    });
                    
                    // For now, filter by getting only questions from this course
                    // and checking responses belong to this course
                    $sql_filter = "SELECT id FROM responses WHERE question_id = ? AND course_id = ? AND academic_year = ? AND semester = ?";
                    $stmt = $GLOBALS['conn']->prepare($sql_filter);
                    if ($stmt) {
                        $stmt->bind_param("iiii", $question['id'], $selected_course, $selected_year, $selected_semester);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $filtered_responses = [];
                        while ($row = $result->fetch_assoc()) {
                            $filtered_responses[] = $row['id'];
                        }
                        // Re-fetch filtered responses with proper data
                        $sql_full = "SELECT id, question_id, answer, respondent, academic_year, semester, created_at FROM responses WHERE question_id = ? AND course_id = ? AND academic_year = ? AND semester = ? ORDER BY created_at DESC";
                        $stmt2 = $GLOBALS['conn']->prepare($sql_full);
                        if ($stmt2) {
                            $stmt2->bind_param("iiii", $question['id'], $selected_course, $selected_year, $selected_semester);
                            $stmt2->execute();
                            $result2 = $stmt2->get_result();
                            $responses = [];
                            while ($row = $result2->fetch_assoc()) {
                                $responses[] = $row;
                            }
                        }
                    }
                }
                
                $total_responses = count($responses);
                $stats = calculate_question_statistics($question['id'], $responses, $question['type'], $question['allow_multiple']);
                ?>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <strong><?php echo htmlspecialchars($question['title']); ?></strong>
                    <span class="badge bg-primary float-end">
                        <?php echo $total_responses; ?> 份回答
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($total_responses === 0): ?>
                    <div class="alert alert-secondary mb-0" role="alert">
                        <i class="fas fa-inbox"></i> 目前沒有回答
                    </div>
                    <?php else: ?>
                    <!-- Statistics Table -->
                    <div class="chart-container">
                        <?php foreach ($stats as $answer => $count): 
                                        $percentage = round(($count / $total_responses) * 100, 1);
                                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo htmlspecialchars($answer); ?></span>
                                <span class="badge bg-info">
                                    <?php echo $count; ?> (<?php echo $percentage; ?>%)
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                    <?php echo $percentage > 10 ? $percentage . '%' : ''; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Detailed Responses (Text Questions) -->
                    <?php if ($question['type'] === 'text'): ?>
                    <hr>
                    <h6 class="mb-2">詳細回答：</h6>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($responses as $response): ?>
                        <div class="alert alert-light border mb-2 p-2">
                            <small class="text-muted d-block">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($response['respondent']); ?>
                            </small>
                            <p class="mb-0">
                                <?php echo htmlspecialchars($response['answer']); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Overview Statistics -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-chart-pie"></i> 總覽統計
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <?php
                            $stats = get_statistics_filtered($selected_course, $selected_year, $selected_semester);
                        ?>
                        <div class="col-md-6">
                            <h3 class="text-primary"><?php echo $stats['total_questions']; ?></h3>
                            <p class="text-muted">總問題數</p>
                        </div>
                        <div class="col-md-6">
                            <h3 class="text-info"><?php echo $stats['total_respondents']; ?></h3>
                            <p class="text-muted">受訪者數</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Return Buttons -->
    <div class="mt-4 mb-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> 返回首頁
        </a>
    </div>
</div>

<!-- Chart.js for advanced charts (optional) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Advanced chart logic can be added here for more complex visualizations
</script>

</body>

</html>