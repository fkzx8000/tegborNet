<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


header('Content-Type: application/json; charset=utf-8');


$response = [
    'success' => false,
    'message' => '',
    'data' => null
];


if (!is_logged_in()) {
    $response['message'] = "אינך מחובר למערכת";
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}


$current_user_id = get_current_user_id();


$action = isset($_GET['action']) ? $_GET['action'] : '';


switch ($action) {
    case 'send':

        if (!is_admin()) {
            $response['message'] = "אין לך הרשאה לשלוח הודעות שידור";
            break;
        }


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';

        if ($role_id <= 0 || empty($message)) {
            $response['message'] = "נדרש לבחור תפקיד ולהזין הודעה";
            break;
        }


        $sql = "INSERT INTO broadcast_messages (sender_id, role_id, message) VALUES (?, ?, ?)";
        $result = db_execute($sql, 'iis', [$current_user_id, $role_id, $message]);

        if ($result !== false) {
            $response['success'] = true;
            $response['message'] = "ההודעה נשלחה בהצלחה";
        } else {
            $response['message'] = "שגיאה בשליחת ההודעה: " . db_error();
        }
        break;

    case 'test':

        if (!is_admin()) {
            $response['message'] = "אין לך הרשאה לשלוח הודעות בדיקה";
            break;
        }


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : 'זוהי הודעת בדיקה אוטומטית.';

        if ($role_id <= 0) {

            $default_role_sql = "SELECT id FROM roles WHERE role_name = 'user'";
            $default_role = db_fetch_one($default_role_sql);

            if ($default_role) {
                $role_id = $default_role['id'];
            } else {

                $first_role_sql = "SELECT id FROM roles LIMIT 1";
                $first_role = db_fetch_one($first_role_sql);

                if ($first_role) {
                    $role_id = $first_role['id'];
                } else {
                    $response['message'] = "לא נמצאו תפקידים במערכת";
                    break;
                }
            }
        }


        $sql = "INSERT INTO broadcast_messages (sender_id, role_id, message) VALUES (?, ?, ?)";
        $result = db_execute($sql, 'iis', [$current_user_id, $role_id, $message]);

        if ($result !== false) {
            $response['success'] = true;
            $response['message'] = "הודעת הבדיקה נשלחה בהצלחה";
        } else {
            $response['message'] = "שגיאה בשליחת הודעת הבדיקה: " . db_error();
        }
        break;

    case 'mark_read':



        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        $broadcast_id = isset($_POST['broadcast_id']) ? intval($_POST['broadcast_id']) : 0;

        if ($broadcast_id <= 0) {
            $response['message'] = "מזהה הודעה לא תקין";
            break;
        }


        $sql = "INSERT INTO user_broadcast_reads (broadcast_message_id, user_id) VALUES (?, ?)";
        $result = db_execute($sql, 'ii', [$broadcast_id, $current_user_id]);

        if ($result !== false) {
            $response['success'] = true;
            $response['message'] = "ההודעה סומנה כנקראה";
        } else {

            $check_sql = "SELECT id FROM user_broadcast_reads WHERE broadcast_message_id = ? AND user_id = ?";
            $already_read = db_fetch_one($check_sql, 'ii', [$broadcast_id, $current_user_id]);

            if ($already_read) {
                $response['success'] = true;
                $response['message'] = "ההודעה כבר סומנה כנקראה בעבר";
            } else {
                $response['message'] = "שגיאה בסימון ההודעה כנקראה: " . db_error();
            }
        }
        break;

    case 'list_unread':



        $role_name = get_current_role();


        $unread_broadcasts = get_unread_broadcasts($current_user_id, $role_name);

        if ($unread_broadcasts !== false) {
            $response['success'] = true;
            $response['data'] = $unread_broadcasts;
        } else {
            $response['message'] = "שגיאה בטעינת הודעות שלא נקראו: " . db_error();
        }
        break;

    default:
        $response['message'] = "פעולה לא מוכרת";
        break;
}


echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();