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
 * Get email configuration from environment variables
 */
function getEmailConfig() {
    return [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'port' => getenv('SMTP_PORT') ?: 587,
        'from_address' => getenv('EMAIL_FROM_ADDRESS') ?: 'noreply@yourinstitution.edu.ng',
        'from_name' => getenv('EMAIL_FROM_NAME') ?: 'Performance Evaluation System',
        'to_address' => getenv('EMAIL_TO_ADDRESS') ?: 'evaluation@yourinstitution.edu.ng',
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