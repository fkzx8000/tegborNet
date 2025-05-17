<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_functions.php';
require_once __DIR__ . '/../includes/functions.php';


header('Content-Type: application/json; charset=utf-8');


$response = [
    'success' => false,
    'message' => '',
    'data' => null
];


if (!has_permission(['mentor', 'coordinator'])) {
    $response['message'] = "אין לך הרשאה לבצע פעולה זו";
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}


$current_user_id = get_current_user_id();


$action = isset($_GET['action']) ? $_GET['action'] : '';


switch ($action) {
    case 'add':

        if (!has_permission(['mentor', 'coordinator'])) {
            $response['message'] = "אין לך הרשאה להוסיף תגבור";
            break;
        }


        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $student_names = isset($_POST['student_names']) ? trim($_POST['student_names']) : '';
        $tutoring_hours = isset($_POST['tutoring_hours']) ? floatval($_POST['tutoring_hours']) : 0;
        $tutoring_date = isset($_POST['tutoring_date']) ? $_POST['tutoring_date'] : '';


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        if ($course_id <= 0 || empty($tutoring_date) || $tutoring_hours <= 0) {
            $response['message'] = "אנא מלא את כל השדות הנדרשים";
            break;
        }


        $formatted_date = date('Y-m-d', strtotime($tutoring_date));


        $sql = "INSERT INTO tutoring_logs (mentor_id, course_id, student_names, tutoring_hours, tutoring_date) 
                VALUES (?, ?, ?, ?, ?)";

        $result = db_execute($sql, 'iisds', [$current_user_id, $course_id, $student_names, $tutoring_hours, $formatted_date]);

        if ($result !== false) {
            $response['success'] = true;
            $response['message'] = "התגבור נרשם בהצלחה!";
        } else {
            $response['message'] = "שגיאה בשמירת התגבור: " . db_error();
        }
        break;

    case 'list':

        if (!has_permission(['mentor', 'coordinator'])) {
            $response['message'] = "אין לך הרשאה לצפות ברשימת תגבורים";
            break;
        }


        $month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
        $year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));


        $date_range = calculate_date_range($month, $year);


        $sql = "SELECT tl.id, tl.tutoring_date, tl.student_names, tl.tutoring_hours, c.course_name
                FROM tutoring_logs tl
                JOIN courses c ON tl.course_id = c.id
                WHERE tl.mentor_id = ?
                AND tl.tutoring_date BETWEEN ? AND ?
                ORDER BY tl.tutoring_date DESC";

        $logs = db_fetch_all($sql, 'iss', [$current_user_id, $date_range['start'], $date_range['end']]);

        if ($logs !== false) {
            $response['success'] = true;
            $response['data'] = $logs;
            $response['date_range'] = $date_range;
        } else {
            $response['message'] = "שגיאה בשליפת רשימת התגבורים: " . db_error();
        }
        break;

    case 'delete':

        if (!has_permission(['mentor', 'coordinator'])) {
            $response['message'] = "אין לך הרשאה למחוק תגבור";
            break;
        }


        $log_id = isset($_POST['log_id']) ? intval($_POST['log_id']) : 0;


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        if ($log_id <= 0) {
            $response['message'] = "מזהה תגבור לא תקין";
            break;
        }


        if (!is_coordinator()) {
            $check_sql = "SELECT id FROM tutoring_logs WHERE id = ? AND mentor_id = ?";
            $check_result = db_fetch_one($check_sql, 'ii', [$log_id, $current_user_id]);

            if (!$check_result) {
                $response['message'] = "אין לך הרשאה למחוק את התגבור הזה";
                break;
            }
        }


        $sql = "DELETE FROM tutoring_logs WHERE id = ?";
        $result = db_execute($sql, 'i', [$log_id]);

        if ($result !== false) {
            $response['success'] = true;
            $response['message'] = "התגבור נמחק בהצלחה!";
        } else {
            $response['message'] = "שגיאה במחיקת התגבור: " . db_error();
        }
        break;

    default:
        $response['message'] = "פעולה לא חוקית";
        break;
}


echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();