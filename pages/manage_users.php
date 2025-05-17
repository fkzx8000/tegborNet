<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['admin'], "גישה נדחתה. מנהלים בלבד.");


$users_sql = "SELECT u.id, u.username, r.id as role_id, r.role_name 
             FROM users u 
             JOIN roles r ON u.role_id = r.id
             ORDER BY u.id ASC";
$users = db_fetch_all($users_sql);


$roles_sql = "SELECT * FROM roles ORDER BY role_name ASC";
$roles = db_fetch_all($roles_sql);


$roles_array = [];
foreach ($roles as $role) {
    $roles_array[$role['id']] = $role['role_name'];
}


$page_title = 'ניהול משתמשים';
$additional_css = ['admin.css'];


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>ניהול משתמשים</h2>
    </div>
</div>

<div class="admin-layout">
    <div class="admin-sidebar">
        <div class="sidebar-card">
            <h3>ניהול תפקידים</h3>

            <div class="sidebar-form">
                <form action="<?php echo get_site_url(); ?>/api/roles.php?action=add" method="post" id="add-role-form">
                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                    <div class="form-group">
                        <label for="role_name">שם תפקיד חדש:</label>
                        <input type="text" name="role_name" id="role_name" placeholder="שם תפקיד חדש" required>
                    </div>
                    <button type="submit" class="btn btn-primary" name="add_role">הוסף תפקיד</button>
                </form>
            </div>

            <div class="sidebar-roles-list">
                <h4>תפקידים קיימים</h4>
                <?php foreach ($roles_array as $id => $name): ?>
                    <form action="<?php echo get_site_url(); ?>/api/roles.php?action=delete" method="post"
                        class="role-delete-form">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <input type="hidden" name="role_id" value="<?php echo $id; ?>">
                        <button type="submit" name="delete_role"><?php echo htmlspecialchars($name); ?> (מחק)</button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sidebar-card">
            <h3>שליחת הודעה לכל המשתמשים בתפקיד</h3>
            <form action="<?php echo get_site_url(); ?>/api/broadcast.php?action=send" method="post"
                id="broadcast-form">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

                <div class="form-group">
                    <label for="role_select">שלח לתפקיד:</label>
                    <select name="role_id" id="role_select" required>
                        <option value="" disabled selected>בחר תפקיד</option>
                        <?php foreach ($roles_array as $id => $name): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="message_text">הודעה:</label>
                    <textarea name="message" id="message_text" rows="4" required></textarea>
                </div>

                <button type="submit" name="send_broadcast" class="btn btn-primary">שלח הודעה</button>
                <button type="button" id="testMessageButton" class="btn btn-secondary">שלח הודעת בדיקה</button>
            </form>
        </div>
    </div>

    <div class="admin-content">
        <div class="dashboard-card">
            <h3 class="card-title">רשימת משתמשים ותפקידים</h3>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>מזהה</th>
                            <th>שם משתמש</th>
                            <th>תפקיד</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <form class="inline-form" id="role-form-<?php echo $user['id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="role_id" class="role-select"
                                                data-user-id="<?php echo $user['id']; ?>">
                                                <?php foreach ($roles_array as $role_id => $role_name): ?>
                                                    <option value="<?php echo $role_id; ?>" <?php echo ($role_id == $user['role_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($role_name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm update-role"
                                            data-user-id="<?php echo $user['id']; ?>">עדכון תפקיד</button>
                                        <button class="btn btn-danger btn-sm delete-user"
                                            data-user-id="<?php echo $user['id']; ?>">מחק משתמש</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">לא נמצאו משתמשים.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-card">
            <h3 class="card-title">איפוס סיסמה למשתמש</h3>
            <form action="<?php echo get_site_url(); ?>/api/users.php?action=reset_password" method="post"
                id="password-reset-form">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">

                <div class="form-group">
                    <label for="user_id_reset">בחר משתמש:</label>
                    <select name="user_id" id="user_id_reset" required>
                        <option value="" disabled selected>בחר משתמש</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="new_password">סיסמה חדשה:</label>
                    <input type="password" name="new_password" id="new_password" required minlength="8">
                </div>

                <div class="form-group">
                    <label for="confirm_password">אימות סיסמה:</label>
                    <input type="password" name="confirm_password" id="confirm_password" required minlength="8">
                </div>

                <button type="submit" class="btn btn-primary">איפוס סיסמה</button>
            </form>
        </div>
    </div>
</div>

<script>

    document.querySelectorAll('.update-role').forEach(button => {
        button.addEventListener('click', function () {
            const userId = this.getAttribute('data-user-id');
            const form = document.getElementById('role-form-' + userId);
            const formData = new FormData(form);


            formData.append('csrf_token', '<?php echo get_csrf_token(); ?>');

            fetch('<?php echo get_site_url(); ?>/api/users.php?action=update_role', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('תפקיד המשתמש עודכן בהצלחה!');
                    } else {
                        alert(data.message || 'שגיאה בעדכון תפקיד המשתמש');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('שגיאה בתקשורת עם השרת');
                });
        });
    });


    document.querySelectorAll('.delete-user').forEach(button => {
        button.addEventListener('click', function () {
            if (confirm('האם אתה בטוח שברצונך למחוק משתמש זה?')) {
                const userId = this.getAttribute('data-user-id');

                const formData = new FormData();
                formData.append('user_id', userId);
                formData.append('csrf_token', '<?php echo get_csrf_token(); ?>');

                fetch('<?php echo get_site_url(); ?>/api/users.php?action=delete', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('המשתמש נמחק בהצלחה!');

                            window.location.reload();
                        } else {
                            alert(data.message || 'שגיאה במחיקת המשתמש');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('שגיאה בתקשורת עם השרת');
                    });
            }
        });
    });


    document.getElementById('testMessageButton').addEventListener('click', function () {

        const formData = new FormData();
        formData.append('role_id', document.getElementById('role_select').value || 1);
        formData.append('message', 'זוהי הודעת בדיקה אוטומטית מהמערכת.');
        formData.append('csrf_token', '<?php echo get_csrf_token(); ?>');

        fetch('<?php echo get_site_url(); ?>/api/broadcast.php?action=test', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('הודעת הבדיקה נשלחה בהצלחה!');
                } else {
                    alert(data.message || 'שגיאה בשליחת הודעת הבדיקה');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה בתקשורת עם השרת');
            });
    });


    document.getElementById('password-reset-form').addEventListener('submit', function (e) {
        const password = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (password !== confirmPassword) {
            e.preventDefault();
            alert('הסיסמאות אינן תואמות');
            return false;
        }
    });
</script>

<?php

include __DIR__ . '/../templates/footer.php';
?>