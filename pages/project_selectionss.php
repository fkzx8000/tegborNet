<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$debug_messages = [];
$fatal_error = false;


function debug_log($message, $is_fatal = false)
{
    global $debug_messages, $fatal_error;
    $debug_messages[] = $message;
    if ($is_fatal) {
        $fatal_error = true;
    }
}


function find_file($file_name, $possible_paths = [])
{

    if (empty($possible_paths)) {
        $possible_paths = [
            __DIR__ . '/includes/',
            __DIR__ . '/../includes/',
            dirname(dirname(__FILE__)) . '/includes/',
            $_SERVER['DOCUMENT_ROOT'] . '/includes/',
            $_SERVER['DOCUMENT_ROOT'] . '/pages/includes/',
            __DIR__ . '/',
            dirname(__DIR__) . '/'
        ];
    }

    foreach ($possible_paths as $path) {
        $full_path = $path . $file_name;
        if (file_exists($full_path)) {
            return $full_path;
        }
    }

    return false;
}


function load_required_file($file_name, $possible_paths = [])
{
    global $debug_messages;

    $file_path = find_file($file_name, $possible_paths);

    if ($file_path) {
        debug_log("מצאתי את $file_name בנתיב: $file_path");
        require_once $file_path;
        return true;
    } else {
        debug_log("לא הצלחתי למצוא את הקובץ $file_name", true);
        return false;
    }
}


$db_file = find_file('database.php', [
    __DIR__ . '/config/',
    __DIR__ . '/../config/',
    dirname(dirname(__FILE__)) . '/config/',
    $_SERVER['DOCUMENT_ROOT'] . '/config/',
    $_SERVER['DOCUMENT_ROOT'] . '/pages/config/'
]);

if ($db_file) {
    debug_log("מצאתי את database.php בנתיב: $db_file");
    require_once $db_file;
} else {
    debug_log("לא הצלחתי למצוא את הקובץ database.php", true);
}


$db_functions_file = find_file('db_functions.php');
if ($db_functions_file) {
    debug_log("מצאתי את db_functions.php בנתיב: $db_functions_file");
    require_once $db_functions_file;
} else {

    debug_log("לא מצאתי את db_functions.php, מגדיר פונקציות בעצמי");


    if (!function_exists('get_database_connection')) {
        function get_database_connection()
        {
            static $conn = null;

            if ($conn !== null) {
                return $conn;
            }


            if (defined('DB_SERVER') && defined('DB_USERNAME') && defined('DB_PASSWORD') && defined('DB_NAME')) {
                $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
                $conn->set_charset('utf8mb4');

                if ($conn->connect_error) {
                    debug_log("שגיאת חיבור למסד נתונים: " . $conn->connect_error, true);
                    return false;
                }

                return $conn;
            } else {
                debug_log("קבועים של מסד נתונים לא מוגדרים", true);
                return false;
            }
        }
    }

    if (!function_exists('db_fetch_all')) {
        function db_fetch_all($sql, $types = null, $params = [])
        {
            $conn = get_database_connection();
            if (!$conn)
                return false;

            $result = [];

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                debug_log("שגיאת SQL בהכנת שאילתה: " . $conn->error);
                return false;
            }

            if ($types !== null && !empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $query_result = $stmt->get_result();

            if (!$query_result) {
                $stmt->close();
                return [];
            }

            while ($row = $query_result->fetch_assoc()) {
                $result[] = $row;
            }

            $stmt->close();
            return $result;
        }
    }

    if (!function_exists('db_fetch_one')) {
        function db_fetch_one($sql, $types = null, $params = [])
        {
            $results = db_fetch_all($sql, $types, $params);

            if ($results && count($results) > 0) {
                return $results[0];
            }

            return null;
        }
    }

    if (!function_exists('db_execute')) {
        function db_execute($sql, $types = null, $params = [])
        {
            $conn = get_database_connection();
            if (!$conn)
                return false;

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                debug_log("שגיאת SQL בהכנת שאילתה: " . $conn->error);
                return false;
            }

            if ($types !== null && !empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $result = $stmt->execute();

            if (!$result) {
                $stmt->close();
                return false;
            }

            $affected_rows = $stmt->affected_rows;
            $stmt->close();

            return $affected_rows;
        }
    }

    if (!function_exists('db_begin_transaction')) {
        function db_begin_transaction()
        {
            $conn = get_database_connection();
            if (!$conn)
                return false;
            return $conn->begin_transaction();
        }
    }

    if (!function_exists('db_commit')) {
        function db_commit()
        {
            $conn = get_database_connection();
            if (!$conn)
                return false;
            return $conn->commit();
        }
    }

    if (!function_exists('db_rollback')) {
        function db_rollback()
        {
            $conn = get_database_connection();
            if (!$conn)
                return false;
            return $conn->rollback();
        }
    }
}


