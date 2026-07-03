<?php
/**
 * Annual Performance Evaluation Report System
 * WordPress Integration
 *
 * This file provides WordPress shortcode integration for the evaluation system
 * and handles all WordPress-specific functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ==========================================
// Plugin Information
// ==========================================

/**
 * Plugin version
 */
define('APER_VERSION', '1.0.0');

/**
 * Plugin directory path
 */
define('APER_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin URL
 */
define('APER_PLUGIN_URL', plugin_dir_url(__FILE__));

// ==========================================
// Composer Autoload
// ==========================================

/**
 * Load Composer autoloader
 */
function aper_load_autoloader() {
    $autoloadPath = APER_PLUGIN_DIR . 'vendor/autoload.php';

    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
}

add_action('init', 'aper_load_autoloader');

// ==========================================
// Shortcode Registration
// ==========================================

/**
 * Register the annual performance evaluation shortcode
 */
function aper_register_shortcode() {
    add_shortcode('annual_performance_evaluation', 'aper_render_evaluation_form');
    add_shortcode('annual_performance_report', 'aper_render_evaluation_form'); // Alias
}

add_action('init', 'aper_register_shortcode');

/**
 * Render the evaluation form
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function aper_render_evaluation_form($atts = []) {
    // Parse shortcode attributes
    $atts = shortcode_atts([
        'institution' => get_option('aper_institution_name', 'Your Institution'),
        'show_logo' => 'true',
        'require_login' => 'false',
        'department_filter' => '',
    ], $atts, 'annual_performance_evaluation');

    // Check if user is logged in (if required)
    if ($atts['require_login'] === 'true' && !is_user_logged_in()) {
        return aper_get_login_message();
    }

    // Get current user info if logged in
    $currentUser = wp_get_current_user();
    $prefillData = [];

    if ($currentUser->exists()) {
        $prefillData = [
            'staff_name' => $currentUser->display_name ?: $currentUser->user_login,
            'email' => $currentUser->user_email,
        ];
    }

    // Enqueue required scripts and styles
    aper_enqueue_assets();

    // Start output buffering
    ob_start();

    // Include the form template
    aper_get_form_template($atts, $prefillData);

    // Get the buffered content
    $output = ob_get_clean();

    return $output;
}

/**
 * Get login message when authentication is required
 *
 * @return string Login message HTML
 */
function aper_get_login_message() {
    $login_url = wp_login_url(get_permalink());
    $login_link = '<a href="' . esc_url($login_url) . '">log in</a>';

    return sprintf(
        '<div class="aper-auth-required">
            <div class="alert alert-info">
                <i class="fas fa-lock"></i>
                <p>Please %s to access the performance evaluation form.</p>
                <p><a href="%s" class="btn btn-primary">Login Now</a></p>
            </div>
        </div>',
        $login_link,
        esc_url($login_url)
    );
}

/**
 * Enqueue required assets
 */
function aper_enqueue_assets() {
    // Bootstrap CSS
    wp_enqueue_style(
        'aper-bootstrap',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
        [],
        '5.3.0'
    );

    // Font Awesome
    wp_enqueue_style(
        'aper-font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        [],
        '6.4.0'
    );

    // Custom CSS
    wp_enqueue_style(
        'aper-styles',
        APER_PLUGIN_URL . 'style.css',
        ['aper-bootstrap'],
        APER_VERSION
    );

    // Bootstrap JS
    wp_enqueue_script(
        'aper-bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
        ['jquery'],
        '5.3.0',
        true
    );

    // Custom JS
    wp_enqueue_script(
        'aper-scripts',
        APER_PLUGIN_URL . 'script.js',
        ['jquery', 'aper-bootstrap-js'],
        APER_VERSION,
        true
    );

    // Localize script for AJAX
    wp_localize_script('aper-scripts', 'aperAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aper_nonce'),
        'home_url' => get_home_url(),
    ]);
}

/**
 * Get form template
 *
 * @param array $atts Shortcode attributes
 * @param array $prefillData Data to prefill
 */
