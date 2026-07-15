<?php
/**
 * Annual Performance Evaluation Report System
 * PHPMailer Email Handler
 *
 * Handles email notifications for evaluation submissions
 */

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPSmtpTransport\Exception as PHPSmtpException;

// ==========================================
// Configuration
// ==========================================

/**
 * Get email configuration from constants in config.php
 */
function getEmailConfig() {
    return [
        'host' => defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com',
        'username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
        'password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
        'port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
        'from_address' => defined('EMAIL_FROM') ? EMAIL_FROM : 'noreply@yourinstitution.edu.ng',
        'from_name' => 'Ekiti State College of Technology, APER Evaluation Form 2026',
        'to_address' => defined('EMAIL_TO') ? EMAIL_TO : 'evaluation@yourinstitution.edu.ng',
        'encryption' => PHPMailer::ENCRYPTION_STARTTLS,
    ];
}

/**
 * Sanitize input to prevent XSS
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Log email activity
 */
function logEmailActivity($message, $context = []) {
    $logFile = __DIR__ . '/email.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}";

    if (!empty($context)) {
        $logEntry .= ' - Context: ' . json_encode($context);
    }

    $logEntry .= PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// ==========================================
// Email Sending Functions
// ==========================================

/**
 * Send evaluation notification email
 *
 * @param array $formData The form data array
 * @param string $attachmentPath Path to the Excel file attachment
 * @param array $scores The calculated scores
 * @return array Result with success status and message
 */
function sendEvaluationEmail($formData, $attachmentPath, $scores = []) {
    $config = getEmailConfig();

    // Validate configuration
    if (empty($config['username']) || empty($config['password'])) {
        logEmailActivity('SMTP credentials not configured', $config);
        return [
            'success' => false,
            'message' => 'Email configuration is incomplete. Please contact administrator.'
        ];
    }

    $mail = new PHPMailer(true);

    try {
        // Enable verbose debug output in development
        $mail->SMTPDebug = getenv('APP_ENV') === 'development'
            ? PHPMailer::DEBUG_SERVER
            : PHPMailer::DEBUG_OFF;

        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = $config['port'];

        // Timeout settings
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = false;

        // Disable auto-TLS for older servers
        $mail->SMTPAutoTLS = true;

        // Recipients
        $mail->setFrom(
            $config['from_address'],
            $config['from_name']
        );

        // Add primary recipient
        $mail->addAddress($config['to_address']);

        // Add reply-to (staff email)
        if (!empty($formData['email'])) {
            $replyToEmail = sanitizeInput($formData['email']);
            $replyToName = sanitizeInput($formData['staff_name'] ?? 'Staff Member');
            $mail->addReplyTo($replyToEmail, $replyToName);
        }

        // Add CC if configured
        $ccRecipients = getenv('EMAIL_CC_RECIPIENTS');
        if (!empty($ccRecipients)) {
            $ccArray = explode(',', $ccRecipients);
            foreach ($ccArray as $cc) {
                $cc = trim($cc);
                if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($cc);
                }
            }
        }

        // Add BCC if configured
        $bccRecipients = getenv('EMAIL_BCC_RECIPIENTS');
        if (!empty($bccRecipients)) {
            $bccArray = explode(',', $bccRecipients);
            foreach ($bccArray as $bcc) {
                $bcc = trim($bcc);
                if (filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($bcc);
                }
            }
        }

        // Attachments
        if (!empty($attachmentPath) && file_exists($attachmentPath)) {
            $fileName = basename($attachmentPath);
            $mail->addAttachment($attachmentPath, $fileName);
            logEmailActivity('Attachment added', ['file' => $fileName]);
        } else {
            logEmailActivity('Warning: Attachment file not found', ['path' => $attachmentPath]);
        }

        // Email content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Annual Performance Evaluation Report Submission';

        // Build email body
        $mail->Body = buildEmailBody($formData, $scores);
        $mail->AltBody = buildAltEmailBody($formData, $scores);

        // Send email
        $mail->send();

        logEmailActivity('Email sent successfully', [
            'staff_id' => $formData['staff_id'] ?? 'N/A',
            'staff_name' => $formData['staff_name'] ?? 'N/A',
            'to' => $config['to_address']
        ]);

        return [
            'success' => true,
            'message' => 'Email notification sent successfully'
        ];

    } catch (PHPSmtpException $e) {
        logEmailActivity('SMTP Error', [
            'error' => $e->getMessage(),
            'staff_id' => $formData['staff_id'] ?? 'N/A'
        ]);

        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        logEmailActivity('Email Error', [
            'error' => $e->getMessage(),
            'staff_id' => $formData['staff_id'] ?? 'N/A'
        ]);

        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $e->getMessage()
        ];
    }
}

