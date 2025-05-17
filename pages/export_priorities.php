<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


$export_all = isset($_GET['all']) && $_GET['all'] == '1';
$mentor_email = $export_all ? null : (isset($_GET['mentor']) ? filter_input(INPUT_GET, 'mentor', FILTER_VALIDATE_EMAIL) : null);


if (!$export_all && !$mentor_email) {
    die("פרמטרים חסרים או לא תקינים");
}


if ($export_all) {

    $priorities_sql = "SELECT ps.mentor_email, ps.priority, p.id as project_id, p.title, p.session
                      FROM project_selections ps
                      JOIN projects p ON ps.project_id = p.id
                      WHERE p.coordinator_id = ?
                      ORDER BY ps.mentor_email, ps.priority ASC";
    $priorities = db_fetch_all($priorities_sql, 'i', [$coordinator_id]);
    $filename = "all_mentors_priorities_" . date('Y-m-d') . ".csv";
} else {

    $priorities_sql = "SELECT ps.mentor_email, ps.priority, p.id as project_id, p.title, p.session
                      FROM project_selections ps
                      JOIN projects p ON ps.project_id = p.id
                      WHERE ps.mentor_email = ? AND p.coordinator_id = ?
                      ORDER BY ps.priority ASC";
    $priorities = db_fetch_all($priorities_sql, 'si', [$mentor_email, $coordinator_id]);
    $filename = "mentor_priorities_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $mentor_email) . "_" . date('Y-m-d') . ".csv";
}


header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');


$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));


$headers = ["אימייל השופט", "עדיפות", "מזהה פרויקט", "שם הפרויקט", "מושב"];
fputcsv($output, $headers);


foreach ($priorities as $priority) {
    $row = [
        $priority['mentor_email'],
        $priority['priority'],
        $priority['project_id'],
        $priority['title'],
        $priority['session']
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
?>