$functions_file = find_file('functions.php');
if ($functions_file) {
    debug_log("מצאתי את functions.php בנתיב: $functions_file");
    require_once $functions_file;
} else {
    debug_log("לא מצאתי את functions.php, ממשיך ללא טעינתו");
}


if (!defined('DB_SERVER'))
    define('DB_SERVER', 'localhost');
if (!defined('DB_USERNAME'))
    define('DB_USERNAME', 'u101146292_Admin');
if (!defined('DB_PASSWORD'))
    define('DB_PASSWORD', 'Dd159159#');
if (!defined('DB_NAME'))
    define('DB_NAME', 'u101146292_TestDb');
if (!defined('SITE_NAME'))
    define('SITE_NAME', 'דורון הוסט');


if (!function_exists('get_site_url')) {
    function get_site_url()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        return $protocol . $domainName;
    }
}


$display_mode = isset($_GET['mode']) ? $_GET['mode'] : 'email_form';
$error_message = '';
$success_message = '';
$mentor_email = '';
$mentor_data = null;
$available_projects = [];


function loadAvailableProjects($mentor_data)
{
    global $available_projects, $debug_messages;

    if (empty($mentor_data) || !isset($mentor_data['coordinator_id'])) {
        debug_log("מנחה ללא מזהה רכז בפונקציית loadAvailableProjects");
        throw new Exception("מידע חסר על המנחה");
    }


    $session_condition = "";
    $params = [];
    $types = "";

    if (!empty($mentor_data['session_restriction'])) {
        $session_condition = " AND p.session = ?";
        $params[] = $mentor_data['session_restriction'];
        $types .= "s";
    }


    $sql = "SELECT p.* FROM projects p
           WHERE p.is_active = 1 
           AND p.coordinator_id = ?
           AND NOT EXISTS (SELECT 1 FROM project_selections ps WHERE ps.project_id = p.id)
           $session_condition
           ORDER BY p.session, p.title";


    array_unshift($params, $mentor_data['coordinator_id']);
    $types = "i" . $types;

    if (function_exists('db_fetch_all')) {
        $available_projects = db_fetch_all($sql, $types, $params);
        if ($available_projects === false) {
            debug_log("שגיאה בשליפת פרויקטים זמינים");
            throw new Exception("שגיאה בשליפת פרויקטים זמינים");
        }
    } else {
        debug_log("הפונקציה db_fetch_all אינה מוגדרת");
        throw new Exception("הפונקציה db_fetch_all אינה מוגדרת");
    }
}


if (!$fatal_error) {

    if (isset($_POST['email']) && !empty($_POST['email'])) {
        try {
            $mentor_email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

            if (!$mentor_email) {
                $error_message = "כתובת האימייל אינה תקינה";
                $display_mode = 'email_form';
            } else {

                $sql = "SELECT * FROM authorized_mentors WHERE email = ?";
                try {
                    $mentor_data = db_fetch_one($sql, 's', [$mentor_email]);

                    if (!$mentor_data) {
                        $error_message = "האימייל שהזנת אינו רשום במערכת כמנחה מורשה";
                        $display_mode = 'email_form';
                    } else {

                        if (isset($mentor_data['level']) && isset($mentor_data['selection_count'])) {
                            if ($mentor_data['level'] == 0 && $mentor_data['selection_count'] >= 2) {
                                $display_mode = 'already_selected';
                            } elseif ($mentor_data['level'] == 1 && isset($_GET['continue']) && $_GET['continue'] == 1) {

                                $display_mode = 'project_list';


                                try {
                                    loadAvailableProjects($mentor_data);
                                } catch (Exception $e) {
                                    debug_log("שגיאה בטעינת פרויקטים: " . $e->getMessage());
                                    $error_message = "שגיאה בטעינת פרויקטים זמינים";
                                    $display_mode = 'email_form';
                                }
                            } else if ($mentor_data['level'] == 0) {

                                $display_mode = 'project_list';


                                try {
                                    loadAvailableProjects($mentor_data);
                                } catch (Exception $e) {
                                    debug_log("שגיאה בטעינת פרויקטים: " . $e->getMessage());
                                    $error_message = "שגיאה בטעינת פרויקטים זמינים";
                                    $display_mode = 'email_form';
                                }
                            } else {

                                $display_mode = 'level1_waiting';
                            }
                        } else {
                            debug_log("חסרים שדות חובה בטבלת authorized_mentors: level או selection_count");
                            $error_message = "שגיאה במבנה טבלת המנחים המורשים";
                            $display_mode = 'email_form';
                        }
                    }
                } catch (Exception $e) {
                    debug_log("שגיאה בשליפת נתוני מנחה: " . $e->getMessage());
                    $error_message = "שגיאה בגישה לנתוני מנחים";
                    $display_mode = 'email_form';
                }
            }
        } catch (Exception $e) {
            debug_log("שגיאה בעיבוד האימייל: " . $e->getMessage());
            $error_message = "שגיאה בעיבוד בקשת האימייל";
            $display_mode = 'email_form';
        }
    }



}


