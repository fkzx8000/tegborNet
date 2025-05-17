<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db_functions.php';
require_once __DIR__ . '/../includes/functions.php';


header('Content-Type: application/json; charset=utf-8');


$response = [
    'success' => false,
    'message' => '',
    'data' => null
];


if (!is_logged_in()) {
    $response['message'] = "יש להתחבר למערכת";
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}


$action = isset($_GET['action']) ? $_GET['action'] : '';


$user_id = get_current_user_id();
$user_role = get_current_role();


switch ($action) {
    case 'add':

        if (!is_admin()) {
            $response['message'] = "אין לך הרשאה להוסיף קורסים";
            break;
        }


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }

        $course_name = isset($_POST['course_name']) ? trim($_POST['course_name']) : '';
        $coordinator_id = isset($_POST['coordinator_id']) ? intval($_POST['coordinator_id']) : 0;

        if (empty($course_name) || $coordinator_id <= 0) {
            $response['message'] = "יש להזין שם קורס ולבחור רכז";
            break;
        }


        $check_sql = "SELECT id FROM courses WHERE course_name = ?";
        $existing_course = db_fetch_one($check_sql, 's', [$course_name]);

        if ($existing_course) {
            $response['message'] = "קורס בשם זה כבר קיים במערכת";
            break;
        }


        $insert_sql = "INSERT INTO courses (course_name, coordinator_id) VALUES (?, ?)";
        $result = db_execute($insert_sql, 'si', [$course_name, $coordinator_id]);

        if ($result !== false) {
            $response['success'] = true;
            $response['message'] = "הקורס נוסף בהצלחה!";
        } else {
            $response['message'] = "שגיאה בהוספת הקורס: " . db_error();
        }
        break;

    case 'update':

        if (!is_admin()) {
            $response['message'] = "אין לך הרשאה לעדכן קורסים";
            break;
        }


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }

        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $course_name = isset($_POST['course_name']) ? trim($_POST['course_name']) : '';
        $coordinator_id = isset($_POST['coordinator_id']) ? intval($_POST['coordinator_id']) : 0;

        if ($course_id <= 0 || empty($course_name) || $coordinator_id <= 0) {
            $response['message'] = "יש להזין שם קורס ולבחור רכז";
            break;
        }


        $update_sql = "UPDATE courses SET course_name = ?, coordinator_id = ? WHERE id = ?";
        $result = db_execute($update_sql, 'sii', [$course_name, $coordinator_id, $course_id]);

        if ($result !== false) {
            $response['success'] = true;
            $response['message'] = "הקורס עודכן בהצלחה!";
        } else {
            $response['message'] = "שגיאה בעדכון הקורס: " . db_error();
        }
        break;

    case 'delete':

        if (!is_admin()) {
            $response['message'] = "אין לך הרשאה למחוק קורסים";
            break;
        }


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }

        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

        if ($course_id <= 0) {
            $response['message'] = "מזהה קורס לא תקין";
            break;
        }


        $check_logs_sql = "SELECT COUNT(*) as log_count FROM tutoring_logs WHERE course_id = ?";
        $logs_check = db_fetch_one($check_logs_sql, 'i', [$course_id]);

        if ($logs_check && $logs_check['log_count'] > 0) {
            $response['message'] = "לא ניתן למחוק קורס זה מכיוון שיש " . $logs_check['log_count'] . " רשומות תגבור משויכות אליו";
            break;
        }

        db_begin_transaction();

        try {

            $delete_mentor_courses_sql = "DELETE FROM mentor_courses WHERE course_id = ?";
            db_execute($delete_mentor_courses_sql, 'i', [$course_id]);


            $delete_course_sql = "DELETE FROM courses WHERE id = ?";
            $result = db_execute($delete_course_sql, 'i', [$course_id]);

            if ($result === false) {
                throw new Exception("שגיאה במחיקת הקורס: " . db_error());
            }

            db_commit();
            $response['success'] = true;
            $response['message'] = "הקורס נמחק בהצלחה!";

        } catch (Exception $e) {
            db_rollback();
            $response['message'] = $e->getMessage();
        }
        break;

    case 'list':

        if (is_admin()) {

            $courses_sql = "SELECT c.id, c.course_name, c.coordinator_id, u.username as coordinator_name
                           FROM courses c
                           JOIN users u ON c.coordinator_id = u.id
                           ORDER BY c.course_name ASC";
            $courses = db_fetch_all($courses_sql);
        } elseif (is_coordinator()) {

            $courses_sql = "SELECT c.id, c.course_name, c.coordinator_id
                           FROM courses c
                           WHERE c.coordinator_id = ?
                           ORDER BY c.course_name ASC";
            $courses = db_fetch_all($courses_sql, 'i', [$user_id]);
        } elseif (is_mentor()) {

            $courses_sql = "SELECT c.id, c.course_name, c.coordinator_id
                           FROM courses c
                           JOIN mentor_courses mc ON c.id = mc.course_id
                           WHERE mc.mentor_id = ?
                           ORDER BY c.course_name ASC";
            $courses = db_fetch_all($courses_sql, 'i', [$user_id]);
        } else {
            $response['message'] = "אין לך הרשאה לצפות בקורסים";
            break;
        }

        if ($courses !== false) {
            $response['success'] = true;
            $response['data'] = $courses;
        } else {
            $response['message'] = "שגיאה בשליפת רשימת הקורסים: " . db_error();
        }
        break;

    default:
        $response['message'] = "פעולה לא חוקית";
        break;
}


echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();