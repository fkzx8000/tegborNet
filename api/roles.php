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


if (!is_admin()) {
    $response['message'] = "אין לך הרשאה לניהול תפקידים";
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}


$action = isset($_GET['action']) ? $_GET['action'] : '';


switch ($action) {
    case 'add':



        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        $role_name = isset($_POST['role_name']) ? trim($_POST['role_name']) : '';

        if (empty($role_name)) {
            $response['message'] = "שם התפקיד לא יכול להיות ריק";
            break;
        }


        $check_sql = "SELECT id FROM roles WHERE role_name = ?";
        $existing_role = db_fetch_one($check_sql, 's', [$role_name]);

        if ($existing_role) {
            $response['message'] = "תפקיד בשם זה כבר קיים במערכת";
            break;
        }


        $sql = "INSERT INTO roles (role_name) VALUES (?)";
        $result = db_execute($sql, 's', [$role_name]);

        if ($result !== false) {
            $response['success'] = true;
            $response['message'] = "התפקיד נוסף בהצלחה";
            $response['data'] = ['id' => db_insert_id()];
        } else {
            $response['message'] = "שגיאה בהוספת התפקיד: " . db_error();
        }
        break;

    case 'delete':



        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            $response['message'] = "שגיאת אבטחה. אנא רענן את הדף ונסה שוב.";
            break;
        }


        $role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;

        if ($role_id <= 0) {
            $response['message'] = "מזהה תפקיד לא תקין";
            break;
        }


        $check_users_sql = "SELECT COUNT(*) as count FROM users WHERE role_id = ?";
        $users_count = db_fetch_one($check_users_sql, 'i', [$role_id]);

        if ($users_count && $users_count['count'] > 0) {
            $response['message'] = "לא ניתן למחוק תפקיד זה כי יש משתמשים המשויכים אליו";
            break;
        }


        db_begin_transaction();

        try {

            $delete_vr_sql = "DELETE FROM video_roles WHERE role_id = ?";
            db_execute($delete_vr_sql, 'i', [$role_id]);


            $delete_zm_sql = "DELETE FROM zoom_meetings WHERE role_id = ?";
            db_execute($delete_zm_sql, 'i', [$role_id]);


            $delete_bm_sql = "DELETE FROM broadcast_messages WHERE role_id = ?";
            db_execute($delete_bm_sql, 'i', [$role_id]);


            $delete_role_sql = "DELETE FROM roles WHERE id = ?";
            $result = db_execute($delete_role_sql, 'i', [$role_id]);

            if ($result === false) {
                throw new Exception("שגיאה במחיקת התפקיד: " . db_error());
            }


            db_commit();

            $response['success'] = true;
            $response['message'] = "התפקיד נמחק בהצלחה";

        } catch (Exception $e) {

            db_rollback();
            $response['message'] = $e->getMessage();
        }
        break;

    case 'list':



        $sql = "SELECT id, role_name FROM roles ORDER BY role_name ASC";
        $roles = db_fetch_all($sql);

        if ($roles !== false) {
            $response['success'] = true;
            $response['data'] = $roles;
        } else {
            $response['message'] = "שגיאה בטעינת רשימת התפקידים: " . db_error();
        }
        break;

    default:
        $response['message'] = "פעולה לא מוכרת";
        break;
}


echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();

function db_insert_id()
{
    $conn = get_database_connection();
    return $conn->insert_id;
}