/**
 * Build HTML email body
 */
function buildEmailBody($formData, $scores) {
    $staffName = sanitizeInput($formData['staff_name'] ?? 'N/A');
    $staffId = sanitizeInput($formData['staff_id'] ?? 'N/A');
    $department = sanitizeInput($formData['department'] ?? 'N/A');
    $faculty = sanitizeInput($formData['faculty'] ?? 'N/A');
    $session = sanitizeInput($formData['academic_session'] ?? 'N/A');
    $semester = sanitizeInput($formData['semester'] ?? 'N/A');
    $year = sanitizeInput($formData['evaluation_year'] ?? 'N/A');
    $grade = sanitizeInput($scores['performance_grade'] ?? '-');
    $status = sanitizeInput($scores['performance_status'] ?? 'Pending');
    $totalScore = intval($scores['total_score'] ?? 0);
    $percentage = floatval($scores['percentage'] ?? 0);

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>New Performance Evaluation Submitted</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 650px;
                margin: 0 auto;
                background: #ffffff;
            }
            .header {
                background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .header p {
                margin: 10px 0 0;
                opacity: 0.9;
                font-size: 14px;
            }
            .content {
                padding: 30px 25px;
                background: #f8fafc;
            }
            .info-box {
                background: white;
                border-radius: 8px;
                padding: 20px;
                margin: 15px 0;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .info-title {
                color: #1E3A8A;
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #3B82F6;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            table tr {
                border-bottom: 1px solid #e2e8f0;
            }
            table tr:last-child {
                border-bottom: none;
            }
            table td {
                padding: 12px 8px;
                vertical-align: middle;
            }
            table td:first-child {
                font-weight: 600;
                color: #334155;
                width: 40%;
            }
            table td:last-child {
                color: #1E3A8A;
            }
            .score-box {
                display: flex;
                justify-content: space-around;
                margin: 20px 0;
            }
            .score-item {
                text-align: center;
                padding: 15px;
                background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%);
                border-radius: 8px;
                min-width: 100px;
            }
            .score-value {
                font-size: 24px;
                font-weight: 700;
                color: #1E3A8A;
            }
            .score-label {
                font-size: 12px;
                color: #64748B;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .status-badge {
                display: inline-block;
                padding: 6px 16px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: 600;
            }
            .status-outstanding { background: #10B981; color: white; }
            .status-excellent { background: #3B82F6; color: white; }
            .status-very-good { background: #06B6D4; color: white; }
            .status-good { background: #F59E0B; color: white; }
            .status-fair { background: #F97316; color: white; }
            .status-poor { background: #EF4444; color: white; }
            .footer {
                background: #1E3A8A;
                color: white;
                padding: 20px;
                text-align: center;
            }
            .footer p {
                margin: 5px 0;
                font-size: 12px;
                opacity: 0.8;
            }
            .footer a {
                color: #BFDBFE;
                text-decoration: none;
            }
            @media (max-width: 600px) {
                .container {
                    width: 100%;
                }
                .score-box {
                    flex-direction: column;
                    gap: 10px;
                }
                .score-item {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1><i class='fas fa-clipboard-check'></i> New Performance Evaluation Submitted</h1>
                <p>Annual Performance Evaluation Report System</p>
            </div>

            <div class='content'>
                <div class='info-box'>
                    <div class='info-title'>Staff Information</div>
                    <table>
                        <tr>
                            <td>Staff Name:</td>
                            <td>{$staffName}</td>
                        </tr>
                        <tr>
                            <td>Staff ID:</td>
                            <td>{$staffId}</td>
                        </tr>
                        <tr>
                            <td>Department:</td>
                            <td>{$department}</td>
                        </tr>
                        <tr>
                            <td>Faculty/School:</td>
                            <td>{$faculty}</td>
                        </tr>
                        <tr>
                            <td>Designation:</td>
                            <td>" . sanitizeInput($formData['designation'] ?? 'N/A') . "</td>
                        </tr>
                    </table>
                </div>

                <div class='info-box'>
                    <div class='info-title'>Evaluation Details</div>
                    <table>
                        <tr>
                            <td>Academic Session:</td>
                            <td>{$session}</td>
                        </tr>
                        <tr>
                            <td>Semester:</td>
                            <td>{$semester}</td>
                        </tr>
                        <tr>
                            <td>Evaluation Year:</td>
                            <td>{$year}</td>
                        </tr>
                        <tr>
                            <td>Date Submitted:</td>
                            <td>" . date('F j, Y, g:i A') . "</td>
                        </tr>
                    </table>
                </div>

                <div class='info-box'>
                    <div class='info-title'>Performance Summary</div>
                    <div class='score-box'>
                        <div class='score-item'>
                            <div class='score-value'>{$totalScore}</div>
                            <div class='score-label'>Total Score</div>
                        </div>
                        <div class='score-item'>
                            <div class='score-value'>{$percentage}%</div>
                            <div class='score-label'>Percentage</div>
                        </div>
                        <div class='score-item'>
                            <div class='score-value'>{$grade}</div>
                            <div class='score-label'>Grade</div>
                        </div>
                    </div>
                    <div style='text-align: center; margin-top: 15px;'>
                        <span class='status-badge " . getStatusClass($grade) . "'>{$status}</span>
                    </div>
                </div>

                <p style='margin-top: 20px; color: #64748B; font-size: 14px;'>
                    <i class='fas fa-file-excel'></i> Please find the attached Excel report containing the complete evaluation details.
                </p>
            </div>

            <div class='footer'>
                <p>This is an automated message from the Annual Performance Evaluation System.</p>
                <p>Please do not reply directly to this email.</p>
                <p>" . date('Y') . " &copy; " . sanitizeInput($formData['institution_name'] ?? 'Your Institution') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Build plain text email body
 */
function buildAltEmailBody($formData, $scores) {
    $staffName = sanitizeInput($formData['staff_name'] ?? 'N/A');
    $staffId = sanitizeInput($formData['staff_id'] ?? 'N/A');
    $department = sanitizeInput($formData['department'] ?? 'N/A');
    $faculty = sanitizeInput($formData['faculty'] ?? 'N/A');
    $session = sanitizeInput($formData['academic_session'] ?? 'N/A');
    $semester = sanitizeInput($formData['semester'] ?? 'N/A');
    $year = sanitizeInput($formData['evaluation_year'] ?? 'N/A');
    $grade = sanitizeInput($scores['performance_grade'] ?? '-');
    $status = sanitizeInput($scores['performance_status'] ?? 'Pending');
    $totalScore = intval($scores['total_score'] ?? 0);
    $percentage = floatval($scores['percentage'] ?? 0);

    $text = "NEW PERFORMANCE EVALUATION SUBMITTED\n";
    $text .= "=====================================\n\n";

    $text .= "STAFF INFORMATION\n";
    $text .= "-----------------\n";
    $text .= "Staff Name: {$staffName}\n";
    $text .= "Staff ID: {$staffId}\n";
    $text .= "Department: {$department}\n";
    $text .= "Faculty/School: {$faculty}\n";
    $text .= "Designation: " . sanitizeInput($formData['designation'] ?? 'N/A') . "\n\n";

    $text .= "EVALUATION DETAILS\n";
    $text .= "------------------\n";
    $text .= "Academic Session: {$session}\n";
    $text .= "Semester: {$semester}\n";
    $text .= "Evaluation Year: {$year}\n";
    $text .= "Date Submitted: " . date('F j, Y, g:i A') . "\n\n";

    $text .= "PERFORMANCE SUMMARY\n";
    $text .= "-------------------\n";
    $text .= "Total Score: {$totalScore}\n";
    $text .= "Percentage: {$percentage}%\n";
    $text .= "Grade: {$grade}\n";
    $text .= "Status: {$status}\n\n";

    $text .= "Please find the attached Excel report containing the complete evaluation details.\n\n";

    $text .= "This is an automated message from the Annual Performance Evaluation System.\n";
    $text .= "Please do not reply directly to this email.\n";

    return $text;
}

/**
 * Get CSS class for status badge
 */
function getStatusClass($grade) {
    $classes = [
        'Outstanding' => 'status-outstanding',
        'Excellent' => 'status-excellent',
        'Very Good' => 'status-very-good',
        'Good' => 'status-good',
        'Fair' => 'status-fair',
        'Poor' => 'status-poor',
    ];
    return $classes[$grade] ?? 'status-good';
}

// ==========================================
// Test Email Function
// ==========================================

/**
 * Test email configuration
 */
function testEmailConfiguration() {
    $config = getEmailConfig();

    if (empty($config['username']) || empty($config['password'])) {
        return [
            'success' => false,
            'message' => 'SMTP credentials not configured'
        ];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = $config['port'];

        $mail->setFrom($config['from_address'], $config['from_name']);
        $mail->addAddress($config['username']); // Send test to own address
        $mail->Subject = 'Test Email - Performance Evaluation System';
        $mail->Body = 'This is a test email to verify the email configuration is working correctly.';
        $mail->AltBody = 'This is a test email to verify the email configuration is working correctly.';

        $mail->send();

        return [
            'success' => true,
            'message' => 'Test email sent successfully'
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Test email failed: ' . $e->getMessage()
        ];
    }
}

// ==========================================
// Handle Direct Requests
// ==========================================

// ==========================================
// Evaluation Stage Notification Functions
// ==========================================

/**
 * Send evaluation stage change notification
 *
 * @param string $stage The new evaluation stage
 * @param array $evaluation The evaluation data
 * @param array $staff The staff data
 * @param array $supervisor The supervisor data (optional)
 * @return array Result with success status and message
 */
function sendEvaluationStageNotification($stage, $evaluation, $staff, $supervisor = null) {
    // Check if email notifications are enabled
    if (!defined('ENABLE_EMAIL_NOTIFICATIONS') || !ENABLE_EMAIL_NOTIFICATIONS) {
        return ['success' => false, 'message' => 'Email notifications disabled'];
    }

    $config = getEmailConfig();

    // Validate configuration
    if (empty($config['username']) || empty($config['password'])) {
        logEmailActivity('SMTP credentials not configured for stage notification', $config);
        return ['success' => false, 'message' => 'Email not configured'];
    }

    // Determine email content based on stage
    $emailData = getStageEmailContent($stage, $evaluation, $staff, $supervisor);

    if (!$emailData) {
        return ['success' => false, 'message' => 'Invalid stage'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['encryption'];
        $mail->Port = $config['port'];

        $mail->setFrom($config['from_address'], $config['from_name']);

        // Add recipient(s)
        if (!empty($emailData['to_emails'])) {
            foreach ($emailData['to_emails'] as $email) {
                if (!empty($email)) {
                    $mail->addAddress($email, $emailData['to_name'] ?? '');
                }
            }
        }

        // Add CC if present
        if (!empty($emailData['cc_emails'])) {
            foreach ($emailData['cc_emails'] as $email) {
                if (!empty($email)) {
                    $mail->addCC($email);
                }
            }
        }

        $mail->Subject = $emailData['subject'];
        $mail->Body = $emailData['body'];
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $emailData['body']));

        $mail->send();

        logEmailActivity("Stage notification sent: $stage", [
            'to' => $emailData['to_emails'],
            'stage' => $stage,
            'evaluation_id' => $evaluation['id'] ?? ''
        ]);

        return ['success' => true, 'message' => 'Notification sent'];

    } catch (Exception $e) {
        logEmailActivity("Stage notification failed: " . $e->getMessage(), [
            'stage' => $stage,
            'evaluation_id' => $evaluation['id'] ?? ''
        ]);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get email content based on evaluation stage
 */
function getStageEmailContent($stage, $evaluation, $staff, $supervisor = null) {
    $instName = 'Ekiti State College of Technology, APER Evaluation Form 2026';
    $year = $evaluation['evaluation_year'] ?? date('Y');
    $staffName = trim(($staff['first_name'] ?? '') . ' ' . ($staff['surname'] ?? ''));
    $staffEmail = $staff['email'] ?? '';
    $department = $staff['department'] ?? '';

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');

    switch ($stage) {
        case 'submitted':
            // Staff submitted their evaluation - notify staff of confirmation
            return [
                'to_emails' => !empty($staffEmail) ? [$staffEmail] : [],
                'to_name' => $staffName,
                'subject' => "Evaluation Submitted Successfully - $year",
                'body' => "<html>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #2c7a1c;'>Evaluation Submitted Successfully!</h2>
        <p>Dear $staffName,</p>
        <p>Your performance evaluation has been submitted successfully for the year <strong>$year</strong>.</p>
        <p><strong>What happens next?</strong></p>
        <ol>
            <li><strong>Your Supervising Officer</strong> will review and grade your evaluation.</li>
            <li>Once completed, you will be notified to review and consent to the evaluation.</li>
            <li>Finally, the <strong>Registrar</strong> will give final approval.</li>
        </ol>
        <p>Please check your portal regularly for updates on your evaluation status.</p>
        <p><a href='$baseUrl/staff-dashboard.php' style='background: #2c7a1c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Your Dashboard</a></p>
        <p style='margin-top: 30px;'>Best regards,<br>$instName</p>
    </div>
</body>
</html>"
            ];

        case 'pending':
            // Staff submitted - notify supervisor
            return [
                'to_emails' => !empty($supervisor['email']) ? [$supervisor['email']] : [],
                'to_name' => $supervisor ? trim($supervisor['first_name'] . ' ' . $supervisor['surname']) : 'Supervising Officer',
                'subject' => "New Evaluation Pending Review - $staffName ($year)",
                'body' => "<html>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #2c7a1c;'>New Evaluation Ready for Review</h2>
        <p>Dear Supervising Officer,</p>
        <p>A new performance evaluation has been submitted by <strong>$staffName</strong> from the <strong>$department</strong> department and is awaiting your review.</p>
        <p><strong>Evaluation Details:</strong></p>
        <ul>
            <li><strong>Staff:</strong> $staffName</li>
            <li><strong>Department:</strong> $department</li>
            <li><strong>Year:</strong> $year</li>
        </ul>
        <p>Please log in to the evaluation system to review and complete this evaluation.</p>
        <p><a href='$baseUrl/evaluate-supervisor.php' style='background: #2c7a1c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Evaluation System</a></p>
        <p style='margin-top: 30px;'>Best regards,<br>$instName</p>
    </div>
</body>
</html>"
            ];

        case 'staff_review':
            // Supervising Officer passed to staff - notify staff
            $soName = $supervisor ? trim($supervisor['first_name'] . ' ' . $supervisor['surname']) : 'Your Supervising Officer';
            return [
                'to_emails' => !empty($staffEmail) ? [$staffEmail] : [],
                'to_name' => $staffName,
                'subject' => "Your Evaluation is Ready for Review - $year",
                'body' => "<html>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #2c7a1c;'>Your Evaluation is Ready for Review</h2>
        <p>Dear $staffName,</p>
        <p>Your Supervising Officer ($soName) has completed your performance evaluation and it is now ready for your review.</p>
        <p><strong>Evaluation Details:</strong></p>
        <ul>
            <li><strong>Year:</strong> $year</li>
            <li><strong>Department:</strong> $department</li>
        </ul>
        <p>Please log in to the evaluation system to review your evaluation, see the scores and remarks, and provide your consent.</p>
        <p><a href='$baseUrl/staff-dashboard.php' style='background: #2c7a1c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Your Evaluation</a></p>
        <p style='margin-top: 30px;'>Best regards,<br>$instName</p>
    </div>
</body>
</html>"
            ];

        case 'registrar':
            // Staff consented - notify registrar
            return [
                'to_emails' => !empty($config['to_address']) ? [$config['to_address']] : [],
                'to_name' => 'Registrar',
                'subject' => "Evaluation Awaiting Final Approval - $staffName ($year)",
                'body' => "<html>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #2c7a1c;'>Evaluation Awaiting Final Approval</h2>
        <p>Dear Registrar,</p>
        <p>The performance evaluation for <strong>$staffName</strong> from the <strong>$department</strong> department has been reviewed by both the Supervising Officer and the staff member, and is now awaiting your final approval.</p>
        <p><strong>Evaluation Details:</strong></p>
        <ul>
            <li><strong>Staff:</strong> $staffName</li>
            <li><strong>Department:</strong> $department</li>
            <li><strong>Year:</strong> $year</li>
        </ul>
        <p>Please log in to the evaluation system to review and approve this evaluation.</p>
        <p><a href='$baseUrl/dashboard.php' style='background: #2c7a1c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Evaluation System</a></p>
        <p style='margin-top: 30px;'>Best regards,<br>$instName</p>
    </div>
</body>
</html>"
            ];

        case 'supervising_officer_reject':
            // Staff rejected - notify supervisor
            return [
                'to_emails' => !empty($supervisor['email']) ? [$supervisor['email']] : [],
                'to_name' => $supervisor ? trim($supervisor['first_name'] . ' ' . $supervisor['surname']) : 'Supervising Officer',
                'subject' => "Evaluation Requires Review - $staffName ($year)",
                'body' => "<html>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #d9534f;'>Evaluation Requires Your Review</h2>
        <p>Dear Supervising Officer,</p>
        <p><strong>$staffName</strong> has reviewed their performance evaluation and has raised concerns. The evaluation has been returned to you for review.</p>
        <p><strong>Evaluation Details:</strong></p>
        <ul>
            <li><strong>Staff:</strong> $staffName</li>
            <li><strong>Department:</strong> $department</li>
            <li><strong>Year:</strong> $year</li>
        </ul>
        <p><strong>Staff's Concerns:</strong></p>
        <p style='background: #f8f8f8; padding: 15px; border-left: 4px solid #d9534f;'>" . nl2br(htmlspecialchars($evaluation['staff_rejection_reason'] ?? 'No reason provided')) . "</p>
        <p>Please log in to the evaluation system to address these concerns and re-submit the evaluation.</p>
        <p><a href='$baseUrl/evaluate-supervisor.php' style='background: #2c7a1c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Evaluation System</a></p>
        <p style='margin-top: 30px;'>Best regards,<br>$instName</p>
    </div>
</body>
</html>"
            ];

        case 'completed':
            // Registrar approved - notify staff
            return [
                'to_emails' => !empty($staffEmail) ? [$staffEmail] : [],
                'to_name' => $staffName,
                'subject' => "Your Evaluation has been Approved - $year",
                'body' => "<html>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #2c7a1c;'>Evaluation Completed</h2>
        <p>Dear $staffName,</p>
        <p>Your performance evaluation for <strong>$year</strong> has been fully completed and approved.</p>
        <p><strong>Evaluation Summary:</strong></p>
        <ul>
            <li><strong>Score:</strong> " . ($evaluation['percentage'] ?? 'N/A') . "%</li>
            <li><strong>Grade:</strong> " . ($evaluation['performance_grade'] ?? 'N/A') . "</li>
            <li><strong>Status:</strong> " . ($evaluation['performance_status'] ?? 'Completed') . "</li>
        </ul>
        <p>You can now access and print your final evaluation report from the system.</p>
        <p><a href='$baseUrl/staff-dashboard.php' style='background: #2c7a1c; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Your Evaluation</a></p>
        <p style='margin-top: 30px;'>Best regards,<br>$instName</p>
    </div>
</body>
</html>"
            ];

        default:
            return null;
    }
}

// If this file is called directly (not included), handle the request
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    header('Content-Type: application/json');

    // Test mode
    if (isset($_GET['test'])) {
        $result = testEmailConfiguration();
        echo json_encode($result);
        exit;
    }

    // Send email mode
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $formData = $_POST['formData'] ?? [];
        $attachmentPath = $_POST['attachmentPath'] ?? '';
        $scores = $_POST['scores'] ?? [];

        $result = sendEvaluationEmail($formData, $attachmentPath, $scores);
        echo json_encode($result);
        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit;
}