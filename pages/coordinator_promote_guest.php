<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['coordinator'], "גישה נדחתה. רק רכזים מורשים.");


$coordinator_id = get_current_user_id();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_user'])) {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($user_id <= 0) {
        set_error_message("מזהה משתמש לא תקין");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    db_begin_transaction();

    try {

        $check_sql = "SELECT id, role_id FROM users WHERE id = ? AND role_id = 1 FOR UPDATE";
        $guest_user = db_fetch_one($check_sql, 'i', [$user_id]);

        if (!$guest_user) {
            throw new Exception("המשתמש אינו אורח או כבר עודכן");
        }


        $role_sql = "SELECT id FROM roles WHERE role_name = 'mentor'";
        $role = db_fetch_one($role_sql);

        if (!$role || !isset($role['id'])) {
            throw new Exception("תפקיד 'mentor' לא נמצא במערכת");
        }

        $mentor_role_id = $role['id'];


        $update_sql = "UPDATE users SET role_id = ? WHERE id = ?";
        $result = db_execute($update_sql, 'ii', [$mentor_role_id, $user_id]);

        if ($result === false) {
            throw new Exception("שגיאה בעדכון המשתמש: " . db_error());
        }


        $insert_sql = "INSERT INTO coordinator_mentors (coordinator_id, mentor_id) VALUES (?, ?)";
        $result = db_execute($insert_sql, 'ii', [$coordinator_id, $user_id]);

        if ($result === false) {

            if (strpos(db_error(), 'Duplicate entry') !== false) {
                throw new Exception("הקשר בין הרכז למתגבר כבר קיים במערכת");
            }
            throw new Exception("שגיאה בהוספת הקשר: " . db_error());
        }


        db_commit();
        set_success_message("המשתמש קודם למתגבר בהצלחה!");

    } catch (Exception $e) {
        db_rollback();
        set_error_message($e->getMessage());
    }


    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


$guests_sql = "SELECT u.id, u.username
               FROM users u 
               JOIN roles r ON u.role_id = r.id
               WHERE r.role_name = 'guest'
               ORDER BY u.username ASC";
$guests = db_fetch_all($guests_sql);


$page_title = 'קידום אורחים למתגברים';
$additional_css = ['coordinator.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>קידום אורחים למתגברים</h2>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">רשימת משתמשים אורחים</h3>

    <?php if (!empty($guests)): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>מזהה</th>
                        <th>שם משתמש</th>
                        <th>קידום</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guests as $guest): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($guest['id']); ?></td>
                            <td><?php echo htmlspecialchars($guest['username']); ?></td>
                            <td>
                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                                    style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $guest['id']; ?>">
                                    <button type="submit" name="promote_user" class="btn btn-primary">הפוך למתגבר</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert-info">
            <p>אין משתמשים עם תפקיד אורח (guest) כרגע.</p>
        </div>
    <?php endif; ?>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>