<?php
/**
 * Annual Performance Evaluation Report System
 * Excel Generation with PhpSpreadsheet
 *
 * Handles form submission, generates Excel file, and triggers email notification
 */

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

// ==========================================
// Security Functions
// ==========================================

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 */
function validatePhone($phone) {
    $cleanedPhone = preg_replace('/[\s\-\(\)]/', '', $phone);
    return preg_match('/^\+?[\d]{10,15}$/', $cleanedPhone) === 1;
}

/**
 * Check for spam (honeypot)
 */
function checkSpam($honeypot) {
    return empty($honeypot);
}

// ==========================================
// Response Functions
// ==========================================

/**
 * Send JSON response
 */
function sendJSONResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

/**
 * Log error message
 */
function logError($message, $context = []) {
    $logFile = __DIR__ . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}";
    if (!empty($context)) {
        $logMessage .= ' - Context: ' . json_encode($context);
    }
    $logMessage .= PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// ==========================================
// Excel Generation
// ==========================================

/**
 * Create Excel spreadsheet from form data
 */
function createExcelSpreadsheet($formData, $scores) {
    // Create new Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('Annual Performance Evaluation System')
        ->setTitle('Performance Evaluation Report')
        ->setSubject('Staff Performance Evaluation')
        ->setDescription('Annual Performance Evaluation Report - Generated on ' . date('Y-m-d H:i:s'));

    // Define styles
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 12,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1E3A8A'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];

    $subHeaderStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => '1E3A8A'],
            'size' => 11,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'DBEAFE'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC'],
            ],
        ],
    ];

    $dataStyle = [
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_LEFT,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC'],
            ],
        ],
    ];

    $scoreStyle = [
        'font' => [
            'bold' => true,
            'size' => 14,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F0FDF4'],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color' => ['rgb' => '10B981'],
            ],
        ],
    ];

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(35);
    $sheet->getColumnDimension('B')->setWidth(50);
    $sheet->getColumnDimension('C')->setWidth(15);

    // Row counter
    $row = 1;

    // ==========================================
    // HEADER SECTION
    // ==========================================
    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->setCellValue("A{$row}", $formData['institution_name'] ?? 'Institution Name');
    $sheet->getStyle("A{$row}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E3A8A']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $row++;

    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->setCellValue("A{$row}", 'Annual Performance Evaluation Report');
    $sheet->getStyle("A{$row}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '3B82F6']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $row++;

    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->setCellValue("A{$row}", "Academic Session: {$formData['academic_session']} | Semester: {$formData['semester']} | Year: {$formData['evaluation_year']}");
    $sheet->getStyle("A{$row}")->applyFromArray([
        'font' => ['size' => 10],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $row += 2;

    // ==========================================
    // SECTION 1: INSTITUTION DETAILS
    // ==========================================
    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->setCellValue("A{$row}", 'SECTION 1: INSTITUTION DETAILS');
    $sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setHeight(25);
    $row++;

    $institutionDetails = [
        'Institution Name' => $formData['institution_name'] ?? '',
        'Academic Session' => $formData['academic_session'] ?? '',
        'Semester' => $formData['semester'] ?? '',
        'Evaluation Year' => $formData['evaluation_year'] ?? '',
        'Date' => $formData['evaluation_date'] ?? '',
    ];

    foreach ($institutionDetails as $label => $value) {
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", $value);
        $sheet->getStyle("A{$row}")->applyFromArray($subHeaderStyle);
        $sheet->getStyle("B{$row}")->applyFromArray($dataStyle);
        $row++;
    }
    $row++;

    // ==========================================
    // SECTION 2: STAFF INFORMATION
    // ==========================================
    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->setCellValue("A{$row}", 'SECTION 2: STAFF INFORMATION');
    $sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setHeight(25);
    $row++;

    $staffInfo = [
        'Staff Full Name' => $formData['staff_name'] ?? '',
        'Staff ID' => $formData['staff_id'] ?? '',
        'Department' => $formData['department'] ?? '',
        'Faculty/School' => $formData['faculty'] ?? '',
        'Designation' => $formData['designation'] ?? '',
        'Grade Level' => $formData['grade_level'] ?? '',
        'Employment Status' => $formData['employment_status'] ?? '',
        'Years of Service' => $formData['years_of_service'] ?? '',
        'Email Address' => $formData['email'] ?? '',
        'Phone Number' => $formData['phone'] ?? '',
    ];

    foreach ($staffInfo as $label => $value) {
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", $value);
        $sheet->getStyle("A{$row}")->applyFromArray($subHeaderStyle);
        $sheet->getStyle("B{$row}")->applyFromArray($dataStyle);
        $row++;
    }
    $row++;

    // ==========================================
    // SECTION 3: PERFORMANCE EVALUATION
    // ==========================================
    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->setCellValue("A{$row}", 'SECTION 3: PERFORMANCE EVALUATION');
    $sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setHeight(25);
    $row++;

    // Categories and their questions
    $categories = [
        'Teaching Performance' => [
            'teaching_1' => 'Lecture Delivery',
            'teaching_2' => 'Class Attendance',
            'teaching_3' => 'Student Engagement',
            'teaching_4' => 'Course Preparation',
            'teaching_5' => 'Course Coverage',
            'teaching_6' => 'Time Management',
        ],
        'Research Performance' => [
            'research_1' => 'Publications',
            'research_2' => 'Conferences',
            'research_3' => 'Research Grants',
            'research_4' => 'Journal Articles',
            'research_5' => 'Innovations',
        ],
        'Administrative Duties' => [
            'admin_1' => 'Attendance',
            'admin_2' => 'Punctuality',
            'admin_3' => 'Leadership',
            'admin_4' => 'Teamwork',
            'admin_5' => 'Record Keeping',
        ],
        'Community Service' => [
            'community_1' => 'Community Development',
            'community_2' => 'Committee Participation',
            'community_3' => 'Institutional Representation',
        ],
        'Professional Development' => [
            'professional_1' => 'Workshops',
            'professional_2' => 'Training',
            'professional_3' => 'Certifications',
            'professional_4' => 'Seminars',
        ],
    ];

    $ratingLabels = [5 => 'Excellent', 4 => 'Very Good', 3 => 'Good', 2 => 'Fair', 1 => 'Poor'];

    $categoryTotals = [];
    $grandTotal = 0;
    $totalQuestions = 0;

    foreach ($categories as $categoryName => $questions) {
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", $categoryName);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E3A8A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
        $sheet->getRowDimension($row)->setHeight(22);
        $row++;

        $categoryTotal = 0;
        $categoryQuestions = 0;

        foreach ($questions as $fieldName => $questionLabel) {
            $score = isset($formData[$fieldName]) ? intval($formData[$fieldName]) : 0;
            $ratingText = $score > 0 ? ($ratingLabels[$score] ?? $score) : 'Not Rated';

            $sheet->setCellValue("A{$row}", $questionLabel);
            $sheet->setCellValue("B{$row}", $ratingText);
            $sheet->setCellValue("C{$row}", $score > 0 ? $score : '-');

            $sheet->getStyle("A{$row}")->applyFromArray($dataStyle);
            $sheet->getStyle("B{$row}")->applyFromArray($dataStyle);
            $sheet->getStyle("C{$row}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            ]);

            if ($score > 0) {
                $categoryTotal += $score;
                $categoryQuestions++;
                $grandTotal += $score;
                $totalQuestions++;
            }
            $row++;
        }

        $categoryAvg = $categoryQuestions > 0 ? number_format($categoryTotal / $categoryQuestions, 2) : 0;
        $categoryTotals[$categoryName] = ['total' => $categoryTotal, 'avg' => $categoryAvg, 'count' => $categoryQuestions];

        $sheet->setCellValue("A{$row}", "{$categoryName} Average");
        $sheet->setCellValue("B{$row}", $categoryAvg);
        $sheet->setCellValue("C{$row}", $categoryTotal);
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
        $row++;
    }
    $row++;

    // ==========================================
    // SCORES SUMMARY
    // ==========================================
    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->setCellValue("A{$row}", 'EVALUATION SCORES SUMMARY');
    $sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setHeight(25);
    $row++;

    $maxPossible = 23 * 5;
    $averageScore = $totalQuestions > 0 ? number_format($grandTotal / $totalQuestions, 2) : 0;
    $percentage = $maxPossible > 0 ? number_format(($grandTotal / $maxPossible) * 100, 1) : 0;

    $scoreData = [
        'Total Score' => $grandTotal . ' / ' . $maxPossible,
        'Average Score' => $averageScore,
        'Percentage' => $percentage . '%',
        'Performance Grade' => $scores['performance_grade'] ?? '-',
        'Performance Status' => $scores['performance_status'] ?? 'Pending',
    ];

    foreach ($scoreData as $label => $value) {
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", $value);
        $sheet->getStyle("A{$row}")->applyFromArray($subHeaderStyle);
        $sheet->getStyle("B{$row}")->applyFromArray($scoreStyle);
        $sheet->mergeCells("B{$row}:C{$row}");
        $row++;
    }
    $row++;

    // ==========================================
    // SECTION 4: SUPERVISOR ASSESSMENT
    // ==========================================
    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->setCellValue("A{$row}", 'SECTION 4: SUPERVISOR ASSESSMENT');
    $sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setHeight(25);
    $row++;

    $supervisorData = [
        'Supervisor Name' => $formData['supervisor_name'] ?? '',
        'Supervisor Designation' => $formData['supervisor_designation'] ?? '',
        'Overall Rating' => $formData['overall_rating'] ?? '',
        'Recommendation' => $formData['recommendation'] ?? '',
        'Date' => $formData['supervisor_date'] ?? '',
        'Digital Signature' => $formData['supervisor_signature_text'] ?? '',
    ];

    foreach ($supervisorData as $label => $value) {
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", $value);
        $sheet->getStyle("A{$row}")->applyFromArray($subHeaderStyle);
        $sheet->getStyle("B{$row}")->applyFromArray($dataStyle);
        $row++;
    }

    // Remarks (can be multi-line)
    $sheet->setCellValue("A{$row}", 'Remarks');
    $sheet->setCellValue("B{$row}", $formData['supervisor_remarks'] ?? '');
    $sheet->getStyle("A{$row}")->applyFromArray($subHeaderStyle);
    $sheet->getStyle("B{$row}")->applyFromArray([
        'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
    ]);
    $sheet->getRowDimension($row)->setHeight(60);
    $row += 2;

    // ==========================================
    // SECTION 5: REGISTRAR/MANAGEMENT
    // ==========================================
    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->setCellValue("A{$row}", 'SECTION 5: REGISTRAR/MANAGEMENT');
    $sheet->getStyle("A{$row}")->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setHeight(25);
    $row++;

    $registrarData = [
        'Registrar Name' => $formData['registrar_name'] ?? '',
        'Approval Status' => $formData['approval_status'] ?? '',
        'Date' => $formData['registrar_date'] ?? '',
        'Digital Signature' => $formData['registrar_signature_text'] ?? '',
    ];

    foreach ($registrarData as $label => $value) {
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", $value);
        $sheet->getStyle("A{$row}")->applyFromArray($subHeaderStyle);
        $sheet->getStyle("B{$row}")->applyFromArray($dataStyle);
        $row++;
    }

    // Remarks
    $sheet->setCellValue("A{$row}", 'Remarks');
    $sheet->setCellValue("B{$row}", $formData['registrar_remarks'] ?? '');
    $sheet->getStyle("A{$row}")->applyFromArray($subHeaderStyle);
    $sheet->getStyle("B{$row}")->applyFromArray([
        'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
    ]);
    $sheet->getRowDimension($row)->setHeight(60);
    $row += 2;

    // ==========================================
    // FOOTER
    // ==========================================
    $sheet->mergeCells("A{$row}:C{$row}");
    $sheet->setCellValue("A{$row}", 'Submission Date: ' . date('Y-m-d H:i:s'));
    $sheet->getStyle("A{$row}")->applyFromArray([
        'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    return $spreadsheet;
}

/**
 * Save Excel file and return path
 */
function saveExcelFile($spreadsheet, $staffId) {
    $uploadDir = __DIR__ . '/uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'evaluation_' . $staffId . '_' . date('YmdHis') . '.xlsx';
    $filePath = $uploadDir . $fileName;

    $writer = new Xlsx($spreadsheet);
    $writer->save($filePath);

    return $filePath;
}

// ==========================================
// Email Notification
// ==========================================

/**
 * Send email with Excel attachment
 */
function sendEmailNotification($formData, $excelFilePath) {
    // Include PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USERNAME') ?: '';
        $mail->Password   = getenv('SMTP_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPSmtpTransport::ENCRYPTION_STARTTLS;
        $mail->Port       = getenv('SMTP_PORT') ?: 587;

        // Recipients
        $mail->setFrom(
            getenv('EMAIL_FROM_ADDRESS') ?: 'noreply@yourinstitution.edu.ng',
            getenv('EMAIL_FROM_NAME') ?: 'Performance Evaluation System'
        );
        $mail->addAddress('evaluation@yourinstitution.edu.ng');
        $mail->addReplyTo($formData['email'] ?? 'noreply@yourinstitution.edu.ng', $formData['staff_name'] ?? 'Staff');

        // Attachments
        if (file_exists($excelFilePath)) {
            $mail->addAttachment($excelFilePath);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Annual Performance Evaluation Report Submission';

        $emailBody = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1E3A8A; color: white; padding: 20px; text-align: center; }
                .content { background: #f8fafc; padding: 20px; margin: 20px 0; }
                .info-table { width: 100%; border-collapse: collapse; }
                .info-table td { padding: 10px; border-bottom: 1px solid #e2e8f0; }
                .info-table td:first-child { font-weight: bold; color: #1E3A8A; width: 40%; }
                .footer { text-align: center; color: #64748b; font-size: 12px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin: 0;'>New Performance Evaluation Submitted</h2>
                </div>
                <div class='content'>
                    <p>A new Annual Performance Evaluation Report has been submitted. Please find the attached Excel report for details.</p>
                    <table class='info-table'>
                        <tr>
                            <td>Staff Name:</td>
                            <td>" . sanitizeInput($formData['staff_name'] ?? 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td>Staff ID:</td>
                            <td>" . sanitizeInput($formData['staff_id'] ?? 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td>Department:</td>
                            <td>" . sanitizeInput($formData['department'] ?? 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td>Faculty/School:</td>
                            <td>" . sanitizeInput($formData['faculty'] ?? 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td>Academic Session:</td>
                            <td>" . sanitizeInput($formData['academic_session'] ?? 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td>Semester:</td>
                            <td>" . sanitizeInput($formData['semester'] ?? 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td>Evaluation Year:</td>
                            <td>" . sanitizeInput($formData['evaluation_year'] ?? 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td>Date Submitted:</td>
                            <td>" . date('Y-m-d H:i:s') . "</td>
                        </tr>
                    </table>
                </div>
                <div class='footer'>
                    <p>This is an automated message from the Annual Performance Evaluation System.</p>
                    <p>Please do not reply directly to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<p>', '</p>'], ["\n", "\n", "\n"], $emailBody));

        $mail->send();

        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (Exception $e) {
        logError('Email sending failed', ['error' => $mail->ErrorInfo, 'exception' => $e->getMessage()]);
        return ['success' => false, 'message' => 'Email could not be sent: ' . $mail->ErrorInfo];
    }
}

// ==========================================
// Main Request Handler
// ==========================================

// Start session for CSRF
session_start();

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(false, 'Invalid request method');
}

// ==========================================
// Security Checks
// ==========================================

// Check CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    logError('CSRF validation failed', ['token' => $csrfToken]);
    sendJSONResponse(false, 'Security validation failed. Please refresh the page and try again.');
}

// Check honeypot (spam protection)
$honeypot = $_POST['website_url'] ?? '';
if (!checkSpam($honeypot)) {
    logError('Spam detected', ['honeypot' => $honeypot]);
    sendJSONResponse(false, 'Submission rejected');
}

// ==========================================
// Validate and Process Form Data
// ==========================================

try {
    // Sanitize all inputs
    $formData = [];
    foreach ($_POST as $key => $value) {
        if ($key !== 'csrf_token' && $key !== 'website_url') {
            $formData[$key] = sanitizeInput($value);
        }
    }

    // Validate required fields
    $requiredFields = [
        'institution_name' => 'Institution Name',
        'academic_session' => 'Academic Session',
        'semester' => 'Semester',
        'evaluation_year' => 'Evaluation Year',
        'staff_name' => 'Staff Name',
        'staff_id' => 'Staff ID',
        'department' => 'Department',
        'faculty' => 'Faculty',
        'designation' => 'Designation',
        'grade_level' => 'Grade Level',
        'employment_status' => 'Employment Status',
        'years_of_service' => 'Years of Service',
        'email' => 'Email Address',
        'phone' => 'Phone Number',
    ];

    $missingFields = [];
    foreach ($requiredFields as $field => $label) {
        if (empty($formData[$field])) {
            $missingFields[] = $label;
        }
    }

    if (!empty($missingFields)) {
        sendJSONResponse(false, 'Missing required fields: ' . implode(', ', $missingFields));
    }

    // Validate email
    if (!validateEmail($formData['email'])) {
        sendJSONResponse(false, 'Invalid email address format');
    }

    // Validate phone
    if (!validatePhone($formData['phone'])) {
        sendJSONResponse(false, 'Invalid phone number format');
    }

    // Get score data
    $scores = [
        'total_score' => intval($_POST['total_score'] ?? 0),
        'average_score' => floatval($_POST['average_score'] ?? 0),
        'percentage' => floatval($_POST['percentage'] ?? 0),
        'performance_grade' => sanitizeInput($_POST['performance_grade'] ?? '-'),
        'performance_status' => sanitizeInput($_POST['performance_status'] ?? 'Pending'),
    ];

    // ==========================================
    // Generate Excel
    // ==========================================

    $spreadsheet = createExcelSpreadsheet($formData, $scores);
    $excelPath = saveExcelFile($spreadsheet, $formData['staff_id']);

    if (!file_exists($excelPath)) {
        logError('Failed to create Excel file', ['path' => $excelPath]);
        sendJSONResponse(false, 'Failed to generate Excel file');
    }

    // ==========================================
    // Send Email Notification
    // ==========================================

    $emailResult = sendEmailNotification($formData, $excelPath);

    // Log email status (but don't fail if email fails)
    if (!$emailResult['success']) {
        logError('Email notification failed', [
            'staff_id' => $formData['staff_id'],
            'error' => $emailResult['message']
        ]);
    }

    // ==========================================
    // Return Success Response
    // ==========================================

    sendJSONResponse(true, 'Evaluation submitted successfully!', [
        'file' => basename($excelPath),
        'email_sent' => $emailResult['success'],
        'scores' => $scores
    ]);

} catch (Exception $e) {
    logError('Form processing error', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    sendJSONResponse(false, 'An error occurred while processing your submission. Please try again.');
}