<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Direct access not allowed']);
    exit();
}


header('Content-Type: application/json');


if (!is_coordinator()) {
    echo json_encode(['success' => false, 'message' => 'אין לך הרשאה לצפות במידע זה']);
    exit();
}


$coordinator_id = get_current_user_id();


$mentor_id = isset($_GET['mentor_id']) ? intval($_GET['mentor_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';


if ($mentor_id <= 0 || $course_id <= 0 || empty($start_date) || empty($end_date)) {
    echo json_encode(['success' => false, 'message' => 'פרמטרים חסרים או לא תקינים']);
    exit();
}


$check_sql = "SELECT 1 FROM coordinator_mentors WHERE coordinator_id = ? AND mentor_id = ?";
$mentor_belongs = db_fetch_one($check_sql, 'ii', [$coordinator_id, $mentor_id]);

if (!$mentor_belongs) {
    echo json_encode(['success' => false, 'message' => 'המתגבר אינו שייך לרכז זה']);
    exit();
}


$details_sql = "SELECT 
                t.tutoring_date, 
                t.tutoring_hours, 
                t.student_names
               FROM tutoring_logs t
               WHERE t.mentor_id = ?
               AND t.course_id = ?
               AND t.tutoring_date BETWEEN ? AND ?
               ORDER BY t.tutoring_date DESC";

$details = db_fetch_all($details_sql, 'iiss', [$mentor_id, $course_id, $start_date, $end_date]);


if ($details !== false) {
    echo json_encode(['success' => true, 'data' => $details]);
} else {
    echo json_encode(['success' => false, 'message' => 'שגיאה בשליפת הנתונים: ' . db_error()]);
}
exit();