$page_title = 'בחירת פרויקטים למנחים';
$additional_css = ['login.css'];
$skip_sidebar = true;


$header_paths = [
    __DIR__ . '/templates/header.php',
    __DIR__ . '/../templates/header.php',
    dirname(dirname(__FILE__)) . '/templates/header.php',
    $_SERVER['DOCUMENT_ROOT'] . '/templates/header.php',
    $_SERVER['DOCUMENT_ROOT'] . '/pages/templates/header.php'
];

$header_found = false;
foreach ($header_paths as $path) {
    if (file_exists($path)) {
        debug_log("מצאתי את header.php בנתיב: $path");
        try {
            include $path;
            $header_found = true;
            break;
        } catch (Exception $e) {
            debug_log("שגיאה בטעינת header.php: " . $e->getMessage());
        }
    }
}

if (!$header_found) {
    debug_log("לא הצלחתי למצוא את header.php", true);
    echo "<!DOCTYPE html>
    <html dir='rtl' lang='he'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>בחירת פרויקטים למנחים</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px; }
            .login-container { max-width: 800px; margin: 0 auto; }
            .login-card { background: white; border-radius: 5px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .message.error { background: #ffe0e0; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0; border-radius: 4px; }
            .message.success { background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 4px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; margin-bottom: 5px; }
            input[type='email'] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
            .btn { display: inline-block; padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
            .btn-secondary { background: #6c757d; }
            .project-item { border: 1px solid #eee; padding: 15px; margin-bottom: 15px; border-radius: 4px; }
            .debug-container { background: #ffe0e0; border: 2px solid #ff0000; padding: 10px; margin: 10px 0; direction: ltr; text-align: left; }
        </style>
    </head>
    <body>";
}
?>

<?php if (!empty($debug_messages)): ?>
    <div class="debug-container">
        <h3>הודעות דיבאג:</h3>
        <ul>
            <?php foreach ($debug_messages as $message): ?>
                <li><?php echo htmlspecialchars($message); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="login-container project-selection-container">
    <div class="login-card">
        <h2>בחירת פרויקטים למנחים</h2>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($fatal_error): ?>
            <div class="message error">אירעה שגיאה קריטית שמונעת המשך פעולה. ראה פרטים למעלה.</div>
        <?php elseif ($display_mode == 'email_form'): ?>
            <!-- טופס הזנת אימייל -->
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="project-email-form">
                <div class="form-group">
                    <label for="email">כתובת האימייל שלך:</label>
                    <input type="email" id="email" name="email" required
                        value="<?php echo htmlspecialchars($mentor_email); ?>" placeholder="הזן את כתובת האימייל שלך">
                </div>
                <button type="submit" class="btn btn-primary">המשך לבחירת פרויקטים</button>
            </form>

        <?php elseif ($display_mode == 'project_list' && !empty($available_projects)): ?>
            <!-- רשימת פרויקטים זמינים לבחירה -->
            <p>שלום, <?php echo htmlspecialchars($mentor_email); ?></p>
            <p>יש באפשרותך לבחור מתוך הפרויקטים הבאים:</p>

            <div class="available-projects">
                <?php foreach ($available_projects as $project): ?>
                    <div class="project-item">
                        <h3><?php echo htmlspecialchars(isset($project['title']) ? $project['title'] : 'שם פרויקט חסר'); ?></h3>
                        <p class="project-session">מושב:
                            <?php echo htmlspecialchars(isset($project['session']) ? $project['session'] : 'לא צוין'); ?></p>
                        <div class="project-details">
                            <?php echo nl2br(htmlspecialchars(isset($project['details']) ? $project['details'] : 'אין פרטים')); ?>
                        </div>
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                            <input type="hidden" name="mentor_email" value="<?php echo htmlspecialchars($mentor_email); ?>">
                            <input type="hidden" name="project_id"
                                value="<?php echo isset($project['id']) ? $project['id'] : 0; ?>">
                            <button type="submit" name="select_project" class="btn btn-primary">בחר פרויקט זה</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($display_mode == 'project_list' && empty($available_projects)): ?>
            <!-- אין פרויקטים זמינים -->
            <p>שלום, <?php echo htmlspecialchars($mentor_email); ?></p>
            <p>לא נמצאו פרויקטים זמינים לבחירה כרגע.</p>
            <p>אנא נסה שוב מאוחר יותר או פנה לרכז שלך.</p>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">חזרה</a>

        <?php elseif ($display_mode == 'thank_you'): ?>
            <!-- הודעת תודה לאחר בחירה -->
            <div class="thank-you-message">
                <h3>תודה על בחירתך!</h3>
                <p>הפרויקט נשמר בהצלחה.</p>
                <?php if (isset($mentor_data) && isset($mentor_data['level']) && isset($mentor_data['selection_count']) && $mentor_data['level'] == 0 && $mentor_data['selection_count'] < 2): ?>
                    <p>באפשרותך לבחור עוד <?php echo 2 - $mentor_data['selection_count']; ?> פרויקטים.</p>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-primary">בחר פרויקט נוסף</a>
                <?php else: ?>
                    <p>השלמת את תהליך בחירת הפרויקטים.</p>
                    <p>כאשר יהיו פרויקטים נוספים זמינים לבחירה, תקבל הודעה במייל.</p>
                <?php endif; ?>
            </div>

        <?php elseif ($display_mode == 'already_selected'): ?>
            <!-- כבר בחרת את מספר הפרויקטים המרבי -->
            <div class="already-selected">
                <h3>הודעה</h3>
                <p>כבר בחרת את מספר הפרויקטים המרבי המותר לך
                    (<?php echo isset($mentor_data['selection_count']) ? $mentor_data['selection_count'] : '?'; ?>
                    פרויקטים).</p>
                <p>כאשר יהיו פרויקטים נוספים זמינים לבחירה, תקבל הודעה במייל.</p>
            </div>

        <?php elseif ($display_mode == 'level1_waiting'): ?>
            <!-- מנחה ברמה 1 שממתין לאישור להמשיך -->
            <div class="level1-waiting">
                <h3>הודעה</h3>
                <p>שלום, <?php echo htmlspecialchars($mentor_email); ?></p>
                <p>בחרת כבר <?php echo isset($mentor_data['selection_count']) ? $mentor_data['selection_count'] : '?'; ?>
                    פרויקטים.</p>
                <p>כאשר יהיו פרויקטים נוספים זמינים לבחירה, תקבל הודעה במייל.</p>
                <?php if (isset($mentor_data['level']) && $mentor_data['level'] == 1): ?>
                    <p>התקבלת אישור לבחירת פרויקטים נוספים. האם תרצה להמשיך כעת?</p>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?continue=1'); ?>" class="btn btn-primary">המשך
                        לבחירת פרויקטים נוספים</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php

$footer_paths = [
    __DIR__ . '/templates/footer.php',
    __DIR__ . '/../templates/footer.php',
    dirname(dirname(__FILE__)) . '/templates/footer.php',
    $_SERVER['DOCUMENT_ROOT'] . '/templates/footer.php',
    $_SERVER['DOCUMENT_ROOT'] . '/pages/templates/footer.php'
];

$footer_found = false;
foreach ($footer_paths as $path) {
    if (file_exists($path)) {
        debug_log("מצאתי את footer.php בנתיב: $path");
        try {
            include $path;
            $footer_found = true;
            break;
        } catch (Exception $e) {
            debug_log("שגיאה בטעינת footer.php: " . $e->getMessage());
        }
    }
}

if (!$footer_found) {
    echo "</body></html>";
}
?>