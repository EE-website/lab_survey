<?php
/**
 * Results Page - Display Statistics and Charts
 */
$page_title = '結果統計';
include 'db.php';
include 'header.php';

$questions = get_all_questions();

// Get current academic settings
$current_settings = get_current_academic_settings();

// Get selected academic year and semester from GET parameters or use current
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $current_settings['academic_year'];
$selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : $current_settings['semester'];

$available_combinations = get_all_academic_year_semester_combinations();

// If no data available, add current settings as default
if (empty($available_combinations)) {
    $available_combinations = [['academic_year' => $current_settings['academic_year'], 'semester' => $current_settings['semester']]];
}
?>

<div class="container container-main">
    <h1 class="page-title">
        <i class="fas fa-chart-bar"></i> 結果統計
    </h1>

    <!-- Academic Year and Semester Filter -->
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
                        <select class="form-select" id="academic_year_filter" name="year" onchange="this.form.submit()">
                            <?php foreach ($available_combinations as $combo): ?>
                            <option value="<?php echo $combo['academic_year']; ?>" 
                                data-semester="<?php echo $combo['semester']; ?>"
                                <?php echo ($selected_year === intval($combo['academic_year']) && $selected_semester === intval($combo['semester'])) ? 'selected' : ''; ?>>
                                民國 <?php echo $combo['academic_year']; ?> <?php echo get_semester_name($combo['semester']); ?> (西元 <?php echo roc_to_western($combo['academic_year']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="semester" id="semester_input" value="<?php echo $selected_semester; ?>">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle"></i>
        <strong>注意：</strong> 以下是民國 <?php echo $selected_year; ?> <?php echo get_semester_name($selected_semester); ?> 的問卷回應統計結果。
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
                            $stats = get_statistics();
                            $total_all_responses = 0;
                            foreach ($questions as $q) {
                                $total_all_responses += count(get_question_responses_by_year_semester($q['id'], $selected_year, $selected_semester));
                            }
                            ?>
                        <div class="col-md-3">
                            <h3 class="text-primary"><?php echo $stats['total_questions']; ?></h3>
                            <p class="text-muted">總問題數</p>
                        </div>
                        <div class="col-md-3">
                            <h3 class="text-success"><?php echo $total_all_responses; ?></h3>
                            <p class="text-muted">總回答數</p>
                        </div>
                        <div class="col-md-3">
                            <h3 class="text-info"><?php echo $stats['total_respondents']; ?></h3>
                            <p class="text-muted">受訪者數</p>
                        </div>
                        <div class="col-md-3">
                            <h3 class="text-warning">
                                <?php echo $stats['total_questions'] > 0 ? round($total_all_responses / $stats['total_questions'], 1) : 0; ?>
                            </h3>
                            <p class="text-muted">平均回答率</p>
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