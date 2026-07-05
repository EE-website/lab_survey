<?php
/**
 * Edit Page - Edit Existing Questions
 */
$page_title = '編輯問題';
include 'db.php';
include 'header.php';

$message = '';
$message_type = '';
$question_id = intval($_GET['id'] ?? 0);
$question = null;

// Get question details
if ($question_id > 0) {
    $question = get_question($question_id);
}

if ($question === null) {
    echo '<div class="container container-main mt-3"><div class="alert alert-danger">Question not found!</div></div>';
    echo '</body></html>';
    exit;
}

// Process edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_question') {
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $allow_multiple = isset($_POST['allow_multiple']) ? 1 : 0;
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    
    // Process options based on question type
    $options = [];
    if ($type === 'text') {
        $options = [];
    } else {
        $options_input = trim($_POST['options'] ?? '');
        if (!empty($options_input)) {
            $options = array_map('trim', explode("\n", $options_input));
            $options = array_filter($options);
        }
    }
    
    if (!empty($title) && !empty($type)) {
        update_question($question_id, $title, $type, $description, $options, $allow_multiple, $is_required);
        $message = '更新問題成功';
        $message_type = 'success';
        // Re-fetch the question
        $question = get_question($question_id);
    } else {
        $message = 'Please fill in all required fields!';
        $message_type = 'warning';
    }
}
?>

<div class="container container-main">
    <h1 class="page-title">
        <i class="fas fa-edit"></i> 編輯問題
    </h1>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-pencil"></i> 編輯
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_question">

                        <div class="mb-3">
                            <label for="title" class="form-label">
                                <strong>問題標題</strong> <span class="text-danger">*</span>
                                <small class="text-muted">(最多255字)</small>
                            </label>
                            <input type="text" class="form-control" id="title" name="title"
                                value="<?php echo htmlspecialchars($question['title']); ?>" maxlength="255" required>
                        </div>

                        <div class="mb-3">
                            <label for="type" class="form-label">
                                <strong>問題類型</strong> <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="type" name="type" required onchange="toggleOptionsInput()">
                                <option value="">請選擇類型</option>
                                <option value="rating" <?php echo $question['type'] === 'rating' ? 'selected' : ''; ?>>
                                    評分（1-5）</option>
                                <option value="multiple_choice"
                                    <?php echo $question['type'] === 'multiple_choice' ? 'selected' : ''; ?>>多選題
                                </option>
                                <option value="text" <?php echo $question['type'] === 'text' ? 'selected' : ''; ?>>文字題
                                </option>
                            </select>
                        </div>

                        <div class="mb-3" id="options_container"
                            <?php echo ($question['type'] === 'text') ? 'style="display: none;"' : ''; ?>>
                            <label for="options" class="form-label">
                                <strong>選項</strong>
                                <small class="text-muted">(每一行一個選項，最多100字)</small>
                            </label>
                            <textarea class="form-control" id="options" name="options" rows="4" maxlength="1000"><?php echo implode("\n", array_map('htmlspecialchars', $question['options'])); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">
                                <strong>問題描述</strong>
                                <small class="text-muted">(最多500字)</small>
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="2" maxlength="500"><?php echo htmlspecialchars($question['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="allow_multiple"
                                    name="allow_multiple" <?php echo ($question['allow_multiple']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="allow_multiple">
                                    <strong>多選題允許複選</strong>
                                    <small class="text-muted">(只有選擇「多選題」時有效)</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_required" name="is_required"
                                    <?php echo ($question['is_required']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_required">
                                    <strong>必填</strong>
                                    <small class="text-muted">(預設必填)</small>
                                </label>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>創建時間：</strong> <?php echo $question['created_at']; ?>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-save"></i> 保存更改
                            </button>
                            <a href="admin.php" class="btn btn-secondary flex-grow-1">
                                <i class="fas fa-times"></i> 取消
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Response Statistics -->
            <div class="card mt-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar"></i> 回答統計
                </div>
                <div class="card-body">
                    <?php
                    $responses = get_question_responses($question_id);
                    if (count($responses) === 0) {
                        echo '<p class="text-muted">此問題尚無回應</p>';
                    } else {
                        echo '<p><strong>回應數量：</strong> ' . count($responses) . '</p>';
                        
                        // Display response statistics
                        $stats = [];
                        foreach ($responses as $response) {
                            $answer = $response['answer'];
                            if (!isset($stats[$answer])) {
                                $stats[$answer] = 0;
                            }
                            $stats[$answer]++;
                        }
                        
                        echo '<div class="mt-3">';
                        foreach ($stats as $answer => $count) {
                            $percentage = round(($count / count($responses)) * 100, 1);
                            echo '<div class="mb-2">';
                            echo '<div class="d-flex justify-content-between mb-1">';
                            echo '<span>' . htmlspecialchars($answer) . '</span>';
                            echo '<span class="badge bg-info">' . $count . ' (' . $percentage . '%)</span>';
                            echo '</div>';
                            echo '<div class="progress">';
                            echo '<div class="progress-bar" style="width: ' . $percentage . '%"></div>';
                            echo '</div>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 mb-3">
        <a href="admin.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> 返回管理頁面
    </div>
</div>

<script>
function toggleOptionsInput() {
    const type = document.getElementById('type').value;
    const optionsContainer = document.getElementById('options_container');
    const optionsTextarea = document.getElementById('options');

    optionsContainer.style.display = (type === 'text') ? 'none' : 'block';

    // Auto-fill default rating options
    if (type === 'rating' && optionsTextarea.value.trim() === '') {
        optionsTextarea.value = '非常同意\n有些同意\n沒意見\n不太同意\n非常不同意';
    }
}
</script>

</body>

</html>