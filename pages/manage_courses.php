<?php


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_functions.php';


require_permission(['admin'], "Access denied. Admins only.");


$coordinators_sql = "SELECT u.id, u.username 
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE r.role_name = 'coordinator'
                    ORDER BY u.username ASC";
$coordinators = db_fetch_all($coordinators_sql);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $course_name = trim($_POST['course_name']);
    $coordinator_id = isset($_POST['coordinator_id']) ? intval($_POST['coordinator_id']) : 0;
    
    if (!empty($course_name) && $coordinator_id > 0) {
        $add_course_sql = "INSERT INTO courses (course_name, coordinator_id) VALUES (?, ?)";
        $result = db_execute($add_course_sql, 'si', [$course_name, $coordinator_id]);
        
        if ($result !== false) {
            set_success_message("הקורס נוסף בהצלחה!");
        } else {
            set_error_message("שגיאה בהוספת הקורס: " . db_error());
        }
    } else {
        set_error_message("נדרש שם קורס ורכז.");
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $course_id = intval($_POST['course_id']);
    
    $delete_course_sql = "DELETE FROM courses WHERE id = ?";
    $result = db_execute($delete_course_sql, 'i', [$course_id]);
    
    if ($result !== false) {
        set_success_message("הקורס נמחק בהצלחה!");
    } else {
        set_error_message("שגיאה במחיקת הקורס: " . db_error());
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_course'])) {
    
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        set_error_message("שגיאת אבטחה. אנא רענן את הדף ונסה שוב.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $course_id = intval($_POST['course_id']);
    $course_name = trim($_POST['course_name']);
    $coordinator_id = isset($_POST['coordinator_id']) ? intval($_POST['coordinator_id']) : 0;
    
    if (!empty($course_name) && $coordinator_id > 0) {
        $update_course_sql = "UPDATE courses SET course_name = ?, coordinator_id = ? WHERE id = ?";
        $result = db_execute($update_course_sql, 'sii', [$course_name, $coordinator_id, $course_id]);
        
        if ($result !== false) {
            set_success_message("הקורס עודכן בהצלחה!");
        } else {
            set_error_message("שגיאה בעדכון הקורס: " . db_error());
        }
    } else {
        set_error_message("נדרש שם קורס ורכז.");
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


$courses_sql = "SELECT c.*, u.username as coordinator_username 
               FROM courses c 
               JOIN users u ON c.coordinator_id = u.id 
               ORDER BY c.course_name ASC";
$courses = db_fetch_all($courses_sql);


$page_title = 'ניהול קורסים';
$additional_css = ['admin.css'];
$additional_js_footer = ['courses.js'];


$additional_scripts = "
    function showEditRow(courseId) {
        document.getElementById('edit-row-' + courseId).classList.add('show');
        document.getElementById('view-row-' + courseId).style.display = 'none';
    }
    function hideEditRow(courseId) {
        document.getElementById('edit-row-' + courseId).classList.remove('show');
        document.getElementById('view-row-' + courseId).style.display = 'table-row';
    }
";


include __DIR__ . '/../templates/header.php';
?>

<div class="dashboard-header">
    <div class="dashboard-title">
        <h2>ניהול קורסים</h2>
    </div>
</div>

<div class="dashboard-card">
    <h3 class="card-title">הוספת קורס חדש</h3>
    
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="add-course-form">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
        
        <div class="form-group">
            <label for="course_name">שם קורס:</label>
            <input type="text" id="course_name" name="course_name" required>
        </div>
        
        <div class="form-group">
            <label for="coordinator_id">שייך לרכז:</label>
            <select name="coordinator_id" id="coordinator_id" required>
                <option value="" disabled selected>בחר רכז</option>
                <?php if (!empty($coordinators)): ?>
                    <?php foreach ($coordinators as $coordinator): ?>
                        <option value="<?php echo $coordinator['id']; ?>">
                            <?php echo htmlspecialchars($coordinator['username']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="" disabled>אין רכזים זמינים</option>
                <?php endif; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary" name="add_course">הוסף קורס</button>
    </form>
</div>

<div class="dashboard-card">
    <h3 class="card-title">קורסים קיימים</h3>
    
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>שם קורס</th>
                    <th>רכז</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $course): ?>
                        <tr class="view-row" id="view-row-<?php echo $course['id']; ?>">
                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                            <td><?php echo htmlspecialchars($course['coordinator_username']); ?></td>
                            <td>
                                <button onclick="showEditRow(<?php echo $course['id']; ?>)" class="btn btn-primary btn-small">ערוך</button>
                                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <button type="submit" name="delete_course" class="btn btn-danger btn-small" 
                                            onclick="return confirm('האם אתה בטוח שברצונך למחוק קורס זה?')">מחק</button>
                                </form>
                            </td>
                        </tr>
                        <tr class="edit-row" id="edit-row-<?php echo $course['id']; ?>">
                            <td colspan="3">
                                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="edit-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="course_name_<?php echo $course['id']; ?>">שם קורס:</label>
                                        <input type="text" id="course_name_<?php echo $course['id']; ?>" 
                                               name="course_name" value="<?php echo htmlspecialchars($course['course_name']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="coordinator_id_<?php echo $course['id']; ?>">רכז:</label>
                                        <select name="coordinator_id" id="coordinator_id_<?php echo $course['id']; ?>" required>
                                            <?php if (!empty($coordinators)): ?>
                                                <?php foreach ($coordinators as $coordinator): ?>
                                                    <option value="<?php echo $coordinator['id']; ?>" 
                                                           <?php echo ($coordinator['id'] == $course['coordinator_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($coordinator['username']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" name="edit_course" class="btn btn-primary btn-small">שמור</button>
                                    <button type="button" onclick="hideEditRow(<?php echo $course['id']; ?>)" 
                                            class="btn btn-secondary btn-small">ביטול</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">לא נוספו קורסים עדיין.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php

include __DIR__ . '/../templates/footer.php';
?>