function aper_get_form_template($atts, $prefillData) {
    ?>
    <div class="aper-container">
        <?php
        // AJAX handler for form submission
        add_action('wp_footer', 'aper_add_ajax_handlers', 99);
        ?>

        <header class="text-center mb-4">
            <div class="institution-header">
                <?php if ($atts['show_logo'] === 'true'): ?>
                <div class="logo-container mb-3">
                    <?php
                    $logoUrl = get_option('aper_logo_url', '');
                    if ($logoUrl):
                        ?>
                        <img id="preview-logo" src="<?php echo esc_url($logoUrl); ?>" alt="Institution Logo" class="img-fluid" style="max-height: 100px;">
                    <?php else: ?>
                        <img id="preview-logo" src="" alt="Institution Logo" class="img-fluid d-none" style="max-height: 100px;">
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <h1 class="institution-name" id="display-institution-name">
                    <?php echo esc_html($atts['institution']); ?>
                </h1>
                <h2 class="report-title">Annual Performance Evaluation Report</h2>
            </div>
        </header>

        <!-- Progress Bar -->
        <div class="progress-container mb-4">
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="progress-bar" style="width: 0%">0%</div>
            </div>
            <div class="progress-steps d-flex justify-content-between mt-2">
                <span class="step-label" data-step="1">Institution</span>
                <span class="step-label" data-step="2">Staff Info</span>
                <span class="step-label" data-step="3">Evaluation</span>
                <span class="step-label" data-step="4">Supervisor</span>
                <span class="step-label" data-step="5">Management</span>
            </div>
        </div>

        <!-- Results Panel -->
        <div class="results-panel mb-4" id="results-panel">
            <h5 class="results-title"><i class="fas fa-calculator me-2"></i>Evaluation Results</h5>
            <div class="results-grid">
                <div class="result-card">
                    <div class="result-icon"><i class="fas fa-star"></i></div>
                    <div class="result-value" id="total-score">0</div>
                    <div class="result-label">Total Score</div>
                </div>
                <div class="result-card">
                    <div class="result-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="result-value" id="average-score">0.00</div>
                    <div class="result-label">Average</div>
                </div>
                <div class="result-card">
                    <div class="result-icon"><i class="fas fa-percentage"></i></div>
                    <div class="result-value" id="percentage">0%</div>
                    <div class="result-label">Percentage</div>
                </div>
                <div class="result-card">
                    <div class="result-icon"><i class="fas fa-award"></i></div>
                    <div class="result-value" id="performance-grade">-</div>
                    <div class="result-label">Grade</div>
                </div>
                <div class="result-card result-status">
                    <div class="result-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="result-value" id="performance-status">Pending</div>
                    <div class="result-label">Status</div>
                </div>
            </div>
        </div>

        <!-- Main Form -->
        <form id="evaluation-form" enctype="multipart/form-data">
            <!-- Security tokens -->
            <input type="hidden" name="csrf_token" id="csrf-token" value="<?php echo esc_attr(wp_create_nonce('aper_form_nonce')); ?>">
            <input type="hidden" name="action" value="aper_submit_evaluation">
            <input type="hidden" name="wordpress_nonce" value="<?php echo esc_attr(wp_create_nonce('aper_form_nonce')); ?>">
            <!-- Honeypot -->
            <input type="text" name="website_url" id="honeypot" style="display:none;" tabindex="-1" autocomplete="off">

            <!-- Section 1: Institution Details -->
            <?php echo aper_render_section_1($atts); ?>

            <!-- Section 2: Staff Information -->
            <?php echo aper_render_section_2($prefillData); ?>

            <!-- Section 3: Performance Evaluation -->
            <?php echo aper_render_section_3(); ?>

            <!-- Section 4: Supervisor Assessment -->
            <?php echo aper_render_section_4(); ?>

            <!-- Section 5: Registrar/Management -->
            <?php echo aper_render_section_5(); ?>

            <!-- Submit Section -->
            <div class="submit-section">
                <div class="row g-3">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-secondary btn-lg w-100" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Form
                        </button>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-primary btn-lg w-100" id="submit-btn">
                            <i class="fas fa-paper-plane me-2"></i>Submit Evaluation
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loading-overlay">
            <div class="loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Processing your evaluation...</p>
            </div>
        </div>

        <!-- Toast Notifications -->
        <div class="toast-container">
            <div class="toast align-items-center text-white bg-success border-0" id="success-toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-check-circle me-2"></i>
                        <span id="success-message">Evaluation submitted successfully!</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" onclick="hideToast('success-toast')"></button>
                </div>
            </div>
            <div class="toast align-items-center text-white bg-danger border-0" id="error-toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span id="error-message">An error occurred. Please try again.</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" onclick="hideToast('error-toast')"></button>
                </div>
            </div>
        </div>

        <footer class="text-center mt-4 py-3">
            <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> Annual Performance Evaluation System. All rights reserved.</p>
        </footer>
    </div>

    <style>
    /* WordPress Compatibility Styles */
    .aper-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }
    .aper-container .form-section {
        margin-bottom: 1.5rem;
    }
    .aper-container .institution-header {
        background: #fff;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    </style>

    <?php
}

// ==========================================
// Section Renderers
// ==========================================

