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


$action = isset($_GET['action']) ? $_GET['action'] : '';


switch ($action) {
    case 'update_role':

        if (!is_admin()) {
            $response['message'] = "אין לך הרשאה לשנות תפקידי משתמשים";
            break;
        }


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;

        if ($user_id <= 0 || $role_id <= 0) {
            $response['message'] = "נתונים חסרים או לא תקינים";
            break;
        }


        $sql = "UPDATE users SET role_id = ? WHERE id = ?";
        $result = db_execute($sql, 'ii', [$role_id, $user_id]);

        if ($result !== false) {

            $check_role_sql = "SELECT role_name FROM roles WHERE id = ?";
            $role_info = db_fetch_one($check_role_sql, 'i', [$role_id]);

            if ($role_info && $role_info['role_name'] === 'coordinator') {

                $check_mentor_sql = "SELECT id FROM coordinator_mentors WHERE mentor_id = ?";
                $is_mentor = db_fetch_one($check_mentor_sql, 'i', [$user_id]);

                if ($is_mentor) {

                    $remove_mentor_sql = "DELETE FROM coordinator_mentors WHERE mentor_id = ?";
                    db_execute($remove_mentor_sql, 'i', [$user_id]);
                }
            }

            $response['success'] = true;
            $response['message'] = "תפקיד המשתמש עודכן בהצלחה";
        } else {
            $response['message'] = "שגיאה בעדכון תפקיד המשתמש: " . db_error();
        }
        break;

    case 'reset_password':

        if (!is_admin()) {
            $response['message'] = "אין לך הרשאה לאפס סיסמאות משתמשים";
            break;
        }


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if ($user_id <= 0) {
            $response['message'] = "נתונים חסרים או לא תקינים";
            break;
        }


        if (empty($new_password)) {
            $response['message'] = "הסיסמה החדשה לא יכולה להיות ריקה";
            break;
        }

        if ($new_password !== $confirm_password) {
            $response['message'] = "הסיסמאות אינן תואמות";
            break;
        }

        if (strlen($new_password) < 8) {
            $response['message'] = "הסיסמה חייבת להכיל לפחות 8 תווים";
            break;
        }


        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);


        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $result = db_execute($sql, 'si', [$hashed_password, $user_id]);

        if ($result !== false) {
            $response['success'] = true;
            $response['message'] = "סיסמת המשתמש אופסה בהצלחה";
        } else {
            $response['message'] = "שגיאה באיפוס סיסמת המשתמש: " . db_error();
        }
        break;

    case 'delete':

        if (!is_admin()) {
            $response['message'] = "אין לך הרשאה למחוק משתמשים";
            break;
        }


        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if ($user_id <= 0) {
            $response['message'] = "נתונים חסרים או לא תקינים";
            break;
        }


        if ($user_id == get_current_user_id()) {
            $response['message'] = "לא ניתן למחוק את המשתמש המחובר כעת";
            break;
        }


        db_begin_transaction();

        try {



            $delete_cm_sql = "DELETE FROM coordinator_mentors WHERE mentor_id = ? OR coordinator_id = ?";
            db_execute($delete_cm_sql, 'ii', [$user_id, $user_id]);


            $delete_md_sql = "DELETE FROM mentor_details WHERE mentor_id = ?";
            db_execute($delete_md_sql, 'i', [$user_id]);


            $delete_tl_sql = "DELETE FROM tutoring_logs WHERE mentor_id = ?";
            db_execute($delete_tl_sql, 'i', [$user_id]);


            $delete_mc_sql = "DELETE FROM mentor_courses WHERE mentor_id = ?";
            db_execute($delete_mc_sql, 'i', [$user_id]);


            $update_courses_sql = "UPDATE courses SET coordinator_id = NULL WHERE coordinator_id = ?";
            db_execute($update_courses_sql, 'i', [$user_id]);


            $delete_bm_sql = "DELETE FROM broadcast_messages WHERE sender_id = ?";
            db_execute($delete_bm_sql, 'i', [$user_id]);


            $delete_ubr_sql = "DELETE FROM user_broadcast_reads WHERE user_id = ?";
            db_execute($delete_ubr_sql, 'i', [$user_id]);


            $delete_user_sql = "DELETE FROM users WHERE id = ?";
            $result = db_execute($delete_user_sql, 'i', [$user_id]);

            if ($result === false) {
                throw new Exception("שגיאה במחיקת המשתמש: " . db_error());
            }


            db_commit();

            $response['success'] = true;
            $response['message'] = "המשתמש נמחק בהצלחה";

        } catch (Exception $e) {

            db_rollback();
            $response['message'] = $e->getMessage();
        }
        break;

    case 'list':

        if (!is_admin()) {
            $response['message'] = "אין לך הרשאה לצפות ברשימת משתמשים";
            break;
        }


        $sql = "SELECT u.id, u.username, r.id as role_id, r.role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                ORDER BY u.username ASC";
        $users = db_fetch_all($sql);

        if ($users !== false) {
            $response['success'] = true;
            $response['data'] = $users;
        } else {
            $response['message'] = "שגיאה בטעינת רשימת המשתמשים: " . db_error();
        }
        break;

    default:
        $response['message'] = "פעולה לא מוכרת";
        break;
}


echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();