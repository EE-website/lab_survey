<?php
/**
 * Admin Page - Design and Manage Questions
 */
$page_title = '管理問題';
include 'db.php';
include 'header.php';

$message = '';
$message_type = '';

// Get current academic settings
$current_settings = get_current_academic_settings();

// Process academic year and semester settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_academic_settings') {
        $academic_year = intval($_POST['academic_year'] ?? $current_settings['academic_year']);
        $semester = intval($_POST['semester'] ?? $current_settings['semester']);
        
        if ($academic_year > 0 && ($semester === 1 || $semester === 2)) {
            if (set_academic_settings($academic_year, $semester)) {
                $message = '學年度設定已更新：民國 ' . $academic_year . ' ' . get_semester_name($semester);
                $message_type = 'success';
                $current_settings = get_current_academic_settings();
            } else {
                $message = '更新設定失敗，請稍後重試';
                $message_type = 'danger';
            }
        } else {
            $message = '請輸入有效的學年度和學期';
            $message_type = 'warning';
        }
    }
    
    // Process new question addition
    if ($_POST['action'] === 'add_question') {
        $title = trim($_POST['title'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
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
        
        // Get allow_multiple and is_required flags
        $allow_multiple = isset($_POST['allow_multiple']) ? 1 : 0;
        $is_required = isset($_POST['is_required']) ? 1 : 0;
        
        if (!empty($title) && !empty($type)) {
            add_question($title, $type, $description, $options, $allow_multiple, $is_required);
            $message = '新增問題成功';
            $message_type = 'success';
        } else {
            $message = '請填寫所有必填欄位';
            $message_type = 'warning';
        }
    }
    
    // Process question deletion
    if ($_POST['action'] === 'delete_question') {
        $question_id = intval($_POST['question_id'] ?? 0);
        if ($question_id > 0) {
            delete_question($question_id);
            $message = '刪除問題成功';
            $message_type = 'success';
        }
    }
}

$questions = get_all_questions();
?>

<div class="container container-main">
    <h1 class="page-title">
        <i class="fas fa-cog"></i> 管理問題
    </h1>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Academic Year and Semester Settings -->
    <div class="alert alert-primary mb-4" role="alert">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-calendar-alt"></i>
                <strong>現在學期：民國 <?php echo $current_settings['academic_year']; ?>
                    <?php echo get_semester_name($current_settings['semester']); ?></strong>
            </div>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                data-bs-target="#academicSettingsModal">
                <i class="fas fa-edit"></i> 修改
            </button>
        </div>
    </div>

    <!-- Academic Settings Modal -->
    <div class="modal fade" id="academicSettingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-cog"></i> 修改學年度和學期
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_academic_settings">

                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="academic_year_input" class="form-label">
                                <strong>學年度（民國）</strong> <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="academic_year_input" name="academic_year"
                                value="<?php echo $current_settings['academic_year']; ?>" min="100" required>
                            <small class="text-muted">例如：114、115、116...</small>
                        </div>

                        <div class="mb-3">
                            <label for="semester_select" class="form-label">
                                <strong>學期</strong> <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="semester_select" name="semester" required>
                                <option value="1"
                                    <?php echo ($current_settings['semester'] === 1) ? 'selected' : ''; ?>>
                                    上學期
                                </option>
                                <option value="2"
                                    <?php echo ($current_settings['semester'] === 2) ? 'selected' : ''; ?>>
                                    下學期
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存設定
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Add Question Form -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus"></i> 新增問題
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_question">

                        <div class="mb-3">
                            <label for="title" class="form-label">
                                <strong>問題標題</strong> <span class="text-danger">*</span>
                                <small class="text-muted">(最多255字)</small>
                            </label>
                            <input type="text" class="form-control" id="title" name="title"
                                placeholder="例如：這個實驗課程的難度如何？" maxlength="255" required>
                        </div>

                        <div class="mb-3">
                            <label for="type" class="form-label">
                                <strong>問題類型</strong> <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="type" name="type" required onchange="toggleOptionsInput()">
                                <option value="">請選擇類型</option>
                                <option value="rating">評分（1-5）</option>
                                <option value="multiple_choice">多選題</option>
                                <option value="text">文字題</option>
                            </select>
                        </div>

                        <div class="mb-3" id="options_container" style="display: none;">
                            <label for="options" class="form-label">
                                <strong>選項</strong>
                                <small class="text-muted">(每一行一個選項，最多100字)</small>
                            </label>
                            <textarea class="form-control" id="options" name="options" rows="4"
                                placeholder="選項 1&#10;選項 2&#10;選項 3" maxlength="1000"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">
                                <strong>描述</strong>
                                <small class="text-muted">(最多500字)</small>
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="2"
                                placeholder="額外說明" maxlength="500"></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="allow_multiple" name="allow_multiple">
                                <label class="form-check-label" for="allow_multiple">
                                    <strong>多選題允許複選</strong>
                                    <small class="text-muted">(只有選擇「多選題」時有效)</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_required" name="is_required" checked>
                                <label class="form-check-label" for="is_required">
                                    <strong>必填</strong>
                                    <small class="text-muted">(預設必填)</small>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i> 新增問題
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Existing Questions List -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> 當前問題 (<?php echo count($questions); ?>)
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    <?php if (count($questions) > 0): ?>
                    <?php foreach ($questions as $question): ?>
                    <div class="card mb-2 border-left border-primary" style="border-left-width: 4px;">
                        <div class="card-body p-2">
                            <h6 class="card-title mb-1">
                                <?php echo htmlspecialchars($question['title']); ?>
                            </h6>
                            <small class="text-muted d-block">
                                <strong>類型：</strong>
                                <?php
                                        $type_names = ['rating' => '評分', 'multiple_choice' => '多選題', 'text' => '文字題'];
                                        echo $type_names[$question['type']] ?? $question['type'];
                                        ?>
                                <?php if ($question['type'] === 'multiple_choice' && $question['allow_multiple']): ?>
                                <span class="badge bg-info ms-2">複選</span>
                                <?php endif; ?>
                                <?php if (!$question['is_required']): ?>
                                <span class="badge bg-warning ms-2">非必填</span>
                                <?php endif; ?>
                            </small>
                            <?php if (!empty($question['options'])): ?>
                            <small class="text-muted d-block">
                                <strong>選項：</strong>
                                <?php echo implode(', ', $question['options']); ?>
                            </small>
                            <?php endif; ?>
                            <div class="mt-2 d-flex gap-2">
                                <a href="edit.php?id=<?php echo $question['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> 編輯
                                </a>
                                <form method="POST" action="" style="display: inline;"
                                    onsubmit="return confirm('確認刪除此問題？');">
                                    <input type="hidden" name="action" value="delete_question">
                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> 刪除
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle"></i> 尚無問題
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle options input field visibility based on question type
function toggleOptionsInput() {
    const type = document.getElementById('type').value;
    const optionsContainer = document.getElementById('options_container');
    const optionsTextarea = document.getElementById('options');

    optionsContainer.style.display = (type === 'text') ? 'none' : 'block';

    // Auto-fill default rating options
    if (type === 'rating') {
        optionsTextarea.value = '非常同意\n有些同意\n沒意見\n不太同意\n非常不同意';
    } else if (type === 'multiple_choice' && optionsTextarea.value.trim() === '') {
        optionsTextarea.value = '';
    }
}

function loadTemplate(templateName) {
    const template = templates[templateName];
    if (template) {
        document.getElementById('title').value = template.title;
        document.getElementById('type').value = template.type;
        document.getElementById('description').value = template.description;
        document.getElementById('options').value = template.options;
        toggleOptionsInput();
        document.getElementById('title').focus();
    }
}
</script>

</body>

</html>