/**
 * Render Section 1: Institution Details
 */
function aper_render_section_1($atts) {
    $institutionName = $atts['institution'];
    $currentYear = date('Y');
    $currentDate = date('Y-m-d');

    ob_start();
    ?>
    <div class="form-section" id="section-1">
        <div class="section-header" onclick="toggleSection(1)">
            <h3><i class="fas fa-university me-2"></i>Institution Details</h3>
            <span class="section-toggle"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="section-content" id="content-1">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Institution Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="institution_name" id="institution-name" value="<?php echo esc_attr($institutionName); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Institution Logo</label>
                    <input type="file" class="form-control" name="institution_logo" id="institution-logo" accept="image/*">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Report Title</label>
                    <input type="text" class="form-control" name="report_title" id="report-title" value="Annual Performance Evaluation Report" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Academic Session <span class="text-danger">*</span></label>
                    <select class="form-select" name="academic_session" id="academic-session" required>
                        <option value="">Select Session</option>
                        <option value="<?php echo ($currentYear) . '/' . ($currentYear + 1); ?>"><?php echo $currentYear . '/' . ($currentYear + 1); ?></option>
                        <option value="<?php echo ($currentYear - 1) . '/' . $currentYear; ?>"><?php echo ($currentYear - 1) . '/' . $currentYear; ?></option>
                        <option value="<?php echo ($currentYear - 2) . '/' . ($currentYear - 1); ?>"><?php echo ($currentYear - 2) . '/' . ($currentYear - 1); ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select class="form-select" name="semester" id="semester" required>
                        <option value="">Select Semester</option>
                        <option value="First">First Semester</option>
                        <option value="Second">Second Semester</option>
                        <option value="Annual">Annual</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Evaluation Year <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="evaluation_year" id="evaluation-year" value="<?php echo esc_attr($currentYear); ?>" min="2000" max="2030" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="evaluation_date" id="evaluation-date" value="<?php echo esc_attr($currentDate); ?>">
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render Section 2: Staff Information
 */
function aper_render_section_2($prefillData) {
    ob_start();
    ?>
    <div class="form-section" id="section-2">
        <div class="section-header" onclick="toggleSection(2)">
            <h3><i class="fas fa-user-tie me-2"></i>Staff Information</h3>
            <span class="section-toggle"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="section-content" id="content-2">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Staff Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="staff_name" id="staff-name" value="<?php echo esc_attr($prefillData['staff_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Staff ID <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="staff_id" id="staff-id" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Department <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="department" id="department" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Faculty/School <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="faculty" id="faculty" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Designation <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="designation" id="designation" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Grade Level <span class="text-danger">*</span></label>
                    <select class="form-select" name="grade_level" id="grade-level" required>
                        <option value="">Select Grade</option>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="Level <?php echo $i; ?>">Level <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Employment Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="employment_status" id="employment-status" required>
                        <option value="">Select Status</option>
                        <option value="Permanent">Permanent</option>
                        <option value="Contract">Contract</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Visiting">Visiting</option>
                        <option value="Intern">Intern</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Years of Service <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="years_of_service" id="years-of-service" min="0" max="50" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" name="email" id="email" value="<?php echo esc_attr($prefillData['email'] ?? ''); ?>" required>
                    <div class="invalid-feedback" id="email-error">Please enter a valid email address</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" name="phone" id="phone" required pattern="[0-9+\-\s]{10,15}">
                    <div class="invalid-feedback">Please enter a valid phone number (10-15 digits)</div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render Section 3: Performance Evaluation
 */
function aper_render_section_3() {
    $categories = [
        'Teaching Performance' => [
            ['name' => 'teaching_1', 'label' => 'Lecture Delivery'],
            ['name' => 'teaching_2', 'label' => 'Class Attendance'],
            ['name' => 'teaching_3', 'label' => 'Student Engagement'],
            ['name' => 'teaching_4', 'label' => 'Course Preparation'],
            ['name' => 'teaching_5', 'label' => 'Course Coverage'],
            ['name' => 'teaching_6', 'label' => 'Time Management'],
        ],
        'Research Performance' => [
            ['name' => 'research_1', 'label' => 'Publications'],
            ['name' => 'research_2', 'label' => 'Conferences'],
            ['name' => 'research_3', 'label' => 'Research Grants'],
            ['name' => 'research_4', 'label' => 'Journal Articles'],
            ['name' => 'research_5', 'label' => 'Innovations'],
        ],
        'Administrative Duties' => [
            ['name' => 'admin_1', 'label' => 'Attendance'],
            ['name' => 'admin_2', 'label' => 'Punctuality'],
            ['name' => 'admin_3', 'label' => 'Leadership'],
            ['name' => 'admin_4', 'label' => 'Teamwork'],
            ['name' => 'admin_5', 'label' => 'Record Keeping'],
        ],
        'Community Service' => [
            ['name' => 'community_1', 'label' => 'Community Development'],
            ['name' => 'community_2', 'label' => 'Committee Participation'],
            ['name' => 'community_3', 'label' => 'Institutional Representation'],
        ],
        'Professional Development' => [
            ['name' => 'professional_1', 'label' => 'Workshops'],
            ['name' => 'professional_2', 'label' => 'Training'],
            ['name' => 'professional_3', 'label' => 'Certifications'],
            ['name' => 'professional_4', 'label' => 'Seminars'],
        ],
    ];

    $ratings = [
        5 => 'Excellent',
        4 => 'Very Good',
        3 => 'Good',
        2 => 'Fair',
        1 => 'Poor',
    ];

    $icons = [
        'Teaching Performance' => 'fa-chalkboard-teacher',
        'Research Performance' => 'fa-flask',
        'Administrative Duties' => 'fa-briefcase',
        'Community Service' => 'fa-users',
        'Professional Development' => 'fa-certificate',
    ];

    ob_start();
    ?>
    <div class="form-section" id="section-3">
        <div class="section-header" onclick="toggleSection(3)">
            <h3><i class="fas fa-clipboard-check me-2"></i>Performance Evaluation</h3>
            <span class="section-toggle"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="section-content" id="content-3">
            <?php foreach ($categories as $categoryName => $questions): ?>
            <div class="evaluation-category">
                <h4 class="category-title"><i class="fas <?php echo esc_attr($icons[$categoryName]); ?> me-2"></i><?php echo esc_html($categoryName); ?></h4>
                <div class="questions-grid">
                    <?php foreach ($questions as $question): ?>
                    <div class="question-item">
                        <label class="question-label"><?php echo esc_html($question['label']); ?></label>
                        <div class="rating-group">
                            <?php foreach ($ratings as $value => $label): ?>
                            <label class="rating-label">
                                <input type="radio" name="<?php echo esc_attr($question['name']); ?>" value="<?php echo esc_attr($value); ?>" onchange="calculateScores()">
                                <?php echo esc_html($label); ?> (<?php echo esc_attr($value); ?>)
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render Section 4: Supervisor Assessment
 */
function aper_render_section_4() {
    $currentDate = date('Y-m-d');

    ob_start();
    ?>
    <div class="form-section" id="section-4">
        <div class="section-header" onclick="toggleSection(4)">
            <h3><i class="fas fa-user-shield me-2"></i>Supervisor Assessment</h3>
            <span class="section-toggle"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="section-content" id="content-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Supervisor Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="supervisor_name" id="supervisor-name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Supervisor Designation <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="supervisor_designation" id="supervisor-designation" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" name="supervisor_remarks" id="supervisor-remarks" rows="3"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Overall Rating <span class="text-danger">*</span></label>
                    <select class="form-select" name="overall_rating" id="overall-rating" required>
                        <option value="">Select Rating</option>
                        <option value="Outstanding">Outstanding</option>
                        <option value="Excellent">Excellent</option>
                        <option value="Very Good">Very Good</option>
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Poor">Poor</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Recommendation <span class="text-danger">*</span></label>
                    <select class="form-select" name="recommendation" id="recommendation" required>
                        <option value="">Select Recommendation</option>
                        <option value="Promoted">Promoted</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Continued">Continued</option>
                        <option value="Probation">Probation</option>
                        <option value="Terminated">Terminated</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="supervisor_date" id="supervisor-date" value="<?php echo esc_attr($currentDate); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Digital Signature (Name or Upload)</label>
                    <input type="text" class="form-control mb-2" name="supervisor_signature_text" id="supervisor-signature-text" placeholder="Type name as signature">
                    <input type="file" class="form-control" name="supervisor_signature_file" id="supervisor-signature-file" accept="image/*">
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render Section 5: Registrar/Management
 */
function aper_render_section_5() {
    $currentDate = date('Y-m-d');

    ob_start();
    ?>
    <div class="form-section" id="section-5">
        <div class="section-header" onclick="toggleSection(5)">
            <h3><i class="fas fa-building me-2"></i>Registrar/Management</h3>
            <span class="section-toggle"><i class="fas fa-chevron-down"></i></span>
        </div>
        <div class="section-content" id="content-5">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Registrar Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="registrar_name" id="registrar-name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Approval Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="approval_status" id="approval-status" required>
                        <option value="">Select Status</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea class="form-control" name="registrar_remarks" id="registrar-remarks" rows="3"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="registrar_date" id="registrar-date" value="<?php echo esc_attr($currentDate); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Digital Signature (Name or Upload)</label>
                    <input type="text" class="form-control mb-2" name="registrar_signature_text" id="registrar-signature-text" placeholder="Type name as signature">
                    <input type="file" class="form-control" name="registrar_signature_file" id="registrar-signature-file" accept="image/*">
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ==========================================
// AJAX Handlers
// ==========================================

/**
 * Add AJAX handlers
 */
function aper_add_ajax_handlers() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Override the API endpoint for WordPress
        if (typeof CONFIG !== 'undefined') {
            CONFIG.apiEndpoint = '<?php echo APER_PLUGIN_URL; ?>generate_excel.php';
        }

        // Form submission handler
        $('#evaluation-form').on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);

            // Add calculated scores
            if (typeof calculateScores === 'function') {
                var scores = calculateScores();
                formData.append('total_score', scores.totalScore);
                formData.append('average_score', scores.averageScore);
                formData.append('percentage', scores.percentage);
                formData.append('performance_grade', scores.grade);
                formData.append('performance_status', scores.status);
            }

            // Show loading
            $('#loading-overlay').addClass('active');

            $.ajax({
                url: '<?php echo APER_PLUGIN_URL; ?>generate_excel.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#loading-overlay').removeClass('active');

                    try {
                        var result = typeof response === 'string' ? JSON.parse(response) : response;

                        if (result.success) {
                            $('#success-message').text(result.message || 'Evaluation submitted successfully!');
                            $('#success-toast').addClass('show');
                            $('#evaluation-form')[0].reset();
                        } else {
                            $('#error-message').text(result.message || 'An error occurred');
                            $('#error-toast').addClass('show');
                        }
                    } catch (e) {
                        $('#error-message').text('An error occurred while processing your submission');
                        $('#error-toast').addClass('show');
                    }
                },
                error: function(xhr, status, error) {
                    $('#loading-overlay').removeClass('active');
                    $('#error-message').text('An error occurred: ' + error);
                    $('#error-toast').addClass('show');
                }
            });
        });
    });
    </script>
    <?php
}

