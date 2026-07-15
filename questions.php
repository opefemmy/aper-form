<?php
require_once 'config.php';
requireAdminLogin();

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$message = getMessage();
$pdo = getDBConnection();

// Get institution settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';
$instAddress = $settings['institution_address'] ?? '';
$logo = $settings['institution_logo'] ?? '';

// Check if new columns exist
$hasSubCategory = false;
$hasFileUpload = false;
$hasQuestionLabel = false;
try {
    $pdo->query("SELECT sub_category FROM evaluation_questions LIMIT 1");
    $hasSubCategory = true;
} catch (Exception $e) {}
try {
    $pdo->query("SELECT allowed_file_types FROM evaluation_questions LIMIT 1");
    $hasFileUpload = true;
} catch (Exception $e) {}
try {
    $pdo->query("SELECT question_label FROM evaluation_questions LIMIT 1");
    $hasQuestionLabel = true;
} catch (Exception $e) {}

// Handle add/edit/delete question
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_question'])) {
        $questionType = $_POST['question_type'];
        $options = '';

        // For multiple choice, get options
        if ($questionType === 'multiple_choice' || $questionType === 'single_choice') {
            $options = sanitize($_POST['options']);
        }

        // Handle custom category
        $category = $_POST['category'];
        if ($category === 'custom' && !empty($_POST['custom_category'])) {
            $category = sanitize($_POST['custom_category']);
        }

        // Handle sub-category (custom sub-category)
        $subCategory = null;
        if (!empty($_POST['sub_category']) && $_POST['sub_category'] !== '') {
            if ($_POST['sub_category'] === 'custom' && !empty($_POST['custom_sub_category'])) {
                $subCategory = sanitize($_POST['custom_sub_category']);
            } elseif ($_POST['sub_category'] !== 'custom') {
                $subCategory = sanitize($_POST['sub_category']);
            }
        }

        // Handle file upload settings
        $allowedFileTypes = 'pdf,doc,docx';
        $maxFileSize = 5;
        if ($questionType === 'file_upload') {
            $allowedFileTypes = sanitize($_POST['allowed_file_types'] ?? 'pdf,doc,docx');
            $maxFileSize = intval($_POST['max_file_size'] ?? 5);
        }

        // Handle question group and label
        $questionGroup = !empty($_POST['question_group']) ? sanitize($_POST['question_group']) : null;
        $questionLabel = !empty($_POST['question_label']) ? sanitize($_POST['question_label']) : null;
        $questionOrder = intval($_POST['question_order'] ?? 0);

        // Build query based on available columns
        if ($hasQuestionLabel) {
            // Full query with all new columns
            $stmt = $pdo->prepare("INSERT INTO evaluation_questions (category, sub_category, question_text, question_type, options, target_staff_category, allowed_file_types, max_file_size, question_group, question_label, question_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $category,
                $subCategory,
                sanitize($_POST['question_text']),
                $questionType,
                $options,
                sanitize($_POST['target_staff_category'] ?? 'both'),
                $allowedFileTypes,
                $maxFileSize,
                $questionGroup,
                $questionLabel,
                $questionOrder
            ]);
        } elseif ($hasSubCategory) {
            $stmt = $pdo->prepare("INSERT INTO evaluation_questions (category, sub_category, question_text, question_type, options, target_staff_category) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $category,
                $subCategory,
                sanitize($_POST['question_text']),
                $questionType,
                $options,
                sanitize($_POST['target_staff_category'] ?? 'both')
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO evaluation_questions (category, question_text, question_type, options, target_staff_category) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $category,
                sanitize($_POST['question_text']),
                $questionType,
                $options,
                sanitize($_POST['target_staff_category'] ?? 'both')
            ]);
        }
        showMessage('Question added successfully!', 'success');
    }

    // Handle question reordering
    if (isset($_POST['reorder_questions']) && isset($_POST['question_orders'])) {
        $orders = $_POST['question_orders'];
        foreach ($orders as $questionId => $order) {
            $stmt = $pdo->prepare("UPDATE evaluation_questions SET question_order = ? WHERE id = ?");
            $stmt->execute([intval($order), intval($questionId)]);
        }
        showMessage('Question order saved successfully!', 'success');
    }

    if (isset($_POST['update_question'])) {
        $questionType = $_POST['question_type'];
        $options = '';

        if ($questionType === 'multiple_choice' || $questionType === 'single_choice') {
            $options = sanitize($_POST['options']);
        }

        // Handle custom category
        $category = $_POST['category'];
        if ($category === 'custom' && !empty($_POST['custom_category'])) {
            $category = sanitize($_POST['custom_category']);
        }

        // Handle sub-category (custom sub-category)
        $subCategory = null;
        if (!empty($_POST['sub_category']) && $_POST['sub_category'] !== '') {
            if ($_POST['sub_category'] === 'custom' && !empty($_POST['custom_sub_category'])) {
                $subCategory = sanitize($_POST['custom_sub_category']);
            } elseif ($_POST['sub_category'] !== 'custom') {
                $subCategory = sanitize($_POST['sub_category']);
            }
        }

        // Handle file upload settings
        $allowedFileTypes = 'pdf,doc,docx';
        $maxFileSize = 5;
        if ($questionType === 'file_upload') {
            $allowedFileTypes = sanitize($_POST['allowed_file_types'] ?? 'pdf,doc,docx');
            $maxFileSize = intval($_POST['max_file_size'] ?? 5);
        }

        // Build query based on available columns
        if ($hasQuestionLabel) {
            $stmt = $pdo->prepare("UPDATE evaluation_questions SET category = ?, sub_category = ?, question_text = ?, question_type = ?, options = ?, is_active = ?, target_staff_category = ?, allowed_file_types = ?, max_file_size = ?, question_group = ?, question_label = ?, question_order = ? WHERE id = ?");
            $stmt->execute([
                $category,
                $subCategory,
                sanitize($_POST['question_text']),
                $questionType,
                $options,
                isset($_POST['is_active']) ? 1 : 0,
                sanitize($_POST['target_staff_category'] ?? 'both'),
                $allowedFileTypes,
                $maxFileSize,
                $questionGroup,
                $questionLabel,
                $questionOrder,
                intval($_POST['question_id'])
            ]);
        } elseif ($hasSubCategory) {
            $stmt = $pdo->prepare("UPDATE evaluation_questions SET category = ?, sub_category = ?, question_text = ?, question_type = ?, options = ?, is_active = ?, target_staff_category = ? WHERE id = ?");
            $stmt->execute([
                $category,
                $subCategory,
                sanitize($_POST['question_text']),
                $questionType,
                $options,
                isset($_POST['is_active']) ? 1 : 0,
                sanitize($_POST['target_staff_category'] ?? 'both'),
                intval($_POST['question_id'])
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE evaluation_questions SET category = ?, question_text = ?, question_type = ?, options = ?, is_active = ?, target_staff_category = ? WHERE id = ?");
            $stmt->execute([
                $category,
                sanitize($_POST['question_text']),
                $questionType,
                $options,
                isset($_POST['is_active']) ? 1 : 0,
                sanitize($_POST['target_staff_category'] ?? 'both'),
                intval($_POST['question_id'])
            ]);
        }
        showMessage('Question updated successfully!', 'success');
    }

    if (isset($_POST['delete_question'])) {
        $stmt = $pdo->prepare("DELETE FROM evaluation_questions WHERE id = ?");
        $stmt->execute([intval($_POST['question_id'])]);
        showMessage('Question deleted successfully!', 'success');
    }

    redirect('questions.php');
}

// Get filter category
$filterCategory = $_GET['filter'] ?? 'all';

// Get sub-categories for dropdown (if table exists)
$subCategories = [];
$subCategoriesByCategory = [];
try {
    $stmt = $pdo->query("SELECT * FROM question_sub_categories WHERE is_active = 1 ORDER BY category, sub_category_order");
    $subCategories = $stmt->fetchAll();
    // Group sub-categories by category
    foreach ($subCategories as $sc) {
        $subCategoriesByCategory[$sc['category']][] = $sc;
    }
} catch (Exception $e) {
    // Table doesn't exist yet - sub-categories will be empty
}

// Build query based on filter - Supervising Officer questions are separate
if ($filterCategory === 'supervising-officer' || $filterCategory === 'hod') {
    // Supervising Officer evaluation questions - ONLY show questions with target_staff_category = 'hod'
    $stmt = $pdo->query("SELECT * FROM evaluation_questions WHERE target_staff_category = 'hod' ORDER BY category, id");
} elseif ($filterCategory === 'academic') {
    $stmt = $pdo->query("SELECT * FROM evaluation_questions WHERE target_staff_category = 'academic' ORDER BY category, id");
} elseif ($filterCategory === 'non-teaching') {
    // Non-Teaching Senior (Level 6+) - only get non-teaching questions (NOT junior)
    $stmt = $pdo->query("SELECT * FROM evaluation_questions WHERE target_staff_category = 'non-teaching' ORDER BY category, id");
} elseif ($filterCategory === 'non-teaching-junior') {
    // Junior Staff (Level 5 and below)
    $stmt = $pdo->query("SELECT * FROM evaluation_questions WHERE target_staff_category = 'non-teaching-junior' ORDER BY category, id");
} else {
    // All questions EXCEPT Supervising Officer - for general staff questions
    $stmt = $pdo->query("SELECT * FROM evaluation_questions WHERE target_staff_category != 'hod' ORDER BY category, id");
}
$questions = $stmt->fetchAll();

$questionsByCategory = [];
foreach ($questions as $q) {
    $questionsByCategory[$q['category']][] = $q;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="theme-overrides.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: <?php echo $settings['primary_color'] ?? '#247d57'; ?>; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #247d57 0%, #1a5238 100%); color: white; }
        .sidebar a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 12px 15px; display: block; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); color: white; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3">
                <div class="text-center sidebar-header">
                    <?php if (!empty($logo)): ?>
                        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" style="max-height: 55px; margin-bottom: 10px;">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap fa-2x mb-2" style="font-size: 2rem;"></i>
                    <?php endif; ?>
                    <h5 class="mb-0" style="font-weight: 800;"><?php echo htmlspecialchars($instName); ?></h5>
                    <?php if (!empty($instAddress)): ?>
                        <small class="d-block" style="max-width: 180px; margin: 5px auto 0; font-weight: 600;"><?php echo htmlspecialchars($instAddress); ?></small>
                    <?php endif; ?>
                </div>
                <div class="py-3">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="staff.php"><i class="fas fa-users"></i> Staff</a>
                    <a href="staff-upload.php"><i class="fas fa-upload"></i> Upload Staff</a>
                    <a href="manage-evaluators.php"><i class="fas fa-user-tie"></i> Evaluators</a>
                    <a href="questions.php" class="active"><i class="fas fa-question-circle"></i> Questions</a>
                    <a href="question-sub-categories.php"><i class="fas fa-folder-tree"></i> Sub-Categories</a>
                    <a href="evaluate.php"><i class="fas fa-clipboard-check"></i> Evaluate</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <?php if (hasPermission('download_all_data')): ?>
                    <a href="download-data.php"><i class="fas fa-download"></i> Download Data</a>
                    <?php endif; ?>
                    <a href="sessions.php"><i class="fas fa-calendar"></i> Sessions</a>
                    <a href="logout.php" class="text-warning"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show">
                        <?php echo $message['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-question-circle me-2"></i>Evaluation Questions</h2>
                    <div class="d-flex gap-2">
                        <!-- Filter Dropdown -->
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-filter me-2"></i>
                                <?php echo $filterCategory === 'all' ? 'All Staff Questions' :
                                    ($filterCategory === 'supervising-officer' ? 'Supervising Officer Questions' :
                                    ($filterCategory === 'academic' ? 'Academic Staff Questions' :
                                    ($filterCategory === 'non-teaching-junior' ? 'Junior Staff Questions (Level 5 and below)' : 'Non-Teaching Senior Questions (Level 6+)'))); ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item <?php echo $filterCategory === 'all' ? 'active' : ''; ?>" href="questions.php?filter=all"><i class="fas fa-list me-2"></i>All Staff Questions</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item <?php echo $filterCategory === 'supervising-officer' ? 'active' : ''; ?>" href="questions.php?filter=supervising-officer"><i class="fas fa-user-tie me-2"></i>Supervising Officer Questions</a></li>
                                <li><a class="dropdown-item <?php echo $filterCategory === 'academic' ? 'active' : ''; ?>" href="questions.php?filter=academic"><i class="fas fa-graduation-cap me-2"></i>Academic Staff Questions</a></li>
                                <li><a class="dropdown-item <?php echo $filterCategory === 'non-teaching' ? 'active' : ''; ?>" href="questions.php?filter=non-teaching"><i class="fas fa-briefcase me-2"></i>Non-Teaching Senior (Level 6+)</a></li>
                                <li><a class="dropdown-item <?php echo $filterCategory === 'non-teaching-junior' ? 'active' : ''; ?>" href="questions.php?filter=non-teaching-junior"><i class="fas fa-user-plus me-2"></i>Junior Staff (Level 5 and below)</a></li>
                            </ul>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                            <i class="fas fa-plus me-2"></i>Add Question
                        </button>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#reorderQuestionsModal">
                            <i class="fas fa-sort-numeric-up me-2"></i>Reorder Questions
                        </button>
                    </div>
                </div>

                <?php if ($filterCategory === 'supervising-officer'): ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Supervising Officer Evaluation Questions:</strong> These are the questions that Supervising Officers will use to evaluate their staff.
                    You can add, edit, or delete these questions. The questions are grouped by category for easy management.
                </div>
                <?php endif; ?>

                <p class="text-muted mb-4">
                    Customize the questions that staff will answer in their self-evaluation form.
                    You can add, edit, or delete questions for each category.
                </p>

                <!-- Questions by Category -->
                <?php if (empty($questionsByCategory)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No questions found for the selected filter.
                    <a href="questions.php?filter=all">View all questions</a> or
                    <a href="#" data-bs-toggle="modal" data-bs-target="#addQuestionModal">add a new question</a>.
                </div>
                <?php else: ?>
                <?php foreach ($questionsByCategory as $category => $categoryQuestions): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-folder me-2"></i><?php echo htmlspecialchars($category); ?> (<?php echo count($categoryQuestions); ?> questions)</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Question Type</th>
                                    <th>Group/Label</th>
                                    <th>Sub-Category</th>
                                    <th>Question</th>
                                    <th>Target Staff</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryQuestions as $q):
                                    $typeLabels = [
                                        'rating' => '⭐ Rating',
                                        'single_choice' => '☐ Single',
                                        'multiple_choice' => '☑ Multiple',
                                        'true_false' => '✓ True/False',
                                        'short_answer' => '✎ Short',
                                        'long_answer' => '📝 Essay',
                                        'yes_no' => 'Yes/No',
                                        'scale' => '📏 Scale',
                                        'file_upload' => '📎 Upload'
                                    ];
                                ?>
                                <tr>
                                    <td><span class="badge bg-info"><?php echo $typeLabels[$q['question_type']] ?? $q['question_type']; ?></span></td>
                                    <td>
                                        <?php if (!empty($q['question_group']) || !empty($q['question_label'])): ?>
                                            <?php if (!empty($q['question_group'])): ?>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($q['question_group']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($q['question_label'])): ?>
                                                <span class="badge bg-warning text-dark">(<?php echo htmlspecialchars($q['question_label']); ?>)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo !empty($q['sub_category']) ? '<span class="badge bg-secondary">' . htmlspecialchars($q['sub_category']) . '</span>' : '<span class="text-muted">-</span>'; ?></td>
                                    <td><?php echo htmlspecialchars($q['question_text']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($q['target_staff_category'] ?? 'both') == 'both' ? 'primary' : (($q['target_staff_category'] ?? '') == 'academic' ? 'success' : (($q['target_staff_category'] ?? '') == 'non-teaching-junior' ? 'info' : 'warning')); ?>">
                                            <?php
                                                $target = $q['target_staff_category'] ?? 'both';
                                                echo $target == 'both' ? 'All Staff' : ($target == 'academic' ? 'Academic' : ($target == 'non-teaching-junior' ? 'Junior Staff (L5)' : ($target == 'non-teaching' ? 'Non-Teaching Senior (L6+)' : 'Non-Teaching')));
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $q['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $q['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editQuestionModal<?php echo $q['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                            <button type="submit" name="delete_question" class="btn btn-sm btn-danger" onclick="return confirm('Delete this question?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editQuestionModal<?php echo $q['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Question</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="question_id" value="<?php echo $q['id']; ?>">
                                                    <div class="row">
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Category</label>
                                                            <select class="form-select" name="category" id="edit_category_<?php echo $q['id']; ?>" onchange="toggleCustomCategory(this, 'edit_<?php echo $q['id']; ?>')">
                                                                <option value="Teaching" <?php echo $q['category'] == 'Teaching' ? 'selected' : ''; ?>>Teaching</option>
                                                                <option value="Research" <?php echo $q['category'] == 'Research' ? 'selected' : ''; ?>>Research</option>
                                                                <option value="Administrative" <?php echo $q['category'] == 'Administrative' ? 'selected' : ''; ?>>Administrative</option>
                                                                <option value="Community" <?php echo $q['category'] == 'Community' ? 'selected' : ''; ?>>Community</option>
                                                                <option value="Professional" <?php echo $q['category'] == 'Professional' ? 'selected' : ''; ?>>Professional</option>
                                                                <option value="custom" <?php echo !in_array($q['category'], ['Teaching', 'Research', 'Administrative', 'Community', 'Professional']) ? 'selected' : ''; ?>>+ Add Custom Category</option>
                                                            </select>
                                                            <input type="text" class="form-control mt-2" name="custom_category" id="edit_<?php echo $q['id']; ?>_custom_category" placeholder="Enter custom category name" style="display:none;" value="<?php echo !in_array($q['category'], ['Teaching', 'Research', 'Administrative', 'Community', 'Professional']) ? htmlspecialchars($q['category']) : ''; ?>">
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Sub-Category</label>
                                                            <select class="form-select" name="sub_category" id="edit_sub_category_<?php echo $q['id']; ?>">
                                                                <option value="">None</option>
                                                                <?php
                                                                $currentCategory = $q['category'];
                                                                if (isset($subCategoriesByCategory[$currentCategory])):
                                                                    foreach ($subCategoriesByCategory[$currentCategory] as $sc): ?>
                                                                <option value="<?php echo htmlspecialchars($sc['sub_category_name']); ?>" <?php echo ($q['sub_category'] ?? '') == $sc['sub_category_name'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($sc['sub_category_name']); ?>
                                                                </option>
                                                                <?php endforeach; ?>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Question Type</label>
                                                            <select class="form-select" name="question_type" id="edit_type_<?php echo $q['id']; ?>" onchange="toggleOptionsField(this, 'edit_<?php echo $q['id']; ?>')">
                                                                <option value="rating" <?php echo $q['question_type'] == 'rating' ? 'selected' : ''; ?>>⭐ Rating (1-5 Stars)</option>
                                                                <option value="single_choice" <?php echo $q['question_type'] == 'single_choice' ? 'selected' : ''; ?>>☐ Single Choice</option>
                                                                <option value="multiple_choice" <?php echo $q['question_type'] == 'multiple_choice' ? 'selected' : ''; ?>>☑ Multiple Choice</option>
                                                                <option value="true_false" <?php echo $q['question_type'] == 'true_false' ? 'selected' : ''; ?>>✓ True / False</option>
                                                                <option value="short_answer" <?php echo $q['question_type'] == 'short_answer' ? 'selected' : ''; ?>>✎ Short Answer</option>
                                                                <option value="long_answer" <?php echo $q['question_type'] == 'long_answer' ? 'selected' : ''; ?>>📝 Long Response</option>
                                                                <option value="yes_no" <?php echo $q['question_type'] == 'yes_no' ? 'selected' : ''; ?>>Yes / No</option>
                                                                <option value="scale" <?php echo $q['question_type'] == 'scale' ? 'selected' : ''; ?>>📏 Scale (1-10)</option>
                                                                <option value="file_upload" <?php echo ($q['question_type'] ?? '') == 'file_upload' ? 'selected' : ''; ?>>📎 File Upload</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Staff Category</label>
                                                            <select class="form-select" name="target_staff_category">
                                                                <option value="both" <?php echo ($q['target_staff_category'] ?? 'both') == 'both' ? 'selected' : ''; ?>>All Staff</option>
                                                                <option value="academic" <?php echo ($q['target_staff_category'] ?? '') == 'academic' ? 'selected' : ''; ?>>Academic Staff Only</option>
                                                                <option value="non-teaching" <?php echo ($q['target_staff_category'] ?? '') == 'non-teaching' ? 'selected' : ''; ?>>Non-Teaching Senior (Level 6+)</option>
                                                                <option value="non-teaching-junior" <?php echo ($q['target_staff_category'] ?? '') == 'non-teaching-junior' ? 'selected' : ''; ?>>Junior Staff</option>
                                                                <option value="hod" <?php echo ($q['target_staff_category'] ?? '') == 'hod' ? 'selected' : ''; ?>>Supervising Officer Evaluation Question</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Question Text</label>
                                                        <input type="text" class="form-control" name="question_text" value="<?php echo htmlspecialchars($q['question_text']); ?>" required>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4 mb-3">
                                                            <label class="form-label">Display Order</label>
                                                            <input type="number" class="form-control" name="question_order" value="<?php echo $q['question_order'] ?? 0; ?>" min="0">
                                                            <small class="text-muted">Questions with lower numbers appear first</small>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3" id="edit_<?php echo $q['id']; ?>_options_field" <?php echo ($q['question_type'] != 'single_choice' && $q['question_type'] != 'multiple_choice') ? 'style="display:none;"' : ''; ?>>
                                                        <label class="form-label">Options (one per line)</label>
                                                        <textarea class="form-control" name="options" rows="4"><?php echo htmlspecialchars($q['options'] ?? ''); ?></textarea>
                                                    </div>
                                                    <div class="mb-3" id="edit_<?php echo $q['id']; ?>_upload_field" <?php echo ($q['question_type'] ?? '') != 'file_upload' ? 'style="display:none;"' : ''; ?>>
                                                        <label class="form-label">Allowed File Types</label>
                                                        <input type="text" class="form-control" name="allowed_file_types" value="<?php echo htmlspecialchars($q['allowed_file_types'] ?? 'pdf,doc,docx'); ?>" placeholder="pdf,doc,docx">
                                                        <small class="text-muted">Comma-separated list (e.g., pdf,doc,docx,jpg,png)</small>
                                                    </div>
                                                    <div class="mb-3" id="edit_<?php echo $q['id']; ?>_size_field" <?php echo ($q['question_type'] ?? '') != 'file_upload' ? 'style="display:none;"' : ''; ?>>
                                                        <label class="form-label">Max File Size (MB)</label>
                                                        <input type="number" class="form-control" name="max_file_size" value="<?php echo htmlspecialchars($q['max_file_size'] ?? 5); ?>" min="1" max="50">
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-12 mb-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="is_active" id="active<?php echo $q['id']; ?>" <?php echo $q['is_active'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="active<?php echo $q['id']; ?>">Active (include in form)</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_question" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php
                $activeCount = 0;
                foreach ($questions as $q) {
                    if (isset($q['is_active']) && $q['is_active'] == 1) {
                        $activeCount++;
                    }
                }
                ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> The total max score will automatically adjust based on the number of active questions.
                    Currently: <strong><?php echo $activeCount; ?></strong> active questions (out of <?php echo count($questions); ?> total).
                </div>
            </div>
        </div>
    </div>

    <!-- Add Question Modal -->
    <div class="modal fade" id="addQuestionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Question</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" id="add_category" required onchange="toggleCustomCategory(this, 'add')">
                                    <option value="">Select Category</option>
                                    <option value="Teaching">Teaching Performance</option>
                                    <option value="Research">Research Performance</option>
                                    <option value="Administrative">Administrative Duties</option>
                                    <option value="Community">Community Service</option>
                                    <option value="Professional">Professional Development</option>
                                    <option value="custom">+ Add Custom Category</option>
                                </select>
                                <input type="text" class="form-control mt-2" name="custom_category" id="add_custom_category" placeholder="Enter custom category name" style="display:none;">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sub-Category</label>
                                <select class="form-select" name="sub_category" id="add_sub_category" onchange="toggleCustomSubCategory(this)">
                                    <option value="">None</option>
                                    <optgroup label="Teaching">
                                        <option value="Lecture Delivery">Lecture Delivery</option>
                                        <option value="Student Engagement">Student Engagement</option>
                                        <option value="Course Preparation">Course Preparation</option>
                                        <option value="Course Coverage">Course Coverage</option>
                                        <option value="Time Management">Time Management</option>
                                        <option value="Assessment & Feedback">Assessment & Feedback</option>
                                    </optgroup>
                                    <optgroup label="Research">
                                        <option value="Publications">Publications</option>
                                        <option value="Conference Participation">Conference Participation</option>
                                        <option value="Research Grants">Research Grants</option>
                                        <option value="Innovations">Innovations</option>
                                        <option value="Journal Articles">Journal Articles</option>
                                    </optgroup>
                                    <optgroup label="Administrative">
                                        <option value="Meeting Attendance">Meeting Attendance</option>
                                        <option value="Punctuality">Punctuality</option>
                                        <option value="Leadership">Leadership</option>
                                        <option value="Teamwork">Teamwork</option>
                                        <option value="Record Keeping">Record Keeping</option>
                                    </optgroup>
                                    <optgroup label="Community">
                                        <option value="Community Development">Community Development</option>
                                        <option value="Committee Participation">Committee Participation</option>
                                        <option value="Institutional Representation">Institutional Representation</option>
                                    </optgroup>
                                    <optgroup label="Professional">
                                        <option value="Workshops">Workshops</option>
                                        <option value="Training Programs">Training Programs</option>
                                        <option value="Certifications">Certifications</option>
                                        <option value="Seminars">Seminars</option>
                                    </optgroup>
                                    <option value="custom">+ Add Custom Sub-Category</option>
                                </select>
                                <input type="text" class="form-control mt-2" name="custom_sub_category" id="add_custom_sub_category" placeholder="Enter custom sub-category name" style="display:none;">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Question Type</label>
                                <select class="form-select" name="question_type" id="add_question_type" onchange="toggleOptionsField(this, 'add')" required>
                                    <option value="rating">⭐ Rating (1-5 Stars)</option>
                                    <option value="single_choice">☐ Single Choice (Radio)</option>
                                    <option value="multiple_choice">☐ Multiple Choice (Checkboxes)</option>
                                    <option value="true_false">✓ True / False</option>
                                    <option value="short_answer">✎ Short Answer</option>
                                    <option value="long_answer">📝 Long Response (Essay)</option>
                                    <option value="yes_no">Yes / No</option>
                                    <option value="scale">📏 Scale (1-10)</option>
                                    <option value="file_upload">📎 File Upload</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Staff Category</label>
                                <select class="form-select" name="target_staff_category" required>
                                    <option value="both" <?php echo $filterCategory === 'all' ? 'selected' : ''; ?>>All Staff</option>
                                    <option value="academic" <?php echo $filterCategory === 'academic' ? 'selected' : ''; ?>>Academic Staff Only</option>
                                    <option value="non-teaching" <?php echo $filterCategory === 'non-teaching' ? 'selected' : ''; ?>>Non-Teaching Senior (Level 6+)</option>
                                    <option value="non-teaching-junior" <?php echo $filterCategory === 'non-teaching-junior' ? 'selected' : ''; ?>>Junior Staff</option>
                                    <option value="hod" <?php echo $filterCategory === 'hod' ? 'selected' : ''; ?>>Supervising Officer Evaluation Question</option>
                                </select>
                                <small class="text-muted">Which staff type sees this question. Junior Staff = Level 5 and below non-teaching staff.</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Question Group (Optional)</label>
                                <input type="text" class="form-control" name="question_group" placeholder="e.g., Publications (groups related questions)">
                                <small class="text-muted">Group related questions together</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Question Label (Optional)</label>
                                <input type="text" class="form-control" name="question_label" placeholder="e.g., a, b, c, I, II, III">
                                <small class="text-muted">Add label like (a), (b), I, II for sub-parts</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Question Text</label>
                            <input type="text" class="form-control" name="question_text" placeholder="e.g., How would you rate your teaching performance?" required>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Display Order</label>
                                <input type="number" class="form-control" name="question_order" value="0" min="0" placeholder="Order number">
                                <small class="text-muted">Questions with lower numbers appear first</small>
                            </div>
                        </div>
                        <div class="mb-3" id="add_options_field" style="display:none;">
                            <label class="form-label">Options (one per line)</label>
                            <textarea class="form-control" name="options" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                            <small class="text-muted">Enter each option on a new line</small>
                        </div>
                        <div class="mb-3" id="add_upload_field" style="display:none;">
                            <label class="form-label">Allowed File Types</label>
                            <input type="text" class="form-control" name="allowed_file_types" value="pdf,doc,docx" placeholder="pdf,doc,docx">
                            <small class="text-muted">Comma-separated list (e.g., pdf,doc,docx,jpg,png)</small>
                        </div>
                        <div class="mb-3" id="add_size_field" style="display:none;">
                            <label class="form-label">Max File Size (MB)</label>
                            <input type="number" class="form-control" name="max_file_size" value="5" min="1" max="50">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_question" class="btn btn-primary">Add Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleOptionsField(selectElem, prefix) {
        var questionType = selectElem.value;
        var optionsField = document.getElementById(prefix + '_options_field');
        var uploadField = document.getElementById(prefix + '_upload_field');
        var sizeField = document.getElementById(prefix + '_size_field');

        if (questionType === 'single_choice' || questionType === 'multiple_choice') {
            optionsField.style.display = 'block';
        } else {
            optionsField.style.display = 'none';
        }

        // Handle file upload fields
        if (questionType === 'file_upload') {
            if (uploadField) uploadField.style.display = 'block';
            if (sizeField) sizeField.style.display = 'block';
        } else {
            if (uploadField) uploadField.style.display = 'none';
            if (sizeField) sizeField.style.display = 'none';
        }
    }
    function toggleCustomCategory(selectElem, prefix) {
        var customField = document.getElementById(prefix + '_custom_category');
        if (selectElem.value === 'custom') {
            customField.style.display = 'block';
            customField.required = true;
        } else {
            customField.style.display = 'none';
            customField.required = false;
            customField.value = '';
        }
    }
    function toggleCustomSubCategory(selectElem) {
        var customField = document.getElementById('add_custom_sub_category');
        if (selectElem.value === 'custom') {
            customField.style.display = 'block';
            customField.required = true;
        } else {
            customField.style.display = 'none';
            customField.required = false;
            customField.value = '';
        }
    }
    // Add event listener for sub-category dropdown
    document.getElementById('add_sub_category')?.addEventListener('change', function() {
        toggleCustomSubCategory(this);
    });
    </script>

    <!-- Reorder Questions Modal -->
    <div class="modal fade" id="reorderQuestionsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="questions.php">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-sort-numeric-up me-2"></i>Reorder Questions</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Enter the display order number for each question. Questions with lower numbers appear first.</p>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-bordered table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 80px;">Order</th>
                                        <th>Question</th>
                                        <th>Category</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get all questions for reordering
                                    $allQuestionsStmt = $pdo->query("SELECT id, question_text, category, question_order, target_staff_category FROM evaluation_questions ORDER BY COALESCE(question_order, 99999), category, id");
                                    $allQuestions = $allQuestionsStmt->fetchAll();
                                    foreach ($allQuestions as $aq):
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" name="question_orders[<?php echo $aq['id']; ?>]" value="<?php echo $aq['question_order'] ?? 0; ?>" min="0" style="width: 80px;">
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($aq['question_text'], 0, 80)) . (strlen($aq['question_text']) > 80 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo $aq['target_staff_category'] === 'academic' ? 'success' :
                                                    ($aq['target_staff_category'] === 'non-teaching' ? 'warning' :
                                                    ($aq['target_staff_category'] === 'non-teaching-junior' ? 'info' :
                                                    ($aq['target_staff_category'] === 'hod' ? 'primary' : 'secondary')));
                                            ?>">
                                                <?php echo htmlspecialchars($aq['category']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reorder_questions" class="btn btn-success"><i class="fas fa-save me-2"></i>Save Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Footer -->
    <footer class="mt-4 py-3" style="background: linear-gradient(180deg, <?php echo $settings['primary_color'] ?? '#247d57'; ?> 0%, <?php echo $settings['secondary_color'] ?? '#1a5238'; ?> 100%); color: white; border-radius: 8px;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <small><?php echo !empty($settings['copyright_text']) ? htmlspecialchars($settings['copyright_text']) : '&copy; ' . date('Y') . ' ' . htmlspecialchars($settings['institution_name'] ?? 'Institution') . '. All rights reserved.'; ?></small>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>Powered by APER System</small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>