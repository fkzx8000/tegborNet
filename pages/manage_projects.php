<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_mentor'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

    if ($project_id <= 0) {
        set_error_message("מזהה פרויקט לא תקין");
    } else {

        $check_sql = "SELECT p.*, ps.mentor_email 
                      FROM projects p
                      LEFT JOIN project_selections ps ON p.id = ps.project_id
                      WHERE p.id = ? AND p.coordinator_id = ?";
        $project = db_fetch_one($check_sql, 'ii', [$project_id, $coordinator_id]);

        if (!$project) {
            set_error_message("הפרויקט לא נמצא או אינו שייך לך");
        } else if (empty($project['mentor_email'])) {
            set_error_message("הפרויקט אינו משויך לשופט כלשהו");
        } else {

            $delete_sql = "DELETE FROM project_selections WHERE project_id = ?";
            $result = db_execute($delete_sql, 'i', [$project_id]);

            if ($result !== false) {

                $update_mentor_sql = "UPDATE authorized_mentors 
                                    SET selection_count = selection_count - 1 
                                    WHERE email = ?";
                $mentor_update = db_execute($update_mentor_sql, 's', [$project['mentor_email']]);

                if ($mentor_update !== false) {
                    set_success_message("השופט " . htmlspecialchars($project['mentor_email']) . " הוסר בהצלחה מהפרויקט");
                } else {
                    set_warning_message("השופט הוסר מהפרויקט, אך הייתה שגיאה בעדכון מונה הבחירות: " . db_error());
                }
            } else {
                set_error_message("שגיאה בהסרת השופט מהפרויקט: " . db_error());
            }
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_projects'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $projects_sql = "SELECT p.id, p.title, p.session, p.details, 
                    CASE WHEN p.is_active = 1 THEN 'פעיל' ELSE 'לא פעיל' END as status,
                    COALESCE(ps.mentor_email, 'טרם נבחר') as selected_by,
                    DATE_FORMAT(p.created_at, '%d/%m/%Y') as creation_date
                    FROM projects p
                    LEFT JOIN project_selections ps ON p.id = ps.project_id
                    WHERE p.coordinator_id = ?
                    ORDER BY p.session, p.title";
    $projects = db_fetch_all($projects_sql, 'i', [$coordinator_id]);

    if ($projects === false) {
        set_error_message("שגיאה בשליפת נתוני הפרויקטים");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $headers = ["מזהה", "כותרת", "מושב", "פרטים", "סטטוס", "נבחר על ידי", "תאריך יצירה"];


    export_to_csv($projects, $headers, "projects_list_" . date('Y-m-d') . ".csv");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_mentors_projects'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $mentors_projects_sql = "SELECT 
                            ps.mentor_email as mentor_email,
                            p.title as project_title,
                            p.session as session,
                            ps.priority as priority,
                            p.details as project_details,
                            DATE_FORMAT(ps.created_at, '%d/%m/%Y') as selection_date
                            FROM project_selections ps
                            JOIN projects p ON ps.project_id = p.id
                            WHERE p.coordinator_id = ?
                            ORDER BY ps.mentor_email, ps.priority ASC";
    $mentors_projects = db_fetch_all($mentors_projects_sql, 'i', [$coordinator_id]);

    if ($mentors_projects === false) {
        set_error_message("שגיאה בשליפת נתוני השופטים והפרויקטים");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $headers = ["אימייל השופט", "כותרת הפרויקט", "מושב", "עדיפות", "פרטי הפרויקט", "תאריך הבחירה"];


    export_to_csv($mentors_projects, $headers, "mentors_projects_" . date('Y-m-d') . ".csv");
    exit();
}


function export_to_csv($data, $headers, $filename)
{

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');


    $output = fopen('php://output', 'w');


    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));


    fputcsv($output, $headers);


    foreach ($data as $row) {
        fputcsv($output, array_values($row));
    }

    fclose($output);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $session = isset($_POST['session']) ? trim($_POST['session']) : '';
    $details = isset($_POST['details']) ? trim($_POST['details']) : '';


    if (empty($title) || empty($session)) {
        set_error_message("יש למלא את כל השדות הנדרשים");
    } else {

        $insert_sql = "INSERT INTO projects (title, session, details, coordinator_id) VALUES (?, ?, ?, ?)";
        $result = db_execute($insert_sql, 'sssi', [$title, $session, $details, $coordinator_id]);

        if ($result !== false) {
            set_success_message("הפרויקט נוסף בהצלחה");
        } else {
            set_error_message("שגיאה בהוספת הפרויקט: " . db_error());
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $session = isset($_POST['session']) ? trim($_POST['session']) : '';
    $details = isset($_POST['details']) ? trim($_POST['details']) : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;


    if ($project_id <= 0 || empty($title) || empty($session)) {
        set_error_message("יש למלא את כל השדות הנדרשים");
    } else {

        $check_sql = "SELECT * FROM projects WHERE id = ? AND coordinator_id = ?";
        $project = db_fetch_one($check_sql, 'ii', [$project_id, $coordinator_id]);

        if (!$project) {
            set_error_message("הפרויקט לא נמצא או אינו שייך לך");
        } else {

            $check_selection_sql = "SELECT * FROM project_selections WHERE project_id = ?";
            $selection = db_fetch_one($check_selection_sql, 'i', [$project_id]);

            if ($selection) {

                $update_sql = "UPDATE projects SET is_active = ? WHERE id = ?";
                $result = db_execute($update_sql, 'ii', [$is_active, $project_id]);

                if ($result !== false) {
                    set_success_message("סטטוס הפרויקט עודכן בהצלחה. שים לב שלא ניתן לערוך את פרטי הפרויקט מכיוון שהוא כבר נבחר על ידי שופט.");
                } else {
                    set_error_message("שגיאה בעדכון סטטוס הפרויקט: " . db_error());
                }
            } else {

                $update_sql = "UPDATE projects SET title = ?, session = ?, details = ?, is_active = ? WHERE id = ?";
                $result = db_execute($update_sql, 'sssii', [$title, $session, $details, $is_active, $project_id]);

                if ($result !== false) {
                    set_success_message("הפרויקט עודכן בהצלחה");
                } else {
                    set_error_message("שגיאה בעדכון הפרויקט: " . db_error());
                }
            }
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

    if ($project_id <= 0) {
        set_error_message("מזהה פרויקט לא תקין");
    } else {

        $check_sql = "SELECT * FROM projects WHERE id = ? AND coordinator_id = ?";
        $project = db_fetch_one($check_sql, 'ii', [$project_id, $coordinator_id]);

        if (!$project) {
            set_error_message("הפרויקט לא נמצא או אינו שייך לך");
        } else {

            $check_selection_sql = "SELECT * FROM project_selections WHERE project_id = ?";
            $selection = db_fetch_one($check_selection_sql, 'i', [$project_id]);

            if ($selection) {
                set_error_message("לא ניתן למחוק פרויקט שכבר נבחר על ידי שופט");
            } else {

                $delete_sql = "DELETE FROM projects WHERE id = ?";
                $result = db_execute($delete_sql, 'i', [$project_id]);

                if ($result !== false) {
                    set_success_message("הפרויקט נמחק בהצלחה");
                } else {
                    set_error_message("שגיאה במחיקת הפרויקט: " . db_error());
                }
            }
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


$sessions_sql = "SELECT DISTINCT session FROM projects WHERE coordinator_id = ? ORDER BY session";
$sessions = db_fetch_all($sessions_sql, 'i', [$coordinator_id]);


$projects_sql = "SELECT p.*,
                (SELECT mentor_email FROM project_selections ps WHERE ps.project_id = p.id ORDER BY ps.priority ASC LIMIT 1) as selected_by,
                (SELECT created_at FROM project_selections ps WHERE ps.project_id = p.id ORDER BY ps.priority ASC LIMIT 1) as selection_date
                FROM projects p
                WHERE p.coordinator_id = ?
                ORDER BY p.session, p.title";
$projects = db_fetch_all($projects_sql, 'i', [$coordinator_id]);


$mentors_count_sql = "SELECT COUNT(DISTINCT ps.mentor_email) as mentors_count
                     FROM project_selections ps
                     JOIN projects p ON ps.project_id = p.id
                     WHERE p.coordinator_id = ?";
$mentors_count_result = db_fetch_one($mentors_count_sql, 'i', [$coordinator_id]);
$mentors_count = $mentors_count_result ? $mentors_count_result['mentors_count'] : 0;


$mentors_by_session_sql = "SELECT p.session, 
                           COUNT(DISTINCT ps.mentor_email) as judges_count
                           FROM project_selections ps
                           JOIN projects p ON ps.project_id = p.id
                           WHERE p.coordinator_id = ?
                           GROUP BY p.session
                           ORDER BY p.session";
$mentors_by_session = db_fetch_all($mentors_by_session_sql, 'i', [$coordinator_id]);


$chart_data = [];
foreach ($mentors_by_session as $item) {
    $chart_data[] = [
        'session' => $item['session'],
        'judges_count' => intval($item['judges_count'])
    ];
}
$chart_data_json = json_encode($chart_data);


$project_status_sql = "SELECT 
                      SUM(CASE WHEN ps.mentor_email IS NOT NULL THEN 1 ELSE 0 END) AS selected_count,
                      SUM(CASE WHEN ps.mentor_email IS NULL THEN 1 ELSE 0 END) AS not_selected_count
                      FROM projects p
                      LEFT JOIN project_selections ps ON p.id = ps.project_id AND ps.priority = 1
                      WHERE p.coordinator_id = ?";
$project_status = db_fetch_one($project_status_sql, 'i', [$coordinator_id]);

$project_status_data = [
    ['status' => 'נבחרו', 'count' => intval($project_status['selected_count'] ?? 0)],
    ['status' => 'טרם נבחרו', 'count' => intval($project_status['not_selected_count'] ?? 0)]
];
$project_status_json = json_encode($project_status_data);


$projects_by_session_sql = "SELECT p.session, 
                           COUNT(*) as projects_count,
                           SUM(CASE WHEN ps.mentor_email IS NOT NULL THEN 1 ELSE 0 END) AS selected_count
                           FROM projects p
                           LEFT JOIN project_selections ps ON p.id = ps.project_id AND ps.priority = 1
                           WHERE p.coordinator_id = ?
                           GROUP BY p.session
                           ORDER BY p.session";
$projects_by_session = db_fetch_all($projects_by_session_sql, 'i', [$coordinator_id]);

$projects_by_session_data = [];
foreach ($projects_by_session as $item) {
    $projects_by_session_data[] = [
        'session' => $item['session'],
        'total' => intval($item['projects_count']),
        'selected' => intval($item['selected_count']),
        'not_selected' => intval($item['projects_count']) - intval($item['selected_count'])
    ];
}
$projects_by_session_json = json_encode($projects_by_session_data);


$page_title = 'ניהול פרויקטים';




include __DIR__ . '/../templates/header.php';


echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>ניהול פרויקטים לבחירת שופטים</h2>
    </div>

    <div class="dashboard-actions">
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <button type="submit" name="export_projects" class="btn btn-secondary">
                <i class="fas fa-file-export"></i> ייצוא רשימת פרויקטים
            </button>
        </form>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post"
            style="display: inline; margin-right: 10px;">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <button type="submit" name="export_mentors_projects" class="btn btn-secondary" <?php echo $mentors_count == 0 ? 'disabled' : ''; ?>>
                <i class="fas fa-users"></i> ייצוא רשימת שופטים ופרויקטים
            </button>
        </form>

        <button type="button" class="btn btn-primary" id="show-priorities-btn" onclick="showPrioritiesModal()">
            <i class="fas fa-list-ol"></i> הצג תעדופי שופטים
        </button>
    </div>
</div>

<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-number"><?php echo count($projects); ?></div>
        <div class="stat-label">סה"כ פרויקטים</div>
    </div>

    <div class="stat-card">
        <div class="stat-number">
            <?php echo array_reduce($projects, function ($carry, $item) {
                return $carry + (!empty($item['selected_by']) ? 1 : 0);
            }, 0); ?>
        </div>
        <div class="stat-label">פרויקטים שנבחרו</div>
    </div>

    <div class="stat-card">
        <div class="stat-number"><?php echo $mentors_count; ?></div>
        <div class="stat-label">שופטים שבחרו פרויקטים</div>
    </div>
</div>

<!-- תצוגת הגרפים המשופרת -->
<div class="analytics-dashboard">
    <div class="analytics-row">
        <!-- גרף 1: התפלגות שופטים לפי מושב -->
        <div class="analytics-card">
            <h3 class="card-title">התפלגות שופטים לפי מושב</h3>

            <?php if (empty($chart_data)): ?>
                <div class="alert-info">
                    <p>אין נתונים להצגה. עדיין לא נבחרו פרויקטים על ידי שופטים.</p>
                </div>
            <?php else: ?>
                <div class="chart-container">
                    <canvas id="judgesDistributionChart"></canvas>
                </div>
            <?php endif; ?>
        </div>

        <!-- גרף 2: סטטוס בחירת פרויקטים -->
        <div class="analytics-card">
            <h3 class="card-title">סטטוס בחירת פרויקטים</h3>

            <div class="chart-container">
                <canvas id="projectStatusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- גרף 3: פרויקטים בכל מושב - כולל סטטוס בחירה -->
    <div class="analytics-row">
        <div class="analytics-card full-width">
            <h3 class="card-title">פרויקטים לפי מושב וסטטוס בחירה</h3>

            <?php if (empty($projects_by_session_data)): ?>
                <div class="alert-info">
                    <p>אין נתונים להצגה. יש להוסיף פרויקטים תחילה.</p>
                </div>
            <?php else: ?>
                <div class="chart-container">
                    <canvas id="projectsBySessionChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">הוספת פרויקט חדש</h3>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="add-project-form">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

        <div class="form-group">
            <label for="title">כותרת הפרויקט:</label>
            <input type="text" id="title" name="title" required placeholder="הזן כותרת לפרויקט">
        </div>

        <div class="form-group">
            <label for="session">מושב:</label>
            <input type="text" id="session" name="session" required list="existing-sessions"
                placeholder="הזן שם מושב או בחר מהרשימה">
            <datalist id="existing-sessions">
                <?php foreach ($sessions as $session): ?>
                    <option value="<?php echo htmlspecialchars($session['session']); ?>">
                    <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-group">
            <label for="details">פרטי הפרויקט:</label>
            <textarea id="details" name="details" rows="5" placeholder="הזן תיאור מפורט של הפרויקט"></textarea>
        </div>

        <button type="submit" name="add_project" class="btn btn-primary">הוסף פרויקט</button>
    </form>
</div>
<div class="dashboard-card">
    <h3 class="card-title">ייבוא מתגברים ומושבים מקובץ אקסל</h3>

    <div class="import-container">
        <div class="dropzone" id="fileDropzone">
            <div class="dropzone-content">
                <i class="fas fa-file-excel fa-3x"></i>
                <p>גרור לכאן קובץ Excel או לחץ לבחירת קובץ</p>
                <p class="small">קבצי XLSX בלבד</p>
            </div>
            <input type="file" id="fileInput" accept=".xlsx" style="display: none;" />
        </div>

        <div class="import-options">
            <h4>הגדרות ייבוא</h4>

            <div class="form-group">
                <label for="mentor_column">עמודת אימייל המתגבר:</label>
                <input type="text" id="mentor_column" value="A" class="form-control">
                <small>עמודה המכילה את כתובת האימייל של המתגבר</small>
            </div>

            <div class="form-group">
                <label for="session_mapping">מיפוי מושבים:</label>
                <div id="session_mapping_container">
                    <div class="session-map-row">
                        <input type="text" value="B" class="column-input" placeholder="עמודה">
                        <span class="equals-sign">=</span>
                        <input type="text" value="A" class="session-input" placeholder="מושב">
                        <button type="button" class="btn-remove-row"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="session-map-row">
                        <input type="text" value="C" class="column-input" placeholder="עמודה">
                        <span class="equals-sign">=</span>
                        <input type="text" value="B" class="session-input" placeholder="מושב">
                        <button type="button" class="btn-remove-row"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="session-map-row">
                        <input type="text" value="D" class="column-input" placeholder="עמודה">
                        <span class="equals-sign">=</span>
                        <input type="text" value="C" class="session-input" placeholder="מושב">
                        <button type="button" class="btn-remove-row"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <button type="button" id="addMappingRow" class="btn btn-secondary">+ הוסף שורת מיפוי</button>
            </div>

            <div class="import-actions">
                <button type="button" id="startImportBtn" class="btn btn-primary" disabled>
                    <i class="fas fa-upload"></i> התחל ייבוא
                </button>
                <button type="button" id="cancelImportBtn" class="btn btn-outline">
                    <i class="fas fa-times"></i> בטל
                </button>
            </div>
        </div>
    </div>

    <div class="import-preview">
        <h4>תצוגה מקדימה <span id="preview-count">(0 שורות)</span></h4>
        <div class="table-responsive">
            <table id="preview-table">
                <thead>
                    <tr>
                        <th>אימייל מתגבר</th>
                        <th>מושבים</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- כאן תוצג תצוגה מקדימה של הנתונים -->
                </tbody>
            </table>
        </div>
    </div>

    <div id="import-progress" style="display: none;">
        <h4>התקדמות הייבוא</h4>
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%;"></div>
            </div>
            <div class="progress-text">0%</div>
        </div>
        <div class="progress-stats">
            <div class="stat-item">
                <span class="stat-label">מתגברים שנוספו:</span>
                <span class="stat-value" id="mentors-added">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">מושבים שנוספו:</span>
                <span class="stat-value" id="sessions-added">0</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">הקצאות שנוספו:</span>
                <span class="stat-value" id="assignments-added">0</span>
            </div>
        </div>
        <div id="import-messages"></div>
    </div>
</div>
<div class="dashboard-card">
    <h3 class="card-title">רשימת פרויקטים</h3>

    <?php if (!empty($projects)): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>מזהה</th>
                        <th>כותרת</th>
                        <th>מושב</th>
                        <th>סטטוס</th>
                        <th>נבחר על ידי</th>
                        <th>תאריך בחירה</th>
                        <th>תאריך יצירה</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr class="<?php echo !empty($project['selected_by']) ? 'project-selected' : ''; ?>">
                            <td><?php echo $project['id']; ?></td>
                            <td><?php echo htmlspecialchars($project['title']); ?></td>
                            <td><?php echo htmlspecialchars($project['session']); ?></td>
                            <td>
                                <?php if ($project['is_active']): ?>
                                    <span class="status-active">פעיל</span>
                                <?php else: ?>
                                    <span class="status-inactive">לא פעיל</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($project['selected_by']): ?>
                                    <span class="mentor-email">
                                        <?php echo htmlspecialchars($project['selected_by']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="not-selected">טרם נבחר</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($project['selection_date']): ?>
                                    <?php echo date('d/m/Y', strtotime($project['selection_date'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($project['created_at'])); ?></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-small edit-project-btn"
                                    data-id="<?php echo $project['id']; ?>"
                                    data-title="<?php echo htmlspecialchars($project['title']); ?>"
                                    data-session="<?php echo htmlspecialchars($project['session']); ?>"
                                    data-details="<?php echo htmlspecialchars($project['details']); ?>"
                                    data-active="<?php echo $project['is_active']; ?>"
                                    data-selected="<?php echo !empty($project['selected_by']) ? '1' : '0'; ?>">
                                    ערוך
                                </button>

                                <?php if (!empty($project['selected_by'])): ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post"
                                        style="display: inline;" class="remove-mentor-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <button type="submit" name="remove_mentor" class="btn btn-warning btn-small"
                                            onclick="return confirm('האם אתה בטוח שברצונך להסיר את השופט מפרויקט זה? פעולה זו תבטל את בחירת הפרויקט.')">
                                            הסר שופט
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (empty($project['selected_by'])): ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post"
                                        style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <button type="submit" name="delete_project" class="btn btn-danger btn-small"
                                            onclick="return confirm('האם אתה בטוח שברצונך למחוק פרויקט זה?')">
                                            מחק
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>אין פרויקטים רשומים. הוסף פרויקט חדש באמצעות הטופס למעלה.</p>
    <?php endif; ?>
</div>

<!-- חלון מודאל לעריכת פרויקט -->
<div id="editProjectModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>עריכת פרטי פרויקט</h3>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="edit-project-form">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <input type="hidden" name="project_id" id="edit_project_id">

            <div class="form-group">
                <label for="edit_title">כותרת הפרויקט:</label>
                <input type="text" id="edit_title" name="title" required>
            </div>

            <div class="form-group">
                <label for="edit_session">מושב:</label>
                <input type="text" id="edit_session" name="session" required list="edit-existing-sessions">
                <datalist id="edit-existing-sessions">
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?php echo htmlspecialchars($session['session']); ?>">
                        <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-group">
                <label for="edit_details">פרטי הפרויקט:</label>
                <textarea id="edit_details" name="details" rows="5"></textarea>
            </div>

            <div class="form-group">
                <label for="edit_is_active" class="checkbox-label">
                    <input type="checkbox" id="edit_is_active" name="is_active">
                    פרויקט פעיל
                </label>
            </div>

            <div id="selected-warning" style="display: none; color: #e74c3c; margin-bottom: 15px;">
                <strong>שים לב:</strong> הפרויקט כבר נבחר על ידי שופט. ניתן לשנות רק את הסטטוס (פעיל/לא פעיל).
            </div>

            <button type="submit" name="update_project" class="btn btn-primary">שמור שינויים</button>
        </form>
    </div>
</div>

<!-- חלון מודאל להצגת תעדופי שופטים -->
<div id="prioritiesModal" class="modal">
    <div class="modal-content modal-lg">
        <span class="close">&times;</span>
        <h3>תעדופי פרויקטים לפי שופטים</h3>

        <div class="mentors-select-container">
            <label for="select-mentor">בחר שופט להצגת התעדופים:</label>
            <select id="select-mentor" class="mentor-select">
                <option value="">-- בחר שופט --</option>
                <?php

                $mentors_sql = "SELECT DISTINCT ps.mentor_email 
                                FROM project_selections ps
                                JOIN projects p ON ps.project_id = p.id
                                WHERE p.coordinator_id = ?
                                ORDER BY ps.mentor_email";
                $mentors_list = db_fetch_all($mentors_sql, 'i', [$coordinator_id]);

                if ($mentors_list) {
                    foreach ($mentors_list as $mentor) {
                        echo '<option value="' . htmlspecialchars($mentor['mentor_email']) . '">' .
                            htmlspecialchars($mentor['mentor_email']) . '</option>';
                    }
                }
                ?>
            </select>
        </div>

        <div id="mentor-priorities-container">
            <div class="no-mentor-selected">בחר שופט כדי להציג את תעדופי הפרויקטים</div>
        </div>
    </div>
</div>
<!-- קוד JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function () {

        const fileDropzone = document.getElementById('fileDropzone');
        const fileInput = document.getElementById('fileInput');
        const startImportBtn = document.getElementById('startImportBtn');
        const cancelImportBtn = document.getElementById('cancelImportBtn');
        const addMappingRowBtn = document.getElementById('addMappingRow');
        const previewTable = document.getElementById('preview-table').querySelector('tbody');
        const previewCount = document.getElementById('preview-count');
        const importProgress = document.getElementById('import-progress');
        const mentorsAdded = document.getElementById('mentors-added');
        const sessionsAdded = document.getElementById('sessions-added');
        const assignmentsAdded = document.getElementById('assignments-added');
        const progressFill = document.querySelector('.progress-fill');
        const progressText = document.querySelector('.progress-text');
        const importMessages = document.getElementById('import-messages');


        let excelData = null;


        fileDropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            fileDropzone.classList.add('drag-over');
        });

        fileDropzone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            fileDropzone.classList.remove('drag-over');
        });

        fileDropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            fileDropzone.classList.remove('drag-over');

            if (e.dataTransfer.files.length) {
                handleFile(e.dataTransfer.files[0]);
            }
        });

        fileDropzone.addEventListener('click', function () {
            fileInput.click();
        });

        fileInput.addEventListener('change', function (e) {
            if (e.target.files.length) {
                handleFile(e.target.files[0]);
            }
        });


        function handleFile(file) {
            if (!file.name.endsWith('.xlsx')) {
                alert('אנא בחר קובץ Excel בפורמט .xlsx');
                return;
            }


            fileDropzone.classList.add('has-file');
            fileDropzone.querySelector('p').textContent = file.name;
            startImportBtn.disabled = false;


            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });


                    const firstSheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[firstSheetName];


                    excelData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });


                    generatePreview();
                } catch (err) {
                    alert('שגיאה בקריאת הקובץ: ' + err.message);
                    console.error(err);
                }
            };
            reader.readAsArrayBuffer(file);
        }


        addMappingRowBtn.addEventListener('click', function () {
            const container = document.getElementById('session_mapping_container');
            const rowCount = container.children.length;


            const nextColumn = String.fromCharCode(69 + rowCount - 3);
            const nextSession = String.fromCharCode(68 + rowCount - 3);

            const newRow = document.createElement('div');
            newRow.className = 'session-map-row';
            newRow.innerHTML = `
            <input type="text" value="${nextColumn}" class="column-input" placeholder="עמודה">
            <span class="equals-sign">=</span>
            <input type="text" value="${nextSession}" class="session-input" placeholder="מושב">
            <button type="button" class="btn-remove-row"><i class="fas fa-times"></i></button>
        `;

            container.appendChild(newRow);


            newRow.querySelector('.btn-remove-row').addEventListener('click', function () {
                container.removeChild(newRow);

                if (excelData) {
                    generatePreview();
                }
            });


            if (excelData) {
                generatePreview();
            }
        });


        document.querySelectorAll('.btn-remove-row').forEach(button => {
            button.addEventListener('click', function () {
                const row = this.parentElement;
                row.parentElement.removeChild(row);

                if (excelData) {
                    generatePreview();
                }
            });
        });


        function generatePreview() {
            if (!excelData || excelData.length < 2) {
                previewTable.innerHTML = '<tr><td colspan="2">אין נתונים להצגה</td></tr>';
                previewCount.textContent = '(0 שורות)';
                return;
            }

            const mentorColumn = document.getElementById('mentor_column').value.toUpperCase().charCodeAt(0) - 65;


            const sessionMapping = [];
            document.querySelectorAll('.session-map-row').forEach(row => {
                const columnInput = row.querySelector('.column-input').value.toUpperCase().charCodeAt(0) - 65;
                const sessionInput = row.querySelector('.session-input').value;

                if (!isNaN(columnInput) && sessionInput) {
                    sessionMapping.push({
                        column: columnInput,
                        session: sessionInput
                    });
                }
            });

            previewTable.innerHTML = '';
            let validRowCount = 0;


            for (let i = 1; i < Math.min(excelData.length, 11); i++) {
                const row = excelData[i];
                if (!row[mentorColumn]) continue;

                validRowCount++;


                const sessions = [];
                sessionMapping.forEach(mapping => {
                    if (row[mapping.column] && (row[mapping.column] === 'X' || row[mapping.column] === 'V' || row[mapping.column] === '✓' || row[mapping.column] === true || row[mapping.column] === 1)) {
                        sessions.push(mapping.session);
                    }
                });

                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${row[mentorColumn]}</td>
                <td>${sessions.join(', ') || 'אין מושבים'}</td>
            `;
                previewTable.appendChild(tr);
            }


            if (excelData.length > 11) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td colspan="2" style="text-align: center;">... ועוד ${excelData.length - 11} שורות</td>
            `;
                previewTable.appendChild(tr);
            }


            const totalRowCount = excelData.length - 1;
            previewCount.textContent = `(${totalRowCount} שורות)`;
        }


        startImportBtn.addEventListener('click', function () {
            if (!excelData || excelData.length < 2) {
                alert('אין נתונים לייבוא');
                return;
            }

            const mentorColumn = document.getElementById('mentor_column').value.toUpperCase().charCodeAt(0) - 65;


            const sessionMapping = [];
            document.querySelectorAll('.session-map-row').forEach(row => {
                const columnInput = row.querySelector('.column-input').value.toUpperCase().charCodeAt(0) - 65;
                const sessionInput = row.querySelector('.session-input').value;

                if (!isNaN(columnInput) && sessionInput) {
                    sessionMapping.push({
                        column: columnInput,
                        session: sessionInput
                    });
                }
            });


            const importData = [];


            for (let i = 1; i < excelData.length; i++) {
                const row = excelData[i];
                const mentorEmail = row[mentorColumn];

                if (!mentorEmail) continue;


                const sessions = [];
                sessionMapping.forEach(mapping => {
                    if (row[mapping.column] && (row[mapping.column] === 'X' || row[mapping.column] === 'V' || row[mapping.column] === '✓' || row[mapping.column] === true || row[mapping.column] === 1)) {
                        sessions.push(mapping.session);
                    }
                });

                importData.push({
                    mentor: mentorEmail,
                    sessions: sessions
                });
            }


            importProgress.style.display = 'block';
            importMessages.innerHTML = '';
            addImportMessage('מתחיל ייבוא...', 'info');


            const formData = new FormData();
            formData.append('action', 'import_mentors_sessions');
            formData.append('data', JSON.stringify(importData));
            formData.append('csrf_token', '<?php echo get_csrf_token(); ?>');

            fetch('<?php echo get_site_url(); ?>/pages/import_excel_data.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {

                        mentorsAdded.textContent = data.stats.mentors_added;
                        sessionsAdded.textContent = data.stats.sessions_added;
                        assignmentsAdded.textContent = data.stats.assignments_added;


                        updateProgress(100);


                        addImportMessage('הייבוא הושלם בהצלחה!', 'success');


                        if (data.warnings && data.warnings.length > 0) {
                            data.warnings.forEach(warning => {
                                addImportMessage(warning, 'warning');
                            });
                        }


                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {

                        addImportMessage('שגיאה בייבוא: ' + data.message, 'error');
                        updateProgress(100, true);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    addImportMessage('שגיאה בתקשורת עם השרת: ' + error.message, 'error');
                    updateProgress(100, true);
                });


            simulateProgress();
        });


        cancelImportBtn.addEventListener('click', function () {
            fileDropzone.classList.remove('has-file');
            fileDropzone.querySelector('p').textContent = 'גרור לכאן קובץ Excel או לחץ לבחירת קובץ';
            fileInput.value = '';
            excelData = null;
            startImportBtn.disabled = true;
            previewTable.innerHTML = '<tr><td colspan="2">אין נתונים להצגה</td></tr>';
            previewCount.textContent = '(0 שורות)';
            importProgress.style.display = 'none';
        });


        function simulateProgress() {
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 10;
                if (progress > 90) {
                    clearInterval(interval);
                    progress = 90;
                }
                updateProgress(progress);
            }, 300);
        }


        function updateProgress(percentage, isError = false) {
            percentage = Math.min(100, Math.max(0, percentage));
            progressFill.style.width = percentage + '%';
            progressText.textContent = Math.round(percentage) + '%';

            if (isError) {
                progressFill.style.backgroundColor = '#e74c3c';
            }
        }


        function addImportMessage(message, type = 'info') {
            const div = document.createElement('div');
            div.className = 'message ' + type;
            div.textContent = message;
            importMessages.appendChild(div);
            importMessages.scrollTop = importMessages.scrollHeight;
        }


        document.getElementById('mentor_column').addEventListener('input', function () {
            if (excelData) {
                generatePreview();
            }
        });
    });
