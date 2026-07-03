/**
 * Annual Performance Evaluation Report System
 * JavaScript - Calculations, Validation, and AJAX
 */

// ==========================================
// Configuration
// ==========================================
const CONFIG = {
    totalQuestions: 23, // Total number of evaluation questions
    maxScorePerQuestion: 5,
    emailRegex: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    phoneRegex: /^[\d+\-\s]{10,15}$/,
    apiEndpoint: 'generate_excel.php',
    submitAttempts: new Set()
};

// ==========================================
// Initialization
// ==========================================
document.addEventListener('DOMContentLoaded', function() {
    initializeForm();
    setDefaultDate();
    generateCSRFToken();
    setupEventListeners();
});

function initializeForm() {
    // Set default values
    document.getElementById('evaluation-year').value = new Date().getFullYear();
    document.getElementById('evaluation-date').value = new Date().toISOString().split('T')[0];

    // Expand first section
    expandSection(1);

    // Initialize results panel
    updateResultsDisplay(0, 0, 0, '-', 'Pending');
}

function setDefaultDate() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    const today = new Date().toISOString().split('T')[0];
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = today;
        }
    });
}

function generateCSRFToken() {
    const token = generateRandomToken(32);
    document.getElementById('csrf-token').value = token;
    return token;
}

function generateRandomToken(length) {
    const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let token = '';
    for (let i = 0; i < length; i++) {
        token += characters.charAt(Math.floor(Math.random() * characters.length));
    }
    return token;
}

// ==========================================
// Section Toggle
// ==========================================
function toggleSection(sectionNumber) {
    const section = document.getElementById(`section-${sectionNumber}`);
    const content = document.getElementById(`content-${sectionNumber}`);
    const icon = section.querySelector('.section-toggle i');

    if (section.classList.contains('collapsed')) {
        expandSection(sectionNumber);
    } else {
        collapseSection(sectionNumber);
    }
}

