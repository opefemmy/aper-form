<?php
require_once 'config.php';
startSession();

// Define the Appointment and Promotion Committee name (renamed from Dean)
define('APC_COMMITTEE_NAME', 'Appointment and Promotion Committee');

// Check access: Registrar/Admin OR Staff viewing their own approved evaluation
$isRegistrar = (isEvaluatorLoggedIn() && getEvaluatorType() === 'Registrar') || (isAdminLoggedIn() && getAdminRole() === 'registrar');
$isAdmin = isAdminLoggedIn();
$isStaff = isStaffLoggedIn();

// Check if evaluation ID is provided
$evalId = $_GET['id'] ?? 0;
if (!$evalId) {
    die('Evaluation ID required');
}

$pdo = getDBConnection();

// Get evaluation with staff details
$stmt = $pdo->prepare("SELECT e.*, s.staff_id AS staff_identifier, s.surname, s.first_name, s.department, s.faculty, s.designation, s.grade_level, s.staff_category, s.email
    FROM evaluations e
    JOIN staff s ON e.staff_id = s.id
    WHERE e.id = ?");
$stmt->execute([$evalId]);
$eval = $stmt->fetch();

if (!$eval) {
    die('Evaluation not found');
}

// Access control
$staffId = $_SESSION['staff_id'] ?? 0;

// Registrar or Admin can view any evaluation
if (!$isRegistrar && !$isAdmin) {
    // Staff can only view their own evaluation AND only if approved
    if (!$isStaff || $eval['staff_id'] != $staffId) {
        die('Access denied: You can only view your own evaluation');
    }

    // Staff can only view if evaluation is approved
    if ($eval['status'] !== 'approved') {
        die('Access denied: Your evaluation is not yet approved. You can only view approved evaluations.');
    }
}

// Get settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';
$instAddress = $settings['institution_address'] ?? '';
$logo = $settings['institution_logo'] ?? '';

// Check if TCPDF is available, otherwise use browser print
$pdf = null;
$useTcpdf = false;

// Try to use TCPDF if available
if (class_exists('TCPDF')) {
    $useTcpdf = true;
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator($instName);
    $pdf->SetAuthor('APER System');
    $pdf->SetTitle('Staff Evaluation Report');
    $pdf->SetSubject('Performance Evaluation');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 11);
} else {
    // Fallback: Generate HTML that can be printed to PDF
    header('Content-Type: text/html; charset=utf-8');
}

// Generate the report content
$staffName = $eval['first_name'] . ' ' . $eval['surname'];
$staffId = $eval['staff_identifier'];
$department = $eval['department'];
$faculty = $eval['faculty'];
$designation = $eval['designation'];
$gradeLevel = $eval['grade_level'];