</script>
<!-- JavaScript להצגת תעדופי שופטים ועריכת פרויקטים -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>

    function showPrioritiesModal() {
        document.getElementById('prioritiesModal').style.display = 'block';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('editProjectModal');
        const closeBtn = modal.querySelector('.close');
        const editButtons = document.querySelectorAll('.edit-project-btn');
        const editForm = document.getElementById('edit-project-form');
        const editTitle = document.getElementById('edit_title');
        const editSession = document.getElementById('edit_session');
        const editDetails = document.getElementById('edit_details');
        const selectedWarning = document.getElementById('selected-warning');


        editButtons.forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const title = this.getAttribute('data-title');
                const session = this.getAttribute('data-session');
                const details = this.getAttribute('data-details');
                const isActive = this.getAttribute('data-active') === '1';
                const isSelected = this.getAttribute('data-selected') === '1';

                document.getElementById('edit_project_id').value = id;
                editTitle.value = title;
                editSession.value = session;
                editDetails.value = details;
                document.getElementById('edit_is_active').checked = isActive;


                if (isSelected) {
                    editTitle.disabled = true;
                    editSession.disabled = true;
                    editDetails.disabled = true;
                    selectedWarning.style.display = 'block';
                } else {
                    editTitle.disabled = false;
                    editSession.disabled = false;
                    editDetails.disabled = false;
                    selectedWarning.style.display = 'none';
                }

                modal.style.display = 'block';
            });
        });


        closeBtn.addEventListener('click', function () {
            modal.style.display = 'none';
        });


        window.addEventListener('click', function (event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });


        const prioritiesModal = document.getElementById('prioritiesModal');
        const prioritiesCloseBtn = prioritiesModal.querySelector('.close');
        const mentorSelect = document.getElementById('select-mentor');


        prioritiesCloseBtn.addEventListener('click', function () {
            prioritiesModal.style.display = 'none';
        });


        window.addEventListener('click', function (event) {
            if (event.target == prioritiesModal) {
                prioritiesModal.style.display = 'none';
            }
        });


        mentorSelect.addEventListener('change', function () {
            const mentorEmail = this.value;
            const container = document.getElementById('mentor-priorities-container');

            if (!mentorEmail) {
                container.innerHTML = '<div class="no-mentor-selected">בחר שופט כדי להציג את תעדופי הפרויקטים</div>';
                return;
            }


            container.innerHTML = '<div class="loading-indicator">טוען נתונים...</div>';


            const baseUrl = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
            const ajaxUrl = baseUrl + 'ajax/get_mentor_priorities.php';


            fetch(ajaxUrl + '?mentor=' + encodeURIComponent(mentorEmail))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('שגיאת רשת: ' + response.status);
                    }
                    return response.text();
                })
                .then(text => {

                    if (text.trim().startsWith('<!DOCTYPE html>') || text.trim().startsWith('<')) {
                        throw new Error('התקבל HTML במקום JSON');
                    }

                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('שגיאה בפרסור המידע: ' + e.message);
                    }
                })
                .then(data => {
                    if (data.error) {
                        container.innerHTML = `<div class="error-message">${data.error}</div>`;
                        return;
                    }

                    if (!data.priorities || data.priorities.length === 0) {
                        container.innerHTML = `<div class="no-priorities">השופט ${mentorEmail} לא בחר פרויקטים</div>`;
                        return;
                    }

                    let html = '<table class="priorities-table">';
                    html += '<thead><tr><th>עדיפות</th><th>כותרת הפרויקט</th><th>מושב</th></tr></thead>';
                    html += '<tbody>';

                    data.priorities.forEach(priority => {
                        html += `<tr>
                        <td class="priority-number">${priority.priority}</td>
                        <td>${priority.title}</td>
                        <td>${priority.session}</td>
                    </tr>`;
                    });

                    html += '</tbody></table>';
                    container.innerHTML = html;
                })
                .catch(error => {
                    container.innerHTML = `
                    <div class="error-message">שגיאה בטעינת הנתונים: ${error.message}</div>
                    <div class="error-details">
                        <p>אנא בדוק את הפרטים הבאים:</p>
                        <ol>
                            <li>וודא שיש תיקייה בשם "ajax" באותה רמה כמו הדף הנוכחי</li>
                            <li>וודא שהקובץ get_mentor_priorities.php קיים בתיקייה זו</li>
                            <li>וודא שיש הרשאות קריאה לקובץ (644)</li>
                            <li>בדוק את קונסולת הדפדפן לשגיאות נוספות</li>
                        </ol>
                        <p>הנתיב שנבדק: ${ajaxUrl}</p>
                    </div>
                `;
                });
        });



        const judgesChartData = <?php echo $chart_data_json; ?>;

        if (judgesChartData && judgesChartData.length > 0) {
            const ctx1 = document.getElementById('judgesDistributionChart').getContext('2d');

            const labels1 = judgesChartData.map(item => item.session);
            const data1 = judgesChartData.map(item => item.judges_count);


            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: labels1,
                    datasets: [{
                        label: 'מספר שופטים',
                        data: data1,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(255, 159, 64, 0.8)',
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(255, 205, 86, 0.8)'
                        ],
                        borderColor: [
                            'rgb(54, 162, 235)',
                            'rgb(75, 192, 192)',
                            'rgb(153, 102, 255)',
                            'rgb(255, 159, 64)',
                            'rgb(255, 99, 132)',
                            'rgb(255, 205, 86)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0,
                                font: {
                                    size: 14
                                }
                            },
                            title: {
                                display: true,
                                text: 'מספר שופטים',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 14
                                }
                            },
                            title: {
                                display: true,
                                text: 'מושבים',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            titleFont: {
                                size: 16
                            },
                            bodyFont: {
                                size: 14
                            },
                            callbacks: {
                                label: function (context) {
                                    return `מספר שופטים: ${context.raw}`;
                                }
                            }
                        }
                    }
                }
            });
        }


        const statusChartData = <?php echo $project_status_json; ?>;

        if (statusChartData && statusChartData.length > 0) {
            const ctx2 = document.getElementById('projectStatusChart').getContext('2d');

            const labels2 = statusChartData.map(item => item.status);
            const data2 = statusChartData.map(item => item.count);


            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: labels2,
                    datasets: [{
                        data: data2,
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 99, 132, 0.8)'
                        ],
                        borderColor: [
                            'rgb(54, 162, 235)',
                            'rgb(255, 99, 132)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 14
                                }
                            }
                        },
                        tooltip: {
                            titleFont: {
                                size: 16
                            },
                            bodyFont: {
                                size: 14
                            },
                            callbacks: {
                                label: function (context) {
                                    const total = context.dataset.data.reduce((sum, val) => sum + val, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return `${context.label}: ${context.raw} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }


        const projectsSessionData = <?php echo $projects_by_session_json; ?>;

        if (projectsSessionData && projectsSessionData.length > 0) {
            const ctx3 = document.getElementById('projectsBySessionChart').getContext('2d');

            const labels3 = projectsSessionData.map(item => item.session);
            const selectedData = projectsSessionData.map(item => item.selected);
            const notSelectedData = projectsSessionData.map(item => item.not_selected);


            new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: labels3,
                    datasets: [
                        {
                            label: 'פרויקטים שנבחרו',
                            data: selectedData,
                            backgroundColor: 'rgba(54, 162, 235, 0.8)',
                            borderColor: 'rgb(54, 162, 235)',
                            borderWidth: 1
                        },
                        {
                            label: 'פרויקטים שטרם נבחרו',
                            data: notSelectedData,
                            backgroundColor: 'rgba(255, 99, 132, 0.8)',
                            borderColor: 'rgb(255, 99, 132)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            stacked: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0,
                                font: {
                                    size: 14
                                }
                            },
                            title: {
                                display: true,
                                text: 'מספר פרויקטים',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        },
                        x: {
                            stacked: true,
                            ticks: {
                                font: {
                                    size: 14
                                }
                            },
                            title: {
                                display: true,
                                text: 'מושבים',
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14
                                }
                            }
                        },
                        tooltip: {
                            titleFont: {
                                size: 16
                            },
                            bodyFont: {
                                size: 14
                            }
                        }
                    }
                }
            });
        }
    });
</script>

<style>
    /* סגנונות לאזור drag & drop */
    .import-container {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .dropzone {
        flex: 1;
        border: 2px dashed #ccc;
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s;
        background-color: #f9f9f9;
        min-height: 180px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .dropzone.drag-over {
        background-color: #e8f4fd;
        border-color: #3498db;
    }

    .dropzone.has-file {
        background-color: #e8f5e9;
        border-color: #2ecc71;
    }

    .dropzone-content {
        color: #666;
    }

    .dropzone-content i {
        margin-bottom: 15px;
        color: #3498db;
    }

    .dropzone-content p {
        margin: 5px 0;
    }

    .dropzone-content p.small {
        font-size: 0.8rem;
        color: #999;
    }

    .import-options {
        flex: 1;
        padding: 20px;
        border: 1px solid #eee;
        border-radius: 10px;
        background-color: #fff;
    }

    .import-options h4 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #2c3e50;
        border-bottom: 1px solid #eee;
        padding-bottom: 8px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
    }

    .form-control {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
    }

    .form-group small {
        display: block;
        margin-top: 5px;
        color: #7f8c8d;
        font-size: 0.85rem;
    }

    #session_mapping_container {
        margin-bottom: 10px;
    }

    .session-map-row {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        gap: 10px;
    }

    .column-input,
    .session-input {
        width: 80px;
        padding: 6px;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-align: center;
    }

    .equals-sign {
        font-weight: bold;
        margin: 0 5px;
    }

    .btn-remove-row {
        background: none;
        border: none;
        color: #e74c3c;
        cursor: pointer;
        font-size: 1rem;
        padding: 0 5px;
    }

    .import-actions {
        margin-top: 20px;
        display: flex;
        gap: 10px;
    }

    .import-preview {
        margin-top: 30px;
    }

    .import-preview h4 {
        margin-bottom: 15px;
        color: #2c3e50;
        border-bottom: 1px solid #eee;
        padding-bottom: 8px;
    }

    #preview-count {
        font-size: 0.9rem;
        color: #7f8c8d;
        font-weight: normal;
    }

    /* סגנונות לסרגל התקדמות */
    #import-progress {
        margin-top: 30px;
        padding: 20px;
        border: 1px solid #eee;
        border-radius: 10px;
        background-color: #f9f9f9;
    }

    .progress-container {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .progress-bar {
        flex-grow: 1;
        height: 24px;
        background-color: #eee;
        border-radius: 12px;
        overflow: hidden;
        margin-right: 10px;
    }

    .progress-fill {
        height: 100%;
        background-color: #3498db;
        border-radius: 12px;
        transition: width 0.3s;
    }

    .progress-text {
        font-weight: bold;
        width: 45px;
        text-align: left;
    }

    .progress-stats {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .stat-item {
        text-align: center;
        flex: 1;
        padding: 10px;
        background-color: white;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .stat-label {
        font-size: 0.85rem;
        color: #7f8c8d;
        display: block;
        margin-bottom: 5px;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: bold;
        color: #3498db;
    }

    #import-messages {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #eee;
        border-radius: 5px;
        padding: 10px;
        background-color: white;
    }

    .message {
        padding: 5px 10px;
        margin-bottom: 5px;
        border-radius: 3px;
    }

    .message.info {
        background-color: #e8f4fd;
        border-left: 3px solid #3498db;
    }

    .message.success {
        background-color: #e8f5e9;
        border-left: 3px solid #2ecc71;
    }

    .message.warning {
        background-color: #fcf8e3;
        border-left: 3px solid #f39c12;
    }

    .message.error {
        background-color: #fcf2f2;
        border-left: 3px solid #e74c3c;
    }

    /* סגנונות למודאל */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 20px;
        border-radius: 8px;
        width: 60%;
        max-width: 700px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .modal-lg {
        width: 80%;
        max-width: 900px;
    }

    .close {
        color: #aaa;
        float: left;
        /* התאמה ל-RTL */
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover {
        color: black;
    }

    .status-active {
        color: #2ecc71;
        font-weight: bold;
    }

    .status-inactive {
        color: #e74c3c;
    }

    .not-selected {
        color: #7f8c8d;
        font-style: italic;
    }

    .mentor-email {
        font-weight: bold;
        color: #2980b9;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .checkbox-label input[type="checkbox"] {
        margin-left: 10px;
    }

    /* סגנונות לכפתורי ייצוא */
    .dashboard-actions {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 20px;
    }

    .btn-secondary {
        background-color: #f8f9fa;
        color: #495057;
        border: 1px solid #ced4da;
        padding: 8px 12px;
        transition: all 0.3s;
    }

    .btn-secondary:hover:not([disabled]) {
        background-color: #e9ecef;
        border-color: #adb5bd;
    }

    .btn-secondary[disabled] {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-secondary i {
        margin-left: 5px;
    }

    /* סגנונות לכפתור הסרת שופט */
    .btn-warning {
        background-color: #ff9800;
        color: white;
        transition: all 0.3s;
    }

    .btn-warning:hover {
        background-color: #e68300;
    }

    .btn-danger {
        background-color: #e74c3c;
        color: white;
        transition: all 0.3s;
    }

    .btn-danger:hover {
        background-color: #c0392b;
    }

    .btn-small {
        padding: 5px 10px;
        font-size: 0.85rem;
        margin: 0 2px;
    }

    /* סגנונות לקלפי סטטיסטיקות */
    .dashboard-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin: 20px 0;
    }

    .stat-card {
        background-color: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 20px;
        flex: 1;
        min-width: 200px;
        text-align: center;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
        color: #3498db;
        margin-bottom: 10px;
    }

    .stat-label {
        font-size: 1rem;
        color: #7f8c8d;
    }

    /* הדגשת שורת פרויקט שנבחר על ידי שופט */
    .project-selected {
        background-color: rgba(52, 152, 219, 0.05);
    }

    .project-selected:hover {
        background-color: rgba(52, 152, 219, 0.1);
    }

    /* סגנונות לטבלה */
    .table-responsive {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    table th,
    table td {
        padding: 10px;
        border-bottom: 1px solid #e9ecef;
    }

    table th {
        background-color: #f8f9fa;
        text-align: right;
        font-weight: 600;
    }

    table tr:hover {
        background-color: #f8f9fa;
    }

    .remove-mentor-form {
        margin: 0 5px;
    }

    /* סגנונות נוספים לתצוגת תעדופים */
    .mentors-select-container {
        margin-bottom: 20px;
    }

    .mentor-select {
        padding: 8px;
        width: 100%;
        max-width: 500px;
        border-radius: 4px;
        border: 1px solid #ccc;
        font-size: 16px;
    }

    .no-mentor-selected,
    .no-priorities,
    .error-message {
        padding: 20px;
        text-align: center;
        color: #666;
        background: #f9f9f9;
        border-radius: 5px;
        margin: 20px 0;
    }

    .error-message {
        color: #e74c3c;
        background: #fce5e5;
    }

    .priorities-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .priorities-table th,
    .priorities-table td {
        padding: 12px;
        border: 1px solid #ddd;
        text-align: right;
    }

    .priorities-table th {
        background-color: #f2f2f2;
        font-weight: bold;
    }

    .priorities-table tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    .priorities-table tr:hover {
        background-color: #eaf5ff;
    }

    .priority-number {
        font-weight: bold;
        text-align: center;
        color: #2980b9;
    }

    #mentor-priorities-container {
        max-height: 600px;
        overflow-y: auto;
    }

    /* אינדיקטור טעינה */
    .loading-indicator {
        text-align: center;
        padding: 20px;
        color: #666;
    }

    .loading-indicator:after {
        content: "...";
        animation: dots 1.5s steps(5, end) infinite;
    }

    @keyframes dots {

        0%,
        20% {
            content: ".";
        }

        40% {
            content: "..";
        }

        60% {
            content: "...";
        }

        80%,
        100% {
            content: "";
        }
    }

    /* סגנונות לתצוגת הגרפים */
    .analytics-dashboard {
        margin: 30px 0;
    }

    .analytics-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 20px;
    }

    .analytics-card {
        background-color: #ffffff;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        padding: 20px;
        flex: 1;
        min-width: 300px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .analytics-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .analytics-card.full-width {
        flex-basis: 100%;
    }

    .chart-container {
        width: 100%;
        height: 350px;
        margin: 15px 0;
        padding: 10px;
        background-color: #fcfcfc;
        border-radius: 8px;
        position: relative;
    }

    .analytics-card h3.card-title {
        color: #2c3e50;
        font-size: 1.4rem;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
        text-align: center;
    }

    .alert-info {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
        padding: 15px;
        border-radius: 5px;
        margin: 20px 0;
        text-align: center;
    }

    /* רספונסיבי */
    @media (max-width: 768px) {
        .analytics-row {
            flex-direction: column;
        }

        .analytics-card {
            width: 100%;
        }
    }
</style>

<?php

include __DIR__ . '/../templates/footer.php';
?>