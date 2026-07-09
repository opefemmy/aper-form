<?php
require_once 'config.php';

// Check for evaluator login first
if (isEvaluatorLoggedIn()) {
    $evalType = getEvaluatorType();
    if ($evalType === 'Registrar') {
        redirect(SITE_URL . '/registrar-reports.php');
    } else {
        redirect(SITE_URL . '/evaluate-supervisor.php');
    }
}

requireAdminLogin();
requirePermission('reports_view');

$message = getMessage();

// Get settings for institution details
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$instName = $settings['institution_name'] ?? 'Institution';
$instAddress = $settings['institution_address'] ?? '';
$logo = $settings['institution_logo'] ?? '';
$primaryColor = $settings['primary_color'] ?? '#308a1e';
$secondaryColor = $settings['secondary_color'] ?? '#269c16';

// Get filters
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';
$year = $_GET['year'] ?? '';

// Build query
$where = [];
$params = [];

if ($department) {
    $where[] = "s.department = ?";
    $params[] = $department;
}
if ($status) {
    $where[] = "e.status = ?";
    $params[] = $status;
}
if ($year) {
    $where[] = "e.evaluation_year = ?";
    $params[] = $year;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$pdo = getDBConnection();

// Get evaluations with all staff details
$sql = "SELECT e.*,
        s.staff_id, s.surname, s.first_name, s.email, s.phone,
        s.department, s.faculty, s.designation, s.grade_level,
        s.employment_status, s.years_of_service
        FROM evaluations e
        JOIN staff s ON e.staff_id = s.id
        $whereClause
        ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evaluations = $stmt->fetchAll();

// Get departments for filter
$stmt = $pdo->query("SELECT DISTINCT department FROM staff WHERE department != '' ORDER BY department");
$departments = $stmt->fetchAll();

// Get years
$stmt = $pdo->query("SELECT DISTINCT evaluation_year FROM evaluations ORDER BY evaluation_year DESC");
$years = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        AVG(percentage) as avg_percentage
    FROM evaluations
");
$stats = $stmt->fetch();

// Handle delete
if (isset($_GET['delete']) && $_GET['delete'] && hasPermission('delete_evaluation')) {
    try {
        $stmt = $pdo->prepare("DELETE FROM evaluations WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        showMessage('Evaluation deleted successfully!', 'success');
        redirect('reports.php');
    } catch (Exception $e) {
        showMessage('Error: ' . $e->getMessage(), 'danger');
    }
}

// Handle export to Excel
if (isset($_GET['export']) && $_GET['export'] && hasPermission('reports_export')) {
    require_once 'vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Headers - ALL FIELDS
    $headers = [
        'A1' => 'Staff ID',
        'B1' => 'Surname',
        'C1' => 'First Name',
        'D1' => 'Email',
        'E1' => 'Phone',
        'F1' => 'Department',
        'G1' => 'Faculty',
        'H1' => 'Designation',
        'I1' => 'Grade Level',
        'J1' => 'Employment Status',
        'K1' => 'Years of Service',
        'L1' => 'Academic Session',
        'M1' => 'Evaluation Year',
        'N1' => 'Teaching 1',
        'O1' => 'Teaching 2',
        'P1' => 'Teaching 3',
        'Q1' => 'Teaching 4',
        'R1' => 'Teaching 5',
        'S1' => 'Teaching 6',
        'T1' => 'Research 1',
        'U1' => 'Research 2',
        'V1' => 'Research 3',
        'W1' => 'Research 4',
        'X1' => 'Research 5',
        'Y1' => 'Admin 1',
        'Z1' => 'Admin 2',
        'AA1' => 'Admin 3',
        'AB1' => 'Admin 4',
        'AC1' => 'Admin 5',
        'AD1' => 'Community 1',
        'AE1' => 'Community 2',
        'AF1' => 'Community 3',
        'AG1' => 'Professional 1',
        'AH1' => 'Professional 2',
        'AI1' => 'Professional 3',
        'AJ1' => 'Professional 4',
        'AK1' => 'Total Score',
        'AL1' => 'Average Score',
        'AM1' => 'Percentage',
        'AN1' => 'Grade',
        'AO1' => 'Status',
        'AP1' => 'Overall Rating',
        'AQ1' => 'Recommendation',
        'AR1' => 'Submission Date',
    ];

    foreach ($headers as $cell => $header) {
        $sheet->setCellValue($cell, $header);
    }

    // Style header row
    $sheet->getStyle('A1:AR1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
    ]);

    $row = 2;
    foreach ($evaluations as $eval) {
        $sheet->setCellValue('A' . $row, $eval['staff_id']);
        $sheet->setCellValue('B' . $row, $eval['surname']);
        $sheet->setCellValue('C' . $row, $eval['first_name']);
        $sheet->setCellValue('D' . $row, $eval['email']);
        $sheet->setCellValue('E' . $row, $eval['phone']);
        $sheet->setCellValue('F' . $row, $eval['department']);
        $sheet->setCellValue('G' . $row, $eval['faculty']);
        $sheet->setCellValue('H' . $row, $eval['designation']);
        $sheet->setCellValue('I' . $row, $eval['grade_level']);
        $sheet->setCellValue('J' . $row, $eval['employment_status']);
        $sheet->setCellValue('K' . $row, $eval['years_of_service']);

        // Get session name
        $sessionName = '';
        if ($eval['academic_session_id']) {
            $s = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE id = ?");
            $s->execute([$eval['academic_session_id']]);
            $sessionName = $s->fetch()['session_name'] ?? '';
        }
        $sheet->setCellValue('L' . $row, $sessionName);
        $sheet->setCellValue('M' . $row, $eval['evaluation_year']);

        // Scores
        $sheet->setCellValue('N' . $row, $eval['teaching_1']);
        $sheet->setCellValue('O' . $row, $eval['teaching_2']);
        $sheet->setCellValue('P' . $row, $eval['teaching_3']);
        $sheet->setCellValue('Q' . $row, $eval['teaching_4']);
        $sheet->setCellValue('R' . $row, $eval['teaching_5']);
        $sheet->setCellValue('S' . $row, $eval['teaching_6']);
        $sheet->setCellValue('T' . $row, $eval['research_1']);
        $sheet->setCellValue('U' . $row, $eval['research_2']);
        $sheet->setCellValue('V' . $row, $eval['research_3']);
        $sheet->setCellValue('W' . $row, $eval['research_4']);
        $sheet->setCellValue('X' . $row, $eval['research_5']);
        $sheet->setCellValue('Y' . $row, $eval['admin_1']);
        $sheet->setCellValue('Z' . $row, $eval['admin_2']);
        $sheet->setCellValue('AA' . $row, $eval['admin_3']);
        $sheet->setCellValue('AB' . $row, $eval['admin_4']);
        $sheet->setCellValue('AC' . $row, $eval['admin_5']);
        $sheet->setCellValue('AD' . $row, $eval['community_1']);
        $sheet->setCellValue('AE' . $row, $eval['community_2']);
        $sheet->setCellValue('AF' . $row, $eval['community_3']);
        $sheet->setCellValue('AG' . $row, $eval['professional_1']);
        $sheet->setCellValue('AH' . $row, $eval['professional_2']);
        $sheet->setCellValue('AI' . $row, $eval['professional_3']);
        $sheet->setCellValue('AJ' . $row, $eval['professional_4']);

        // Calculated
        $sheet->setCellValue('AK' . $row, $eval['total_score']);
        $sheet->setCellValue('AL' . $row, $eval['average_score']);
        $sheet->setCellValue('AM' . $row, $eval['percentage'] . '%');
        $sheet->setCellValue('AN' . $row, $eval['performance_grade']);
        $sheet->setCellValue('AO' . $row, $eval['status']);
        $sheet->setCellValue('AP' . $row, $eval['overall_rating']);
        $sheet->setCellValue('AQ' . $row, $eval['recommendation']);
        $sheet->setCellValue('AR' . $row, date('Y-m-d', strtotime($eval['created_at'])));

        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'AR') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename=evaluation_report_' . date('Ymd') . '.xlsx');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Handle Single PDF Export (Individual Staff)
if (isset($_GET['single_pdf']) && $_GET['single_pdf'] && hasPermission('reports_pdf')) {
    $evalId = intval($_GET['single_pdf']);
    $stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.email, s.phone, s.department, s.faculty, s.designation, s.grade_level, s.employment_status, s.years_of_service
        FROM evaluations e JOIN staff s ON e.staff_id = s.id WHERE e.id = ?");
    $stmt->execute([$evalId]);
    $eval = $stmt->fetch();

    if (!$eval) {
        die("Evaluation not found");
    }

    // Get institution settings
    $stmt = $pdo->query("SELECT * FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $instName = $settings['institution_name'] ?? 'Institution';
    $instAddress = $settings['institution_address'] ?? '';
    $logo = $settings['institution_logo'] ?? '';
    $primaryColor = $settings['primary_color'] ?? '#308a1e';
    $secondaryColor = $settings['secondary_color'] ?? '#269c16';

    $sessionName = '';
    if ($eval['academic_session_id']) {
        $s = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE id = ?");
        $s->execute([$eval['academic_session_id']]);
        $sessionName = $s->fetch()['session_name'] ?? '';
    }
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Evaluation Report - <?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; padding: 20px; }
        .header { text-align: center; padding: 20px; border-bottom: 3px solid <?php echo $primaryColor; ?>; margin-bottom: 20px; }
        .header h1 { color: <?php echo $primaryColor; ?>; font-size: 24px; margin: 0; }
        .header p { color: #64748b; margin: 5px 0; }
        .staff-info { background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .staff-info .info-row { display: flex; flex-wrap: wrap; }
        .staff-info .info-item { flex: 1 1 200px; padding: 5px; }
        .staff-info .label { font-weight: bold; color: <?php echo $primaryColor; ?>; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th { background: <?php echo $primaryColor; ?>; color: white; padding: 10px; text-align: left; }
        td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
        .score-table th { background: #64748b; }
        .total-row { background: <?php echo $primaryColor; ?>; color: white; font-weight: bold; }
        .grade-box { display: inline-block; padding: 10px 20px; border-radius: 8px; font-weight: bold; font-size: 18px; }
        .grade-O { background: #10b981; color: white; }
        .grade-E { background: #269c16; color: white; }
        .grade-VG { background: #06b6d4; color: white; }
        .grade-G { background: #f59e0b; color: white; }
        .grade-F { background: #ef4444; color: white; }
        @media print { body { -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="header">
        <?php if (!empty($logo)): ?>
        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" style="max-height: 60px; margin-bottom: 10px;">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($instName); ?></h1>
        <?php if (!empty($instAddress)): ?>
        <p><?php echo htmlspecialchars($instAddress); ?></p>
        <?php endif; ?>
        <p>Performance Evaluation Report</p>
    </div>

    <div class="staff-info">
        <h4 style="margin-bottom: 15px; color: <?php echo $primaryColor; ?>;">Staff Information</h4>
        <div class="info-row">
            <div class="info-item"><span class="label">Staff ID:</span> <?php echo htmlspecialchars($eval['staff_id']); ?></div>
            <div class="info-item"><span class="label">Name:</span> <?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></div>
            <div class="info-item"><span class="label">Email:</span> <?php echo htmlspecialchars($eval['email']); ?></div>
            <div class="info-item"><span class="label">Department:</span> <?php echo htmlspecialchars($eval['department']); ?></div>
            <div class="info-item"><span class="label">Faculty:</span> <?php echo htmlspecialchars($eval['faculty'] ?? 'N/A'); ?></div>
            <div class="info-item"><span class="label">Designation:</span> <?php echo htmlspecialchars($eval['designation'] ?? 'N/A'); ?></div>
            <div class="info-item"><span class="label">Grade Level:</span> <?php echo htmlspecialchars($eval['grade_level'] ?? 'N/A'); ?></div>
            <div class="info-item"><span class="label">Session/Year:</span> <?php echo htmlspecialchars($sessionName . ' / ' . $eval['evaluation_year']); ?></div>
        </div>
    </div>

    <table class="score-table">
        <tr><th colspan="7">Teaching Performance (6 questions)</th></tr>
        <tr>
            <td><strong>1.</strong> <?php echo $eval['teaching_1']; ?>/5</td>
            <td><strong>2.</strong> <?php echo $eval['teaching_2']; ?>/5</td>
            <td><strong>3.</strong> <?php echo $eval['teaching_3']; ?>/5</td>
            <td><strong>4.</strong> <?php echo $eval['teaching_4']; ?>/5</td>
            <td><strong>5.</strong> <?php echo $eval['teaching_5']; ?>/5</td>
            <td><strong>6.</strong> <?php echo $eval['teaching_6']; ?>/5</td>
            <td><strong>Subtotal:</strong> <?php echo $eval['teaching_1']+$eval['teaching_2']+$eval['teaching_3']+$eval['teaching_4']+$eval['teaching_5']+$eval['teaching_6']; ?>/30</td>
        </tr>
    </table>

    <table class="score-table">
        <tr><th colspan="6">Research Performance (5 questions)</th></tr>
        <tr>
            <td><strong>1.</strong> <?php echo $eval['research_1']; ?>/5</td>
            <td><strong>2.</strong> <?php echo $eval['research_2']; ?>/5</td>
            <td><strong>3.</strong> <?php echo $eval['research_3']; ?>/5</td>
            <td><strong>4.</strong> <?php echo $eval['research_4']; ?>/5</td>
            <td><strong>5.</strong> <?php echo $eval['research_5']; ?>/5</td>
            <td><strong>Subtotal:</strong> <?php echo $eval['research_1']+$eval['research_2']+$eval['research_3']+$eval['research_4']+$eval['research_5']; ?>/25</td>
        </tr>
    </table>

    <table class="score-table">
        <tr><th colspan="6">Administrative Duties (5 questions)</th></tr>
        <tr>
            <td><strong>1.</strong> <?php echo $eval['admin_1']; ?>/5</td>
            <td><strong>2.</strong> <?php echo $eval['admin_2']; ?>/5</td>
            <td><strong>3.</strong> <?php echo $eval['admin_3']; ?>/5</td>
            <td><strong>4.</strong> <?php echo $eval['admin_4']; ?>/5</td>
            <td><strong>5.</strong> <?php echo $eval['admin_5']; ?>/5</td>
            <td><strong>Subtotal:</strong> <?php echo $eval['admin_1']+$eval['admin_2']+$eval['admin_3']+$eval['admin_4']+$eval['admin_5']; ?>/25</td>
        </tr>
    </table>

    <table class="score-table">
        <tr><th colspan="4">Community Service (3 questions)</th></tr>
        <tr>
            <td><strong>1.</strong> <?php echo $eval['community_1']; ?>/5</td>
            <td><strong>2.</strong> <?php echo $eval['community_2']; ?>/5</td>
            <td><strong>3.</strong> <?php echo $eval['community_3']; ?>/5</td>
            <td><strong>Subtotal:</strong> <?php echo $eval['community_1']+$eval['community_2']+$eval['community_3']; ?>/15</td>
        </tr>
    </table>

    <table class="score-table">
        <tr><th colspan="5">Professional Development (4 questions)</th></tr>
        <tr>
            <td><strong>1.</strong> <?php echo $eval['professional_1']; ?>/5</td>
            <td><strong>2.</strong> <?php echo $eval['professional_2']; ?>/5</td>
            <td><strong>3.</strong> <?php echo $eval['professional_3']; ?>/5</td>
            <td><strong>4.</strong> <?php echo $eval['professional_4']; ?>/5</td>
            <td><strong>Subtotal:</strong> <?php echo $eval['professional_1']+$eval['professional_2']+$eval['professional_3']+$eval['professional_4']; ?>/20</td>
        </tr>
    </table>

    <table>
        <tr class="total-row">
            <td colspan="6" style="text-align: right; padding: 15px;">TOTAL SCORE: <?php echo $eval['total_score']; ?>/115</td>
        </tr>
        <tr style="background: #f0f9ff;">
            <td colspan="6" style="text-align: right; padding: 15px; font-size: 16px;">PERCENTAGE: <?php echo $eval['percentage']; ?>%</td>
        </tr>
    </table>

    <div style="text-align: center; margin: 20px 0;">
        <span class="grade-box grade-<?php echo strtok($eval['performance_grade'], ' '); ?>">GRADE: <?php echo $eval['performance_grade']; ?></span>
    </div>

    <?php if ($eval['supervisor_name']): ?>
    <div class="staff-info">
        <h4 style="color: <?php echo $primaryColor; ?>;">Supervisor Assessment</h4>
        <p><strong>Supervisor:</strong> <?php echo htmlspecialchars($eval['supervisor_name']); ?> (<?php echo htmlspecialchars($eval['supervisor_designation'] ?? ''); ?>)</p>
        <p><strong>Rating:</strong> <?php echo htmlspecialchars($eval['overall_rating'] ?? 'N/A'); ?></p>
        <p><strong>Recommendation:</strong> <?php echo htmlspecialchars($eval['recommendation'] ?? 'N/A'); ?></p>
        <p><strong>Remarks:</strong> <?php echo htmlspecialchars($eval['supervisor_remarks'] ?? ''); ?></p>
    </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #64748b;">
        <p><?php echo htmlspecialchars($instName); ?> - Annual Performance Evaluation System</p>
        <p>Generated on <?php echo date('F j, Y'); ?></p>
    </div>

    <script>window.onload = function() { window.print(); }</script>
</body>
</html>
    <?php
    exit;
}

// Handle Enhanced Individual PDF Export - A4 with Background Logo
if (isset($_GET['individual_pdf']) && $_GET['individual_pdf'] && hasPermission('reports_pdf')) {
    $evalId = intval($_GET['individual_pdf']);
    $stmt = $pdo->prepare("SELECT e.*, s.staff_id, s.surname, s.first_name, s.email, s.phone, s.department, s.faculty, s.designation, s.grade_level, s.employment_status, s.years_of_service
        FROM evaluations e JOIN staff s ON e.staff_id = s.id WHERE e.id = ?");
    $stmt->execute([$evalId]);
    $eval = $stmt->fetch();

    if (!$eval) {
        die("Evaluation not found");
    }

    // Get institution settings
    $stmt = $pdo->query("SELECT * FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $instName = $settings['institution_name'] ?? 'Institution';
    $instAddress = $settings['institution_address'] ?? '';
    $logo = $settings['institution_logo'] ?? '';
    $primaryColor = $settings['primary_color'] ?? '#308a1e';
    $secondaryColor = $settings['secondary_color'] ?? '#269c16';

    $sessionName = '';
    if ($eval['academic_session_id']) {
        $s = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE id = ?");
        $s->execute([$eval['academic_session_id']]);
        $sessionName = $s->fetch()['session_name'] ?? '';
    }

    // Calculate subtotals
    $teachingTotal = $eval['teaching_1'] + $eval['teaching_2'] + $eval['teaching_3'] + $eval['teaching_4'] + $eval['teaching_5'] + $eval['teaching_6'];
    $researchTotal = $eval['research_1'] + $eval['research_2'] + $eval['research_3'] + $eval['research_4'] + $eval['research_5'];
    $adminTotal = $eval['admin_1'] + $eval['admin_2'] + $eval['admin_3'] + $eval['admin_4'] + $eval['admin_5'];
    $communityTotal = $eval['community_1'] + $eval['community_2'] + $eval['community_3'];
    $professionalTotal = $eval['professional_1'] + $eval['professional_2'] + $eval['professional_3'] + $eval['professional_4'];
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Individual Evaluation Report - <?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            padding: 0;
            margin: 0;
            background-color: #fff;
            background-image: <?php if (!empty($logo)): ?>url('<?php echo htmlspecialchars($logo); ?>')<?php else: ?>none<?php endif; ?>;
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 60% auto;
            opacity: 0.15;
        }
        .page-container {
            position: relative;
            min-height: 297mm;
            padding: 15mm;
            background: rgba(255, 255, 255, 0.95);
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 15px;
            border-bottom: 3px solid <?php echo $primaryColor; ?>;
            margin-bottom: 20px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-left img {
            max-height: 50px;
            width: auto;
        }
        .header-text h1 {
            color: <?php echo $primaryColor; ?>;
            font-size: 20px;
            margin: 0;
            font-weight: bold;
        }
        .header-text p {
            color: #64748b;
            margin: 3px 0 0 0;
            font-size: 11px;
        }
        .header-right {
            text-align: right;
            color: <?php echo $primaryColor; ?>;
            font-size: 10px;
        }
        .report-title {
            text-align: center;
            background: <?php echo $primaryColor; ?>;
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .staff-info {
            background: #f8fafc;
            border: 1px solid <?php echo $primaryColor; ?>;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .staff-info h4 {
            color: <?php echo $primaryColor; ?>;
            font-size: 13px;
            margin: 0 0 10px 0;
            border-bottom: 1px solid <?php echo $secondaryColor; ?>;
            padding-bottom: 5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }
        .info-item {
            font-size: 10px;
        }
        .info-item .label {
            font-weight: bold;
            color: <?php echo $primaryColor; ?>;
        }
        .section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .section-title {
            background: <?php echo $secondaryColor; ?>;
            color: white;
            padding: 8px 12px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .question-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .question-table th {
            background: <?php echo $primaryColor; ?>;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        .question-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .question-table tr:nth-child(even) {
            background: #f8fafc;
        }
        .score-cell {
            text-align: center;
            font-weight: bold;
            color: <?php echo $primaryColor; ?>;
        }
        .subtotal-row {
            background: <?php echo $primaryColor; ?> !important;
            color: white !important;
            font-weight: bold;
        }
        .subtotal-row td {
            border-bottom: none;
        }
        .summary-box {
            background: linear-gradient(135deg, <?php echo $primaryColor; ?>, <?php echo $secondaryColor; ?>);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .summary-item {
            padding: 10px;
        }
        .summary-item .value {
            font-size: 24px;
            font-weight: bold;
        }
        .summary-item .label {
            font-size: 10px;
            opacity: 0.9;
        }
        .grade-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 16px;
        }
        .grade-O { background: #10b981; color: white; }
        .grade-E { background: #269c16; color: white; }
        .grade-VG { background: #06b6d4; color: white; }
        .grade-G { background: #f59e0b; color: white; }
        .grade-F { background: #ef4444; color: white; }
        .supervisor-section {
            background: #fffbeb;
            border: 1px solid #f59e0b;
            padding: 12px;
            border-radius: 5px;
            margin-top: 15px;
        }
        .supervisor-section h4 {
            color: #b45309;
            font-size: 12px;
            margin: 0 0 8px 0;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #64748b;
            font-size: 9px;
        }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page-container { background: white; }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Header with Logo, Name and Address -->
        <div class="header">
            <div class="header-left">
                <?php if (!empty($logo)): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>" alt="Institution Logo">
                <?php endif; ?>
                <div class="header-text">
                    <h1><?php echo htmlspecialchars($instName); ?></h1>
                    <?php if (!empty($instAddress)): ?>
                    <p><?php echo htmlspecialchars($instAddress); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-right">
                <p>Academic Year: <?php echo htmlspecialchars($sessionName . ' ' . $eval['evaluation_year']); ?></p>
                <p>Generated: <?php echo date('d F Y'); ?></p>
            </div>
        </div>

        <!-- Report Title -->
        <div class="report-title">
            <i class="fas fa-user-tie"></i> ANNUAL PERFORMANCE EVALUATION REPORT
        </div>

        <!-- Staff Information -->
        <div class="staff-info">
            <h4><i class="fas fa-user"></i> Staff Information</h4>
            <div class="info-grid">
                <div class="info-item"><span class="label">Staff ID:</span> <?php echo htmlspecialchars($eval['staff_id']); ?></div>
                <div class="info-item"><span class="label">Full Name:</span> <?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></div>
                <div class="info-item"><span class="label">Email:</span> <?php echo htmlspecialchars($eval['email']); ?></div>
                <div class="info-item"><span class="label">Phone:</span> <?php echo htmlspecialchars($eval['phone'] ?? 'N/A'); ?></div>
                <div class="info-item"><span class="label">Department:</span> <?php echo htmlspecialchars($eval['department']); ?></div>
                <div class="info-item"><span class="label">Faculty:</span> <?php echo htmlspecialchars($eval['faculty'] ?? 'N/A'); ?></div>
                <div class="info-item"><span class="label">Designation:</span> <?php echo htmlspecialchars($eval['designation'] ?? 'N/A'); ?></div>
                <div class="info-item"><span class="label">Grade Level:</span> <?php echo htmlspecialchars($eval['grade_level'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <!-- Teaching Performance -->
        <div class="section">
            <div class="section-title"><i class="fas fa-chalkboard-teacher"></i> TEACHING PERFORMANCE (30 marks)</div>
            <table class="question-table">
                <tr>
                    <th>#</th>
                    <th>Criterion</th>
                    <th>Score</th>
                    <th>Rating</th>
                </tr>
                <tr><td>1</td><td>Teaching Quality & Methodology</td><td class="score-cell"><?php echo $eval['teaching_1']; ?>/5</td><td><?php echo getRating($eval['teaching_1']); ?></td></tr>
                <tr><td>2</td><td>Course Preparation & Materials</td><td class="score-cell"><?php echo $eval['teaching_2']; ?>/5</td><td><?php echo getRating($eval['teaching_2']); ?></td></tr>
                <tr><td>3</td><td>Student Interaction & Support</td><td class="score-cell"><?php echo $eval['teaching_3']; ?>/5</td><td><?php echo getRating($eval['teaching_3']); ?></td></tr>
                <tr><td>4</td><td>Assessment & Feedback</td><td class="score-cell"><?php echo $eval['teaching_4']; ?>/5</td><td><?php echo getRating($eval['teaching_4']); ?></td></tr>
                <tr><td>5</td><td>Innovation in Teaching</td><td class="score-cell"><?php echo $eval['teaching_5']; ?>/5</td><td><?php echo getRating($eval['teaching_5']); ?></td></tr>
                <tr><td>6</td><td>Knowledge of Subject</td><td class="score-cell"><?php echo $eval['teaching_6']; ?>/5</td><td><?php echo getRating($eval['teaching_6']); ?></td></tr>
                <tr class="subtotal-row"><td colspan="2">SUBTOTAL</td><td class="score-cell"><?php echo $teachingTotal; ?>/30</td><td></td></tr>
            </table>
        </div>

        <!-- Research Performance -->
        <div class="section">
            <div class="section-title"><i class="fas fa-search"></i> RESEARCH PERFORMANCE (25 marks)</div>
            <table class="question-table">
                <tr>
                    <th>#</th>
                    <th>Criterion</th>
                    <th>Score</th>
                    <th>Rating</th>
                </tr>
                <tr><td>1</td><td>Research Output & Publications</td><td class="score-cell"><?php echo $eval['research_1']; ?>/5</td><td><?php echo getRating($eval['research_1']); ?></td></tr>
                <tr><td>2</td><td>Research Grants & Funding</td><td class="score-cell"><?php echo $eval['research_2']; ?>/5</td><td><?php echo getRating($eval['research_2']); ?></td></tr>
                <tr><td>3</td><td>Conference Presentations</td><td class="score-cell"><?php echo $eval['research_3']; ?>/5</td><td><?php echo getRating($eval['research_3']); ?></td></tr>
                <tr><td>4</td><td>Research Collaboration</td><td class="score-cell"><?php echo $eval['research_4']; ?>/5</td><td><?php echo getRating($eval['research_4']); ?></td></tr>
                <tr><td>5</td><td>Student Research Supervision</td><td class="score-cell"><?php echo $eval['research_5']; ?>/5</td><td><?php echo getRating($eval['research_5']); ?></td></tr>
                <tr class="subtotal-row"><td colspan="2">SUBTOTAL</td><td class="score-cell"><?php echo $researchTotal; ?>/25</td><td></td></tr>
            </table>
        </div>

        <!-- Administrative Duties -->
        <div class="section">
            <div class="section-title"><i class="fas fa-tasks"></i> ADMINISTRATIVE DUTIES (25 marks)</div>
            <table class="question-table">
                <tr>
                    <th>#</th>
                    <th>Criterion</th>
                    <th>Score</th>
                    <th>Rating</th>
                </tr>
                <tr><td>1</td><td>Committee & Administrative Tasks</td><td class="score-cell"><?php echo $eval['admin_1']; ?>/5</td><td><?php echo getRating($eval['admin_1']); ?></td></tr>
                <tr><td>2</td><td>Departmental Responsibilities</td><td class="score-cell"><?php echo $eval['admin_2']; ?>/5</td><td><?php echo getRating($eval['admin_2']); ?></td></tr>
                <tr><td>3</td><td>Attendance & Punctuality</td><td class="score-cell"><?php echo $eval['admin_3']; ?>/5</td><td><?php echo getRating($eval['admin_3']); ?></td></tr>
                <tr><td>4</td><td>Compliance with Policies</td><td class="score-cell"><?php echo $eval['admin_4']; ?>/5</td><td><?php echo getRating($eval['admin_4']); ?></td></tr>
                <tr><td>5</td><td>Team Work & Collaboration</td><td class="score-cell"><?php echo $eval['admin_5']; ?>/5</td><td><?php echo getRating($eval['admin_5']); ?></td></tr>
                <tr class="subtotal-row"><td colspan="2">SUBTOTAL</td><td class="score-cell"><?php echo $adminTotal; ?>/25</td><td></td></tr>
            </table>
        </div>

        <!-- Community Service -->
        <div class="section">
            <div class="section-title"><i class="fas fa-hands-helping"></i> COMMUNITY SERVICE (15 marks)</div>
            <table class="question-table">
                <tr>
                    <th>#</th>
                    <th>Criterion</th>
                    <th>Score</th>
                    <th>Rating</th>
                </tr>
                <tr><td>1</td><td>Community Outreach</td><td class="score-cell"><?php echo $eval['community_1']; ?>/5</td><td><?php echo getRating($eval['community_1']); ?></td></tr>
                <tr><td>2</td><td>Public Service & Engagement</td><td class="score-cell"><?php echo $eval['community_2']; ?>/5</td><td><?php echo getRating($eval['community_2']); ?></td></tr>
                <tr><td>3</td><td>Extension Services</td><td class="score-cell"><?php echo $eval['community_3']; ?>/5</td><td><?php echo getRating($eval['community_3']); ?></td></tr>
                <tr class="subtotal-row"><td colspan="2">SUBTOTAL</td><td class="score-cell"><?php echo $communityTotal; ?>/15</td><td></td></tr>
            </table>
        </div>

        <!-- Professional Development -->
        <div class="section">
            <div class="section-title"><i class="fas fa-certificate"></i> PROFESSIONAL DEVELOPMENT (20 marks)</div>
            <table class="question-table">
                <tr>
                    <th>#</th>
                    <th>Criterion</th>
                    <th>Score</th>
                    <th>Rating</th>
                </tr>
                <tr><td>1</td><td>Training & Capacity Building</td><td class="score-cell"><?php echo $eval['professional_1']; ?>/5</td><td><?php echo getRating($eval['professional_1']); ?></td></tr>
                <tr><td>2</td><td>Professional Certifications</td><td class="score-cell"><?php echo $eval['professional_2']; ?>/5</td><td><?php echo getRating($eval['professional_2']); ?></td></tr>
                <tr><td>3</td><td>Membership in Professional Bodies</td><td class="score-cell"><?php echo $eval['professional_3']; ?>/5</td><td><?php echo getRating($eval['professional_3']); ?></td></tr>
                <tr><td>4</td><td>Career Progression & Growth</td><td class="score-cell"><?php echo $eval['professional_4']; ?>/5</td><td><?php echo getRating($eval['professional_4']); ?></td></tr>
                <tr class="subtotal-row"><td colspan="2">SUBTOTAL</td><td class="score-cell"><?php echo $professionalTotal; ?>/20</td><td></td></tr>
            </table>
        </div>

        <!-- Summary Box -->
        <div class="summary-box">
            <div class="summary-item">
                <div class="value"><?php echo $eval['total_score']; ?>/115</div>
                <div class="label">TOTAL SCORE</div>
            </div>
            <div class="summary-item">
                <div class="value"><?php echo $eval['percentage']; ?>%</div>
                <div class="label">PERCENTAGE</div>
            </div>
            <div class="summary-item">
                <div class="grade-badge grade-<?php echo strtok($eval['performance_grade'], ' '); ?>"><?php echo $eval['performance_grade']; ?></div>
                <div class="label">GRADE</div>
            </div>
        </div>

        <!-- Supervisor Assessment -->
        <?php if ($eval['supervisor_name']): ?>
        <div class="supervisor-section">
            <h4><i class="fas fa-user-check"></i> Supervisor Assessment</h4>
            <div class="info-grid">
                <div class="info-item"><span class="label">Supervisor:</span> <?php echo htmlspecialchars($eval['supervisor_name']); ?></div>
                <div class="info-item"><span class="label">Designation:</span> <?php echo htmlspecialchars($eval['supervisor_designation'] ?? 'N/A'); ?></div>
                <div class="info-item"><span class="label">Rating:</span> <?php echo htmlspecialchars($eval['overall_rating'] ?? 'N/A'); ?></div>
                <div class="info-item"><span class="label">Recommendation:</span> <?php echo htmlspecialchars($eval['recommendation'] ?? 'N/A'); ?></div>
            </div>
            <?php if (!empty($eval['supervisor_remarks'])): ?>
            <div style="margin-top: 10px;">
                <span class="label">Remarks:</span> <?php echo htmlspecialchars($eval['supervisor_remarks']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <p><?php echo htmlspecialchars($instName); ?> - Annual Performance Evaluation System</p>
            <p>This document is officially generated. Signature and stamp required for authentication.</p>
        </div>
    </div>

    <script>window.onload = function() { window.print(); }</script>
</body>
</html>
    <?php
    exit;
}

// Helper function for ratings
function getRating($score) {
    if ($score >= 5) return 'Outstanding';
    if ($score >= 4) return 'Excellent';
    if ($score >= 3) return 'Very Good';
    if ($score >= 2) return 'Good';
    if ($score >= 1) return 'Fair';
    return 'Poor';
}

// Handle PDF Export - Comprehensive with ALL FIELDS (Browser Print-to-PDF)
if (isset($_GET['pdf']) && $_GET['pdf'] && hasPermission('reports_pdf')) {
    // Get institution settings
    $stmt = $pdo->query("SELECT * FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Get active session
    $stmt = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1 LIMIT 1");
    $activeSession = $stmt->fetch();

    $instName = $settings['institution_name'] ?? 'Institution';
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Evaluation Report - <?php echo date('Y-m-d'); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; line-height: 1.4; }
        .header { text-align: center; padding: 15px; border-bottom: 3px solid #308a1e; margin-bottom: 20px; }
        .header h1 { color: #308a1e; font-size: 24px; margin: 0; }
        .header p { color: #64748b; margin: 5px 0 0 0; }
        .summary { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .summary-box { background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; flex: 1; margin: 0 5px; border: 1px solid #e2e8f0; }
        .summary-box h3 { color: #308a1e; font-size: 22px; margin: 0; }
        .summary-box p { color: #64748b; margin: 0; font-size: 11px; }

        .section-title { background: #308a1e; color: white; padding: 10px 15px; font-weight: bold; margin: 25px 0 15px 0; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10px; }
        th { background: #269c16; color: white; padding: 8px 6px; text-align: left; font-weight: bold; }
        td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        tr:hover { background: #f1f5f9; }

        .grade-Outstanding { color: #10b981; font-weight: bold; }
        .grade-Excellent { color: #269c16; font-weight: bold; }
        .grade-Very { color: #06b6d4; font-weight: bold; }
        .grade-Good { color: #f59e0b; font-weight: bold; }
        .grade-Fair { color: #ef4444; font-weight: bold; }
        .grade-Poor { color: #dc2626; font-weight: bold; }

        .detail-card { border: 2px solid #308a1e; border-radius: 8px; margin-bottom: 20px; page-break-inside: avoid; }
        .detail-header { background: #308a1e; color: white; padding: 10px 15px; font-weight: bold; }
        .detail-body { padding: 15px; }
        .detail-row { display: flex; margin-bottom: 8px; }
        .detail-label { font-weight: bold; width: 150px; color: #308a1e; }
        .detail-value { flex: 1; }

        .scores-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .scores-table th { background: #64748b; font-size: 9px; padding: 5px; }
        .scores-table td { text-align: center; padding: 4px; border: 1px solid #e2e8f0; }

        .supervisor-section { background: #f0f9ff; padding: 10px; border-radius: 5px; margin-top: 10px; }
        .supervisor-title { font-weight: bold; color: #0369a1; margin-bottom: 5px; }

        .footer { text-align: center; color: #64748b; font-size: 10px; margin-top: 25px; padding-top: 15px; border-top: 2px solid #e2e8f0; }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page-break { page-break-before: always; }
            .no-print { display: none; }
            .detail-card { page-break-inside: avoid; }
        }

        .no-print { background: #fef3c7; border: 2px solid #f59e0b; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .no-print h3 { color: #92400e; margin: 0 0 10px 0; }
        .no-print button { background: #308a1e; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .no-print button:hover { background: #269c16; }
    </style>
</head>
<body>
    <div class="no-print">
        <h3><i class="fas fa-print"></i> Print to PDF</h3>
        <p style="margin-bottom: 15px;">Click the button below to save this report as PDF using your browser's print function.</p>
        <button onclick="window.print()"><i class="fas fa-print"></i> Print / Save as PDF</button>
    </div>

    <div class="header">
        <h1><?php echo htmlspecialchars($instName); ?></h1>
        <p>Annual Performance Evaluation Report</p>
        <p><?php echo htmlspecialchars($activeSession['session_name'] ?? ''); ?> Semester | Generated: <?php echo date('F j, Y'); ?></p>
    </div>

    <div class="summary">
        <div class="summary-box">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total Evaluations</p>
        </div>
        <div class="summary-box">
            <h3><?php echo number_format($stats['avg_percentage'], 1); ?>%</h3>
            <p>Average Score</p>
        </div>
        <div class="summary-box">
            <h3><?php echo $stats['approved']; ?></h3>
            <p>Approved</p>
        </div>
        <div class="summary-box">
            <h3><?php echo $stats['submitted']; ?></h3>
            <p>Submitted</p>
        </div>
        <div class="summary-box">
            <h3><?php echo $stats['draft']; ?></h3>
            <p>Draft</p>
        </div>
    </div>

    <!-- Main Summary Table with ALL FIELDS -->
    <div class="section-title">Staff Evaluation Summary (All Fields)</div>
    <table>
        <thead>
            <tr>
                <th>Staff ID</th>
                <th>Surname</th>
                <th>First Name</th>
                <th>Email</th>
                <th>Department</th>
                <th>Faculty</th>
                <th>Designation</th>
                <th>Grade Level</th>
                <th>Employment</th>
                <th>Years</th>
                <th>Year</th>
                <th>Total</th>
                <th>%</th>
                <th>Grade</th>
                <th>Status</th>
                <th>Rating</th>
                <th>Recommendation</th>
                <th>Submitted</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($evaluations as $eval):
                $gradeClass = 'grade-' . strtok($eval['performance_grade'], ' ');
            ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($eval['staff_id']); ?></strong></td>
                <td><?php echo htmlspecialchars($eval['surname']); ?></td>
                <td><?php echo htmlspecialchars($eval['first_name']); ?></td>
                <td><?php echo htmlspecialchars($eval['email']); ?></td>
                <td><?php echo htmlspecialchars($eval['department']); ?></td>
                <td><?php echo htmlspecialchars($eval['faculty'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($eval['designation'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($eval['grade_level'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($eval['employment_status'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($eval['years_of_service'] ?? '0'); ?></td>
                <td><?php echo $eval['evaluation_year']; ?></td>
                <td><strong><?php echo $eval['total_score']; ?>/115</strong></td>
                <td><?php echo $eval['percentage']; ?>%</td>
                <td class="<?php echo $gradeClass; ?>"><?php echo $eval['performance_grade']; ?></td>
                <td><?php echo ucfirst($eval['status']); ?></td>
                <td><?php echo htmlspecialchars($eval['overall_rating'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($eval['recommendation'] ?? '-'); ?></td>
                <td><?php echo date('Y-m-d', strtotime($eval['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Detailed Section -->
    <div class="section-title">Detailed Evaluation Reports</div>

    <?php foreach ($evaluations as $eval):
        $sessionName = '';
        if ($eval['academic_session_id']) {
            $s = $pdo->prepare("SELECT session_name FROM academic_sessions WHERE id = ?");
            $s->execute([$eval['academic_session_id']]);
            $sessionName = $s->fetch()['session_name'] ?? '';
        }
    ?>
    <div class="detail-card">
        <div class="detail-header">
            Staff: <?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?> (<?php echo htmlspecialchars($eval['staff_id']); ?>)
        </div>
        <div class="detail-body">
            <div class="detail-row">
                <span class="detail-label">Department:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eval['department']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Faculty:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eval['faculty'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Designation:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eval['designation'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Grade Level:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eval['grade_level'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Employment Status:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eval['employment_status'] ?? 'N/A'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Years of Service:</span>
                <span class="detail-value"><?php echo htmlspecialchars($eval['years_of_service'] ?? '0'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Session/Year:</span>
                <span class="detail-value"><?php echo htmlspecialchars($sessionName); ?> / <?php echo $eval['evaluation_year']; ?></span>
            </div>

            <!-- Scores Table -->
            <table class="scores-table">
                <tr>
                    <th colspan="7">Teaching Performance (6 questions)</th>
                </tr>
                <tr>
                    <td><strong>1</strong><br><?php echo $eval['teaching_1']; ?></td>
                    <td><strong>2</strong><br><?php echo $eval['teaching_2']; ?></td>
                    <td><strong>3</strong><br><?php echo $eval['teaching_3']; ?></td>
                    <td><strong>4</strong><br><?php echo $eval['teaching_4']; ?></td>
                    <td><strong>5</strong><br><?php echo $eval['teaching_5']; ?></td>
                    <td><strong>6</strong><br><?php echo $eval['teaching_6']; ?></td>
                    <td><strong>Subtotal</strong><br><?php echo $eval['teaching_1']+$eval['teaching_2']+$eval['teaching_3']+$eval['teaching_4']+$eval['teaching_5']+$eval['teaching_6']; ?>/30</td>
                </tr>
                <tr>
                    <th colspan="7">Research Performance (5 questions)</th>
                </tr>
                <tr>
                    <td><strong>1</strong><br><?php echo $eval['research_1']; ?></td>
                    <td><strong>2</strong><br><?php echo $eval['research_2']; ?></td>
                    <td><strong>3</strong><br><?php echo $eval['research_3']; ?></td>
                    <td><strong>4</strong><br><?php echo $eval['research_4']; ?></td>
                    <td><strong>5</strong><br><?php echo $eval['research_5']; ?></td>
                    <td></td>
                    <td><strong>Subtotal</strong><br><?php echo $eval['research_1']+$eval['research_2']+$eval['research_3']+$eval['research_4']+$eval['research_5']; ?>/25</td>
                </tr>
                <tr>
                    <th colspan="7">Administrative Duties (5 questions)</th>
                </tr>
                <tr>
                    <td><strong>1</strong><br><?php echo $eval['admin_1']; ?></td>
                    <td><strong>2</strong><br><?php echo $eval['admin_2']; ?></td>
                    <td><strong>3</strong><br><?php echo $eval['admin_3']; ?></td>
                    <td><strong>4</strong><br><?php echo $eval['admin_4']; ?></td>
                    <td><strong>5</strong><br><?php echo $eval['admin_5']; ?></td>
                    <td></td>
                    <td><strong>Subtotal</strong><br><?php echo $eval['admin_1']+$eval['admin_2']+$eval['admin_3']+$eval['admin_4']+$eval['admin_5']; ?>/25</td>
                </tr>
                <tr>
                    <th colspan="7">Community Service (3 questions)</th>
                </tr>
                <tr>
                    <td><strong>1</strong><br><?php echo $eval['community_1']; ?></td>
                    <td><strong>2</strong><br><?php echo $eval['community_2']; ?></td>
                    <td><strong>3</strong><br><?php echo $eval['community_3']; ?></td>
                    <td></td><td></td><td></td>
                    <td><strong>Subtotal</strong><br><?php echo $eval['community_1']+$eval['community_2']+$eval['community_3']; ?>/15</td>
                </tr>
                <tr>
                    <th colspan="7">Professional Development (4 questions)</th>
                </tr>
                <tr>
                    <td><strong>1</strong><br><?php echo $eval['professional_1']; ?></td>
                    <td><strong>2</strong><br><?php echo $eval['professional_2']; ?></td>
                    <td><strong>3</strong><br><?php echo $eval['professional_3']; ?></td>
                    <td><strong>4</strong><br><?php echo $eval['professional_4']; ?></td>
                    <td></td><td></td>
                    <td><strong>Subtotal</strong><br><?php echo $eval['professional_1']+$eval['professional_2']+$eval['professional_3']+$eval['professional_4']; ?>/20</td>
                </tr>
                <tr style="background: #e8f4fd;">
                    <td colspan="6" style="text-align: right;"><strong>TOTAL SCORE:</strong></td>
                    <td style="background: #308a1e; color: white;"><strong><?php echo $eval['total_score']; ?>/115</strong></td>
                </tr>
                <tr style="background: #e8f4fd;">
                    <td colspan="6" style="text-align: right;"><strong>PERCENTAGE:</strong></td>
                    <td style="background: #308a1e; color: white;"><strong><?php echo $eval['percentage']; ?>%</strong></td>
                </tr>
                <tr style="background: #e8f4fd;">
                    <td colspan="6" style="text-align: right;"><strong>GRADE:</strong></td>
                    <td style="background: #10b981; color: white;"><strong><?php echo $eval['performance_grade']; ?></strong></td>
                </tr>
            </table>

            <!-- Supervisor Assessment -->
            <?php if ($eval['supervisor_name'] || $eval['recommendation']): ?>
            <div class="supervisor-section">
                <div class="supervisor-title"><i class="fas fa-user-tie"></i> Supervisor Assessment</div>
                <div class="detail-row">
                    <span class="detail-label">Supervisor:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($eval['supervisor_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Designation:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($eval['supervisor_designation'] ?? ''); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Overall Rating:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($eval['overall_rating'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Recommendation:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($eval['recommendation'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Remarks:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($eval['supervisor_remarks'] ?? ''); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Management Approval -->
            <?php if ($eval['registrar_name'] || $eval['approval_status']): ?>
            <div class="supervisor-section" style="background: #f0fdf4;">
                <div class="supervisor-title" style="color: #166534;"><i class="fas fa-check-circle"></i> Management Approval</div>
                <div class="detail-row">
                    <span class="detail-label">Registrar:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($eval['registrar_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Approval Status:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($eval['approval_status'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Remarks:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($eval['registrar_remarks'] ?? ''); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="footer">
        <p><?php echo htmlspecialchars($instName); ?> - Annual Performance Evaluation System</p>
        <p>This report was automatically generated on <?php echo date('F j, Y g:i A'); ?></p>
    </div>

    <script>
        // Auto-print prompt after page loads
        window.onload = function() {
            // Uncomment the line below to auto-prompt print dialog
            // window.print();
        }
    </script>
</body>
</html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo htmlspecialchars($instName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-blue: #308a1e; }
        body { background: #f3f4f6; }
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #308a1e 0%, #269c16 100%); color: white; }
        .sidebar .sidebar-header h5 { color: #10b981 !important; font-weight: 700; }
        .sidebar .sidebar-header small { color: #10b981 !important; font-weight: 600; }
        .sidebar a { color: rgba(255,255,255,0.8); text-decoration: none; padding: 12px 15px; display: block; border-radius: 8px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.15); color: white; }
        .stat-box { background: white; padding: 1.5rem; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }

        /* Mobile Hamburger Menu */
        .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 10px; z-index: 1001; position: fixed; top: 10px; left: 10px; }
        .hamburger span { display: block; width: 25px; height: 3px; background: white; margin: 5px 0; border-radius: 2px; transition: 0.3s; }
        .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 6px); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -6px); }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 768px) {
            .hamburger { display: block; }
            .sidebar { position: fixed; left: -280px; top: 0; bottom: 0; width: 280px; z-index: 1000; transition: left 0.3s ease; overflow-y: auto; }
            .sidebar.active { left: 0; }
        }
    </style>
</head>
<body>
    <!-- Mobile Hamburger Menu -->
    <button class="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
    </button>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-3" id="sidebar">
                <div class="text-center sidebar-header" style="padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 10px;">
                    <?php if (!empty($logo)): ?>
                        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" style="max-height: 45px; margin-bottom: 8px; border: 2px solid white; border-radius: 6px; padding: 2px;">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                    <?php endif; ?>
                    <h5 class="mb-0" style="font-size: 1rem; font-weight: 700;"><?php echo htmlspecialchars($instName); ?></h5>
                    <?php if (!empty($instAddress)): ?>
                        <small class="d-block text-truncate" style="max-width: 150px; margin: 0 auto; font-size: 0.7rem;"><?php echo htmlspecialchars($instAddress); ?></small>
                    <?php endif; ?>
                </div>
                <div class="py-2">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="staff.php"><i class="fas fa-users"></i> Staff</a>
                    <a href="staff-upload.php"><i class="fas fa-upload"></i> Upload Staff</a>
                    <a href="manage-evaluators.php"><i class="fas fa-user-tie"></i> Evaluators</a>
                    <a href="questions.php"><i class="fas fa-question-circle"></i> Questions</a>
                    <a href="roles.php"><i class="fas fa-user-tag"></i> Staff Roles</a>
                    <a href="evaluate.php"><i class="fas fa-clipboard-check"></i> Evaluate</a>
                    <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
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
                    <h2><i class="fas fa-chart-bar me-2"></i>Evaluation Reports</h2>
                    <div class="btn-group">
                        <?php if (hasPermission('reports_export')): ?>
                        <a href="?export=1<?php echo $department ? '&department=' . $department : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $year ? '&year=' . $year : ''; ?>" class="btn btn-success" title="Export all filtered evaluations to Excel spreadsheet">
                            <i class="fas fa-file-excel me-2"></i>Export All (Excel)
                        </a>
                        <a href="?pdf=1<?php echo $department ? '&department=' . $department : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $year ? '&year=' . $year : ''; ?>" class="btn btn-danger" target="_blank" title="Export all filtered evaluations to PDF for printing">
                            <i class="fas fa-file-pdf me-2"></i>Export All (PDF)
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-2">
                        <div class="stat-box">
                            <div class="h2 mb-0 text-primary"><?php echo $stats['total']; ?></div>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-box">
                            <div class="h2 mb-0 text-success"><?php echo $stats['approved']; ?></div>
                            <small class="text-muted">Approved</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-box">
                            <div class="h2 mb-0 text-primary"><?php echo $stats['submitted']; ?></div>
                            <small class="text-muted">Submitted</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-box">
                            <div class="h2 mb-0 text-warning"><?php echo $stats['draft']; ?></div>
                            <small class="text-muted">Draft</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <div class="h2 mb-0 text-info"><?php echo number_format($stats['avg_percentage'], 1); ?>%</div>
                            <small class="text-muted">Average Score</small>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $department == $dept['department'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="draft" <?php echo $status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="submitted" <?php echo $status == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                    <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Year</label>
                                <select class="form-select" name="year">
                                    <option value="">All Years</option>
                                    <?php foreach ($years as $y): ?>
                                        <option value="<?php echo $y['evaluation_year']; ?>" <?php echo $year == $y['evaluation_year'] ? 'selected' : ''; ?>>
                                            <?php echo $y['evaluation_year']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter me-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Staff ID</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Grade Level</th>
                                        <th>Year</th>
                                        <th>Score</th>
                                        <th>Percentage</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($evaluations)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted py-4">
                                                No evaluations found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($evaluations as $eval): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($eval['staff_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($eval['first_name'] . ' ' . $eval['surname']); ?></td>
                                            <td><?php echo htmlspecialchars($eval['department']); ?></td>
                                            <td><?php echo htmlspecialchars($eval['grade_level']); ?></td>
                                            <td><?php echo $eval['evaluation_year']; ?></td>
                                            <td><?php echo $eval['total_score']; ?>/115</td>
                                            <td><?php echo $eval['percentage']; ?>%</td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    echo $eval['performance_grade'] == 'Outstanding' ? 'success' :
                                                        ($eval['performance_grade'] == 'Excellent' ? 'primary' :
                                                        ($eval['performance_grade'] == 'Very Good' ? 'info' :
                                                        ($eval['performance_grade'] == 'Good' ? 'warning' : 'danger')));
                                                ?>">
                                                    <?php echo $eval['performance_grade']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php
                                                    echo $eval['status'] == 'approved' ? 'success' :
                                                        ($eval['status'] == 'submitted' ? 'primary' :
                                                        ($eval['status'] == 'rejected' ? 'danger' : 'warning'));
                                                ?>">
                                                    <?php echo ucfirst($eval['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?single_pdf=<?php echo $eval['id']; ?>" class="btn btn-sm btn-info" target="_blank" title="Export Basic PDF - Simple format">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                                <a href="?individual_pdf=<?php echo $eval['id']; ?>" class="btn btn-sm btn-success" target="_blank" title="Export Individual Report (A4) - Full format with background logo">
                                                    <i class="fas fa-file-contract"></i>
                                                </a>
                                                <a href="evaluate.php?eval_id=<?php echo $eval['id']; ?>" class="btn btn-sm btn-primary" title="Edit this evaluation">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if (hasPermission('delete_evaluation')): ?>
                                                <a href="?delete=<?php echo $eval['id']; ?>" class="btn btn-sm btn-danger" title="Delete this evaluation permanently" onclick="return confirm('Delete this evaluation?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.hamburger').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }
    </script>

    <!-- Footer -->
    <footer class="mt-4 py-3" style="background: linear-gradient(180deg, <?php echo $primaryColor; ?> 0%, <?php echo $secondaryColor; ?> 100%); color: white; border-radius: 8px;">
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