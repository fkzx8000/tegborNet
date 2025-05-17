<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['admin'], "Access denied. Admins only.");


$roles_sql = "SELECT * FROM roles ORDER BY role_name ASC";
$roles = db_fetch_all($roles_sql);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_video'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $video_url = trim($_POST['video_url']);
    $video_title = trim($_POST['video_title']);
    $selected_roles = isset($_POST['roles']) ? $_POST['roles'] : [];

    if (!empty($video_url) && !empty($video_title) && !empty($selected_roles)) {
        db_begin_transaction();

        try {

            $video_sql = "INSERT INTO videos (url, title) VALUES (?, ?)";
            $video_id = db_insert($video_sql, 'ss', [$video_url, $video_title]);

            if ($video_id === false) {
                throw new Exception("שגיאה בהוספת וידאו: " . db_error());
            }


            $role_sql = "INSERT INTO video_roles (video_id, role_id) VALUES (?, ?)";

            foreach ($selected_roles as $role_id) {
                $result = db_execute($role_sql, 'ii', [$video_id, $role_id]);

                if ($result === false) {
                    throw new Exception("שגיאה בהוספת תפקיד לוידאו: " . db_error());
                }
            }

            db_commit();
            set_success_message("הוידאו נוסף בהצלחה!");

        } catch (Exception $e) {
            db_rollback();
            set_error_message($e->getMessage());
        }
    } else {
        set_error_message("אנא מלא את כל השדות ובחר לפחות תפקיד אחד.");
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_video'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $video_id = intval($_POST['video_id']);

    db_begin_transaction();

    try {

        $delete_roles_sql = "DELETE FROM video_roles WHERE video_id = ?";
        $result = db_execute($delete_roles_sql, 'i', [$video_id]);

        if ($result === false) {
            throw new Exception("שגיאה במחיקת הקשרי תפקידים לוידאו: " . db_error());
        }


        $delete_video_sql = "DELETE FROM videos WHERE id = ?";
        $result = db_execute($delete_video_sql, 'i', [$video_id]);

        if ($result === false) {
            throw new Exception("שגיאה במחיקת הוידאו: " . db_error());
        }

        db_commit();
        set_success_message("הוידאו נמחק בהצלחה!");

    } catch (Exception $e) {
        db_rollback();
        set_error_message($e->getMessage());
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_meeting'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $role_id = intval($_POST['role_id']);
    $meeting_time_str = $_POST['meeting_time'];
    $meeting_link = trim($_POST['meeting_link']);


    $meeting_time = new DateTime($meeting_time_str);
    $formatted_meeting_time = $meeting_time->format('Y-m-d H:i:s');


    if (!filter_var($meeting_link, FILTER_VALIDATE_URL)) {
        set_error_message("אנא הזן כתובת URL תקינה לפגישת הזום.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    if (
        !preg_match('/^https:\/\/([\w-]+\.)*zoom\.us\//i', $meeting_link) &&
        !preg_match('/^https:\/\/([\w-]+\.)*zoomgov\.com\//i', $meeting_link)
    ) {
        set_error_message("אנא הזן קישור זום תקין המתחיל ב-https.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if ($role_id > 0 && !empty($formatted_meeting_time) && !empty($meeting_link)) {
        $sql = "INSERT INTO zoom_meetings (role_id, meeting_time, meeting_link) VALUES (?, ?, ?)";
        $result = db_execute($sql, 'iss', [$role_id, $formatted_meeting_time, $meeting_link]);

        if ($result !== false) {
            set_success_message("פגישת הזום תוזמנה בהצלחה!");
        } else {
            set_error_message("שגיאה בתזמון פגישת זום: " . db_error());
        }
    } else {
        set_error_message("אנא מלא את כל השדות באופן תקין.");
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


$videos_sql = "
    SELECT videos.id, videos.title, videos.url, GROUP_CONCAT(roles.role_name SEPARATOR ', ') AS assigned_roles
    FROM videos
    LEFT JOIN video_roles ON videos.id = video_roles.video_id
    LEFT JOIN roles ON video_roles.role_id = roles.id
    GROUP BY videos.id
";
$videos = db_fetch_all($videos_sql);


$zoom_meetings_sql = "
    SELECT zm.id, zm.meeting_time, zm.meeting_link, zm.created_at, r.role_name
    FROM zoom_meetings zm
    JOIN roles r ON zm.role_id = r.id
    ORDER BY zm.meeting_time ASC
";
$zoom_meetings = db_fetch_all($zoom_meetings_sql);


$page_title = 'ניהול וידאו ופגישות זום';
$additional_css = ['admin.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="admin-layout">
    <div class="admin-sidebar">
        <div class="sidebar-card">
            <h3>הוספת וידאו חדש</h3>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="sidebar-form">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

                <div class="form-group">
                    <label for="video_title">כותרת וידאו:</label>
                    <input type="text" name="video_title" id="video_title" placeholder="כותרת וידאו" required>
                </div>

                <div class="form-group">
                    <label for="video_url">כתובת וידאו:</label>
                    <input type="url" name="video_url" id="video_url" placeholder="כתובת URL של הוידאו" required>
                </div>

                <div class="form-group">
                    <label for="roles">בחר תפקידים:</label>
                    <select name="roles[]" id="roles" multiple required>
                        <option value="" disabled>בחר תפקידים</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>לחץ Ctrl/Cmd להוספת תפקידים מרובים</small>
                </div>

                <button type="submit" class="btn btn-primary" name="add_video">הוסף וידאו</button>
            </form>
        </div>

        <div class="sidebar-card">
            <h3>תזמון פגישת Zoom</h3>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="sidebar-form">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

                <div class="form-group">
                    <label for="role_select">תפקיד יעד:</label>
                    <select name="role_id" id="role_select" required>
                        <option value="" disabled selected>בחר תפקיד</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="meeting_time">זמן פגישה:</label>
                    <input type="datetime-local" id="meeting_time" name="meeting_time" required>
                </div>

                <div class="form-group">
                    <label for="meeting_link">קישור לפגישה:</label>
                    <input type="url" id="meeting_link" name="meeting_link" placeholder="https://zoom.us/j/..."
                        required>
                </div>

                <button type="submit" class="btn btn-primary" name="schedule_meeting">תזמן פגישה</button>
            </form>
        </div>
    </div>

    <div class="admin-content">
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h2>ניהול וידאו ופגישות Zoom</h2>
            </div>
        </div>

        <div class="dashboard-card">
            <h3 class="card-title">פגישות Zoom מתוזמנות</h3>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>תפקיד</th>
                            <th>זמן פגישה</th>
                            <th>קישור לפגישה</th>
                            <th>נוצר בתאריך</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($zoom_meetings)): ?>
                            <?php foreach ($zoom_meetings as $meeting): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($meeting['role_name']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($meeting['meeting_time'])); ?></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($meeting['meeting_link']); ?>" target="_blank"
                                            class="btn btn-primary btn-small">
                                            הצטרף לפגישה
                                        </a>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($meeting['created_at'])); ?></td>
                                    <td>
                                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post"
                                            style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                            <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                            <button type="submit" name="delete_meeting" class="btn btn-danger btn-small"
                                                onclick="return confirm('האם אתה בטוח שברצונך למחוק פגישה זו?')">
                                                מחק
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">לא נמצאו פגישות Zoom מתוזמנות.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-card">
            <h3 class="card-title">וידאו קיימים</h3>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>כותרת</th>
                            <th>כתובת URL</th>
                            <th>תפקידים</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($videos)): ?>
                            <?php foreach ($videos as $video): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($video['title']); ?></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($video['url']); ?>" target="_blank"
                                            class="video-link">
                                            <?php echo htmlspecialchars($video['url']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($video['assigned_roles'] ?? 'אין'); ?></td>
                                    <td>
                                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post"
                                            style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                            <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                            <button type="submit" name="delete_video" class="btn btn-danger btn-small"
                                                onclick="return confirm('האם אתה בטוח שברצונך למחוק וידאו זה?')">
                                                מחק
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">לא נמצאו וידאו במערכת.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>