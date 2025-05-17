<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['admin'], "גישה נדחתה. עמוד למנהלים בלבד.");


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    if (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id']);


        if (!empty($_POST['new_password'])) {

            $hashedPass = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
            $updatePassSql = "UPDATE users SET password = ? WHERE id = ?";
            $result = db_execute($updatePassSql, 'si', [$hashedPass, $user_id]);

            if ($result !== false) {
                set_success_message("הסיסמה עודכנה בהצלחה עבור משתמש מספר $user_id.");
            } else {
                set_error_message("שגיאה בעדכון הסיסמה: " . db_error());
            }
        }


        $newUsername = trim($_POST['username']);
        $newRoleId = intval($_POST['role_id']);


        $updateUserSql = "UPDATE users SET username = ?, role_id = ? WHERE id = ?";
        $result = db_execute($updateUserSql, 'sii', [$newUsername, $newRoleId, $user_id]);

        if ($result !== false) {
            set_success_message("הפרטים האישיים עודכנו בהצלחה עבור משתמש מספר $user_id.");
        } else {
            set_error_message("שגיאה בעדכון הפרטים האישיים: " . db_error());
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);


        if ($user_id == get_current_user_id()) {
            set_error_message("לא ניתן למחוק את המשתמש המחובר כעת.");
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        db_begin_transaction();

        try {

            $deleteCM = "DELETE FROM coordinator_mentors WHERE mentor_id = ? OR coordinator_id = ?";
            db_execute($deleteCM, 'ii', [$user_id, $user_id]);


            $deleteMD = "DELETE FROM mentor_details WHERE mentor_id = ?";
            db_execute($deleteMD, 'i', [$user_id]);


            $deleteUserSql = "DELETE FROM users WHERE id = ?";
            $result = db_execute($deleteUserSql, 'i', [$user_id]);

            if ($result !== false) {
                db_commit();
                set_success_message("המשתמש נמחק בהצלחה!");
            } else {
                throw new Exception("שגיאה במחיקת המשתמש: " . db_error());
            }
        } catch (Exception $e) {
            db_rollback();
            set_error_message($e->getMessage());
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}


$sql = "SELECT u.id, u.username, u.role_id, r.role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY u.id ASC";
$users = db_fetch_all($sql);


$rolesSql = "SELECT id, role_name FROM roles ORDER BY role_name ASC";
$roles = db_fetch_all($rolesSql);


$page_title = 'ניהול סיסמאות ומשתמשים';
$additional_css = ['admin.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>ניהול סיסמאות ומשתמשים</h2>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">רשימת משתמשים</h3>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>מזהה</th>
                    <th>שם משתמש</th>
                    <th>תפקיד</th>
                    <th>סיסמה חדשה</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <form class="form-inline" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                                    method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="text" name="username"
                                        value="<?php echo htmlspecialchars($user['username']); ?>" style="width:120px;">
                            </td>
                            <td>
                                <select name="role_id">
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $user['role_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="password" name="new_password" placeholder="סיסמה חדשה">
                            </td>
                            <td>
                                <button type="submit" name="update_user" class="btn btn-primary">עדכן</button>
                                </form>

                                <!-- טופס מחיקה -->
                                <form class="form-inline" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                                    method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-danger"
                                        onclick="return confirm('האם אתה בטוח שברצונך למחוק משתמש זה?')">מחק</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">לא נמצאו משתמשים.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>