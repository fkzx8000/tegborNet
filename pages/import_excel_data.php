<?php


require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';


if (!check_permission(['coordinator'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'אין לך הרשאות לבצע פעולה זו. רק רכזים מורשים.'
    ]);
    exit();
}


if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת אבטחה. אנא רענן את הדף ונסה שוב.'
    ]);
    exit();
}


if (!isset($_POST['action']) || $_POST['action'] !== 'import_mentors_sessions' || !isset($_POST['data'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'בקשה לא תקינה.'
    ]);
    exit();
}


$coordinator_id = get_current_user_id();


$data = json_decode($_POST['data'], true);

if (!$data || !is_array($data) || empty($data)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'לא התקבלו נתונים לייבוא.'
    ]);
    exit();
}


db_begin_transaction();

try {

    $stats = [
        'mentors_added' => 0,
        'sessions_added' => 0,
        'assignments_added' => 0
    ];


    $warnings = [];


    $existing_sessions = [];
    $sessions_sql = "SELECT session FROM projects WHERE coordinator_id = ? GROUP BY session";
    $existing_sessions_result = db_fetch_all($sessions_sql, 'i', [$coordinator_id]);

    if ($existing_sessions_result) {
        foreach ($existing_sessions_result as $row) {
            $existing_sessions[$row['session']] = true;
        }
    }


    $existing_mentors = [];
    $mentors_sql = "SELECT email FROM authorized_mentors WHERE coordinator_id = ?";
    $existing_mentors_result = db_fetch_all($mentors_sql, 'i', [$coordinator_id]);

    if ($existing_mentors_result) {
        foreach ($existing_mentors_result as $row) {
            $existing_mentors[$row['email']] = true;
        }
    }


    foreach ($data as $item) {
        $mentor_email = trim($item['mentor']);
        $sessions = $item['sessions'];


        if (filter_var($mentor_email, FILTER_VALIDATE_EMAIL)) {

        } else {


            if (!preg_match('/^[a-zA-Z0-9_]+$/', $mentor_email)) {
                $warnings[] = "אימייל לא תקין: $mentor_email. דילוג על רשומה זו.";
                continue;
            }
        }


        if (!isset($existing_mentors[$mentor_email])) {

            $insert_mentor_sql = "INSERT INTO authorized_mentors (email, coordinator_id, level, created_at) VALUES (?, ?, 0, NOW())";
            $result = db_execute($insert_mentor_sql, 'si', [$mentor_email, $coordinator_id]);

            if ($result !== false) {
                $existing_mentors[$mentor_email] = true;
                $stats['mentors_added']++;
            } else {
                $warnings[] = "לא ניתן להוסיף את השופט: $mentor_email. " . db_error();
            }
        }


        foreach ($sessions as $session) {
            $session = trim($session);


            if (empty($session)) {
                continue;
            }


            if (!isset($existing_sessions[$session])) {

                $insert_session_sql = "INSERT INTO projects (title, session, coordinator_id, is_active, created_at) 
                                     VALUES (?, ?, ?, 1, NOW())";
                $dummy_title = "פרויקט ריק מושב $session";

                $result = db_execute($insert_session_sql, 'ssi', [$dummy_title, $session, $coordinator_id]);

                if ($result !== false) {
                    $existing_sessions[$session] = true;
                    $stats['sessions_added']++;
                } else {
                    $warnings[] = "לא ניתן להוסיף את המושב: $session. " . db_error();
                }
            }


            if (isset($existing_mentors[$mentor_email]) && isset($existing_sessions[$session])) {

                $check_user_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
                $user = db_fetch_one($check_user_sql, 'ss', [$mentor_email, $mentor_email]);

                if ($user) {
                    $mentor_id = $user['id'];



                    $courses_sql = "SELECT id FROM courses WHERE session = ? AND coordinator_id = ?";
                    $courses = db_fetch_all($courses_sql, 'si', [$session, $coordinator_id]);

                    if ($courses) {
                        foreach ($courses as $course) {

                            $check_assignment_sql = "SELECT 1 FROM mentor_courses WHERE mentor_id = ? AND course_id = ?";
                            $existing_assignment = db_fetch_one($check_assignment_sql, 'ii', [$mentor_id, $course['id']]);

                            if (!$existing_assignment) {

                                $insert_assignment_sql = "INSERT INTO mentor_courses (mentor_id, course_id) VALUES (?, ?)";
                                $result = db_execute($insert_assignment_sql, 'ii', [$mentor_id, $course['id']]);

                                if ($result !== false) {
                                    $stats['assignments_added']++;
                                } else {
                                    $warnings[] = "לא ניתן להקצות את הקורס ID: " . $course['id'] . " לשופט: $mentor_email. " . db_error();
                                }
                            }
                        }
                    }
                } else {


                    $session_restriction_sql = "UPDATE authorized_mentors SET session_restriction = ? WHERE email = ? AND coordinator_id = ?";
                    $result = db_execute($session_restriction_sql, 'ssi', [$session, $mentor_email, $coordinator_id]);

                    if ($result !== false) {
                        $stats['assignments_added']++;
                    } else {
                        $warnings[] = "לא ניתן לעדכן את מגבלת המושב לשופט: $mentor_email. " . db_error();
                    }
                }
            }
        }
    }


    db_commit();


    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'הייבוא הושלם בהצלחה',
        'stats' => $stats,
        'warnings' => $warnings
    ]);

} catch (Exception $e) {

    db_rollback();


    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'אירעה שגיאה במהלך הייבוא: ' . $e->getMessage()
    ]);
}
?>