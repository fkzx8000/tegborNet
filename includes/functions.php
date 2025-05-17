<?php

require_once __DIR__ . '/db_functions.php';

function get_display_name($user_id)
{
    $sql = "SELECT u.username, md.full_name 
            FROM users u 
            LEFT JOIN mentor_details md ON u.id = md.mentor_id
            WHERE u.id = ?";

    $result = db_fetch_one($sql, 'i', [$user_id]);

    if ($result && !empty($result['full_name'])) {
        return $result['full_name'];
    } else if ($result) {
        return $result['username'];
    }

    return 'משתמש לא ידוע';
}

function get_mentor_coordinator_id($mentor_id)
{
    $sql = "SELECT coordinator_id FROM coordinator_mentors WHERE mentor_id = ?";
    $result = db_fetch_one($sql, 'i', [$mentor_id]);

    if ($result) {
        return $result['coordinator_id'];
    }

    return null;
}

function get_coordinator_courses($coordinator_id)
{
    $sql = "SELECT id, course_name FROM courses WHERE coordinator_id = ? ORDER BY course_name ASC";
    return db_fetch_all($sql, 'i', [$coordinator_id]);
}

function get_coordinator_mentors($coordinator_id)
{
    $sql = "SELECT u.id, u.username, md.full_name
            FROM coordinator_mentors cm
            JOIN users u ON cm.mentor_id = u.id
            LEFT JOIN mentor_details md ON u.id = md.mentor_id
            WHERE cm.coordinator_id = ?
            ORDER BY u.username ASC";

    return db_fetch_all($sql, 'i', [$coordinator_id]);
}

function calculate_date_range($month, $year)
{

    if ($month == 1) {
        $start_month = 12;
        $start_year = $year - 1;
    } else {
        $start_month = $month - 1;
        $start_year = $year;
    }

    $start_date = new DateTime("$start_year-$start_month-16");
    $end_date = new DateTime("$year-$month-15");

    return [
        'start' => $start_date->format('Y-m-d'),
        'end' => $end_date->format('Y-m-d'),
        'start_formatted' => $start_date->format('d/m/Y'),
        'end_formatted' => $end_date->format('d/m/Y')
    ];
}

function calculate_semester_range($semester, $year)
{
    switch ($semester) {
        case 'A':
            $start_date = new DateTime("$year-10-01");
            $end_year = $year + 1;
            $end_date = new DateTime("$end_year-02-28");


            if ($end_date->format('m') == '02' && $end_year % 4 === 0) {
                $end_date = new DateTime("$end_year-02-29");
            }
            break;

        case 'B':
            $start_date = new DateTime("$year-03-01");
            $end_date = new DateTime("$year-07-31");
            break;

        case 'C':
            $start_date = new DateTime("$year-08-01");
            $end_date = new DateTime("$year-09-30");
            break;

        default:
            $start_date = new DateTime("$year-01-01");
            $end_date = new DateTime("$year-12-31");
    }

    return [
        'start' => $start_date->format('Y-m-d'),
        'end' => $end_date->format('Y-m-d'),
        'start_formatted' => $start_date->format('d/m/Y'),
        'end_formatted' => $end_date->format('d/m/Y')
    ];
}

function get_hebrew_months()
{
    return [
        1 => "ינואר",
        2 => "פברואר",
        3 => "מרץ",
        4 => "אפריל",
        5 => "מאי",
        6 => "יוני",
        7 => "יולי",
        8 => "אוגוסט",
        9 => "ספטמבר",
        10 => "אוקטובר",
        11 => "נובמבר",
        12 => "דצמבר"
    ];
}

function validate_range($value, $min, $max, $default)
{
    if ($value >= $min && $value <= $max) {
        return $value;
    }
    return $default;
}

function is_valid_json($string)
{
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

function sanitize_input($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function create_broadcast_message($role_id, $message, $sender_id)
{
    $sql = "INSERT INTO broadcast_messages (sender_id, message, role_id) VALUES (?, ?, ?)";
    $result = db_execute($sql, 'isi', [$sender_id, $message, $role_id]);
    return $result !== false;
}

function mark_broadcast_as_read($broadcast_id, $user_id)
{
    $sql = "INSERT INTO user_broadcast_reads (broadcast_message_id, user_id) VALUES (?, ?)";
    $result = db_execute($sql, 'ii', [$broadcast_id, $user_id]);
    return $result !== false;
}

function get_unread_broadcasts($user_id, $role_name, $limit = 5)
{
    $sql = "SELECT bm.id as broadcast_id, bm.message, u.username AS sender_username, bm.created_at
            FROM broadcast_messages bm
            JOIN roles r ON bm.role_id = r.id
            JOIN users u ON bm.sender_id = u.id
            WHERE r.role_name = ?
            AND NOT EXISTS (
                SELECT 1
                FROM user_broadcast_reads ubr
                WHERE ubr.broadcast_message_id = bm.id
                AND ubr.user_id = ?
            )
            ORDER BY bm.created_at DESC
            LIMIT ?";

    return db_fetch_all($sql, 'sii', [$role_name, $user_id, $limit]);
}