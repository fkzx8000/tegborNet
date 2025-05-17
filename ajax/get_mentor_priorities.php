<?php


error_reporting(0);
ini_set('display_errors', 0);


if (ob_get_length())
    ob_clean();



require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


header('Content-Type: application/json; charset=utf-8');


function return_error($message)
{
    echo json_encode([
        'error' => $message
    ]);
    exit();
}


if (!function_exists('has_permission') || !has_permission(['coordinator'])) {
    return_error('אין לך הרשאה לצפות בתעדופים');
}


$coordinator_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
if (!$coordinator_id) {
    return_error('לא ניתן לזהות את המשתמש הנוכחי');
}


$mentor_email = filter_input(INPUT_GET, 'mentor', FILTER_VALIDATE_EMAIL);
if (!$mentor_email) {
    return_error('אימייל שופט לא תקין');
}

try {

    $check_sql = "SELECT 1 FROM authorized_mentors 
                WHERE email = ? AND coordinator_id = ?";
    $is_authorized = db_fetch_one($check_sql, 'si', [$mentor_email, $coordinator_id]);

    if (!$is_authorized) {
        return_error('השופט אינו משויך לרכז זה');
    }


    $priorities_sql = "SELECT ps.priority, p.id, p.title, p.session
                    FROM project_selections ps
                    JOIN projects p ON ps.project_id = p.id
                    WHERE ps.mentor_email = ? AND p.coordinator_id = ?
                    ORDER BY ps.priority ASC";
    $priorities = db_fetch_all($priorities_sql, 'si', [$mentor_email, $coordinator_id]);

    if ($priorities === false) {
        return_error('שגיאה בשליפת התעדופים');
    }


    echo json_encode([
        'mentor' => $mentor_email,
        'priorities' => $priorities ?: []
    ]);

} catch (Exception $e) {

    return_error('שגיאה בלתי צפויה: ' . $e->getMessage());
}