function expandSection(sectionNumber) {
    const section = document.getElementById(`section-${sectionNumber}`);
    const content = document.getElementById(`content-${sectionNumber}`);
    const icon = section.querySelector('.section-toggle i');

    section.classList.remove('collapsed');
    icon.classList.remove('fa-chevron-right');
    icon.classList.add('fa-chevron-down');

    // Smooth scroll to section
    setTimeout(() => {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
}

function collapseSection(sectionNumber) {
    const section = document.getElementById(`section-${sectionNumber}`);
    const content = document.getElementById(`content-${sectionNumber}`);
    const icon = section.querySelector('.section-toggle i');

    section.classList.add('collapsed');
    icon.classList.remove('fa-chevron-down');
    icon.classList.add('fa-chevron-right');
}

// ==========================================
// Score Calculation
// ==========================================
function calculateScores() {
    let totalScore = 0;
    let answeredQuestions = 0;

    // Get all radio buttons for evaluation questions
    const radioButtons = document.querySelectorAll('#section-3 input[type="radio"]');

    // Group by question name
    const questionGroups = {};
    radioButtons.forEach(radio => {
        if (!questionGroups[radio.name]) {
            questionGroups[radio.name] = [];
        }
        questionGroups[radio.name].push(radio);
    });

    // Calculate score for each question
    Object.keys(questionGroups).forEach(questionName => {
        const checked = questionGroups[questionName].find(r => r.checked);
        if (checked) {
            totalScore += parseInt(checked.value);
            answeredQuestions++;
        }
    });

    // Calculate average and percentage
    const maxPossibleScore = CONFIG.totalQuestions * CONFIG.maxScorePerQuestion;
    const averageScore = answeredQuestions > 0 ? (totalScore / answeredQuestions).toFixed(2) : 0;
    const percentage = maxPossibleScore > 0 ? ((totalScore / maxPossibleScore) * 100).toFixed(1) : 0;

    // Determine grade and status
    const { grade, status } = calculateGradeAndStatus(percentage);

    // Update display
    updateResultsDisplay(totalScore, averageScore, percentage, grade, status);

    // Update progress
    updateProgress(answeredQuestions);

    // Mark section as complete if all questions answered
    if (answeredQuestions === CONFIG.totalQuestions) {
        markSectionComplete(3);
    } else {
        markSectionIncomplete(3);
    }

    return { totalScore, averageScore, percentage, grade, status };
}

function calculateGradeAndStatus(percentage) {
    let grade, status;
    const pct = parseFloat(percentage);

    if (pct >= 90) {
        grade = 'Outstanding';
        status = 'Excellent Performance';
    } else if (pct >= 80) {
        grade = 'Excellent';
        status = 'Very Good Performance';
    } else if (pct >= 70) {
        grade = 'Very Good';
        status = 'Good Performance';
    } else if (pct >= 60) {
        grade = 'Good';
        status = 'Satisfactory';
    } else if (pct >= 50) {
        grade = 'Fair';
        status = 'Needs Improvement';
    } else {
        grade = 'Poor';
        status = 'Unsatisfactory';
    }

    return { grade, status };
}

function updateResultsDisplay(totalScore, averageScore, percentage, grade, status) {
    // Animate value changes
    animateValue('total-score', totalScore, 500);
    animateValue('average-score', averageScore, 500, true);
    animateValue('percentage', percentage + '%', 500, true);

    // Update grade and status
    const gradeEl = document.getElementById('performance-grade');
    const statusEl = document.getElementById('performance-status');

    gradeEl.textContent = grade;
    statusEl.textContent = status;

    // Color coding based on grade
    const colors = {
        'Outstanding': '#10b981',
        'Excellent': '#3b82f6',
        'Very Good': '#06b6d4',
        'Good': '#f59e0b',
        'Fair': '#f97316',
        'Poor': '#ef4444'
    };

    gradeEl.style.color = colors[grade] || '#64748b';
    statusEl.style.color = colors[grade] || '#64748b';
}

function animateValue(elementId, endValue, duration, isString = false) {
    const element = document.getElementById(elementId);
    const startValue = 0;
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        if (!isString) {
            const value = Math.floor(progress * (endValue - startValue) + startValue);
            element.textContent = value;
        } else {
            element.textContent = endValue;
        }

        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }

    requestAnimationFrame(update);
}

function updateProgress(answeredQuestions) {
    const percentage = Math.round((answeredQuestions / CONFIG.totalQuestions) * 100);
    const progressBar = document.getElementById('progress-bar');

    progressBar.style.width = percentage + '%';
    progressBar.textContent = percentage + '%';

    // Update step labels
    document.querySelectorAll('.step-label').forEach((label, index) => {
        const step = index + 1;
        if (step <= 5 && answeredQuestions >= (step - 1) * (CONFIG.totalQuestions / 5)) {
            label.classList.add('active');
        }
    });
}

function markSectionComplete(sectionNumber) {
    const header = document.getElementById(`section-${sectionNumber}`).querySelector('.section-header');
    header.classList.add('completed');
}

function markSectionIncomplete(sectionNumber) {
    const header = document.getElementById(`section-${sectionNumber}`).querySelector('.section-header');
    header.classList.remove('completed');
}

// ==========================================
// Form Validation
// ==========================================
function validateForm() {
    let isValid = true;
    const errors = [];

    // Clear previous error states
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.querySelectorAll('.invalid-feedback').forEach(el => el.style.display = 'none');

    // Validate required fields
    const requiredFields = document.querySelectorAll('#evaluation-form [required]');
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
            errors.push(`${field.name} is required`);
        }
    });

    // Validate email
    const emailField = document.getElementById('email');
    if (emailField.value && !CONFIG.emailRegex.test(emailField.value)) {
        emailField.classList.add('is-invalid');
        document.getElementById('email-error').style.display = 'block';
        isValid = false;
        errors.push('Invalid email format');
    }

    // Validate phone
    const phoneField = document.getElementById('phone');
    if (phoneField.value && !CONFIG.phoneRegex.test(phoneField.value.replace(/\s/g, ''))) {
        phoneField.classList.add('is-invalid');
        isValid = false;
        errors.push('Invalid phone number format');
    }

    // Validate evaluation questions are answered
    const scores = calculateScores();
    if (scores.totalScore === 0) {
        isValid = false;
        errors.push('Please rate at least one evaluation question');
    }

    // Validate year range
    const yearField = document.getElementById('evaluation-year');
    const currentYear = new Date().getFullYear();
    if (yearField.value < 2000 || yearField.value > currentYear + 5) {
        yearField.classList.add('is-invalid');
        isValid = false;
        errors.push('Invalid evaluation year');
    }

    return { isValid, errors };
}