// ==========================================
// Plugin Settings
// ==========================================

/**
 * Register plugin settings
 */
function aper_register_settings() {
    register_setting('aper_settings', 'aper_institution_name');
    register_setting('aper_settings', 'aper_logo_url');
    register_setting('aper_settings', 'aper_email_to');
    register_setting('aper_settings', 'aper_email_from');
}

add_action('admin_init', 'aper_register_settings');

/**
 * Add plugin settings menu
 */
function aper_add_admin_menu() {
    add_options_page(
        'Performance Evaluation',
        'Performance Evaluation',
        'manage_options',
        'aper-settings',
        'aper_render_settings_page'
    );
}

add_action('admin_menu', 'aper_add_admin_menu');

/**
 * Render plugin settings page
 */
function aper_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    ?>
    <div class="wrap">
        <h1>Annual Performance Evaluation Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('aper_settings'); ?>
            <?php do_settings_sections('aper_settings'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Institution Name</th>
                    <td><input type="text" name="aper_institution_name" value="<?php echo esc_attr(get_option('aper_institution_name', 'Your Institution')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Logo URL</th>
                    <td><input type="text" name="aper_logo_url" value="<?php echo esc_attr(get_option('aper_logo_url', '')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Email To Address</th>
                    <td><input type="email" name="aper_email_to" value="<?php echo esc_attr(get_option('aper_email_to', 'evaluation@yourinstitution.edu.ng')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Email From Address</th>
                    <td><input type="email" name="aper_email_from" value="<?php echo esc_attr(get_option('aper_email_from', 'noreply@yourinstitution.edu.ng')); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// ==========================================
// Plugin Activation/Deactivation
// ==========================================

/**
 * Plugin activation
 */
function aper_activate() {
    // Create uploads directory
    $uploadDir = APER_PLUGIN_DIR . 'uploads';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'aper_activate');

/**
 * Plugin deactivation
 */
function aper_deactivate() {
    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'aper_deactivate');

// ==========================================
// Gutenberg Block (Optional)
// ==========================================

/**
 * Register Gutenberg block
 */
function aper_register_block() {
    wp_register_script(
        'aper-block',
        APER_PLUGIN_URL . 'block.js',
        ['wp-blocks', 'wp-element', 'wp-editor'],
        APER_VERSION
    );

    register_block_type('aper/evaluation-form', [
        'editor_script' => 'aper-block',
    ]);
}

// Add_action('init', 'aper_register_block');