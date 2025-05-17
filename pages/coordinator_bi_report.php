<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


$action = isset($_GET['action']) ? $_GET['action'] : 'view';


function getBiReportData($coordinator_id)
{
    $sql = "
        SELECT 
            u.id AS mentor_id,
            u.username,
            md.full_name,
            COUNT(tl.id) AS total_tutoring_logs,
            IFNULL(SUM(tl.tutoring_hours), 0) AS total_hours
        FROM coordinator_mentors cm
        JOIN users u ON u.id = cm.mentor_id
        LEFT JOIN mentor_details md ON md.mentor_id = u.id
        LEFT JOIN tutoring_logs tl ON tl.mentor_id = u.id
        WHERE cm.coordinator_id = ?
          AND u.role_id = 12
        GROUP BY u.id
        ORDER BY u.username
    ";

    return db_fetch_all($sql, 'i', [$coordinator_id]);
}


$reportData = getBiReportData($coordinator_id);


if ($action === 'csv') {

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bi_report.csv"');


    $output = fopen('php://output', 'w');


    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));


    fputcsv($output, ['מזהה מתגבר', 'שם משתמש', 'שם מלא', 'כמות רשומות תגבור', 'סה"כ שעות']);

    foreach ($reportData as $row) {

        $mentorId = filter_var($row['mentor_id'], FILTER_VALIDATE_INT) ? $row['mentor_id'] : 0;
        $username = !empty($row['username']) ? preg_replace('/[\r\n\t,"]/', ' ', $row['username']) : '';
        $fullName = !empty($row['full_name']) ? preg_replace('/[\r\n\t,"]/', ' ', $row['full_name']) : '';
        $logsCount = filter_var($row['total_tutoring_logs'], FILTER_VALIDATE_INT) ? $row['total_tutoring_logs'] : 0;
        $hoursSum = is_numeric($row['total_hours']) ? $row['total_hours'] : 0;

        fputcsv($output, [$mentorId, $username, $fullName, $logsCount, $hoursSum]);
    }
    fclose($output);
    exit();
} elseif ($action === 'pdf') {


    set_error_message("ייצוא PDF אינו זמין כעת. אנא השתמש בייצוא CSV.");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();

    /* 
    
    require_once __DIR__ . '/../vendor/fpdf/fpdf.php';
    
    class PDF extends FPDF {
        
        function Header() {
            
            $this->SetFont('Arial','B',15);
            
            $this->Cell(0,10,'דו"ח BI למתרגלים',0,1,'C');
            
            $this->Ln(5);
        }
        
        function Footer() {
            
            $this->SetY(-15);
            
            $this->SetFont('Arial','I',8);
            
            $this->Cell(0,10,'עמוד '.$this->PageNo(),0,0,'C');
        }
    }
    
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','',12);
    
    
    $pdf->Cell(30,10,'מזהה',1,0,'C');
    $pdf->Cell(40,10,'שם משתמש',1,0,'C');
    $pdf->Cell(40,10,'שם מלא',1,0,'C');
    $pdf->Cell(40,10,'כמות תגבורים',1,0,'C');
    $pdf->Cell(40,10,'סה"כ שעות',1,1,'C');
    
    
    foreach ($reportData as $row) {
        $mentorId   = $row['mentor_id'];
        $username   = !empty($row['username'])   ? $row['username']   : '';
        $fullName   = !empty($row['full_name'])  ? $row['full_name']  : '';
        $logsCount  = $row['total_tutoring_logs'];
        $hoursSum   = $row['total_hours'];
        
        $pdf->Cell(30,10, $mentorId, 1,0,'C');
        $pdf->Cell(40,10, iconv('UTF-8','windows-1252//IGNORE', $username), 1,0,'C');
        $pdf->Cell(40,10, iconv('UTF-8','windows-1252//IGNORE', $fullName), 1,0,'C');
        $pdf->Cell(40,10, $logsCount, 1,0,'C');
        $pdf->Cell(40,10, $hoursSum, 1,1,'C');
    }
    
    
    $pdf->Output('D','bi_report.pdf');
    exit();
    */
}


$page_title = 'דוחות BI';
$additional_css = ['reports.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>דוחות BI</h2>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">ייצוא דוחות</h3>

    <div class="action-buttons">
        <!-- כפתורים לייצוא CSV/PDF ולצפייה -->
        <a href="?action=csv" class="btn btn-primary">ייצא ל-CSV</a>
        <a href="?action=pdf" class="btn btn-primary">ייצא ל-PDF</a>
        <a href="?action=view" class="btn btn-secondary">רענן דוח</a>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>מזהה מתרגל</th>
                    <th>שם משתמש</th>
                    <th>שם מלא</th>
                    <th>כמות תגבורים</th>
                    <th>סה"כ שעות</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reportData)): ?>
                    <?php foreach ($reportData as $row):
                        $mentorId = $row['mentor_id'];
                        $username = !empty($row['username']) ? $row['username'] : '';
                        $fullName = !empty($row['full_name']) ? $row['full_name'] : '';
                        $logsCount = $row['total_tutoring_logs'];
                        $hoursSum = $row['total_hours'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mentorId); ?></td>
                            <td><?php echo htmlspecialchars($username); ?></td>
                            <td><?php echo htmlspecialchars($fullName); ?></td>
                            <td><?php echo htmlspecialchars($logsCount); ?></td>
                            <td><?php echo htmlspecialchars($hoursSum); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">לא נמצאו נתונים.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>