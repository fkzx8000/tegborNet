<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_mentor'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $mentor_email = filter_input(INPUT_POST, 'mentor_email', FILTER_VALIDATE_EMAIL);
    $session_restriction = isset($_POST['session_restriction']) ? trim($_POST['session_restriction']) : null;

    if (!$mentor_email) {
        set_error_message("כתובת אימייל לא תקינה");
    } else {

        $check_sql = "SELECT * FROM authorized_mentors WHERE email = ? AND coordinator_id = ?";
        $existing = db_fetch_one($check_sql, 'si', [$mentor_email, $coordinator_id]);

        if ($existing) {
            set_error_message("כתובת האימייל כבר קיימת ברשימה");
        } else {

            $insert_sql = "INSERT INTO authorized_mentors (email, coordinator_id, session_restriction) VALUES (?, ?, ?)";
            $result = db_execute($insert_sql, 'sis', [$mentor_email, $coordinator_id, $session_restriction]);

            if ($result !== false) {
                set_success_message("המנחה נוסף בהצלחה");
            } else {
                set_error_message("שגיאה בהוספת המנחה: " . db_error());
            }
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_level'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $mentor_id = isset($_POST['mentor_id']) ? intval($_POST['mentor_id']) : 0;
    $new_level = isset($_POST['level']) ? intval($_POST['level']) : 0;
    $session_restriction = isset($_POST['session_restriction']) ? trim($_POST['session_restriction']) : null;

    if ($mentor_id <= 0) {
        set_error_message("מזהה מנחה לא תקין");
    } else {

        $check_sql = "SELECT * FROM authorized_mentors WHERE id = ? AND coordinator_id = ?";
        $existing = db_fetch_one($check_sql, 'ii', [$mentor_id, $coordinator_id]);

        if (!$existing) {
            set_error_message("המנחה לא נמצא או אינו שייך לך");
        } else {

            $update_sql = "UPDATE authorized_mentors SET level = ?, session_restriction = ? WHERE id = ?";
            $result = db_execute($update_sql, 'isi', [$new_level, $session_restriction, $mentor_id]);

            if ($result !== false) {
                set_success_message("רמת המנחה עודכנה בהצלחה");
            } else {
                set_error_message("שגיאה בעדכון רמת המנחה: " . db_error());
            }
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_mentor'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $mentor_id = isset($_POST['mentor_id']) ? intval($_POST['mentor_id']) : 0;

    if ($mentor_id <= 0) {
        set_error_message("מזהה מנחה לא תקין");
    } else {

        $check_sql = "SELECT * FROM authorized_mentors WHERE id = ? AND coordinator_id = ?";
        $existing = db_fetch_one($check_sql, 'ii', [$mentor_id, $coordinator_id]);

        if (!$existing) {
            set_error_message("המנחה לא נמצא או אינו שייך לך");
        } else {

            $check_selections_sql = "SELECT COUNT(*) as count FROM project_selections WHERE mentor_email = ?";
            $selections = db_fetch_one($check_selections_sql, 's', [$existing['email']]);

            if ($selections && $selections['count'] > 0) {
                set_error_message("לא ניתן למחוק מנחה שכבר בחר פרויקטים");
            } else {

                $delete_sql = "DELETE FROM authorized_mentors WHERE id = ?";
                $result = db_execute($delete_sql, 'i', [$mentor_id]);

                if ($result !== false) {
                    set_success_message("המנחה נמחק בהצלחה");
                } else {
                    set_error_message("שגיאה במחיקת המנחה: " . db_error());
                }
            }
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


$mentors_sql = "SELECT am.*, 
               (SELECT COUNT(*) FROM project_selections WHERE mentor_email = am.email) as selections_count
               FROM authorized_mentors am
               WHERE am.coordinator_id = ?
               ORDER BY am.email";
$mentors = db_fetch_all($mentors_sql, 'i', [$coordinator_id]);


$sessions_sql = "SELECT DISTINCT session FROM projects WHERE coordinator_id = ? ORDER BY session";
$sessions = db_fetch_all($sessions_sql, 'i', [$coordinator_id]);


$page_title = 'ניהול מנחים';
$additional_css = ['coordinator.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>ניהול מנחים לבחירת פרויקטים</h2>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">הוספת מנחה חדש</h3>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="add-mentor-form">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

        <div class="form-group">
            <label for="mentor_email">כתובת אימייל:</label>
            <input type="email" id="mentor_email" name="mentor_email" required placeholder="הזן כתובת אימייל של המנחה">
        </div>

        <div class="form-group">
            <label for="session_restriction">הגבלת מושב (אופציונלי):</label>
            <select name="session_restriction" id="session_restriction">
                <option value="">ללא הגבלה</option>
                <?php foreach ($sessions as $session): ?>
                    <option value="<?php echo htmlspecialchars($session['session']); ?>">
                        <?php echo htmlspecialchars($session['session']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>בחר "ללא הגבלה" כדי לאפשר גישה לכל המושבים</small>
        </div>

        <button type="submit" name="add_mentor" class="btn btn-primary">הוסף מנחה</button>
    </form>
</div>

<div class="dashboard-card">
    <h3 class="card-title">רשימת מנחים</h3>

    <?php if (!empty($mentors)): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>מזהה</th>
                        <th>כתובת אימייל</th>
                        <th>רמה</th>
                        <th>הגבלת מושב</th>
                        <th>מספר בחירות</th>
                        <th>תאריך בחירה אחרון</th>
                        <th>תאריך הוספה</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mentors as $mentor): ?>
                        <tr>
                            <td><?php echo $mentor['id']; ?></td>
                            <td><?php echo htmlspecialchars($mentor['email']); ?></td>
                            <td><?php echo $mentor['level'] == 0 ? 'רמה 0 (בסיסית)' : 'רמה 1 (מתקדמת)'; ?></td>
                            <td><?php echo !empty($mentor['session_restriction']) ? htmlspecialchars($mentor['session_restriction']) : 'ללא הגבלה'; ?>
                            </td>
                            <td><?php echo $mentor['selection_count']; ?></td>
                            <td><?php echo $mentor['last_selection_date'] ? date('d/m/Y H:i', strtotime($mentor['last_selection_date'])) : '-'; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($mentor['created_at'])); ?></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-small edit-mentor-btn"
                                    data-id="<?php echo $mentor['id']; ?>"
                                    data-email="<?php echo htmlspecialchars($mentor['email']); ?>"
                                    data-level="<?php echo $mentor['level']; ?>"
                                    data-session="<?php echo htmlspecialchars($mentor['session_restriction']); ?>">
                                    ערוך
                                </button>

                                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post"
                                    style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                    <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                                    <button type="submit" name="delete_mentor" class="btn btn-danger btn-small"
                                        onclick="return confirm('האם אתה בטוח שברצונך למחוק מנחה זה?')">
                                        מחק
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>אין מנחים רשומים. הוסף מנחה חדש באמצעות הטופס למעלה.</p>
    <?php endif; ?>
</div>

<!-- חלון מודאל לעריכת מנחה -->
<div id="editMentorModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>עריכת פרטי מנחה</h3>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" id="edit-mentor-form">
            <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
            <input type="hidden" name="mentor_id" id="edit_mentor_id">

            <div class="form-group">
                <label for="edit_email">כתובת אימייל:</label>
                <input type="email" id="edit_email" disabled>
            </div>

            <div class="form-group">
                <label for="edit_level">רמת הרשאה:</label>
                <select name="level" id="edit_level">
                    <option value="0">רמה 0 (בסיסית - עד 2 פרויקטים)</option>
                    <option value="1">רמה 1 (מתקדמת - פרויקטים נוספים)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="edit_session_restriction">הגבלת מושב:</label>
                <select name="session_restriction" id="edit_session_restriction">
                    <option value="">ללא הגבלה</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?php echo htmlspecialchars($session['session']); ?>">
                            <?php echo htmlspecialchars($session['session']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="update_level" class="btn btn-primary">שמור שינויים</button>
        </form>
    </div>
</div>

<script>

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('editMentorModal');
        const closeBtn = modal.querySelector('.close');
        const editButtons = document.querySelectorAll('.edit-mentor-btn');


        editButtons.forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const email = this.getAttribute('data-email');
                const level = this.getAttribute('data-level');
                const session = this.getAttribute('data-session');

                document.getElementById('edit_mentor_id').value = id;
                document.getElementById('edit_email').value = email;
                document.getElementById('edit_level').value = level;
                document.getElementById('edit_session_restriction').value = session;

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
    });
</script>

<style>
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
        width: 50%;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover {
        color: black;
    }
</style>

<?php

include __DIR__ . '/../templates/footer.php';
?>