if ($useTcpdf) {
    // Add watermark logo in background
    if (!empty($logo)) {
        // Get image dimensions
        $imgInfo = getimagesize($logo);
        if ($imgInfo) {
            $imgWidth = 80; // Set watermark width
            $imgHeight = ($imgInfo[1] / $imgInfo[0]) * $imgWidth;
            // Place watermark in center of page
            $pdf->Image($logo, 65, 120, $imgWidth, $imgHeight, '', '', '', true, 150);
        }
    }

    // Header with logo
    if (!empty($logo)) {
        // Logo on the left, text on the right
        $pdf->Image($logo, 15, 10, 25, 25, '', '', '', true, 150);
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $instName, 0, true, 'C');
    } else {
        // No logo - just text
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $instName, 0, true, 'C');
    }

    $pdf->SetFont('helvetica', '', 10);
    if (!empty($instAddress)) {
        $pdf->Cell(0, 5, $instAddress, 0, true, 'C');
    }
    $pdf->Ln(5);

    // Title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'STAFF PERFORMANCE EVALUATION REPORT', 0, true, 'C');
    $pdf->Ln(5);

    // Horizontal line
    $pdf->SetDrawColor(0);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(5);

    // Staff Information
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'STAFF INFORMATION', 0, true, 'L');
    $pdf->SetFont('helvetica', '', 10);

    $pdf->Cell(50, 6, 'Staff ID:', 0, 0, 'L');
    $pdf->Cell(60, 6, $staffId, 0, 0, 'L');
    $pdf->Cell(50, 6, 'Academic Year:', 0, 1, 'L');

    $pdf->Cell(50, 6, 'Full Name:', 0, 0, 'L');
    $pdf->Cell(60, 6, $staffName, 0, 0, 'L');
    $pdf->Cell(50, 6, 'Evaluation Date:', 0, 1, 'L');

    $pdf->Cell(50, 6, 'Department:', 0, 0, 'L');
    $pdf->Cell(60, 6, $department, 0, 0, 'L');
    $pdf->Cell(50, 6, 'Status:', 0, 1, 'L');

    $pdf->Cell(50, 6, 'Faculty:', 0, 0, 'L');
    $pdf->Cell(60, 6, $faculty, 0, 0, 'L');
    $pdf->Cell(50, 6, 'Stage:', 0, 1, 'L');

    $pdf->Cell(50, 6, 'Designation:', 0, 0, 'L');
    $pdf->Cell(60, 6, $designation, 0, 0, 'L');
    $pdf->Cell(50, 6, 'Grade Level:', 0, 1, 'L');

    $pdf->Ln(10);

    // Horizontal line
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(5);

    // Performance Evaluation
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'PERFORMANCE EVALUATION (By HOD)', 0, true, 'L');
    $pdf->Ln(2);

    // Score display
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 8, 'Score Percentage:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(40, 8, $eval['percentage'] . '%', 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 8, 'Performance Grade:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(40, 8, $eval['performance_grade'], 0, 1, 'L');

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 8, 'Performance Status:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(60, 8, $eval['performance_status'], 0, 1, 'L');

    $pdf->Ln(10);

    // HOD Evaluation Details
    if (!empty($eval['supervisor_remarks']) || !empty($eval['supervisor_name'])) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'HOD EVALUATION DETAILS', 0, true, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', '', 10);

        if (!empty($eval['supervisor_name'])) {
            $pdf->Cell(50, 6, 'Evaluated By (HOD):', 0, 0, 'L');
            $pdf->Cell(0, 6, $eval['supervisor_name'], 0, 1, 'L');
        }

        if (!empty($eval['supervisor_designation'])) {
            $pdf->Cell(50, 6, 'Designation:', 0, 0, 'L');
            $pdf->Cell(0, 6, $eval['supervisor_designation'], 0, 1, 'L');
        }

        if (!empty($eval['supervisor_date'])) {
            $pdf->Cell(50, 6, 'Evaluation Date:', 0, 0, 'L');
            $pdf->Cell(0, 6, date('F j, Y', strtotime($eval['supervisor_date'])), 0, 1, 'L');
        }

        if (!empty($eval['supervisor_remarks'])) {
            $pdf->Cell(0, 6, 'HOD Remarks:', 0, 1, 'L');
            $pdf->MultiCell(0, 6, $eval['supervisor_remarks'], 0, 'L');
        }

        if (!empty($eval['overall_rating'])) {
            $pdf->Cell(50, 6, 'Overall Rating:', 0, 0, 'L');
            $pdf->Cell(0, 6, $eval['overall_rating'], 0, 1, 'L');
        }

        if (!empty($eval['recommendation'])) {
            $pdf->Cell(50, 6, 'Recommendation:', 0, 0, 'L');
            $pdf->Cell(0, 6, $eval['recommendation'], 0, 1, 'L');
        }

        $pdf->Ln(10);
    }

    // Dean Comments
    if (!empty($eval['dean_remarks'])) {
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'DEAN REVIEW', 0, true, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', '', 10);

        if (!empty($eval['dean_name'])) {
            $pdf->Cell(50, 6, 'Reviewed By:', 0, 0, 'L');
            $pdf->Cell(0, 6, $eval['dean_name'], 0, 1, 'L');
        }

        if (!empty($eval['dean_date'])) {
            $pdf->Cell(50, 6, 'Review Date:', 0, 0, 'L');
            $pdf->Cell(0, 6, date('F j, Y', strtotime($eval['dean_date'])), 0, 1, 'L');
        }

        $pdf->Cell(0, 6, APC_COMMITTEE_NAME . ' Comments:', 0, 1, 'L');
        $pdf->MultiCell(0, 6, $eval['dean_remarks'], 0, 'L');

        $pdf->Ln(10);
    }

    // Registrar Approval
    if ($eval['evaluation_stage'] === 'completed' || $eval['evaluation_stage'] === 'registrar') {
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'REGISTRAR APPROVAL', 0, true, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', '', 10);

        if (!empty($eval['registrar_name'])) {
            $pdf->Cell(50, 6, 'Approved By:', 0, 0, 'L');
            $pdf->Cell(0, 6, $eval['registrar_name'], 0, 1, 'L');
        }

        if (!empty($eval['registrar_date'])) {
            $pdf->Cell(50, 6, 'Approval Date:', 0, 0, 'L');
            $pdf->Cell(0, 6, date('F j, Y', strtotime($eval['registrar_date'])), 0, 1, 'L');
        }

        if (!empty($eval['approval_status'])) {
            $pdf->Cell(50, 6, 'Approval Status:', 0, 0, 'L');
            $pdf->Cell(0, 6, $eval['approval_status'], 0, 1, 'L');
        }

        if (!empty($eval['registrar_remarks'])) {
            $pdf->Cell(0, 6, 'Registrar Remarks:', 0, 1, 'L');
            $pdf->MultiCell(0, 6, $eval['registrar_remarks'], 0, 'L');
        }

        if (!empty($eval['registrar_signature'])) {
            $pdf->Cell(50, 6, 'Digital Signature:', 0, 0, 'L');
            $pdf->Cell(0, 6, $eval['registrar_signature'], 0, 1, 'L');
        }
    }

    $pdf->Ln(15);

    // Footer note
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 5, 'This is a computer-generated document. Generated by APER System on ' . date('F j, Y g:i A'), 0, true, 'C');

    // Output PDF
    $pdf->Output('Evaluation_Report_' . $staffId . '_' . date('Y') . '.pdf', 'D');
} else {
    // HTML Fallback with Logo and Watermark
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Evaluation Report - ' . htmlspecialchars($staffName) . '</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; position: relative; }
            h1 { text-align: center; color: #308a1e; }
            .header-logo { position: absolute; top: 10px; left: 20px; max-height: 60px; }
            .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 300px; opacity: 0.1; z-index: -1; pointer-events: none; }
            .watermark img { width: 100%; height: auto; }
            h2 { border-bottom: 2px solid #308a1e; padding-bottom: 5px; }
            .info-grid { display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin: 20px 0; }
            .info-label { font-weight: bold; }
            .score-box { background: #f0f9ff; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
            .score-value { font-size: 36px; font-weight: bold; color: #308a1e; }
            .remarks-box { background: #f9f9f9; padding: 15px; border-left: 4px solid #308a1e; margin: 15px 0; }
            .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
            @media print { body { padding: 0; } }
        </style>
    </head>
    <body>';

    // Watermark (background logo)
    if (!empty($logo)) {
        echo '<div class="watermark"><img src="' . htmlspecialchars($logo) . '" alt="Watermark"></div>';
    }

    // Header with logo
    if (!empty($logo)) {
        echo '<img src="' . htmlspecialchars($logo) . '" class="header-logo" alt="Institution Logo">';
    }
    echo '<h1 style="margin-top: 20px;">' . htmlspecialchars($instName) . '</h1>
        <p style="text-align:center">' . htmlspecialchars($instAddress) . '</p>
        <h2 style="text-align:center">STAFF PERFORMANCE EVALUATION REPORT</h2>

        <h3>Staff Information</h3>
        <div class="info-grid">
            <div class="info-label">Staff ID:</div><div>' . htmlspecialchars($staffId) . '</div>
            <div class="info-label">Full Name:</div><div>' . htmlspecialchars($staffName) . '</div>
            <div class="info-label">Department:</div><div>' . htmlspecialchars($department) . '</div>
            <div class="info-label">Faculty:</div><div>' . htmlspecialchars($faculty) . '</div>
            <div class="info-label">Designation:</div><div>' . htmlspecialchars($designation) . '</div>
            <div class="info-label">Grade Level:</div><div>' . htmlspecialchars($gradeLevel) . '</div>
            <div class="info-label">Academic Year:</div><div>' . htmlspecialchars($eval['evaluation_year']) . '</div>
        </div>

        <h3>Performance Evaluation (By HOD)</h3>
        <div class="score-box">
            <div class="score-value">' . htmlspecialchars($eval['percentage']) . '%</div>
            <div>Score Percentage</div>
            <div style="font-size: 24px; margin-top: 10px;">' . htmlspecialchars($eval['performance_grade']) . ' - ' . htmlspecialchars($eval['performance_status']) . '</div>
        </div>';

    if (!empty($eval['supervisor_remarks']) || !empty($eval['supervisor_name'])) {
        echo '
        <h3>HOD Evaluation Details</h3>
        <div class="info-grid">
            <div class="info-label">Evaluated By:</div><div>' . htmlspecialchars($eval['supervisor_name'] ?? 'N/A') . '</div>
            <div class="info-label">Designation:</div><div>' . htmlspecialchars($eval['supervisor_designation'] ?? 'N/A') . '</div>
            <div class="info-label">Date:</div><div>' . (!empty($eval['supervisor_date']) ? date('F j, Y', strtotime($eval['supervisor_date'])) : 'N/A') . '</div>
        </div>';

        if (!empty($eval['supervisor_remarks'])) {
            echo '<div class="remarks-box"><strong>HOD Remarks:</strong><br>' . nl2br(htmlspecialchars($eval['supervisor_remarks'])) . '</div>';
        }

        if (!empty($eval['overall_rating'])) {
            echo '<div class="info-grid"><div class="info-label">Overall Rating:</div><div>' . htmlspecialchars($eval['overall_rating']) . '</div></div>';
        }

        if (!empty($eval['recommendation'])) {
            echo '<div class="info-grid"><div class="info-label">Recommendation:</div><div>' . htmlspecialchars($eval['recommendation']) . '</div></div>';
        }
    }

    if (!empty($eval['dean_remarks'])) {
        echo '
        <h3><?php echo APC_COMMITTEE_NAME; ?> Review</h3>
        <div class="info-grid">
            <div class="info-label">Reviewed By:</div><div>' . htmlspecialchars($eval['dean_name'] ?? 'N/A') . '</div>
            <div class="info-label">Date:</div><div>' . (!empty($eval['dean_date']) ? date('F j, Y', strtotime($eval['dean_date'])) : 'N/A') . '</div>
        </div>
        <div class="remarks-box"><strong><?php echo APC_COMMITTEE_NAME; ?> Comments:</strong><br>' . nl2br(htmlspecialchars($eval['dean_remarks'])) . '</div>';
    }

    if ($eval['evaluation_stage'] === 'completed' || $eval['evaluation_stage'] === 'registrar') {
        echo '
        <h3>Registrar Approval</h3>
        <div class="info-grid">
            <div class="info-label">Approved By:</div><div>' . htmlspecialchars($eval['registrar_name'] ?? 'N/A') . '</div>
            <div class="info-label">Date:</div><div>' . (!empty($eval['registrar_date']) ? date('F j, Y', strtotime($eval['registrar_date'])) : 'N/A') . '</div>
            <div class="info-label">Status:</div><div>' . htmlspecialchars($eval['approval_status'] ?? 'N/A') . '</div>
        </div>';

        if (!empty($eval['registrar_remarks'])) {
            echo '<div class="remarks-box"><strong>Registrar Remarks:</strong><br>' . nl2br(htmlspecialchars($eval['registrar_remarks'])) . '</div>';
        }
    }

    echo '
        <div class="footer">
            <p>This is a computer-generated document. Generated by APER System on ' . date('F j, Y g:i A') . '</p>
            <button onclick="window.print()" style="padding: 10px 20px; background: #308a1e; color: white; border: none; border-radius: 5px; cursor: pointer;">Print / Save as PDF</button>
        </div>
    </body>
    </html>';
}