// ==========================================
// Event Listeners
// ==========================================
function setupEventListeners() {
    // Form submission
    document.getElementById('evaluation-form').addEventListener('submit', handleFormSubmit);

    // Logo preview
    document.getElementById('institution-logo').addEventListener('change', handleLogoPreview);

    // Institution name display
    document.getElementById('institution-name').addEventListener('input', function() {
        document.getElementById('display-institution-name').textContent = this.value || 'Institution Name';
    });

    // Input validation on blur
    document.querySelectorAll('#evaluation-form input, #evaluation-form select, #evaluation-form textarea').forEach(field => {
        field.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                // Don't show error on blur, wait for submit
            } else if (this.type === 'email' && this.value && !CONFIG.emailRegex.test(this.value)) {
                this.classList.add('is-invalid');
            } else if (this.type === 'tel' && this.value && !CONFIG.phoneRegex.test(this.value.replace(/\s/g, ''))) {
                this.classList.add('is-invalid');
            }
        });
    });

    // Real-time validation
    document.querySelectorAll('#evaluation-form input, #evaluation-form select').forEach(field => {
        field.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
}

function handleLogoPreview(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('preview-logo');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
        };
        reader.readAsDataURL(file);
    } else {
        preview.classList.add('d-none');
    }
}

// ==========================================
// Form Submission
// ==========================================
async function handleFormSubmit(event) {
    event.preventDefault();

    // Check for spam (honeypot)
    const honeypot = document.getElementById('honeypot').value;
    if (honeypot) {
        console.log('Spam detected');
        return;
    }

    // Validate form
    const validation = validateForm();
    if (!validation.isValid) {
        showError('Please fill all required fields correctly.');
        scrollToFirstError();
        return;
    }

    // Show loading overlay
    const loadingOverlay = document.getElementById('loading-overlay');
    loadingOverlay.classList.add('active');

    try {
        // Prepare form data
        const formData = new FormData(event.target);

        // Add calculated scores
        const scores = calculateScores();
        formData.append('total_score', scores.totalScore);
        formData.append('average_score', scores.averageScore);
        formData.append('percentage', scores.percentage);
        formData.append('performance_grade', scores.grade);
        formData.append('performance_status', scores.status);

        // Send to server
        const response = await fetch(CONFIG.apiEndpoint, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Server error: ' + response.status);
        }

        const result = await response.json();

        if (result.success) {
            showSuccess('Evaluation submitted successfully! Excel file has been generated and sent to the administration.');

            // Reset form
            document.getElementById('evaluation-form').reset();
            generateCSRFToken();
            calculateScores();

            // Mark as submitted
            CONFIG.submitAttempts.add(Date.now());
        } else {
            throw new Error(result.message || 'Submission failed');
        }
    } catch (error) {
        console.error('Submission error:', error);
        showError('An error occurred while submitting. Please try again. ' + error.message);
    } finally {
        loadingOverlay.classList.remove('active');
    }
}

function scrollToFirstError() {
    const firstError = document.querySelector('.is-invalid');
    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstError.focus();
    }
}

// ==========================================
// Toast Notifications
// ==========================================
function showSuccess(message) {
    const toast = document.getElementById('success-toast');
    document.getElementById('success-message').textContent = message;

    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    // Auto-hide after 10 seconds
    setTimeout(() => {
        hideToast('success-toast');
    }, 10000);
}

function showError(message) {
    const toast = document.getElementById('error-toast');
    document.getElementById('error-message').textContent = message;

    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    // Auto-hide after 10 seconds
    setTimeout(() => {
        hideToast('error-toast');
    }, 10000);
}

function hideToast(toastId) {
    const toast = document.getElementById(toastId);
    toast.classList.remove('show');
}

// ==========================================
// Utility Functions
// ==========================================
function sanitizeInput(input) {
    if (typeof input !== 'string') return input;
    return input
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/javascript:/gi, '')
        .replace(/on\w+=/gi, '');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// ==========================================
// Navigation Functions
// ==========================================
function goToSection(sectionNumber) {
    expandSection(sectionNumber);
}

function validateSection(sectionNumber) {
    let isValid = true;
    const section = document.getElementById(`section-${sectionNumber}`);
    const requiredFields = section.querySelectorAll('[required]');

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        }
    });

    if (isValid) {
        markSectionComplete(sectionNumber);
        const nextSection = sectionNumber + 1;
        if (nextSection <= 5) {
            goToSection(nextSection);
        }
    }

    return isValid;
}

// ==========================================
// Export Functions (for external use)
// ==========================================
window.apeSystem = {
    calculateScores,
    validateForm,
    toggleSection,
    expandSection,
    collapseSection,
    showSuccess,
    showError
};