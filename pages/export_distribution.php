<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


$projects_per_mentor = isset($_GET['projects']) ? intval($_GET['projects']) : 2;
$max_mentors_per_project = isset($_GET['mentors']) ? intval($_GET['mentors']) : 1;
$prioritize_by = isset($_GET['by']) && $_GET['by'] == 'popularity' ? 'popularity' : 'priority';



$mentors_sql = "SELECT DISTINCT ps.mentor_email 
                FROM project_selections ps
                JOIN projects p ON ps.project_id = p.id
                WHERE p.coordinator_id = ?
                ORDER BY ps.mentor_email";
$mentors = db_fetch_all($mentors_sql, 'i', [$coordinator_id]);


$projects_sql = "SELECT p.id, p.title, p.session, 
                 (SELECT COUNT(*) FROM project_selections ps WHERE ps.project_id = p.id) as popularity
                 FROM projects p
                 WHERE p.coordinator_id = ? AND p.is_active = 1
                 ORDER BY p.session, p.title";
$projects = db_fetch_all($projects_sql, 'i', [$coordinator_id]);


$priorities_sql = "SELECT ps.project_id, ps.mentor_email, ps.priority
                  FROM project_selections ps
                  JOIN projects p ON ps.project_id = p.id
                  WHERE p.coordinator_id = ?
                  ORDER BY ps.priority";
$priorities = db_fetch_all($priorities_sql, 'i', [$coordinator_id]);

$priority_matrix = [];
$project_popularity = [];

foreach ($priorities as $priority) {
    $project_id = $priority['project_id'];
    $mentor = $priority['mentor_email'];
    $priority_value = $priority['priority'];

    if (!isset($priority_matrix[$project_id])) {
        $priority_matrix[$project_id] = [];
        $project_popularity[$project_id] = 0;
    }

    $priority_matrix[$project_id][$mentor] = $priority_value;
    $project_popularity[$project_id]++;
}


$distribution = calculateOptimalDistribution($projects, $mentors, $priority_matrix, $project_popularity, $projects_per_mentor, $prioritize_by, $max_mentors_per_project);


function calculateOptimalDistribution($projects, $mentors, $priority_matrix, $project_popularity, $projects_per_mentor, $prioritize_by, $max_mentors_per_project)
{

    $distribution = [];
    foreach ($mentors as $mentor) {
        $distribution[$mentor['mentor_email']] = [];
    }


    $project_allocation_count = [];
    foreach ($projects as $project) {
        $project_allocation_count[$project['id']] = 0;
    }


    if ($prioritize_by === 'popularity') {

        usort($projects, function ($a, $b) use ($project_popularity) {
            $a_popularity = isset($project_popularity[$a['id']]) ? $project_popularity[$a['id']] : 0;
            $b_popularity = isset($project_popularity[$b['id']]) ? $project_popularity[$b['id']] : 0;
            return $b_popularity - $a_popularity;
        });
    }


    foreach ($projects as $project) {
        $project_id = $project['id'];


        if (!isset($priority_matrix[$project_id]) || empty($priority_matrix[$project_id])) {
            continue;
        }


        $project_priorities = $priority_matrix[$project_id];
        asort($project_priorities);


        foreach ($project_priorities as $mentor_email => $priority) {

            if ($project_allocation_count[$project_id] >= $max_mentors_per_project) {
                break;
            }


            if (count($distribution[$mentor_email]) < $projects_per_mentor) {
                $distribution[$mentor_email][] = [
                    'project_id' => $project_id,
                    'title' => $project['title'],
                    'session' => $project['session'],
                    'priority' => $priority
                ];

                $project_allocation_count[$project_id]++;
            }
        }
    }


    foreach ($mentors as $mentor) {
        $mentor_email = $mentor['mentor_email'];


        if (count($distribution[$mentor_email]) >= $projects_per_mentor) {
            continue;
        }


        foreach ($priority_matrix as $project_id => $priorities) {

            if (isset($priorities[$mentor_email]) && $project_allocation_count[$project_id] < $max_mentors_per_project) {

                $project_info = null;
                foreach ($projects as $project) {
                    if ($project['id'] == $project_id) {
                        $project_info = $project;
                        break;
                    }
                }

                if ($project_info) {
                    $distribution[$mentor_email][] = [
                        'project_id' => $project_id,
                        'title' => $project_info['title'],
                        'session' => $project_info['session'],
                        'priority' => $priorities[$mentor_email]
                    ];

                    $project_allocation_count[$project_id]++;


                    if (count($distribution[$mentor_email]) >= $projects_per_mentor) {
                        break;
                    }
                }
            }
        }
    }

    return $distribution;
}


$export_data = [];

foreach ($distribution as $mentor_email => $assigned_projects) {
    foreach ($assigned_projects as $project) {
        $export_data[] = [
            'mentor_email' => $mentor_email,
            'project_id' => $project['project_id'],
            'project_title' => $project['title'],
            'project_session' => $project['session'],
            'priority' => $project['priority']
        ];
    }
}


$filename = "projects_distribution_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');


$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));


$headers = ["אימייל השופט", "מזהה פרויקט", "כותרת הפרויקט", "מושב", "עדיפות מקורית"];
fputcsv($output, $headers);


foreach ($export_data as $row) {
    $csv_row = [
        $row['mentor_email'],
        $row['project_id'],
        $row['project_title'],
        $row['project_session'],
        $row['priority']
    ];
    fputcsv($output, $csv_row);
}

fclose($output);
exit;
?>