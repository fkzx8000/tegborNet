<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['admin'], "Access denied. Admins only.");


$success_message = "";
$error_message = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    if (isset($_POST['add_mentor_to_coordinator'])) {
        $coordinator_id = isset($_POST['coordinator_id']) ? intval($_POST['coordinator_id']) : 0;
        $mentor_id = isset($_POST['mentor_id']) ? intval($_POST['mentor_id']) : 0;

        if ($coordinator_id > 0 && $mentor_id > 0) {
            db_begin_transaction();

            try {

                $check_sql = "SELECT id FROM coordinator_mentors WHERE coordinator_id = ? AND mentor_id = ?";
                $existing_relation = db_fetch_one($check_sql, 'ii', [$coordinator_id, $mentor_id]);

                if (!$existing_relation) {

                    $insert_sql = "INSERT INTO coordinator_mentors (coordinator_id, mentor_id) VALUES (?, ?)";
                    $result = db_execute($insert_sql, 'ii', [$coordinator_id, $mentor_id]);

                    if ($result !== false) {
                        db_commit();
                        set_success_message("Mentor added to coordinator successfully!");
                    } else {
                        throw new Exception("Error adding mentor: " . htmlspecialchars(db_error(), ENT_QUOTES, 'UTF-8'));
                    }
                } else {
                    throw new Exception("This mentor is already assigned to this coordinator.");
                }
            } catch (Exception $e) {
                db_rollback();
                set_error_message($e->getMessage());
            }
        } else {
            set_error_message("Invalid coordinator or mentor selected.");
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }


    if (isset($_POST['remove_mentor_from_coordinator'])) {
        $coordinator_id = isset($_POST['coordinator_id']) ? intval($_POST['coordinator_id']) : 0;
        $mentor_id = isset($_POST['mentor_id']) ? intval($_POST['mentor_id']) : 0;

        if ($coordinator_id > 0 && $mentor_id > 0) {
            $delete_sql = "DELETE FROM coordinator_mentors WHERE coordinator_id = ? AND mentor_id = ?";
            $result = db_execute($delete_sql, 'ii', [$coordinator_id, $mentor_id]);

            if ($result !== false) {
                set_success_message("Mentor removed from coordinator successfully!");
            } else {
                set_error_message("Error removing mentor: " . db_error());
            }
        } else {
            set_error_message("Invalid coordinator or mentor information.");
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}



$coordinators_sql = "SELECT id, username FROM users WHERE role_id = 13 ORDER BY username ASC";
$coordinators = db_fetch_all($coordinators_sql);


$mentors_sql = "SELECT id, username FROM users WHERE role_id = 12 ORDER BY username ASC";
$mentors = db_fetch_all($mentors_sql);


$page_title = 'ניהול רכזים ומתגברים';
$additional_css = ['admin.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>ניהול רכזים ומתגברים</h2>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">ניהול מתגברים לפי רכז</h3>

    <?php if (!empty($coordinators)): ?>
        <?php foreach ($coordinators as $coordinator): ?>
            <div class="coordinator-section">
                <h4><?php echo htmlspecialchars($coordinator['username']); ?></h4>

                <div class="mentors-list">
                    <h5>מתגברים משוייכים:</h5>
                    <ul>
                        <?php

                        $assigned_mentors_sql = "SELECT u.id, u.username 
                                                FROM users u
                                                INNER JOIN coordinator_mentors cm ON u.id = cm.mentor_id
                                                WHERE cm.coordinator_id = ?";
                        $assigned_mentors = db_fetch_all($assigned_mentors_sql, 'i', [$coordinator['id']]);

                        if (!empty($assigned_mentors)):
                            foreach ($assigned_mentors as $mentor):
                                ?>
                                <li>
                                    <?php echo htmlspecialchars($mentor['username']); ?>
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post"
                                        style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="coordinator_id" value="<?php echo $coordinator['id']; ?>">
                                        <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                                        <button type="submit" name="remove_mentor_from_coordinator"
                                            class="btn btn-danger btn-small">הסר</button>
                                    </form>
                                </li>
                            <?php
                            endforeach;
                        else:
                            ?>
                            <li>אין מתגברים משוייכים עדיין.</li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="add-mentor-form">
                    <h5>הוסף מתגבר:</h5>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="coordinator_id" value="<?php echo $coordinator['id']; ?>">
                        <div class="form-group">
                            <label for="mentor_id_<?php echo $coordinator['id']; ?>">בחר מתגבר:</label>
                            <select name="mentor_id" id="mentor_id_<?php echo $coordinator['id']; ?>">
                                <option value="">בחר מתגבר</option>
                                <?php if (!empty($mentors)): ?>
                                    <?php foreach ($mentors as $mentor): ?>
                                        <option value="<?php echo $mentor['id']; ?>">
                                            <?php echo htmlspecialchars($mentor['username']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_mentor_to_coordinator" class="btn btn-primary btn-small">הוסף
                            מתגבר</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>לא נמצאו רכזים.</p>
    <?php endif; ?>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>