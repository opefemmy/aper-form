<?php
require_once 'config.php';
requireAdminLogin();

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

// Handle add/edit/delete sub-category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if table exists
    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM question_sub_categories LIMIT 1");
        $tableExists = true;
    } catch (Exception $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        showMessage('Please run the database update first. Go to run_db_update.php', 'danger');
    } else {
        if (isset($_POST['add_sub_category'])) {
            $category = sanitize($_POST['category']);
            $subCategoryName = sanitize($_POST['sub_category_name']);

            // Handle custom category
            if ($category === 'custom' && !empty($_POST['custom_category'])) {
                $category = sanitize($_POST['custom_category']);
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO question_sub_categories (category, sub_category_name, sub_category_order) VALUES (?, ?, ?)");
                $stmt->execute([$category, $subCategoryName, intval($_POST['sub_category_order'] ?? 0)]);
                showMessage('Sub-category added successfully!', 'success');
            } catch (Exception $e) {
                showMessage('Error adding sub-category. It may already exist.', 'danger');
            }
        }

        if (isset($_POST['update_sub_category'])) {
            $category = sanitize($_POST['category']);
            $subCategoryName = sanitize($_POST['sub_category_name']);

            // Handle custom category
            if ($category === 'custom' && !empty($_POST['custom_category'])) {
                $category = sanitize($_POST['custom_category']);
            }

            try {
                $stmt = $pdo->prepare("UPDATE question_sub_categories SET category = ?, sub_category_name = ?, sub_category_order = ?, is_active = ? WHERE id = ?");
                $stmt->execute([
                    $category,
                    $subCategoryName,
                    intval($_POST['sub_category_order'] ?? 0),
                    isset($_POST['is_active']) ? 1 : 0,
                    intval($_POST['sub_category_id'])
                ]);
                showMessage('Sub-category updated successfully!', 'success');
            } catch (Exception $e) {
                showMessage('Error updating sub-category: ' . $e->getMessage(), 'danger');
            }
        }

        if (isset($_POST['delete_sub_category'])) {
            $stmt = $pdo->prepare("DELETE FROM question_sub_categories WHERE id = ?");
            $stmt->execute([intval($_POST['sub_category_id'])]);
            showMessage('Sub-category deleted successfully!', 'success');
        }

        redirect('question-sub-categories.php');
    }
}

// Get all sub-categories grouped by category
$subCategories = [];
$subCategoriesByCategory = [];
try {
    $stmt = $pdo->query("SELECT * FROM question_sub_categories ORDER BY category, sub_category_order");
    $subCategories = $stmt->fetchAll();

    foreach ($subCategories as $sc) {
        $subCategoriesByCategory[$sc['category']][] = $sc;
    }
} catch (Exception $e) {
    // Table doesn't exist yet - sub-categories will be empty
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sub-Categories - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="theme-overrides.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: <?php echo $settings['primary_color'] ?? '#308a1e'; ?>; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #308a1e 0%, #269c16 100%); color: white; }
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
                    <a href="questions.php"><i class="fas fa-question-circle"></i> Questions</a>
                    <a href="question-sub-categories.php" class="active"><i class="fas fa-folder-tree"></i> Sub-Categories</a>
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
                    <h2><i class="fas fa-folder-tree me-2"></i>Question Sub-Categories</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubCategoryModal">
                        <i class="fas fa-plus me-2"></i>Add Sub-Category
                    </button>
                </div>

                <p class="text-muted mb-4">
                    Sub-categories help organize questions within each main category.
                    For example, under "Teaching" you can have sub-categories like "Lecture Delivery", "Student Engagement", etc.
                </p>

                <?php if (empty($subCategoriesByCategory)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No sub-categories found. Click "Add Sub-Category" to create one.
                </div>
                <?php else: ?>
                <?php foreach ($subCategoriesByCategory as $category => $categorySubCategories): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-folder me-2"></i><?php echo htmlspecialchars($category); ?> (<?php echo count($categorySubCategories); ?> sub-categories)</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Sub-Category</th>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorySubCategories as $sc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sc['sub_category_name']); ?></td>
                                    <td><?php echo $sc['sub_category_order']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $sc['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $sc['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editSubCategoryModal<?php echo $sc['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="sub_category_id" value="<?php echo $sc['id']; ?>">
                                            <button type="submit" name="delete_sub_category" class="btn btn-sm btn-danger" onclick="return confirm('Delete this sub-category?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editSubCategoryModal<?php echo $sc['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Sub-Category</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="sub_category_id" value="<?php echo $sc['id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Category</label>
                                                        <select class="form-select" name="category">
                                                            <option value="Teaching" <?php echo $sc['category'] == 'Teaching' ? 'selected' : ''; ?>>Teaching</option>
                                                            <option value="Research" <?php echo $sc['category'] == 'Research' ? 'selected' : ''; ?>>Research</option>
                                                            <option value="Administrative" <?php echo $sc['category'] == 'Administrative' ? 'selected' : ''; ?>>Administrative</option>
                                                            <option value="Community" <?php echo $sc['category'] == 'Community' ? 'selected' : ''; ?>>Community</option>
                                                            <option value="Professional" <?php echo $sc['category'] == 'Professional' ? 'selected' : ''; ?>>Professional</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Sub-Category Name</label>
                                                        <input type="text" class="form-control" name="sub_category_name" value="<?php echo htmlspecialchars($sc['sub_category_name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Display Order</label>
                                                        <input type="number" class="form-control" name="sub_category_order" value="<?php echo $sc['sub_category_order']; ?>" min="0">
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="is_active" id="active<?php echo $sc['id']; ?>" <?php echo $sc['is_active'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="active<?php echo $sc['id']; ?>">Active</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_sub_category" class="btn btn-primary">Save Changes</button>
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
            </div>
        </div>
    </div>

    <!-- Add Sub-Category Modal -->
    <div class="modal fade" id="addSubCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Sub-Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Teaching">Teaching</option>
                                <option value="Research">Research</option>
                                <option value="Administrative">Administrative</option>
                                <option value="Community">Community</option>
                                <option value="Professional">Professional</option>
                                <option value="custom">+ Add Custom Category</option>
                            </select>
                            <input type="text" class="form-control mt-2" name="custom_category" placeholder="Enter custom category name" style="display:none;" onchange="this.previousElementSibling.value = this.value;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sub-Category Name</label>
                            <input type="text" class="form-control" name="sub_category_name" placeholder="e.g., Lecture Delivery" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" name="sub_category_order" value="0" min="0">
                            <small class="text-muted">Order in which to display sub-categories</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_sub_category" class="btn btn-primary">Add Sub-Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Show/hide custom category input
    document.querySelector('select[name="category"]').addEventListener('change', function() {
        var customInput = this.nextElementSibling;
        if (this.value === 'custom') {
            customInput.style.display = 'block';
            customInput.required = true;
        } else {
            customInput.style.display = 'none';
            customInput.required = false;
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Footer -->
    <footer class="mt-4 py-3" style="background: linear-gradient(180deg, <?php echo $settings['primary_color'] ?? '#308a1e'; ?> 0%, <?php echo $settings['secondary_color'] ?? '#269c16'; ?> 100%); color: white; border-radius: 8px;">
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